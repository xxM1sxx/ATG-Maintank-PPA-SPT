<?php
	session_start();
	require_once 'conn.php';
	
	
	$username = $_POST['username'];
	$password = $_POST['password'];
	
	$query = "SELECT COUNT(*) as count FROM `logger_user` WHERE `username` = :username AND `password` = :password";
	$stmt = $conn->prepare($query);
	$stmt->bindParam(':username', $username);
	$stmt->bindParam(':password', $password);
	$stmt->execute();
	$row = $stmt->fetch();
	
	$count = $row['count'];
	 
	if($count > 0){
		$_SESSION['username'] = $username;
		header('location:home.php');
	}else{
		$_SESSION['error'] = "Salah";
		header('location:index.php');
	}
	
?>