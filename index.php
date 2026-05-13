<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } else {
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role, assigned_section FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['assigned_section'] = $user['assigned_section'];
                logAudit($pdo, 'login', 'users', $user['id'], 'Successful login');
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="images/logo_feed.png?v=1">
    <title>Nasugbu West Central School — Login</title>
    <link rel="stylesheet" href="css/login.css" />
  </head>
  <body>
    <div class="page-wrapper">
      <div class="header-section">
        <div class="logo-wrapper">
            <img src="images/logo.jpg" alt="Nasugbu West Central School" class="logo" />
        </div>
        <h3 class="nwcs-header">
              <span style="width:6px;height:6px;border-radius:50%;background:#9B1A2F;flex-shrink:0;display:inline-block;"></span>
          FEEDING ENCODING, EVALUATION, AND DATA MANAGEMENT
        </h3>
      </div>
      <div class="login-container">
        <h2 class="nas-title">Nasugbu West Central School</h2>
        <p class="login">Login</p>
        <p class="label">Enter your credentials to access the system.</p>

        <?php if (!empty($error)): ?>
          <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="" method="post" aria-label="Login form">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <div class="form-row">
            <label class="label" for="username">Username</label>
            <input
              id="username"
              name="username"
              type="text"
              class="input-field"
              placeholder="Enter your username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              required
            />
          </div>

          <div class="form-row">
            <label class="label" for="password">Password</label>
            <input
              id="password"
              name="password"
              type="password"
              class="input-field"
              placeholder="Enter your password"
              required
            />
          </div>

          <div class="form-row">
            <button type="submit" class="login-button">Login</button>
          </div>
        </form>
      </div>
    </div>
  </body>
</html>
