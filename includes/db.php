<?php
// Get credentials from DigitalOcean Environment Variables
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '25060'; // DO Managed DBs use 25060
$db   = getenv('DB_NAME') ?: 'feed_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// Added port to the DSN
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // DigitalOcean Managed DBs often require SSL to connect
    PDO::MYSQL_ATTR_SSL_CA => true, 
];

try { 
    $pdo = new PDO($dsn, $user, $pass, $options); 
} 
catch (\PDOException $e) { 
    // This logs the real error to DO logs but hides it from users
    error_log("DB Error: " . $e->getMessage()); 
    die("System maintenance."); 
}
?>
