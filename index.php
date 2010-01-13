<?php
	
	require('autobahn.php');
	
	$library = Autobahn::getConnection('default');
	

	//	Classic SQL
	$authors = $library->query('SELECT * FROM authors');	

	//	Find (like Select)
	$book 			= $library->findBooksById(1);
	$books 			= $library->findAllBooks();
	$favorite_books = $library->findAllBooksById(1,2,3,4,5);

	//	Insert
	$newBook = array('id' => null, 'title' => 'Frameworks for PHP');
	$library->insertBooks($newBook);

	//	Update
	$values = array('title' => 'Frameworks for PHP 5', 'description' => '...');
	$conditions = array('id' => 10);
	$library->updateBooks($values, $conditions);
	
	//	Delete
	$library->deleteBooksById(10,11,12);

	//	Show stats of all queries :)
	$library->showLogs();

?>
