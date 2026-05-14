<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

// Get selected date (default today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) $selectedDate = date('Y-m-d');

function pageUrl($pageParam, $page) {
    $query = $_GET;
    $query[$pageParam] = $page;
    return '?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8');
}

// ========== EXPORT LOGIC (Must run before HTML/Headers) ==========
if (isset($_GET['export'])) {
    
    // Fetch students specifically for export
    $whereExport = []; $paramsExport = [];
    addEncoderStudentScope($whereExport, $paramsExport);
    
    $sqlExport = "SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, s.grade_level, s.section,
                   fl.is_present, fl.meal_served, fl.remarks
            FROM students s
            LEFT JOIN feeding_logs fl ON s.id = fl.student_id AND fl.feeding_date = ?
            INNER JOIN measurements m ON s.id = m.student_id AND m.type = 'baseline' AND m.nutritional_status = 'Underweight'
            " . ($whereExport ? "WHERE " . implode(' AND ', $whereExport) : "") . "
            ORDER BY s.grade_level, s.section, s.last_name";
            
    $stmtExport = $pdo->prepare($sqlExport);
    $stmtExport->execute(array_merge([$selectedDate], $paramsExport));
    $exportStudents = $stmtExport->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="feeding_log_'.$selectedDate.'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Student','Grade/Section','Present','Meal Served','Remarks']);
    foreach ($exportStudents as $s) {
      fputcsv($out, [
        $selectedDate,
        $s['name'],
        'Grade '.$s['grade_level'].' - '.$s['section'],
        $s['is_present'] ? 'Yes' : 'No',
        $s['meal_served'] ? 'Yes' : 'No',
        $s['remarks'] ?? ''
      ]);
    }
    fclose($out); 
    exit;
}

// Handle Save Attendance (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $records = $_POST['attendance'] ?? [];
    $saved = 0;
    
    foreach ($records as $studentId => $data) {
        $studentId = (int)$studentId;
        if (!canAccessStudent($pdo, $studentId)) {
            continue;
        }

        $present = isset($data['present']) ? 1 : 0;
        $meal = isset($data['meal']) ? 1 : 0;
        $remarks = trim($data['remarks'] ?? '');
        
        // Upsert: insert or update
        $stmt = $pdo->prepare("
            INSERT INTO feeding_logs (student_id, feeding_date, is_present, meal_served, remarks, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                is_present = VALUES(is_present),
                meal_served = VALUES(meal_served),
                remarks = VALUES(remarks),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$studentId, $selectedDate, $present, $meal, $remarks, $_SESSION['user_id']]);
        $saved++;
    }
    
    logAudit($pdo, 'insert', 'feeding_logs', 0, "Saved attendance for $selectedDate: $saved records");
    header("Location: feeding_log.php?date=$selectedDate&saved=1"); exit;
}

// Fetch students for this date (with existing logs)
$where = []; $params = [];
addEncoderStudentScope($where, $params);
$search = trim($_GET['search'] ?? '');
if ($search) {
    $where[] = "(CONCAT(s.first_name, ' ', s.last_name) LIKE ?)"; $params[] = "%$search%";
}

$studentsPerPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$fromSql = "FROM students s
        LEFT JOIN feeding_logs fl ON s.id = fl.student_id AND fl.feeding_date = ?
        INNER JOIN measurements m ON s.id = m.student_id AND m.type = 'baseline' AND m.nutritional_status = 'Underweight'
        " . ($where ? "WHERE " . implode(' AND ', $where) : "");
$countSql = "SELECT COUNT(*) " . $fromSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute(array_merge([$selectedDate], $params));
$totalStudents = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalStudents / $studentsPerPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $studentsPerPage;

$statsSql = "SELECT
          COUNT(*) as total_students,
          SUM(CASE WHEN fl.is_present = 1 THEN 1 ELSE 0 END) as present_students,
          SUM(CASE WHEN fl.meal_served = 1 THEN 1 ELSE 0 END) as meals_served
        " . $fromSql;
$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute(array_merge([$selectedDate], $params));
$feedingStats = $statsStmt->fetch() ?: ['total_students' => 0, 'present_students' => 0, 'meals_served' => 0];

$sql = "SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, s.grade_level, s.section,
               fl.is_present, fl.meal_served, fl.remarks
        " . $fromSql . "
        ORDER BY s.grade_level, s.section, s.last_name
        LIMIT $studentsPerPage OFFSET $offset";
