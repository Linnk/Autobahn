<?php

/* Loading Autobahn
-------------------------- */
require 'lib/autobahn.php';

$library = Autobahn::getConnection(array(
	'driver' 	=> 'mysql',
	'host' 		=> 'localhost',
	'user' 		=> 'root',
	'password' 	=> 'root',
	'database' 	=> 'spumer_db',
));


/* Classic SQL 
---------------------------------------------------------- */
$users = $library->query('SELECT User.* FROM users User;');


/* Magic Find All
--------------------------------- */
$users = $library->findAllUsers();


/* Magic Find Some
------------------------------------------------ */
$users = $library->findAllUsersById(array(1, 2));


/* Magic Find One
---------------------------------------------------------- */
$user = $library->findUsersByUsername('fake@username.com');


/* Magic Insert
---------------------------------------------------------------------- */
$new_user = array('username' => 'fake@username.com', 'name' => 'Fake');

$library->insertUsers($new_user);


/* Magic Update
---------------------------------------------------------------------- */
$values     = array('name' => 'Totally fake');
$conditions = array('username' => 'fake@username.com');

$library->updateUsers($values, $conditions);


/* Magic Delete
---------------------------------------------------------------------- */
$library->deleteUsersById(4);


// TO-DO:
// $library->showLogs();
