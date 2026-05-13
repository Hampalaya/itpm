<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

function pageUrl($pageParam, $page) {
    $query = $_GET;
    $query[$pageParam] = $page;
    return '?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8');
}

// Fetch students with baseline AND endline measurements for comparison
$where = []; $params = [];

// Role-based filter
if (($_SESSION['role'] ?? '') === 'encoder' && !empty($_SESSION['assigned_section'])) {
    $where[] = "s.section = ?"; $params[] = $_SESSION['assigned_section'];
}

// Search filter
$search = trim($_GET['search'] ?? '');
if ($search) {
    $where[] = "(CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.lrn LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

// Status filter
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter) {
    $where[] = "end_status.nutritional_status = ?"; $params[] = $statusFilter;
}

// Progress filter
$progressFilter = $_GET['progress'] ?? '';
// (We'll filter progress in PHP after fetching, since it's computed)

$sql = "SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, s.grade_level, s.section,
               base.bmi as base_bmi, base.nutritional_status as base_status,
               end_status.bmi as end_bmi, end_status.nutritional_status as end_status,
               base.measured_date as base_date, end_status.measured_date as end_date
        FROM students s
        JOIN measurements base ON s.id = base.student_id AND base.type = 'baseline'
        JOIN measurements end_status ON s.id = end_status.student_id AND end_status.type = 'endline'
        " . ($where ? "WHERE " . implode(' AND ', $where) : "") . "
        ORDER BY s.grade_level, s.section, s.last_name";

$stmt = $pdo->prepare($sql); $stmt->execute($params);
$students = $stmt->fetchAll();

// Compute progress and filter
$filtered = [];
$stats = ['total' => 0, 'improved' => 0, 'maintained' => 0, 'declined' => 0];

foreach ($students as $s) {
    // Compute progress
    if ($s['base_status'] === $s['end_status']) {
        $progress = 'maintained';
    } elseif (
        // Moving to Normal from any malnourished state is an Improvement
        ($s['end_status'] === 'Normal') || 
        // Moving from Obese down to Overweight is an Improvement
        ($s['base_status'] === 'Obese' && $s['end_status'] === 'Overweight')
    ) {
        $progress = 'improved';
    } else {
        // Any other change (e.g., getting worse, or overshooting from Underweight to Overweight/Obese)
        $progress = 'declined';
    }
    
    // Apply progress filter
    if ($progressFilter && $progress !== $progressFilter) continue;
    
    $s['progress'] = $progress;
    $filtered[] = $s;
    
    // Update stats
    $stats['total']++;
    $stats[$progress]++;
}

// Export CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="nutritional_status_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student','Grade/Section','Baseline BMI','Baseline Status','Endline BMI','Endline Status','Progress','Baseline Date','Endline Date']);
    foreach ($filtered as $s) {
        fputcsv($out, [
            $s['name'],
            'Grade '.$s['grade_level'].' - '.$s['section'],
            number_format($s['base_bmi'],2),
            $s['base_status'],
            number_format($s['end_bmi'],2),
            $s['end_status'],
            ucfirst($s['progress']),
            $s['base_date'],
            $s['end_date']
        ]);
    }
    fclose($out); exit;
}

$studentsPerPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalStudents = count($filtered);
$totalPages = max(1, (int)ceil($totalStudents / $studentsPerPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $studentsPerPage;
$pagedStudents = array_slice($filtered, $offset, $studentsPerPage);
$studentsStart = $totalStudents > 0 ? $offset + 1 : 0;
$studentsEnd = min($offset + count($pagedStudents), $totalStudents);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="images/logo_feed.png?v=1">
  <title>FEED System - Nutritional Status</title>
  <link rel="stylesheet" href="css/nutritional_status.css?v=20260513" />
  <link rel="stylesheet" href="css/sidebar.css?v=20260513" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script src="js/sidebar.js" defer></script>
  <style>
    /* Minimal inline styles to guarantee functionality */
    .stat-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    .stat-label { font-size:13px; color:#6b7280; margin-bottom:8px; }
    .stat-value-row { display:flex; justify-content:space-between; align-items:center; }
    .stat-value { font-size:28px; font-weight:700; color:#101828; }
    .stat-icon-wrapper { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; }
    .stat-icon-wrapper.blue { background:#dbeafe; color:#1e40af; }
    .stat-icon-wrapper.green { background:#dcfce7; color:#166534; }
    .stat-icon-wrapper.gray { background:#f3f4f6; color:#6b7280; }
    .stat-icon-wrapper.red { background:#fee2e2; color:#991b1b; }
    
    .card { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin:24px 0; }
    .card-header { padding:16px 20px; border-bottom:1px solid #e5e7eb; }
    .card-title { font-size:18px; font-weight:600; color:#101828; }
    
    .filter-bar { padding:16px 20px; display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end; }
    .filter-field { display:flex; flex-direction:column; gap:6px; min-width:200px; }
    .filter-label { font-size:13px; font-weight:500; color:#374151; }
    .filter-input-wrapper { position:relative; }
    .filter-input-wrapper svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:#6b7280; pointer-events:none; }
    .filter-input { width:100%; padding:8px 12px 8px 36px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
    .filter-select { padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; background:#fff; }
    
    .table-container { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; min-width:1000px; }
    thead th { padding:12px 16px; text-align:left; font-size:13px; font-weight:600; color:#6b7280; background:#f9fafb; border-bottom:1px solid #e5e7eb; }
    tbody td { padding:12px 16px; font-size:14px; border-bottom:1px solid #f3f4f6; }
    tbody tr:hover { background:#f9fafb; cursor:pointer; }
    
    .progress-badge { padding:4px 10px; border-radius:999px; font-size:11px; font-weight:600; display:inline-flex; align-items:center; gap:4px; }
    .progress-improved { background:#dcfce7; color:#166534; }
    .progress-maintained { background:#f3f4f6; color:#6b7280; }
    .progress-declined { background:#fee2e2; color:#991b1b; }
    
    .status-badge { padding:4px 10px; border-radius:999px; font-size:11px; font-weight:600; }
    .status-underweight { background:#fef3c7; color:#92400e; }
    .status-normal { background:#dcfce7; color:#166534; }
    .status-overweight { background:#e0e7ff; color:#3730a3; }
    .status-obese { background:#f3e8ff; color:#6b21a8; }
    
    .btn { padding:8px 16px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:6px; }
    .btn-secondary { background:#fff; border:1px solid #e5e7eb; color:#101828; }
    
    /* Modal */
    .modal-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; display:none; align-items:center; justify-content:center; padding:20px; }
    .modal-overlay.active { display:flex; }
    .modal { background:#fff; border-radius:12px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.2); }
    .modal-header { padding:16px 20px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; }
    .modal-title { font-size:18px; font-weight:600; color:#101828; }
    .modal-close { background:none; border:none; color:#6b7280; cursor:pointer; padding:4px; }
    .modal-body { padding:20px; }
    
    /* Toast */
    .toast-container { position:fixed; bottom:24px; right:24px; z-index:9999; }
    .toast { background:#101828; color:white; padding:12px 20px; border-radius:8px; margin-top:8px; display:flex; align-items:center; gap:10px; animation:slideIn 0.3s; }
    .toast.success { background:#00bc7d; }
    @keyframes slideIn { from { transform:translateY(100px); opacity:0; } to { transform:translateY(0); opacity:1; } }
    .toast button { background:none; border:none; color:inherit; font-size:18px; cursor:pointer; margin-left:auto; }
    
    @media (max-width:768px) {
      .filter-bar { flex-direction:column; align-items:stretch; }
      .filter-field { min-width:100%; }
      table { font-size:12px; }
      th, td { padding:8px 12px; }
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content-wrapper" id="mainWrapper">
      <main class="page-content main-content">
        
        <div class="page-header">
          <div>
            <h1>Nutritional Status</h1>
            <p>Track student nutritional progress and improvements</p>
          </div>
          <a href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-secondary">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export Report
          </a>
        </div>

        <!-- Stats (Dynamic) -->
        <div class="stats-grid">
          <div class="stat-card blue">
            <div class="stat-label">Total Monitored</div>
            <div class="stat-value-row">
              <div class="stat-value" id="statTotal"><?= $stats['total'] ?></div>
              <div class="stat-icon-wrapper blue">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
            </div>
          </div>
          <div class="stat-card green">
            <div class="stat-label">Improved</div>
            <div class="stat-value-row">
              <div class="stat-value" id="statImproved"><?= $stats['improved'] ?></div>
              <div class="stat-icon-wrapper green">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
              </div>
            </div>
          </div>
          <div class="stat-card gray">
            <div class="stat-label">Maintained</div>
            <div class="stat-value-row">
              <div class="stat-value" id="statMaintained"><?= $stats['maintained'] ?></div>
              <div class="stat-icon-wrapper gray">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></svg>
              </div>
            </div>
          </div>
          <div class="stat-card red">
            <div class="stat-label">Declined</div>
            <div class="stat-value-row">
              <div class="stat-value" id="statDeclined"><?= $stats['declined'] ?></div>
              <div class="stat-icon-wrapper red">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom:24px">
          <form method="get" action="" class="filter-bar" style="border-bottom:none">
            <div class="filter-field">
              <div class="filter-label">Search Student</div>
              <div class="filter-input-wrapper">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" class="filter-input" placeholder="Name or LRN..." value="<?= htmlspecialchars($search) ?>">
              </div>
            </div>
            <div class="filter-field">
              <div class="filter-label">Current Status</div>
              <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="Underweight" <?= $statusFilter==='Underweight'?'selected':'' ?>>Underweight</option>
                <option value="Normal" <?= $statusFilter==='Normal'?'selected':'' ?>>Normal</option>
                <option value="Overweight" <?= $statusFilter==='Overweight'?'selected':'' ?>>Overweight</option>
                <option value="Obese" <?= $statusFilter==='Obese'?'selected':'' ?>>Obese</option>
              </select>
            </div>
            <div class="filter-field">
              <div class="filter-label">Progress</div>
              <select name="progress" class="filter-select" onchange="this.form.submit()">
                <option value="">All Progress</option>
                <option value="improved" <?= $progressFilter==='improved'?'selected':'' ?>>Improved</option>
                <option value="maintained" <?= $progressFilter==='maintained'?'selected':'' ?>>Maintained</option>
                <option value="declined" <?= $progressFilter==='declined'?'selected':'' ?>>Declined</option>
              </select>
            </div>
            <!-- Hidden inputs to preserve other params -->
            <?php foreach(['export'] as $k): if(isset($_GET[$k])): ?>
              <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_GET[$k]) ?>">
            <?php endif; endforeach; ?>
          </form>
        </div>

        <!-- Main Table Card -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Nutritional Status Comparison</div>
          </div>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Grade/Section</th>
                  <th class="center">Baseline BMI</th>
                  <th class="center">Latest BMI</th>
                  <th class="center">Baseline Status</th>
                  <th class="center">Current Status</th>
                  <th class="center">Progress</th>
                  <th class="center">Actions</th>
                </tr>
              </thead>
              <tbody id="studentTableBody">
                <?php if (empty($pagedStudents)): ?>
                  <tr><td colspan="8" style="text-align:center;padding:2rem;color:#6b7a8d">No students with both baseline and endline measurements found.</td></tr>
                <?php else: ?>
                  <?php foreach ($pagedStudents as $s): 
                    $progressClass = 'progress-'.$s['progress'];
                    $baseStatusClass = 'status-'.strtolower(str_replace(' ','-',$s['base_status']));
                    $endStatusClass = 'status-'.strtolower(str_replace(' ','-',$s['end_status']));
                  ?>
                  <tr onclick="openDetailModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)">
                    <td><?= htmlspecialchars($s['name']) ?></td>
                    <td>Grade <?= $s['grade_level'] ?> - <?= $s['section'] ?></td>
                    <td class="center"><?= number_format($s['base_bmi'],2) ?></td>
                    <td class="center"><?= number_format($s['end_bmi'],2) ?></td>
                    <td class="center"><span class="status-badge <?= $baseStatusClass ?>"><?= $s['base_status'] ?></span></td>
                    <td class="center"><span class="status-badge <?= $endStatusClass ?>"><?= $s['end_status'] ?></span></td>
                    <td class="center"><span class="progress-badge <?= $progressClass ?>"><?= ucfirst($s['progress']) ?></span></td>
                    <td class="center">
                      <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation(); openDetailModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)">View</button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="pagination">
            <div class="pagination-info">
              Showing <span><?= $studentsStart ?></span> to <span><?= $studentsEnd ?></span> of <span><?= $totalStudents ?></span> students
            </div>
            <div class="pagination-controls" aria-label="Nutritional status pagination">
              <a class="page-btn page-btn-text <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : pageUrl('page', $page - 1) ?>">Previous</a>
              <span class="page-count">Page <?= $page ?> of <?= $totalPages ?></span>
              <a class="page-btn page-btn-text <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : pageUrl('page', $page + 1) ?>">Next</a>
            </div>
          </div>
        </div>
        
      </main>
    </div>
  </div>

  <!-- Detail Modal -->
  <div class="modal-overlay" id="detailModal">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">Student Nutritional Details</div>
        <button class="modal-close" onclick="closeDetailModal()">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body" id="modalBody">
        <!-- Populated by JS -->
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container" id="toastContainer"></div>

  <script>
  // Modal functions
  function openDetailModal(student) {
    const modal = document.getElementById('detailModal');
    const body = document.getElementById('modalBody');
    body.innerHTML = `
      <div style="margin-bottom:20px">
        <div style="font-size:18px;font-weight:600;color:#101828;margin-bottom:4px">${student.name}</div>
        <div style="font-size:14px;color:#6b7280">Grade ${student.grade_level} - ${student.section}</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
        <div style="background:#f9fafb;padding:16px;border-radius:8px">
          <div style="font-size:13px;color:#6b7280;margin-bottom:8px">Baseline</div>
          <div style="font-size:24px;font-weight:700;color:#101828">${parseFloat(student.base_bmi).toFixed(2)}</div>
          <div style="margin-top:8px"><span class="status-badge status-${student.base_status.toLowerCase().replace(' ','-')}">${student.base_status}</span></div>
          <div style="font-size:12px;color:#9ca3af;margin-top:4px">${student.base_date}</div>
        </div>
        <div style="background:#f9fafb;padding:16px;border-radius:8px">
          <div style="font-size:13px;color:#6b7280;margin-bottom:8px">Endline</div>
          <div style="font-size:24px;font-weight:700;color:#101828">${parseFloat(student.end_bmi).toFixed(2)}</div>
          <div style="margin-top:8px"><span class="status-badge status-${student.end_status.toLowerCase().replace(' ','-')}">${student.end_status}</span></div>
          <div style="font-size:12px;color:#9ca3af;margin-top:4px">${student.end_date}</div>
        </div>
      </div>
      <div style="padding:12px 16px;background:${student.progress==='improved'?'#dcfce7':(student.progress==='declined'?'#fee2e2':'#f3f4f6')};border-radius:8px;display:flex;align-items:center;gap:10px">
        <svg width="20" height="20" fill="none" stroke="${student.progress==='improved'?'#166534':(student.progress==='declined'?'#991b1b':'#6b7280')}" stroke-width="2" viewBox="0 0 24 24">
          ${student.progress==='improved'?'<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>':(student.progress==='declined'?'<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/>':'<line x1="5" y1="12" x2="19" y2="12"/>')}
        </svg>
        <div style="font-weight:600;color:${student.progress==='improved'?'#166534':(student.progress==='declined'?'#991b1b':'#6b7280')}">
          ${student.progress.charAt(0).toUpperCase()+student.progress.slice(1)}
        </div>
      </div>
    `;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
  
  function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
    document.body.style.overflow = '';
  }
  
  // Close modal on overlay click or Escape key
  document.getElementById('detailModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeDetailModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDetailModal();
  });
  
  // Auto-hide toast if any
  setTimeout(() => {
    document.querySelectorAll('.toast').forEach(t => {
      t.style.animation = 'slideIn 0.3s reverse';
      setTimeout(() => t.remove(), 300);
    });
  }, 4000);
  </script>
</body>
</html>
