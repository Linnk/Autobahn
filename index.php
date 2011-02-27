<?php

	class DB_CONFIG
	{
		public $default = array(
			'driver' 	=> 'mysql',
			'host' 		=> 'localhost',
			'user' 		=> 'root',
			'password' 	=> 'root',
			'database' 	=> 'library',
		);
	}

	require('lib/autobahn.php');

	$library = Autobahn::getConnection('default');

	/* Classic SQL */
	$authors = $library->query('SELECT Author.*, Book.* FROM authors Author, books Book WHERE Book.author_id = Author.id');
	
	/* Magic Find */
	$book = $library->findBooksById(1);
	$books = $library->findAllBooks();
	$favorite_books = $library->findAllBooksById(array(1,2,3,4,5));

	/* Insert */
	$newBook = array('id' => null, 'author_id' => 1, 'title' => 'Frameworks for languages');
	$library->insertBooks($newBook);

	/* Update */
	$values = array('title' => 'Frameworks for PHP 5', 'description' => '...');
	$conditions = array('id' => 1);
	$library->updateBooks($values, $conditions);
	
	/* Delete */
	$library->deleteBooksById(99);

	/* Show some cool stats of all queries :) */
	$library->showLogs();

?>
