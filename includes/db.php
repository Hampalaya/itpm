<?php
// Get credentials from DigitalOcean Environment Variables
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '25060'; // DO Managed DBs use 25060
$db   = getenv('DB_NAME') ?: 'feed_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// Added port to the DSN
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

// Point PDO to your CA cert in /includes
$caCertPath = __DIR__ . "/ca-certificate.crt"; // <-- change filename to the exact cert filename you added

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // Use the CA cert file path (NOT true)
    PDO::MYSQL_ATTR_SSL_CA => $caCertPath,

    // Optional but commonly needed with managed MySQL
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
}
catch (\PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    die("System maintenance.");
}
?>
