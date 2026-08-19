<?php
	//check if the database file exists and create a new if not
	/* if(!is_file('db/data.sqlite')){
		file_put_contents('db/data.sqlite', null);
	} */
	// connecting the database
	//$conn = new PDO('sqlite:/home/user/Apps/data.sqlite');
	$conn = new PDO('sqlite:/home/user/Apps/qims_logger.db');
	//Setting connection attributes
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	// This file is written to concurrently by the web (PHP) and the atg-reader
	// C++ daemon (sensor readings + cloud MQTT pending queue, several times a
	// second). Default rollback-journal mode takes an exclusive lock on the
	// whole file for every write and fails immediately if another writer holds
	// it ("database is locked"). WAL mode lets readers and writers proceed
	// without blocking each other in most cases, and busy_timeout makes PHP
	// wait/retry for a bit instead of throwing immediately on the rare
	// contention that remains.
	$conn->exec('PRAGMA journal_mode=WAL');
	$conn->exec('PRAGMA busy_timeout=5000');
?>