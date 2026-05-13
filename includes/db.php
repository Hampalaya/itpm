<?php
$host = '127.0.0.1'; $db = 'feed_db'; $user = 'root'; $pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try {
	$pdo = new PDO($dsn, $user, $pass, $options);
	// Keep DB-side timestamps (NOW/CURRENT_TIMESTAMP) aligned to PH time.
	$pdo->exec("SET time_zone = '+08:00'");
} 
catch (\PDOException $e) { error_log("DB Error: " . $e->getMessage()); die("System maintenance."); }
?>