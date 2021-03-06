<?php

abstract class AutobahnManager
{
	public $connected = false;
	public $debug = true;

	private $__pdo;
	private $__pdo_statement;

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
	private $__select_types = array('first', 'all', 'count', 'list');

	protected function __construct($config)
	{
		extract($config);

		if (!isset($encoding))
			$encoding = 'utf8';

		$this->connect("mysql:host=$host;dbname=$database;charset=$encoding", $user, $password);
	}

	public function connect($pdo_config, $user, $password)
	{
		try
		{
			$this->__pdo = new PDO($pdo_config, $user, $password);
		    $this->__pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch (PDOException $e)
		{
		    trigger_error('Falló la conexión: '.$e->getMessage());
		}
		$this->connected = true;
	}

	public function query($sql)
	{
		$this->execute($sql);
		
		return $this->getFormatedRows();
	}

	public function execute($sql)
	{
		if ($this->debug)
			$t = getMicrotime();
		
		$resource = $this->__execute($sql);

		if ($this->debug)
			$this->__logQuery($sql, $t);
		
		return $resource;
	}

	public function getFormatedRows()
	{
		$key = 0;
		$results = array();
		
		while ($row = $this->__pdo_statement->fetch(PDO::FETCH_NUM))
		{
			foreach($row as $col => $value) {
				$table_meta = $this->__pdo_statement->getColumnMeta($col);
				$results[$key][$table_meta['table']][$table_meta['name']] = $value;
			}
			$key++;
		}
		return $results;
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
		
		$sql = $this->renderStatement('select', $query);

		$results = $this->query($sql);

		if ($type === 'first')
			return isset($results[0]) ? $results[0] : false;

		return $results;
	}

	public function findAll($table, $options = array())
	{
		return $this->find('all', $table, $options);
	}

	public function __call($method, $arguments)
	{
		if (strpos($method, 'findAll') === 0)
		{
			$magic = explode('findAll', $method);
			if (($magic[0] === '') && ($magic[1] !== ''))
			{
				if (strpos($magic[1], 'By') !== false)
					list($CamelizedTable, $CamelizedField) = explode('By', $magic[1]);
				else
					$CamelizedTable = $magic[1];

				$options = array();

				if (isset($CamelizedField))
				{
					if (empty($CamelizedField) || empty($arguments))
						trigger_error('Autobahn: You need to specify the parameters of findAllBy.',E_USER_ERROR);

					$values = is_array($arguments[0]) ? $arguments[0] : $arguments;

					$options['conditions'] = array(underscore($CamelizedField) => $values);
				}

				return $this->find('all', underscore($CamelizedTable), $options);
			}
		}
		elseif ((count($arguments) === 1) && (strpos($method, 'find') === 0))
		{
			$magic = explode('find', $method);
			if (($magic[0] === '') && ($magic[1] !== '') && (strpos($magic[1], 'By') !== false))
			{
				list($CamelizedTable, $CamelizedField) = explode('By', $magic[1]);
				
				if (empty($CamelizedField) || !isset($arguments[0]))
					trigger_error('Autobahn: You need to specify the values of findBy.',E_USER_ERROR);

				$options = array(
					'conditions' => array(underscore($CamelizedField) => $arguments[0]),
					'limit' => '1',
				);

				return $this->find('first', underscore($CamelizedTable), $options);
			}
		}
		elseif ((count($arguments) === 1) && (strpos($method, 'insert') === 0))
		{
			$magic = explode('insert', $method);
			if (($magic[0] === '') && ($magic[1] !== ''))
			{
				if (!isset($arguments[0]) || !is_array($arguments[0]) || empty($arguments[0]))
					trigger_error('Autobahn: You need to specify the values of insert.',E_USER_ERROR);

				$query = array(
					'table' => underscore($magic[1]),
					'fields' => array_keys($arguments[0]),
					'values' => $arguments[0],
				);
				
				return $this->execute($this->renderStatement('insert', $query));
			}
		}
		elseif ((count($arguments) === 2) && (strpos($method, 'update') === 0))
		{
			$magic = explode('update', $method);
			if (($magic[0] === '') && ($magic[1] !== ''))
			{
				if (!isset($arguments[0]) || !is_array($arguments[0]) || empty($arguments[0]))
					trigger_error('Autobahn: You need to specify the values of insert.',E_USER_ERROR);

				if (!isset($arguments[1]) || !is_array($arguments[1]) || empty($arguments[1]))
					trigger_error('Autobahn: You need to specify the conditions of insert.',E_USER_ERROR);

				$query = array(
					'table' => underscore($magic[1]),
					'declarations' => $arguments[0],
					'conditions' => $arguments[1],
				);
				
				return $this->execute($this->renderStatement('update', $query));
			}
		}
		elseif ((count($arguments) > 0) && (strpos($method, 'delete') === 0))
		{
			$magic = explode('delete', $method);
			if (($magic[0] === '') && ($magic[1] !== '') && (strpos($magic[1], 'By') !== false))
			{
				list($CamelizedTable, $CamelizedField) = explode('By', $magic[1]);
				
				if (empty($CamelizedField) || !isset($arguments[0]))
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

	public function showLogs()
	{
		if (PHP_SAPI != 'cli')
			pr($this->__logs);
		else
			print_r($this->__logs);
	}

	private function __execute($query)
	{
		return $this->__pdo_statement = $this->__pdo->query($query);
	}

	private function __lastAffected()
	{
		return null;
	}

	private function __lastError()
	{
		return $this->__pdo->errorInfo();
	}

	private function __lastNumRows()
	{
		return null;
	}

	private function __logQuery($sql, $time)
	{
		$this->__logs[] = array(
			'sql' => $sql,
			'took' => round((getMicrotime() - $time) * 1000, 0),
			'error' => $this->__lastError(),
			'num_rows' => $this->__lastNumRows(),
		);
	}
}
