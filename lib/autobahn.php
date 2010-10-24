<?php

	error_reporting(E_ALL);
	
	if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

	if(!defined('AUTOBAHN_ROOT')) define('AUTOBAHN_ROOT', dirname(__FILE__).DS);
	if(!defined('AUTOBAHN_DBO')) define('AUTOBAHN_DBO', AUTOBAHN_ROOT.'dbo'.DS);

	require('autobahn-functions.php');
	require('autobahn.manager.php');

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
					trigger_error('No database configuration.', E_ERROR);
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
					trigger_error('No "database" name in '.$db.' configuration', E_ERROR);
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
