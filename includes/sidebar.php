<?php
// SECURITY: Ensure user is logged in before rendering sidebar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Dynamic user info from session
$userName = htmlspecialchars($_SESSION['username'] ?? 'User');
$userRole = htmlspecialchars(ucfirst($_SESSION['role'] ?? 'encoder'));
$userInitial = strtoupper(substr($userName, 0, 1));

// Auto-highlight active link based on current filename
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Overlay for mobile menu -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <!-- Header -->
  <div class="sidebar-header">
    <div class="header-left">
      <div class="header-icon">
        <svg viewBox="0 0 24 24"><path d="M7 3v18M3 7h8c0 0 0-4-4-4s-4 4-4 4zm-1 14h12c0 0 0-3 3-3s3 3 3 3M17 3v18M21 7h-8c0 0 0-4 4-4s4 4 4 4z"/></svg>
      </div>
      <div class="header-text">
        <h2>FEED</h2>
        <p>System</p>
      </div>
    </div>
    <button class="collapse-btn" id="collapseBtn" aria-label="Collapse sidebar">
      <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
  </div>

  <!-- Navigation Items -->
  <div class="sidebar-nav">
    <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>" data-page="dashboard">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
      </span>
      <span class="nav-label">Dashboard</span>
    </a>

    <a href="students.php" class="nav-item <?= $currentPage === 'students' ? 'active' : '' ?>" data-page="student-profiles">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </span>
      <span class="nav-label">Student Profiles</span>
    </a>

    <a href="measurements.php" class="nav-item <?= $currentPage === 'measurements' ? 'active' : '' ?>" data-page="measurements">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M2 12h2M6 8v8M10 5v14M14 9v6M18 3v18M22 12h0"/><rect x="1" y="1" width="22" height="22" rx="3"/></svg>
      </span>
      <span class="nav-label">Measurements</span>
    </a>

    <a href="reports.php" class="nav-item <?= $currentPage === 'reports' ? 'active' : '' ?>" data-page="reports">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      </span>
      <span class="nav-label">Reports</span>
    </a>

    <a href="#" class="nav-item" data-page="settings">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      </span>
      <span class="nav-label">Settings</span>
    </a>
  </div>

  <!-- Footer: User Profile -->
  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="user-avatar"><?= $userInitial ?></div>
      <div class="user-info">
        <div class="user-name"><?= $userName ?></div>
        <div class="user-role"><?= $userRole ?></div>
      </div>
      <div class="user-dropdown">
        <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
    </div>
  </div>
</nav>

<!-- Mobile Toggle Button (outside sidebar for positioning) -->
<button class="mobile-toggle" id="mobileToggle" aria-label="Open menu">
  <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>