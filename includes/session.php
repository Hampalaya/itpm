<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '7200');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('FEEDSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    class FeedDatabaseSessionHandler implements SessionHandlerInterface
    {
        private PDO $pdo;
        private int $ttl;

        public function __construct(PDO $pdo, int $ttl)
        {
            $this->pdo = $pdo;
            $this->ttl = $ttl;
        }

        public function open(string $path, string $name): bool
        {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS app_sessions (
                    id VARCHAR(128) PRIMARY KEY,
                    data MEDIUMBLOB NOT NULL,
                    expires INT UNSIGNED NOT NULL,
                    INDEX idx_expires (expires)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            return true;
        }

        public function close(): bool
        {
            return true;
        }

        public function read(string $id): string|false
        {
            $stmt = $this->pdo->prepare("SELECT data FROM app_sessions WHERE id = ? AND expires >= ?");
            $stmt->execute([$id, time()]);
            $data = $stmt->fetchColumn();
            return $data === false ? '' : (string) $data;
        }

        public function write(string $id, string $data): bool
        {
            $stmt = $this->pdo->prepare("
                INSERT INTO app_sessions (id, data, expires)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE data = VALUES(data), expires = VALUES(expires)
            ");
            return $stmt->execute([$id, $data, time() + $this->ttl]);
        }

        public function destroy(string $id): bool
        {
            $stmt = $this->pdo->prepare("DELETE FROM app_sessions WHERE id = ?");
            return $stmt->execute([$id]);
        }

        public function gc(int $max_lifetime): int|false
        {
            $stmt = $this->pdo->prepare("DELETE FROM app_sessions WHERE expires < ?");
            $stmt->execute([time()]);
            return $stmt->rowCount();
        }
    }

    session_set_save_handler(new FeedDatabaseSessionHandler($pdo, (int) ini_get('session.gc_maxlifetime')), true);
    session_start();
}
