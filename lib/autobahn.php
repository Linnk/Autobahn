<?php

error_reporting(E_ALL);

if (!defined('DS'))
{
	define('DS', DIRECTORY_SEPARATOR);
}

if (!defined('AUTOBAHN_ROOT'))
{
	define('AUTOBAHN_ROOT', dirname(__FILE__).DS);
}
if (!defined('AUTOBAHN_DBO'))
{
	define('AUTOBAHN_DBO', AUTOBAHN_ROOT.'dbo'.DS);
}
 
require('autobahn-functions.php');
require('autobahn.manager.php');

class Autobahn
{
	private static $__instances = array();

	public static function getConnection($config = null)
	{
		if (!is_array($config))
			trigger_error('No proper configuration.');

		//$db = new PDO('mysql:host=localhost;dbname=testdb;charset=utf8', 'username', 'password');
		$db = $config['driver'].':'.$config['user'].'@'.$config['host'];

		if (isset(self::$__instances[$db]))
			return self::$__instances[$db];

		require_once AUTOBAHN_DBO.'dbo_'.$config['driver'].'.php';

		$AutobahnDbo = 'AutobahnDbo'.ucfirst($config['driver']);

		return self::$__instances[$db] = new $AutobahnDbo($config);
	}

	private function __construct() { }
}