$params = array_merge([$selectedDate], $params);

$stmt = $pdo->prepare($sql); $stmt->execute($params);
$students = $stmt->fetchAll();
$studentsStart = $totalStudents > 0 ? $offset + 1 : 0;
$studentsEnd = min($offset + count($students), $totalStudents);

// Calculate stats
$total = (int)$feedingStats['total_students'];
$present = (int)$feedingStats['present_students'];
$absent = $total - $present;
$meals = (int)$feedingStats['meals_served'];
$rate = $total > 0 ? round(($present / $total) * 100) : 0;

// Show toast if saved
$showToast = isset($_GET['saved']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="images/logo_feed.png?v=<?= time() ?>">
  <title>FEED System - Feeding Log</title>
  <link rel="stylesheet" href="css/feeding_log.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="css/sidebar.css?v=20260515" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script src="js/sidebar.js" defer></script>
</head>
<body>
  <div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content-wrapper" id="mainWrapper">
      <main class="page-content">
        
        <div class="page-header">
          <h1>Feeding Log</h1>
          <p>Track daily attendance and meal distribution for the School-Based Feeding Program</p>
        </div>

        <!-- Stats (Dynamic) -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-label">Total Present</span>
              <div class="stat-icon green">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </div>
            </div>
            <div class="stat-value" id="statPresent"><?= $present ?></div>
            <div class="stat-change positive">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
              <span>Today's attendance</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-label">Total Absent</span>
              <div class="stat-icon red">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="18"/></svg>
              </div>
            </div>
            <div class="stat-value" id="statAbsent"><?= $absent ?></div>
            <div class="stat-change negative">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/></svg>
              <span>Absent students</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-label">Meals Served</span>
              <div class="stat-icon teal">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
              </div>
            </div>
            <div class="stat-value" id="statMeals"><?= $meals ?></div>
            <div class="stat-change positive">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
              <span>Meals distributed</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-label">Attendance Rate</span>
              <div class="stat-icon blue">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
              </div>
            </div>
            <div class="stat-value" id="statRate"><?= $rate ?>%</div>
            <div class="stat-change positive">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
              <span>Overall rate</span>
            </div>
          </div>
        </div>

        <!-- Main Card -->
        <div class="card">
          <form method="post" action="">
          <div class="card-header">
            <div class="card-header-left">
              <div>
                <div class="card-title" id="attendanceTitle">Attendance Sheet</div>
                <div class="card-subtitle" id="attendanceSubtitle"><?= date('F j, Y', strtotime($selectedDate)) ?></div>
              </div>
            </div>
            <div style="display:flex;gap:8px">
              <!-- Export CSV -->
              <a href="?date=<?= $selectedDate ?>&export=1" class="btn btn-secondary btn-sm">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export
              </a>
              <button type="submit" name="save_attendance" class="btn btn-success btn-sm">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Save Attendance
              </button>
            </div>
          </div>

          <!-- Controls -->
          <div class="controls-bar">
            <div class="controls-left" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
              <div class="date-picker-wrapper">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <input type="date" class="date-input" id="datePicker" name="date" value="<?= $selectedDate ?>" onchange="this.form.submit()">
              </div>
              <div class="filter-group">
                <button type="button" class="filter-chip active" data-filter="all">All</button>
                <button type="button" class="filter-chip" data-filter="present">Present</button>
                <button type="button" class="filter-chip" data-filter="absent">Absent</button>
              </div>
            </div>
            <div class="controls-right">
              <div class="search-wrapper">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="search-input" id="searchInput" placeholder="Search students..." value="<?= htmlspecialchars($search) ?>">
              </div>
            </div>
          </div>

          <!-- Table -->
          <div class="table-container">
            <table class="feeding-table">
              <colgroup>
                <col class="col-number">
                <col class="col-student">
                <col class="col-grade">
                <col class="col-present">
                <col class="col-meal">
                <col class="col-status">
                <col class="col-remarks">
              </colgroup>
              <thead>
                <tr>
                  <th class="col-number">#</th>
                  <th class="col-student">Student</th>
                  <th class="col-grade">Grade/Section</th>
                  <th class="center col-present">Present</th>
                  <th class="center col-meal">Meal</th>
                  <th class="col-status">Status</th>
                  <th class="col-remarks">Remarks</th>
                </tr>
              </thead>
              <tbody id="studentTableBody">
                <?php if (empty($students)): ?>
                  <tr><td colspan="7" style="text-align:center;padding:2rem;color:#6b7a8d">No students found for this date/section.</td></tr>
                <?php else: ?>
                  <?php foreach ($students as $i => $s): 
                    $status = $s['is_present'] === null ? 'pending' : ($s['is_present'] ? 'present' : 'absent');
                  ?>
                  <tr data-status="<?= $status ?>" data-name="<?= strtolower(htmlspecialchars($s['name'])) ?>">
                    <td class="col-number"><?= $i + 1 ?></td>
                    <td class="col-student"><?= htmlspecialchars($s['name']) ?></td>
                    <td class="col-grade">Grade <?= $s['grade_level'] ?> - <?= $s['section'] ?></td>
                    <td class="center checkbox-wrapper col-present">
                      <input type="checkbox" name="attendance[<?= $s['id'] ?>][present]" value="1" <?= $s['is_present'] ? 'checked' : '' ?> id="present_<?= $s['id'] ?>">
                    </td>
                    <td class="center checkbox-wrapper col-meal">
                      <input type="checkbox" name="attendance[<?= $s['id'] ?>][meal]" value="1" <?= $s['meal_served'] ? 'checked' : '' ?> id="meal_<?= $s['id'] ?>" <?= !$s['is_present'] ? 'disabled' : '' ?>>
                    </td>
                    <td class="col-status"><span class="status-badge status-<?= $status ?>"><?= ucfirst($status) ?></span></td>
                    <td class="col-remarks">
                      <input type="text" class="remarks-input" name="attendance[<?= $s['id'] ?>][remarks]" value="<?= htmlspecialchars($s['remarks'] ?? '') ?>" placeholder="Optional notes">
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination (simplified for MVP) -->
          <div class="pagination">
            <div class="pagination-info">
              Showing <span><?= $studentsStart ?></span> to <span><?= $studentsEnd ?></span> of <span><?= $totalStudents ?></span> entries
            </div>
            <div class="pagination-controls" aria-label="Feeding log pagination">
              <a class="page-btn page-btn-text <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : pageUrl('page', $page - 1) ?>">Previous</a>
              <span class="page-count">Page <?= $page ?> of <?= $totalPages ?></span>
              <a class="page-btn page-btn-text <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : pageUrl('page', $page + 1) ?>">Next</a>
            </div>
          </div>
          </form>
        </div>
        
      </main>
    </div>
  </div>

  <!-- Toast -->
  <div class="toast-container" id="toastContainer">
    <?php if ($showToast): ?>
    <div class="toast success">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <span>Attendance saved for <?= date('M j', strtotime($selectedDate)) ?></span>
      <button type="button" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endif; ?>
  </div>

  <script>
  // Filter chips
  document.querySelectorAll('.filter-chip').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      const filter = this.dataset.filter;
      document.querySelectorAll('#studentTableBody tr').forEach(row => {
        if (filter === 'all' || row.dataset.status === filter) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });

  // Live search
  document.getElementById('searchInput')?.addEventListener('input', function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#studentTableBody tr').forEach(row => {
      const name = row.dataset.name || '';
      row.style.display = name.includes(term) ? '' : 'none';
    });
  });

  // Disable meal checkbox if not present
  document.querySelectorAll('input[id^="present_"]').forEach(cb => {
    cb.addEventListener('change', function() {
      const id = this.id.split('_')[1];
      const mealCb = document.getElementById('meal_' + id);
      if (mealCb) mealCb.disabled = !this.checked;
      // Update status badge
      const row = this.closest('tr');
      const badge = row.querySelector('.status-badge');
      if (this.checked) {
        row.dataset.status = 'present';
        badge.className = 'status-badge status-present';
        badge.textContent = 'Present';
      } else {
        row.dataset.status = 'absent';
        badge.className = 'status-badge status-absent';
        badge.textContent = 'Absent';
      }
    });
  });

  // Auto-hide toast
  setTimeout(() => {
    document.querySelectorAll('.toast').forEach(t => {
      t.style.animation = 'slideIn 0.3s reverse';
      setTimeout(() => t.remove(), 300);
    });
  }, 4000);

  // Date picker: reload page on change (form submit handled by PHP)
  document.getElementById('datePicker')?.addEventListener('change', function() {
    window.location.href = '?date=' + this.value;
  });
  </script>
</body>
</html>
