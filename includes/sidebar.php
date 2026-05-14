<?php
// SECURITY: Ensure user is logged in before rendering sidebar
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/session.php';
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
    <div class="header-left" style="gap: 8px;">
      <div class="header-icon" style="background: transparent; box-shadow: none; border-radius: 0; width: 44px; height: 44px;">
        <img src="images/logo_feed.png" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
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

    <a href="student_profile.php" class="nav-item <?= $currentPage === 'student_profile' ? 'active' : '' ?>" data-page="student-profiles">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </span>
      <span class="nav-label">Student Profiles</span>
    </a>

    <a href="measurement.php" class="nav-item <?= $currentPage === 'measurement' ? 'active' : '' ?>" data-page="measurements">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M2 12h2M6 8v8M10 5v14M14 9v6M18 3v18M22 12h0"/><rect x="1" y="1" width="22" height="22" rx="3"/></svg>
      </span>
      <span class="nav-label">Measurements</span>
    </a>

    <a href="data_validation.php" class="nav-item <?= $currentPage === 'data_validation' ? 'active' : '' ?>" data-page="data-validation">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </span>
      <span class="nav-label">Data Validation</span>
    </a>

    <a href="feeding_log.php" class="nav-item <?= $currentPage === 'feeding_log' ? 'active' : '' ?>" data-page="feeding-log">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M8 3v18"/><path d="M12 3v18"/><path d="M17 7c0 2-2 3-2 5v9"/><path d="M3 7h14"/></svg>
      </span>
      <span class="nav-label">Feeding Log</span>
    </a>

    <a href="nutritional_status.php" class="nav-item <?= $currentPage === 'nutritional_status' ? 'active' : '' ?>" data-page="nutritional-status">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2v20"/><path d="M5 7h7"/><path d="M5 12h10"/><path d="M5 17h7"/></svg>
      </span>
      <span class="nav-label">Nutritional Status</span>
    </a>

    <a href="report.php" class="nav-item <?= $currentPage === 'report' ? 'active' : '' ?>" data-page="reports">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      </span>
      <span class="nav-label">Reports</span>
    </a>

    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <a href="user_management.php" class="nav-item <?= $currentPage === 'user_management' ? 'active' : '' ?>" data-page="user-management">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11v4"/><path d="M20 13h4"/></svg>
      </span>
      <span class="nav-label">User Management</span>
    </a>
    <?php endif; ?>

  </div>

  <!-- Footer: User Profile -->
  <div class="sidebar-footer">
    <div class="user-profile" id="userProfileBtn">
      <div class="user-avatar"><?= $userInitial ?></div>
      <div class="user-info">
        <div class="user-name"><?= $userName ?></div>
        <div class="user-role"><?= $userRole ?></div>
      </div>
      <div class="user-dropdown">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
    </div>

    <!-- Profile Popup Menu -->
    <div class="profile-popup" id="profilePopup">
      <div class="popup-item" onclick="openSidebarChangePassword()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        Change Password
      </div>
      <a href="logout.php" class="popup-item text-danger">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        Logout
      </a>
    </div>
  </div>
</nav>

<!-- Change Password Modal (Global) -->
<div class="sidebar-modal-overlay" id="sidebarChangePassModal">
  <div class="sidebar-modal">
    <div class="sidebar-modal-header">
      <h3 style="margin: 0;font-size: 18px;color: #101828;">Change Password</h3>
      <button onclick="closeSidebarChangePassword()" class="sidebar-modal-close" style="background: none;border: none;cursor: pointer;color: #6b7280;padding: 4px;display: flex;align-items: center;justify-content: center;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px;height: 20px;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <form method="post" action="change_password.php" id="sidebarChangePassForm">
      <div style="margin-bottom: 16px;">
        <label style="display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#374151">New Password</label>
        <input type="password" name="new_password" required minlength="6" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;">
      </div>
      <div style="margin-bottom: 24px;">
        <label style="display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:#374151">Confirm Password</label>
        <input type="password" name="confirm_password" required minlength="6" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;">
      </div>
      <div style="display:flex;justify-content:flex-end;gap:12px;">
        <button type="button" onclick="closeSidebarChangePassword()" style="padding:8px 16px;border-radius:8px;background:#fff;border:1px solid #e5e7eb;font-weight:600;cursor:pointer">Cancel</button>
        <button type="submit" style="padding:8px 16px;border-radius:8px;background:#dc2626;color:white;border:none;font-weight:600;cursor:pointer">Save Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Mobile Toggle Button (outside sidebar for positioning) -->
<button class="mobile-toggle" id="mobileToggle" aria-label="Open menu">
  <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>
