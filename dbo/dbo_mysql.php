<?php

	class AutobahnDboMysql extends AutobahnManager
	{		
		private $__dbo_connection;
		private $__resource;

		public function __construct($config)
		{
			parent::__construct($config);
		}
		public function connect($host, $user, $password, $database, $encoding)
		{
			if(!$this->__dbo_connection = mysql_connect($host, $user, $password))
				trigger_error('Database connection error',E_USER_ERROR);
			
			if(!mysql_select_db($database, $this->__dbo_connection))
				trigger_error('Database selection error',E_USER_ERROR);
			
			$this->connected = true;
			
			$this->setEncoding($encoding);
		}
		public function close()
		{
			return mysql_close($this->__dbo_connection);
		}
		public function renderStatement($type, $query)
		{
			extract($query);

			if(isset($conditions) && !empty($conditions))
				$conditions = 'WHERE '.$conditions;

			if(isset($group) && !empty($group))
				$group = 'GROUP BY '.$group;

			if(isset($order) && !empty($order))
				$order = 'ORDER BY '.$order;

			if(isset($limit) && !empty($limit))
				$limit = 'LIMIT '.$limit;

			if(isset($fields) && is_array($fields))
				$fields = implode(', ',$fields);

			if(isset($values) && is_array($values))
				$values = implode(', ',$values);

			switch (strtolower($type))
			{
				case 'select':
					return "SELECT {$fields} FROM {$table} {$conditions} {$group} {$order} {$limit}";
				break;
				case 'insert':
					return "INSERT INTO {$table} ({$fields}) VALUES ({$values})";
				break;
				case 'update':
					return "UPDATE {$table} SET {$fields} {$conditions}";
				break;
				case 'delete':
					return "DELETE FROM {$table} {$conditions}";
				break;
			}
		}
		public function getRow($row = null, $index = 'associative')
		{
			if(is_numeric($row))
				mysql_data_seek($this->__resource, $row);

			switch($index)
			{
				case 'associative':
					return mysql_fetch_array($this->__resource, MYSQL_ASSOC);
				case 'number':
					return mysql_fetch_array($this->__resource, MYSQL_NUM);
				case 'both':
					return mysql_fetch_array($this->__resource, MYSQL_BOTH);
			}
			return false;
		}
		public function lastInsertId()
		{
			return mysql_insert_id($this->__dbo_connection);
		}
		public function setEncoding($encoding)
		{
			$this->_execute('SET NAMES '.$encoding);
		}
		public function getEncoding()
		{
			return mysql_client_encoding($this->connection);
		}
		public function getFields()
		{
			$fields = array();
			while($field = mysql_fetch_field($this->__resource))
			{
				$fields[] = array('table' => $field->table, 'name' => $field->name);
			}
			return $fields;
		}
		public function lastAffected()
		{
			if(is_resource($this->__resource))
				return mysql_affected_rows($this->__dbo_connection);

			return null;
		}
		public function lastError()
		{
			if($errno = mysql_errno($this->__dbo_connection))
				return $errno.': '.mysql_error($this->__dbo_connection);

			return null;
		}
		public function lastNumRows()
		{
			if(is_resource($this->__resource))
				return mysql_num_rows($this->__resource);

			return null;
		}
    	protected function _sqlEquivalentConditions($arguments)
    	{
    		$conditions = array();
			foreach($arguments as $field => $value)
			{						
				$operator = is_string($value) ? 'LIKE' : '=';
				if(is_string($field) && ($value = $this->_sqlEquivalentValue($value)))
					$conditions[] = "$field $operator $value";
			}
			return $conditions;
    	}
    	protected function _sqlEquivalentDeclarations($arguments)
    	{
    		$declarations = array();
			foreach($arguments as $field => $value)
			{
				if(is_string($field) && ($value = $this->_sqlEquivalentValue($value)))
					$declarations[] = "$field = $value";
			}
			return $declarations;
    	}
    	protected function _sqlEquivalentValues($arguments)
    	{
			foreach($arguments as $field => $value)
			{						
				if($value = $this->_sqlEquivalentValue($value))
					$arguments[$field] = $value;
			}
			return $arguments;
    	}
    	protected function _sqlEquivalentValue($value)
    	{
			if(is_numeric($value))
				return (string) $value;
			elseif(is_string($value))
				return '\''.addslashes($value).'\'';
			elseif(is_bool($value) === true)
				return $value === true ? 'TRUE' : 'FALSE';
			elseif($value == null)
				return 'NULL';

			return false;			
    	}
		protected function _execute($query)
		{
			return $this->__resource = mysql_query($query, $this->__dbo_connection);
		}
	}

?>
