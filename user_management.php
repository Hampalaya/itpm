<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 🔐 ADMIN-ONLY GUARD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// ========== HANDLE POST ACTIONS ==========
$message = '';
$messageType = '';

// TOGGLE User Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['activate', 'deactivate'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    // Prevent changing yourself
    if ($userId === $_SESSION['user_id']) {
        $message = 'You cannot change your own account status.'; $messageType = 'error';
    } elseif ($userId > 0) {
        $status = $_POST['action'] === 'activate' ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$status, $userId]);
        $verb = $status ? 'Activated' : 'Deactivated';
        logAudit($pdo, 'update', 'users', $userId, "$verb user account");
        $message = "User $verb."; $messageType = 'success';
    }
}

// ADD or UPDATE User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add','update'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? 'encoder';
    $section = trim($_POST['assigned_section'] ?? '');
    
    // For inserting, default to active (1), for updating rely on the post data
    $isActive = $_POST['action'] === 'add' ? 1 : (($_POST['is_active'] ?? '0') === '1' ? 1 : 0);
    
    // Validate
    if (!$fullName || !$username || !in_array($role, ['admin','encoder'])) {
        $message = 'Fill all required fields with valid values.'; $messageType = 'error';
    } else {
        try {
            if ($_POST['action'] === 'add') {
                if ($role === 'admin') {
                    $message = 'You are not permitted to add a new Administrator account.'; $messageType = 'error';
                } else {
                    // Check duplicate username
                    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $chk->execute([$username]);
                    if ($chk->fetch()) {
                        $message = 'Username already exists.'; $messageType = 'error';
                    } else {
                        // Generate random password for new users (admin can reset later)
                        $tempPass = bin2hex(random_bytes(4));
                        $hash = password_hash($tempPass, PASSWORD_DEFAULT);
                        
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, assigned_section, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $hash, $role, $fullName, $role === 'encoder' && $section ? $section : null, $isActive]);
                        
                        // Show temp password in toast
                        $message = "User added. Temporary password: <strong>$tempPass</strong> (user should change on first login)"; 
                        $messageType = 'success';
                        logAudit($pdo,'insert','users',$pdo->lastInsertId(),"Added user: $username");
                    }
                }
            } else {
                // Update existing user
                $userId = (int)($_POST['id'] ?? 0);
                
                $currStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $currStmt->execute([$userId]);
                $currentRole = $currStmt->fetchColumn();

                if ($userId === $_SESSION['user_id'] && $role !== 'admin') {
                    $message = 'You cannot demote your own account.'; $messageType = 'error';
                } elseif ($currentRole !== 'admin' && $role === 'admin') {
                    $message = 'You are not permitted to promote users to Administrator.'; $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, role=?, assigned_section=?, is_active=? WHERE id=?");
                    $stmt->execute([$fullName, $role, $role === 'encoder' && $section ? $section : null, $isActive, $userId]);
                    logAudit($pdo,'update','users',$userId,"Updated user: $username");
                    $message = 'User updated.'; $messageType = 'success';
                }
            }
        } catch (PDOException $e) {
            error_log("User management error: " . $e->getMessage());
            $message = 'Database error.'; $messageType = 'error';
        }
    }
}

