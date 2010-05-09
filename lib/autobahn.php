<?php

	error_reporting(E_ALL);
	
	if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

	define('AUTOBAHN_ROOT', dirname(__FILE__).DS);
	
	if(!defined('AUTOBAHN_DBO')) define('AUTOBAHN_DBO', AUTOBAHN_ROOT.'dbo'.DS);

	if(!function_exists('getMicrotime'))
	{
		function getMicrotime()
		{
			list($usec, $sec) = explode(' ', microtime());
			return ((float)$usec + (float)$sec);
		}
	}
	if(!function_exists('pr'))
	{
		function pr($mixed)
		{
			if(PHP_SAPI != 'cli')
			{
				echo '<pre>';
				print_r($mixed);
				echo '</pre>';
			}
			else
				print_r($mixed);
		}
	}
	if(!function_exists('vd'))
	{
		function vd($mixed)
		{
			if(PHP_SAPI != 'cli')
			{
				echo '<pre>';
				var_dump($mixed);
				echo '</pre>';
			}
			else
				var_dump($mixed);
		}
	}
	if(!function_exists('camelize'))
	{
		function camelize($lowerCaseAndUnderscoredWord)
		{
			return str_replace(" ", "", ucwords(str_replace("_", " ", $lowerCaseAndUnderscoredWord)));
		}
	}
	if(!function_exists('underscore'))
	{
		function underscore($camelCasedWord)
		{
			return strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $camelCasedWord));
		}
	}
	if(!function_exists('humanize'))
	{
		function humanize($lowerCaseAndUnderscoredWord)
		{
			return ucwords(str_replace("_", " ", $lowerCaseAndUnderscoredWord));
		}
	}
	
	abstract class AutobahnManager
	{
		public $connected = false;
		public $debug = true;

		private $__logs = array();
		protected $_config;

		protected function __construct($config)
		{				
			if(isset($config['prefix']))
				$this->_prefix = $config['prefix'];

			if(!isset($config['encoding']))
				$config['encoding'] = 'UTF8';

			$this->connect($config['host'], $config['user'], $config['password'], $config['database'], $config['encoding']);
		}
		private function __logQuery($sql, $time)
		{
			$this->__logs[] = array(
				'sql' => $sql,
				'took' => round((getMicrotime() - $time) * 1000, 0),
				'affected' => $this->lastAffected(),
				'error' => $this->lastError(),
				'num_rows' => $this->lastNumRows(),
			);
		}
		public function query($sql)
		{
			$this->execute($sql);
			
			return $this->getFormatedRows();
		}
		public function execute($sql)
		{
			if($this->debug)
				$t = getMicrotime();
			
			$resource = $this->_execute($sql);

			if($this->debug)
				$this->__logQuery($sql, $t);
			
			return $resource;
		}
		public function getFormatedRows()
		{
			$fields = $this->getFields();
			$limit = $this->lastNumRows();
			$results = array();

			for($i = 0; ($i < $limit) && ($row = $this->getRow($i,'number')); $i++)
			{
				foreach($row as $index => $value)
				{
					if(!empty($fields[$index]['table']))
						$results[$i][$fields[$index]['table']][$fields[$index]['name']] = $value;
					else
						$results[$i][$fields[$index]['table']] = $value;
				}
			}

			return empty($results) ? false : $results;
		}
		public function showLogs()
		{
			if(PHP_SAPI != 'cli')
			{
				pr($this->__logs);
			}
			else
				print_r($this->__logs);
		}
		public function __call($method, $arguments)
		{			
			if(strpos($method, 'findAll') === 0)
			{
				$magic = explode('findAll', $method);
				if(($magic[0] === '') && ($magic[1] !== ''))
				{
					if(strpos($magic[1], 'By') !== false)
						list($CamelizedTable, $CamelizedField) = explode('By', $magic[1]);
					else
						$CamelizedTable = $magic[1];
					
					if(isset($CamelizedField) && !empty($CamelizedField) && (count($arguments[0]) > 0))
					{
						$field = underscore($CamelizedField);

						$values = $this->_sqlEquivalentValues($arguments);
						$conditions = underscore($CamelizedField).' IN ('.implode(',',$values).')';
					}
					elseif(!isset($CamelizedField))
						$conditions = '';
					else
						return false;
					
					$query = array(
						'fields' => '*',
						'table' => underscore($CamelizedTable),
						'conditions' => $conditions,
						'group' => '',
						'order' => '',
						'limit' => '',
					);
					
					$sql = $this->renderStatement('select',$query);

					return $this->query($sql);
				}
			}
			elseif((count($arguments) === 1) && (strpos($method, 'find') === 0))
			{
				$magic = explode('find', $method);
				if(($magic[0] === '') && ($magic[1] !== '') && (strpos($magic[1], 'By') !== false))
				{
					list($CamelizedTable, $CamelizedField) = explode('By', $magic[1]);
					
					$value = is_numeric($arguments[0]) ? $arguments[0] : '\''.addslashes($arguments[0]).'\'';
					
					$query = array(
						'fields' => '*',
						'table' => underscore($CamelizedTable),
						'conditions' => underscore($CamelizedField).' = '.$value,
						'group' => '',
						'order' => '',
						'limit' => '1',
					);
					
					$sql = $this->renderStatement('select', $query);

					$result = $this->query($sql);

					return $result[0];
				}
			}
			elseif((count($arguments) === 1) && (strpos($method, 'insert') === 0))
			{
				$magic = explode('insert', $method);
				if(($magic[0] === '') && ($magic[1] !== ''))
				{
					$values = $this->_sqlEquivalentValues($arguments[0]);
					$fields = array_keys($values);

					$query = array(
						'table' => underscore($magic[1]),
						'fields' => $fields,
						'values' => $values,
					);
					
					$sql = $this->renderStatement('insert', $query);

					return $this->execute($sql);
				}
			}
			elseif((count($arguments) === 2) && (strpos($method, 'update') === 0))
			{
				$magic = explode('update', $method);
				if(($magic[0] === '') && ($magic[1] !== ''))
				{
					$declarations = $this->_sqlEquivalentDeclarations($arguments[0]);
					$conditions = $this->_sqlEquivalentConditions($arguments[1]);

					$query = array(
						'table' => underscore($magic[1]),
						'fields' => $declarations,
						'conditions' => implode(' AND ',$conditions),
					);
					
					$sql = $this->renderStatement('update', $query);

					return $this->execute($sql);
				}
			}
			elseif((count($arguments) > 0) && (strpos($method, 'delete') === 0))
			{
				$magic = explode('delete', $method);
				if(($magic[0] === '') && ($magic[1] !== ''))
				{
					if(strpos($magic[1], 'By') === false)
						return false;

					list($CamelizedTable, $CamelizedField) = explode('By', $magic[1]);
					
					$field = underscore($CamelizedField);

					$values = $this->_sqlEquivalentValues($arguments);
					$conditions = underscore($CamelizedField).' IN ('.implode(',',$values).')';

					$query = array(
						'table' => underscore($CamelizedTable),
						'conditions' => $conditions,
					);
					
					$sql = $this->renderStatement('delete', $query);

					return $this->execute($sql);
				}
			}
			else
				echo "Error. Method '$method' not found. ".count($arguments)." \n";
    	}
	}
	
	class Autobahn
	{
		private static $__instances = array();
		private static $__configs;
		
		private static function getConfigClass()
		{
			if(!class_exists('DB_CONFIG'))
			{
				if(defined('AUTOBAHN_DB_CONFIG'))
					require(AUTOBAHN_DB_CONFIG);
				else
					trigger_error('No existe una base de datos configurada, tampoco un archivo de configuración definido.', E_ERROR);
			}

			self::$__configs = get_class_vars('DB_CONFIG');
			
			foreach (self::$__configs as $db => $config)
			{
				if(!isset($config['driver']))
					trigger_error('No "driver" in '.$db.' database configuration', E_ERROR);

				if(!isset($config['host']))
					trigger_error('No "host" in '.$db.' database configuration', E_ERROR);

				if(!isset($config['user']))
					trigger_error('No "user" in '.$db.' database configuration', E_ERROR);

				if(!isset($config['password']))
					trigger_error('No "password" in '.$db.' database configuration', E_ERROR);

				if(!isset($config['database']))
					trigger_error('No "database" in '.$db.' database configuration', E_ERROR);
			}
		}
		public static function getConnection($db = 'default')
		{
			if(self::$__configs == null)
				self::getConfigClass();
						
			if(isset(self::$__instances[$db]))
				return self::$__instances[$db];
			
			$c = 'AutobahnDbo'.ucfirst(self::$__configs[$db]['driver']);
			
			require(AUTOBAHN_DBO.'dbo_'.self::$__configs[$db]['driver'].'.php');
			
			return self::$__instances[$db] =& new $c(self::$__configs[$db]);
		}

		private function __construct() { }
	}

?>
