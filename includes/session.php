<?php
require_once __DIR__ . '/db.php';

// Use Philippine local time across the app.
date_default_timezone_set('Asia/Manila');

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

// Update last active timestamp for logged-in users
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Silently fail if column doesn't exist yet to prevent breaking the app before migration
    }

    try {
        $stmt = $pdo->prepare("SELECT role, assigned_section, assigned_grade, is_active FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("SELECT role, assigned_section, is_active FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!empty($currentUser)) {
            $currentUser['assigned_grade'] = null;
        }
    }

    if (empty($currentUser) || (isset($currentUser['is_active']) && (int) $currentUser['is_active'] !== 1)) {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    $_SESSION['role'] = $currentUser['role'] ?? $_SESSION['role'] ?? null;
    $_SESSION['assigned_section'] = $currentUser['assigned_section'] ?? null;
    $_SESSION['assigned_grade'] = $currentUser['assigned_grade'] ?? null;
}
