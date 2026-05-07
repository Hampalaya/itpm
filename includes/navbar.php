<?php
// No session_start() here. Parent pages must start sessions first.
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}
?>
<nav class="navbar">
  <div class="nav-left">
    <a href="students.php" class="nav-brand">FEED System</a>
  </div>
  <div class="nav-links">
    <a href="students.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'students.php') ? 'active' : '' ?>">Students</a>
    <a href="measurements.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'measurements.php') ? 'active' : '' ?>">Measurements</a>
    <?php if ($_SESSION['role'] === 'admin'): ?>
      <a href="reports.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : '' ?>">Reports</a>
    <?php endif; ?>
  </div>
  <div class="nav-right">
    <span class="user-info"><?= htmlspecialchars($_SESSION['username']) ?> | <?= ucfirst($_SESSION['role']) ?></span>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</nav>