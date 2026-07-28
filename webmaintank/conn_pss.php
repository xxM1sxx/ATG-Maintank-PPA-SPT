<?php
	//check if the database file exists and create a new if not
	/* if(!is_file('db/data.sqlite')){
		file_put_contents('db/data.sqlite', null);
	} */
	// connecting the database
	//$conn = new PDO('sqlite:/home/user/Apps/data.sqlite');
	$conn_pss = new PDO('sqlite:/home/user/Apps/qimsPSS.db');
	//Setting connection attributes
	$conn_pss->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>