// ========== FETCH USERS ==========
$search = trim($_GET['search'] ?? '');
$where = []; $params = [];
if ($search) {
    $where[] = "(full_name LIKE ? OR username LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}
$sql = "SELECT id, username, role, full_name, assigned_section, is_active, created_at, last_active FROM users";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$users = $stmt->fetchAll();

// Current user info for display
$currentUser = $pdo->prepare("SELECT full_name, role FROM users WHERE id = ?");
$currentUser->execute([$_SESSION['user_id']]);
$currentUser = $currentUser->fetch();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/user_management.css" />
  <link rel="stylesheet" href="css/sidebar.css" />
  <script src="js/sidebar.js" defer></script>
  <style>
    /* Minimal inline styles to guarantee functionality */
    .app-container { min-height: 100vh; background: #f9fafb; }
    .main-content { display: block; min-height: 100vh; padding: 24px 40px; }
    .page-header { display:flex; justify-content:space-between; align-items:center; padding:24px 40px; border-bottom:1px solid #e5e7eb; background:#fff; }
    .page-title { font-size:24px; font-weight:700; color:#101828; }
    .page-subtitle { font-size:14px; color:#6b7280; margin-top:4px; }
    .btn-add-user { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:#00bc7d; color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; }
    .btn-add-user svg { width:18px; height:18px; }
    
    .logged-in-card { display:flex; justify-content:space-between; align-items:center; padding:16px 40px; background:#fff; border-bottom:1px solid #e5e7eb; }
    .logged-in-info { display:flex; align-items:center; gap:12px; }
    .avatar { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:600; }
    .avatar-green { background:#00bc7d; }
    .logged-in-label { font-size:12px; color:#6b7280; }
    .logged-in-name { font-weight:600; color:#101828; }
    .role-badge-viewer { padding:6px 12px; background:#f3f4f6; border-radius:999px; font-size:12px; font-weight:600; color:#6b7280; display:flex; align-items:center; gap:6px; }
    
    .system-users-card { padding:24px 40px; }
    .card-title { font-size:18px; font-weight:600; color:#101828; margin-bottom:16px; }
    .table-wrapper { overflow-x:auto; background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    .user-table { width:100%; border-collapse:collapse; min-width:800px; }
    .user-table th { padding:12px 16px; text-align:left; font-size:13px; font-weight:600; color:#6b7280; background:#f9fafb; border-bottom:1px solid #e5e7eb; }
    .user-table td { padding:12px 16px; font-size:14px; border-bottom:1px solid #f3f4f6; }
    .user-table tr:hover { background:#f9fafb; }
    
    .status-badge { padding:4px 10px; border-radius:999px; font-size:11px; font-weight:600; }
    .status-active { background:#dcfce7; color:#166534; }
    .status-inactive { background:#f3f4f6; color:#6b7280; }
    
    .action-btn { padding:6px 12px; border-radius:6px; font-size:12px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; margin-right:4px; }
    .action-btn.edit { color:#101828; }
    .action-btn.delete { color:#e7000b; }
    
    /* Modal */
    .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; display:none; align-items:center; justify-content:center; }
    .modal-overlay.active { display:flex; }
    .modal { background:#fff; border-radius:12px; width:90%; max-width:480px; padding:24px; box-shadow:0 10px 40px rgba(0,0,0,0.2); }
    .modal-title { font-size:18px; font-weight:600; color:#101828; margin-bottom:20px; }
    .form-group { margin-bottom:16px; }
    .form-label { display:block; font-size:13px; font-weight:500; color:#374151; margin-bottom:6px; }
    .form-input, .form-select { width:100%; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
    .modal-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:24px; }
    .btn-cancel { padding:10px 20px; border-radius:8px; background:#fff; border:1px solid #e5e7eb; color:#101828; font-weight:600; cursor:pointer; }
    .btn-save { padding:10px 20px; border-radius:8px; background:#00bc7d; color:white; border:none; font-weight:600; cursor:pointer; }
    .btn-confirm-delete { padding:10px 20px; border-radius:8px; background:#e7000b; color:white; border:none; font-weight:600; cursor:pointer; }
    
    /* Toast */
    .toast-container { position:fixed; bottom:24px; right:24px; z-index:10000; }
    .toast { background:#101828; color:white; padding:12px 20px; border-radius:8px; margin-top:8px; display:flex; align-items:center; gap:10px; animation:slideIn 0.3s; }
    .toast.success { background:#00bc7d; }
    .toast.error { background:#e7000b; }
    @keyframes slideIn { from { transform:translateY(100px); opacity:0; } to { transform:translateY(0); opacity:1; } }
    .toast button { background:none; border:none; color:inherit; font-size:18px; cursor:pointer; margin-left:auto; }
    
    @media (max-width:768px) {
      .page-header, .logged-in-card, .system-users-card { padding:16px; flex-direction:column; align-items:flex-start; gap:12px; }
      .user-table { font-size:12px; }
      th, td { padding:8px 12px; }
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content-wrapper" id="mainWrapper">
      <main class="main-content">
        <div class="container">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <div class="page-title">User Management</div>
          <div class="page-subtitle">Manage system users and access levels</div>
        </div>
        <button class="btn-add-user" onclick="openAddModal()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
          </svg>
          Add User
        </button>
      </div>

      <!-- Logged-in User Card -->
      <div class="logged-in-card">
        <div class="logged-in-info">
          <div class="avatar avatar-green"><?= strtoupper(substr($currentUser['full_name'],0,1)) ?></div>
          <div>
            <div class="logged-in-label">Logged in as</div>
            <div class="logged-in-name"><?= htmlspecialchars($currentUser['full_name']) ?></div>
          </div>
        </div>
        <div class="role-badge-viewer">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
          <?= ucfirst($currentUser['role']) ?>
        </div>
      </div>

      <!-- System Users Card -->
      <div class="system-users-card">
        <div class="card-title">System Users</div>
        <div class="table-wrapper">
          <table class="user-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Username</th>
                <th>Role</th>
                <th>Section</th>
                <th>Status</th>
                <th>Last Active</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="userTableBody">
              <?php if (empty($users)): ?>
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:#6b7a8d">No users found.</td></tr>
              <?php else: ?>
                <?php foreach ($users as $u):
                  $userJson = htmlspecialchars(json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                  $userNameAttr = htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px">
                      <div class="avatar" style="width:32px;height:32px;font-size:12px;background:<?= $u['is_active']?'#00bc7d':'#9ca3af' ?>"><?= strtoupper(substr($u['full_name'],0,1)) ?></div>
                      <div>
                        <div style="font-weight:600;color:#101828"><?= htmlspecialchars($u['full_name']) ?></div>
                        <div style="font-size:12px;color:#6b7280">Created: <?= date('M j, Y', strtotime($u['created_at'])) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($u['username']) ?></td>
                  <td><span style="padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600;background:#f3f4f6;color:#6b7280"><?= ucfirst($u['role']) ?></span></td>
                  <td><?= htmlspecialchars($u['assigned_section'] ?? '—') ?></td>
                  <td><span class="status-badge <?= $u['is_active']?'status-active':'status-inactive' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                  <td>
                    <?php if ($u['last_active']): ?>
                      <div style="font-size:12px;color:#374151;font-weight:500;"><?= date('M j, Y', strtotime($u['last_active'])) ?></div>
                      <div style="font-size:11px;color:#6b7280;"><?= date('g:i A', strtotime($u['last_active'])) ?></div>
                    <?php else: ?>
                      <span style="font-size:12px;color:#9ca3af;font-style:italic">Never</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button type="button" class="action-btn edit js-edit-user" data-user="<?= $userJson ?>">Edit</button>
                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                      <?php if ($u['is_active']): ?>
                        <button type="button" class="action-btn delete js-toggle-user" data-user-id="<?= (int)$u['id'] ?>" data-user-name="<?= $userNameAttr ?>" data-action="deactivate">Deactivate</button>
                      <?php else: ?>
                        <button type="button" class="action-btn js-toggle-user" style="color:#00bc7d; border-color:#e5e7eb" data-user-id="<?= (int)$u['id'] ?>" data-user-name="<?= $userNameAttr ?>" data-action="activate">Activate</button>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Add/Edit Modal -->
  <div class="modal-overlay" id="userModal">
    <div class="modal">
      <div class="modal-title" id="modalTitle">Add User</div>
      <form method="post" action="" id="userForm">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="id" id="formId" value="">
        
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" class="form-input" id="inputName" name="full_name" required placeholder="Enter full name">
        </div>
        
        <div class="form-group">
          <label class="form-label">Username *</label>
          <input type="text" class="form-input" id="inputUsername" name="username" required placeholder="Enter username">
        </div>
        
        <!-- Password only for new users -->
        <div class="form-group" id="passwordGroup">
          <label class="form-label">Password <span style="color:#9ca3af;font-weight:400">(auto-generated for new users)</span></label>
          <input type="text" class="form-input" value="••••••••" disabled style="background:#f9fafb">
          <small style="color:#6b7280;display:block;margin-top:4px">A secure temporary password will be generated and shown after creation.</small>
        </div>
        
        <div class="form-group">
          <label class="form-label">Role *</label>
          <select class="form-select" id="inputRole" name="role" required>
            <option value="admin">Administrator</option>
            <option value="encoder" selected>Encoder</option>
          </select>
        </div>
        
        <div class="form-group">
          <label class="form-label">Assigned Section <span style="color:#9ca3af;font-weight:400">(for encoders)</span></label>
          <input type="text" class="form-input" id="inputSection" name="assigned_section" placeholder="e.g., Grade 4-A" list="sections">
          <datalist id="sections">
            <option value="A"><option value="B"><option value="C"><option value="D"><option value="E">
          </datalist>
        </div>
        
        <div class="form-group" id="statusGroup">
          <label class="form-label">Status</label>
          <select class="form-select" id="inputStatus" name="is_active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn-save" id="btnSave">Add User</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Toggle Status Modal -->
  <div class="modal-overlay" id="toggleModal">
    <div class="modal">
      <div class="modal-title" id="toggleModalTitle">Deactivate User</div>
      <p class="confirm-text" style="margin:16px 0;color:#6b7280">
        Are you sure you want to <span id="toggleModalActionText" style="font-weight:600">deactivate</span> <strong id="toggleUserName"></strong>?
      </p>
      <form method="post" action="">
        <input type="hidden" name="action" id="toggleActionInput" value="deactivate">
        <input type="hidden" name="user_id" id="toggleUserId" value="">
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeToggleModal()">Cancel</button>
          <button type="submit" class="btn-confirm-delete" id="toggleConfirmBtn">Deactivate</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container" id="toastContainer">
    <?php if ($message): ?>
    <div class="toast toast-<?= $messageType ?>">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <?php if($messageType==='success'): ?>
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        <?php else: ?>
          <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="18"/>
        <?php endif; ?>
      </svg>
      <span><?= $message ?></span>
      <button type="button" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endif; ?>
  </div>

  <script>
  const currentUserId = <?= (int)$_SESSION['user_id'] ?>;
  
  // Modal functions
  const userModal = document.getElementById('userModal');
  const toggleModal = document.getElementById('toggleModal');
  
  function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add User';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('inputName').value = '';
    document.getElementById('inputUsername').value = '';
    document.getElementById('inputSection').value = '';
    
    document.getElementById('statusGroup').style.display = 'none';

    const roleSelect = document.getElementById('inputRole');
    const statusSelect = document.getElementById('inputStatus');
    roleSelect.value = 'encoder';
    statusSelect.value = '1';
    
    // Admins cannot create new admins
    Array.from(roleSelect.options).forEach(opt => opt.disabled = (opt.value === 'admin'));
    Array.from(statusSelect.options).forEach(opt => opt.disabled = false);
    
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('btnSave').textContent = 'Add User';
    userModal.classList.add('active');
  }
  
  function openEditModal(user) {
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value = user.id;
    document.getElementById('inputName').value = user.full_name;
    document.getElementById('inputUsername').value = user.username;
    document.getElementById('inputSection').value = user.assigned_section || '';
    
    document.getElementById('statusGroup').style.display = 'block';

    const roleSelect = document.getElementById('inputRole');
    const statusSelect = document.getElementById('inputStatus');
    
    roleSelect.value = user.role;
    statusSelect.value = Number(user.is_active) === 1 ? '1' : '0';
    
    // Set restrictions on Edit
    Array.from(roleSelect.options).forEach(opt => opt.disabled = false);
    Array.from(statusSelect.options).forEach(opt => opt.disabled = false);
    
    if (Number(user.id) === currentUserId) {
        // Prevent editing own role & status
        Array.from(roleSelect.options).forEach(opt => opt.disabled = (opt.value !== user.role));
        Array.from(statusSelect.options).forEach(opt => opt.disabled = (opt.value !== statusSelect.value));
    } else {
        // Prevent promoting non-admins to admin and demoting other actual admins
        Array.from(roleSelect.options).forEach(opt => {
            if (user.role === 'admin') opt.disabled = (opt.value !== 'admin');
            else opt.disabled = (opt.value === 'admin');
        });
    }

    document.getElementById('passwordGroup').style.display = 'none'; // No password change in edit
    document.getElementById('btnSave').textContent = 'Update User';
    userModal.classList.add('active');
  }
  
  function closeModal() {
    userModal.classList.remove('active');
  }
  
  function openToggleModal(userId, userName, action) {
    document.getElementById('toggleUserId').value = userId;
    document.getElementById('toggleUserName').textContent = userName;
    document.getElementById('toggleActionInput').value = action;
    
    const isActivate = action === 'activate';
    document.getElementById('toggleModalTitle').textContent = isActivate ? 'Activate User' : 'Deactivate User';
    document.getElementById('toggleModalActionText').textContent = action;
    
    const btn = document.getElementById('toggleConfirmBtn');
    btn.textContent = isActivate ? 'Activate' : 'Deactivate';
    btn.style.backgroundColor = isActivate ? '#00bc7d' : '#e7000b';
    
    toggleModal.classList.add('active');
  }
  
  function closeToggleModal() {
    toggleModal.classList.remove('active');
  }

  document.querySelectorAll('.js-edit-user').forEach(button => {
    button.addEventListener('click', () => {
      try {
        openEditModal(JSON.parse(button.dataset.user));
      } catch (error) {
        console.error('Unable to open user editor:', error);
      }
    });
  });

  document.querySelectorAll('.js-toggle-user').forEach(button => {
    button.addEventListener('click', () => {
      openToggleModal(button.dataset.userId, button.dataset.userName, button.dataset.action);
    });
  });
  
  // Close modals on overlay click or Escape
  [userModal, toggleModal].forEach(modal => {
    modal?.addEventListener('click', function(e) {
      if (e.target === this) this.classList.remove('active');
    });
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeModal(); closeToggleModal();
    }
  });
  
  // Auto-hide toast
  setTimeout(() => {
    document.querySelectorAll('.toast').forEach(t => {
      t.style.animation = 'slideIn 0.3s reverse';
      setTimeout(() => t.remove(), 300);
    });
  }, 5000);
  
  // Role-based section field hint
  document.getElementById('inputRole')?.addEventListener('change', function() {
    const sectionGroup = this.closest('.form-group').nextElementSibling;
    if (this.value === 'encoder') {
      sectionGroup.style.opacity = '1';
    } else {
      sectionGroup.style.opacity = '0.6';
    }
  });
  </script>
</body>
</html>
