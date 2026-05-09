<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

// Get report params
$reportType = $_GET['type'] ?? $_POST['type'] ?? 'nutritional';
$startDate = $_GET['start_date'] ?? $_POST['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? $_POST['end_date'] ?? date('Y-m-d');
$gradeFilter = $_GET['grade'] ?? $_POST['grade'] ?? 'all';

// Build WHERE clauses for role-based and grade filtering
$where = []; $params = [];
if (in_array($_SESSION['role'], ['teacher','encoder']) && !empty($_SESSION['assigned_section'])) {
    $where[] = "s.section = ?"; $params[] = $_SESSION['assigned_section'];
}
if ($gradeFilter !== 'all') {
    $where[] = "s.grade_level = ?"; $params[] = (int)$gradeFilter;
}
$whereClause = $where ? "WHERE " . implode(' AND ', $where) : '';

// ========== DYNAMIC STATS ==========
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM students $whereClause")->fetchColumn(),
    'beneficiaries' => $pdo->query("SELECT COUNT(DISTINCT student_id) FROM measurements $whereClause ?")->fetchColumn(), // Simplified
    'measurements' => $pdo->query("SELECT COUNT(*) FROM measurements m JOIN students s ON m.student_id = s.id $whereClause")->fetchColumn(),
    'attendance' => $pdo->query("SELECT COUNT(*) FROM feeding_logs fl JOIN students s ON fl.student_id = s.id $whereClause")->fetchColumn(),
];

