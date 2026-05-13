<?php
// Deployment-first config with local-safe fallbacks
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'feed_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// Use env port if provided. Otherwise: local -> 3306, remote -> 25060
$isLocalHost = in_array($host, ['127.0.0.1', 'localhost'], true);
$port = getenv('DB_PORT') ?: ($isLocalHost ? '3306' : '25060');

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// Optional SSL for managed DB (only if cert exists and PDO mysql SSL constants are available)
$caCertPath = __DIR__ . '/ca-certificate.crt';
if (!$isLocalHost && file_exists($caCertPath) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $caCertPath;
    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Keep DB timestamps (NOW/CURRENT_TIMESTAMP) in PH time
    $pdo->exec("SET time_zone = '+08:00'");
} catch (\PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());
    die('System maintenance.');
}
?>
