<?php

	abstract class AutobahnManager
	{
		public $connected = false;
		public $debug = true;

		private $__logs = array();
		private $__options = array(
			'fields' => array('*'),
			'table' => null,
			'conditions' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null,
			'page' => null,
		);
		private $__select_types = array('first','all','count','list');

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
				pr($this->__logs);
			else
				print_r($this->__logs);
		}
		public function find($type = 'first', $table, $options = array())
		{
			$options = $options + $this->__options;

			$query = array(
				'fields' 		=> $options['fields'],
				'table' 		=> $table,
				'conditions' 	=> $options['conditions'],
				'group' 		=> $options['group'],
				'order' 		=> $options['order'],
				'limit' 		=> $type === 'first' ? 1 : $options['limit'],
				'page' 			=> $options['page'],
			);
			
			$sql = $this->renderStatement('select',$query);

			return $this->query($sql);
		}
		public function findAll($table, $options = array())
		{
			return $this->find('all', $table, $options);
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

					$options = array();

					if(isset($CamelizedField))
					{
						if(empty($CamelizedField) || empty($arguments))
							trigger_error('Autobahn: You need to specify the parameters of findAllBy.',E_USER_ERROR);

						$values = is_array($arguments[0]) ? $arguments[0] : $arguments;

						$options['conditions'] = array(underscore($CamelizedField) => $values);
					}

					return $this->find('all', underscore($CamelizedTable), $options);
				}
			}
			elseif((count($arguments) === 1) && (strpos($method, 'find') === 0))
			{
				$magic = explode('find', $method);
				if(($magic[0] === '') && ($magic[1] !== '') && (strpos($magic[1], 'By') !== false))
				{
					list($CamelizedTable, $CamelizedField) = explode('By', $magic[1]);
					
					if(empty($CamelizedField) || !isset($arguments[0]))
						trigger_error('Autobahn: You need to specify the values of findBy.',E_USER_ERROR);

					$options = array(
						'conditions' => array(underscore($CamelizedField) => $arguments[0]),
						'limit' => '1',
					);

					return $this->find('first', underscore($CamelizedTable), $options);
				}
			}
			elseif((count($arguments) === 1) && (strpos($method, 'insert') === 0))
			{
				$magic = explode('insert', $method);
				if(($magic[0] === '') && ($magic[1] !== ''))
				{
					if(!isset($arguments[0]) || !is_array($arguments[0]) || empty($arguments[0]))
						trigger_error('Autobahn: You need to specify the values of insert.',E_USER_ERROR);

					$query = array(
						'table' => underscore($magic[1]),
						'fields' => array_keys($arguments[0]),
						'values' => $arguments[0],
					);
					
					return $this->execute($this->renderStatement('insert', $query));
				}
			}
			elseif((count($arguments) === 2) && (strpos($method, 'update') === 0))
			{
				$magic = explode('update', $method);
				if(($magic[0] === '') && ($magic[1] !== ''))
				{
					if(!isset($arguments[0]) || !is_array($arguments[0]) || empty($arguments[0]))
						trigger_error('Autobahn: You need to specify the values of insert.',E_USER_ERROR);

					if(!isset($arguments[1]) || !is_array($arguments[1]) || empty($arguments[1]))
						trigger_error('Autobahn: You need to specify the conditions of insert.',E_USER_ERROR);

					$query = array(
						'table' => underscore($magic[1]),
						'declarations' => $arguments[0],
						'conditions' => $arguments[1],
					);
					
					return $this->execute($this->renderStatement('update', $query));
				}
			}
			elseif((count($arguments) > 0) && (strpos($method, 'delete') === 0))
			{
				$magic = explode('delete', $method);
				if(($magic[0] === '') && ($magic[1] !== '') && (strpos($magic[1], 'By') !== false))
				{
					list($CamelizedTable, $CamelizedField) = explode('By', $magic[1]);
					
					if(empty($CamelizedField) || !isset($arguments[0]))
						trigger_error('Autobahn: You need to specify the values of deleteBy.',E_USER_ERROR);

					$values = is_array($arguments[0]) ? $arguments[0] : $arguments;

					$query = array(
						'table' => underscore($CamelizedTable),
						'conditions' => array(underscore($CamelizedField) => $values),
					);

					return $this->execute($this->renderStatement('delete', $query));
				}
			}
			else
				trigger_error("Error. Method '$method' not found. ".count($arguments), E_ERROR);
    	}
	}

?>
