CREATE TABLE books(
	id bigint unsigned not null primary key auto_increment,
	author_id bigint unsigned not null,
	title tinytext
);
CREATE TABLE authors(
        id bigint unsigned not null primary key auto_increment,
        firstname tinytext,
	lastname tinytext
);

