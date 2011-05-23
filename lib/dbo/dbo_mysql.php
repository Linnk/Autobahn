<?php

	class AutobahnDboMysql extends AutobahnManager
	{		
		private $__dbo_connection;
		private $__resource;
		
		private $operators = array('=','<>','<=>','>','<','>=','<=','LIKE','NOT');

		public function __construct($config)
		{
			parent::__construct($config);
		}
		public function connect($host, $user, $password, $database, $encoding)
		{
			if(!$this->__dbo_connection = mysql_connect($host, $user, $password))
				trigger_error('Autobahn: Database connection error',E_USER_ERROR);
			
			if(!mysql_select_db($database, $this->__dbo_connection))
				trigger_error('Autobahn: Database selection error',E_USER_ERROR);
			
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
				$conditions = 'WHERE '.(is_array($conditions) ? $this->_sqlEquivalentConditions($conditions) : $conditions);
			else
				$conditions = '';

			if(isset($group) && !empty($group))
				$group = 'GROUP BY '.(is_array($group) ? $this->_sqlEquivalentGroup($group) : $group);
			else
				$group = '';

			if(isset($order) && !empty($order))
				$order = 'ORDER BY '.(is_array($order) ? $this->_sqlEquivalentOrder($order) : $order);
			else
				$order = '';

			if(isset($fields) && !empty($fields))
				$fields = is_array($fields) ? $this->_sqlEquivalentFields($fields) : $fields;

			if(isset($limit) && !empty($limit))
				$limit = 'LIMIT '.$limit;
			else
				$limit = '';

			switch (strtolower($type))
			{
				case 'select':
					return "SELECT {$fields} FROM {$table} {$conditions} {$group} {$order} {$limit}";
				break;

				case 'insert':
					if(!isset($values) || empty($values))
						trigger_error('Autobahn: INSERT values error.',E_USER_ERROR);

					$values = implode(',', $this->_sqlEquivalentValues($values));

					return "INSERT INTO {$table} ({$fields}) VALUES ({$values})";
				break;

				case 'update':
					if(!isset($declarations) || empty($declarations))
						trigger_error('Autobahn: UPDATE values error.',E_USER_ERROR);
		
					$declarations = is_array($declarations) ? $this->_sqlEquivalentDeclarations($declarations) : $declarations;

					return "UPDATE {$table} SET {$declarations} {$conditions}";
				break;

				case 'delete':
					return "DELETE FROM {$table} {$conditions}";
				break;
			}
		}
		public function getRow($row = null, $index = 'associative')
		{
			if(is_resource($this->__resource))
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
			if(is_resource($this->__resource))
			{
				$fields = array();
				while($field = mysql_fetch_field($this->__resource))
				{
					$fields[] = array('table' => $field->table, 'name' => $field->name);
				}
				return $fields;
			}
			return false;
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
    	protected function _sqlEquivalentDeclarations($arguments)
    	{
			foreach($arguments as $field => $value)
			{
				$arguments[$field] = $field.' = '.$this->_sqlEquivalentValue($value);
			}
			return implode(', ', $arguments);
    	}
    	protected function _sqlEquivalentGroup($arguments)
    	{
			return implode(', ',$arguments);
    	}
    	protected function _sqlEquivalentOrder($arguments)
    	{
			foreach($arguments as $field => $value)
			{
				if(!is_numerc($field))
				{
					$value = strtoupper($value);
					if($value === 'ASC' || $value === 'DESC')
						$arguments[$field] = "$field $value";
					else
						trigger_error('Autobahn: Order\'s parameter unknown',E_USER_ERROR);
				}
			}
			return implode(', ',$arguments);
    	}
    	protected function _sqlEquivalentFields($arguments)
    	{
			foreach($arguments as $field => $value)
			{
				if(!is_numeric($field))
					$arguments[$field] = "$field AS '$value'";
			}
			return implode(', ',$arguments);
    	}
    	protected function _sqlEquivalentConditions($arguments)
    	{
    		$conditions = array();
			foreach($arguments as $field => $value)
			{
				$field = trim($field);

				if(strpos($field, ' ') !== false)
				{
					list($field,$operator) = explode(' ',$field);

					if(!in_array($operator,$this->__operators))
						trigger_error('Autobahn: Operator type unknown',E_USER_ERROR);
				}
				else
					$operator = '=';

				if(is_string($value))
				{
					$operator = ($operator === 'NOT') ? 'NOT LIKE' : 'LIKE';
					
					$value = $this->_sqlEquivalentValue($value);
				}
				elseif(is_bool($value) || is_null($value))
				{
					$operator = ($operator === 'NOT') ? 'IS NOT' : 'IS';
					
					$value = $this->_sqlEquivalentValue($value);
				}
				elseif(is_array($value))
				{
					$operator = ($operator === 'NOT') ? 'NOT IN' : 'IN';

					if(empty($value))
						trigger_error("Autobahn: The second parameter of operator $operator most not be empty.",E_USER_ERROR);

					$value = '('.implode(',', $this->_sqlEquivalentValues($value)).')';
				}
				elseif(!is_numeric($value))
					trigger_error('Autobahn: Data type not supported.',E_USER_ERROR);

				$conditions[] = $field.' '.$operator.' '.$value;
			}
			return implode(' AND ', $conditions);
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