// ========== REPORT GENERATION LOGIC ==========
if (isset($_GET['export']) || isset($_POST['generate'])) {
    $filename = "FEED_Report_{$reportType}_" . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $out = fopen('php://output', 'w');
    
    switch ($reportType) {
        case 'nutritional':
            fputcsv($out, ['Student','Grade/Section','Current BMI','Nutritional Status','Last Measured']);
            $sql = "SELECT CONCAT(s.first_name,' ',s.last_name) as name, s.grade_level, s.section, 
                           m.bmi, m.nutritional_status, m.measured_date
                    FROM measurements m JOIN students s ON m.student_id = s.id 
                    $whereClause AND m.type = 'endline' ORDER BY s.last_name";
            foreach ($pdo->query($sql)->fetchAll() as $row) {
                fputcsv($out, [$row['name'], "Grade {$row['grade_level']}-{$row['section']}", 
                             number_format($row['bmi'],2), $row['nutritional_status'], $row['measured_date']]);
            }
            break;
            
        case 'attendance':
            fputcsv($out, ['Date','Student','Grade/Section','Present','Meal Served','Remarks']);
            $sql = "SELECT fl.feeding_date, CONCAT(s.first_name,' ',s.last_name) as name, s.grade_level, s.section,
                           fl.is_present, fl.meal_served, fl.remarks
                    FROM feeding_logs fl JOIN students s ON fl.student_id = s.id 
                    $whereClause AND fl.feeding_date BETWEEN ? AND ? ORDER BY fl.feeding_date DESC, s.last_name";
            $stmt = $pdo->prepare($sql); $stmt->execute(array_merge($params, [$startDate, $endDate]));
            foreach ($stmt->fetchAll() as $row) {
                fputcsv($out, [$row['feeding_date'], $row['name'], "Grade {$row['grade_level']}-{$row['section']}",
                             $row['is_present'] ? 'Yes' : 'No', $row['meal_served'] ? 'Yes' : 'No', $row['remarks']]);
            }
            break;
            
        case 'beneficiary':
            fputcsv($out, ['LRN','Student','Grade/Section','Age','Sex','School Year','Enrollment Status']);
            $sql = "SELECT lrn, CONCAT(first_name,' ',last_name) as name, grade_level, section, age, sex, school_year, 'Active' as status
                    FROM students $whereClause ORDER BY grade_level, section, last_name";
            foreach ($pdo->query($sql)->fetchAll() as $row) {
                fputcsv($out, [$row['lrn'], $row['name'], "Grade {$row['grade_level']}-{$row['section']}",
                             $row['age'], $row['sex'], $row['school_year'], $row['status']]);
            }
            break;
            
        case 'anthropometric':
            fputcsv($out, ['Student','Grade/Section','Type','Date','Height (cm)','Weight (kg)','BMI','Status','Recorded By']);
            $sql = "SELECT CONCAT(s.first_name,' ',s.last_name) as name, s.grade_level, s.section, m.type, m.measured_date,
                           m.height_cm, m.weight_kg, m.bmi, m.nutritional_status, u.full_name as recorder
                    FROM measurements m 
                    JOIN students s ON m.student_id = s.id 
                    LEFT JOIN users u ON m.recorded_by = u.id
                    $whereClause ORDER BY m.measured_date DESC, s.last_name";
            foreach ($pdo->query($sql)->fetchAll() as $row) {
                fputcsv($out, [$row['name'], "Grade {$row['grade_level']}-{$row['section']}", ucfirst($row['type']),
                             $row['measured_date'], $row['height_cm'], $row['weight_kg'], 
                             number_format($row['bmi'],2), $row['nutritional_status'], $row['recorder'] ?? 'System']);
            }
            break;
            
        case 'progress':
            fputcsv($out, ['Student','Grade/Section','Baseline BMI','Baseline Status','Endline BMI','Endline Status','Progress']);
            $sql = "SELECT CONCAT(s.first_name,' ',s.last_name) as name, s.grade_level, s.section,
                           base.bmi as base_bmi, base.nutritional_status as base_status,
                           endm.bmi as end_bmi, endm.nutritional_status as end_status
                    FROM students s
                    JOIN measurements base ON s.id = base.student_id AND base.type = 'baseline'
                    JOIN measurements endm ON s.id = endm.student_id AND endm.type = 'endline'
                    $whereClause ORDER BY s.last_name";
            foreach ($pdo->query($sql)->fetchAll() as $row) {
                $progress = ($row['base_status'] === $row['end_status']) ? 'Maintained' : 
                           (strpos($row['base_status'],'Underweight') !== false && $row['end_status'] === 'Normal') ? 'Improved' : 'Changed';
                fputcsv($out, [$row['name'], "Grade {$row['grade_level']}-{$row['section']}",
                             number_format($row['base_bmi'],2), $row['base_status'],
                             number_format($row['end_bmi'],2), $row['end_status'], $progress]);
            }
            break;
            
        case 'monthly':
            fputcsv($out, ['Month','Total Students','Measurements Taken','Avg Attendance Rate','Top Improvement']);
            // Simplified monthly aggregation
            $sql = "SELECT DATE_FORMAT(measured_date, '%Y-%m') as month, COUNT(DISTINCT student_id) as students, COUNT(*) as measurements
                    FROM measurements m JOIN students s ON m.student_id = s.id $whereClause 
                    AND measured_date BETWEEN ? AND ? GROUP BY month ORDER BY month";
            $stmt = $pdo->prepare($sql); $stmt->execute(array_merge($params, [$startDate, $endDate]));
            foreach ($stmt->fetchAll() as $row) {
                fputcsv($out, [$row['month'], $row['students'], $row['measurements'], 'N/A', 'See detailed reports']);
            }
            break;
    }
    
    fclose($out);
    logAudit($pdo, 'export', 'reports', 0, "Exported {$reportType} report");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FEED System - Reports</title>
  <link rel="stylesheet" href="css/report.css" />
  <link rel="stylesheet" href="css/sidebar.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script src="js/sidebar.js" defer></script>
  <style>
    /* Minimal inline styles to guarantee functionality */
    .stat-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    .stat-label { font-size:13px; color:#6b7280; margin-bottom:8px; }
    .stat-value { font-size:28px; font-weight:700; color:#101828; }
    .stat-card.blue { border-left:4px solid #3b82f6; } .stat-card.green { border-left:4px solid #00bc7d; }
    .stat-card.orange { border-left:4px solid #f59e0b; } .stat-card.purple { border-left:4px solid #8b5cf6; }
    
    .card { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.1); margin:24px 0; }
    .card-header { padding:16px 20px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; gap:10px; font-weight:600; color:#101828; }
    .card-body { padding:20px; }
    
    .form-row { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
    .form-group { display:flex; flex-direction:column; gap:6px; flex:1; min-width:200px; }
    .form-group.full { flex:100%; }
    .form-label { font-size:13px; font-weight:500; color:#374151; }
    .form-label span { color:#9ca3af; font-weight:400; }
    .form-input, .form-select { padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
    
    .btn-group { display:flex; gap:12px; flex-wrap:wrap; margin-top:20px; }
    .btn { padding:10px 20px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:8px; }
    .btn-primary { background:#00bc7d; color:white; } .btn-secondary { background:#fff; border:1px solid #e5e7eb; color:#101828; }
    
    .reports-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px; }
    .report-card { display:flex; align-items:center; gap:16px; padding:16px; border:1px solid #e5e7eb; border-radius:12px; cursor:pointer; transition:all 0.15s; }
    .report-card:hover { border-color:#00bc7d; box-shadow:0 4px 12px rgba(0,188,125,0.1); }
    .report-icon { width:40px; height:40px; border-radius:10px; background:#f9fafb; display:flex; align-items:center; justify-content:center; color:#6b7280; }
    .report-info { flex:1; }
    .report-name { font-weight:600; color:#101828; margin-bottom:4px; }
    .report-desc { font-size:13px; color:#6b7280; }
    .report-arrow { color:#9ca3af; }
    
    /* Toast */
    .toast-container { position:fixed; bottom:24px; right:24px; z-index:9999; }
    .toast { background:#101828; color:white; padding:12px 20px; border-radius:8px; margin-top:8px; display:flex; align-items:center; gap:10px; animation:slideIn 0.3s; }
    .toast.success { background:#00bc7d; }
    @keyframes slideIn { from { transform:translateY(100px); opacity:0; } to { transform:translateY(0); opacity:1; } }
    .toast button { background:none; border:none; color:inherit; font-size:18px; cursor:pointer; margin-left:auto; }
    
    @media print {
      .sidebar, .page-header > button, .btn-group, .report-card { display:none !important; }
      .main-content-wrapper { margin-left:0 !important; }
      .card { box-shadow:none !important; border:1px solid #000 !important; }
    }
    @media (max-width:768px) {
      .form-row { flex-direction:column; }
      .btn-group { flex-direction:column; }
      .reports-grid { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
  <div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content-wrapper" id="mainWrapper">
      <main class="page-content main-content">
        
        <div class="page-header">
          <h1>Reports</h1>
          <p>Generate and export program reports</p>
        </div>

        <!-- Stats (Dynamic) -->
        <div class="stats-grid">
          <div class="stat-card blue animate-in" style="animation-delay:0.05s">
            <div class="stat-label">Total Students</div>
            <div class="stat-value" id="statTotal"><?= $stats['total'] ?></div>
          </div>
          <div class="stat-card green animate-in" style="animation-delay:0.1s">
            <div class="stat-label">Beneficiaries</div>
            <div class="stat-value" id="statBeneficiaries"><?= $stats['beneficiaries'] ?></div>
          </div>
          <div class="stat-card orange animate-in" style="animation-delay:0.15s">
            <div class="stat-label">Measurements</div>
            <div class="stat-value" id="statMeasurements"><?= $stats['measurements'] ?></div>
          </div>
          <div class="stat-card purple animate-in" style="animation-delay:0.2s">
            <div class="stat-label">Attendance Records</div>
            <div class="stat-value" id="statAttendance"><?= $stats['attendance'] ?></div>
          </div>
        </div>

        <!-- Generate Report Form -->
        <form method="get" action="" class="card animate-in" style="animation-delay:0.25s;margin-bottom:24px">
          <div class="card-header">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span class="card-title">Generate Report</span>
          </div>
          <div class="card-body">
            <input type="hidden" name="export" value="1">
            <div class="form-row">
              <div class="form-group full">
                <label class="form-label">Report Type</label>
                <select class="form-select" name="type" id="reportType">
                  <option value="nutritional" <?= $reportType==='nutritional'?'selected':'' ?>>Nutritional Status Summary</option>
                  <option value="attendance" <?= $reportType==='attendance'?'selected':'' ?>>Feeding Attendance Report</option>
                  <option value="beneficiary" <?= $reportType==='beneficiary'?'selected':'' ?>>Beneficiary Master List</option>
                  <option value="anthropometric" <?= $reportType==='anthropometric'?'selected':'' ?>>Anthropometric Measurements</option>
                  <option value="progress" <?= $reportType==='progress'?'selected':'' ?>>Progress Report (Baseline vs Endline)</option>
                  <option value="monthly" <?= $reportType==='monthly'?'selected':'' ?>>Monthly Summary Report</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-input" name="start_date" id="startDate" value="<?= $startDate ?>">
              </div>
              <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" class="form-input" name="end_date" id="endDate" value="<?= $endDate ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Filter by Grade <span>(Optional)</span></label>
                <select class="form-select" name="grade" id="gradeFilter">
                  <option value="all" <?= $gradeFilter==='all'?'selected':'' ?>>All Grades</option>
                  <?php for($g=1;$g<=6;$g++): ?><option value="<?= $g ?>" <?= $gradeFilter===$g?'selected':'' ?>>Grade <?= $g ?></option><?php endfor; ?>
                </select>
              </div>
            </div>
            <div class="btn-group">
              <button type="submit" name="generate" class="btn btn-primary" id="generateBtn">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Generate & Download
              </button>
              <button type="button" class="btn btn-secondary" id="exportPdfBtn" onclick="window.print()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Print as PDF
              </button>
              <button type="button" class="btn btn-secondary" id="exportExcelBtn" onclick="document.getElementById('generateBtn').click()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export as Excel
              </button>
            </div>
          </div>
        </form>

        <!-- Available Reports (Click to pre-fill form) -->
        <div class="card animate-in" style="animation-delay:0.3s">
          <div class="card-header"><span class="card-title">Available Reports</span></div>
          <div class="card-body">
            <div class="reports-grid">
              <?php $reports = [
                ['nutritional','Nutritional Status Summary','Overview of current nutritional status distribution'],
                ['attendance','Feeding Attendance Report','Daily feeding attendance and meal distribution'],
                ['beneficiary','Beneficiary Master List','Complete list of program beneficiaries'],
                ['anthropometric','Anthropometric Measurements','All anthropometric measurements recorded'],
                ['progress','Progress Report (Baseline vs Endline)','Comparison between baseline and endline data'],
                ['monthly','Monthly Summary Report','Monthly feeding program summary']
              ]; ?>
              <?php foreach ($reports as [$type, $name, $desc]): ?>
              <div class="report-card" onclick="selectReport('<?= $type ?>')">
                <div class="report-icon">
                  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div class="report-info">
                  <div class="report-name"><?= $name ?></div>
                  <div class="report-desc"><?= $desc ?></div>
                </div>
                <div class="report-arrow">
                  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        
      </main>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container" id="toastContainer">
    <?php if (isset($_GET['export'])): ?>
    <div class="toast success">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <span>Report downloaded: <?= htmlspecialchars($filename) ?></span>
      <button type="button" onclick="this.parentElement.remove()">×</button>
    </div>
    <?php endif; ?>
  </div>

  <script>
  // Pre-fill form when clicking a report card
  function selectReport(type) {
    document.getElementById('reportType').value = type;
    document.getElementById('reportType').dispatchEvent(new Event('change'));
    // Scroll to form
    document.querySelector('.card').scrollIntoView({behavior:'smooth'});
  }
  
  // Auto-submit form when report type changes (optional UX)
  document.getElementById('reportType')?.addEventListener('change', function() {
    // Could auto-generate, but we'll wait for explicit click
  });
  
  // Auto-hide toast
  setTimeout(() => {
    document.querySelectorAll('.toast').forEach(t => {
      t.style.animation = 'slideIn 0.3s reverse';
      setTimeout(() => t.remove(), 300);
    });
  }, 4000);
  </script>
</body>
</html>