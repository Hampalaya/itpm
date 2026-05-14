<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// ========== VALIDATION LOGIC ==========
$validationResults = [];
$stats = ['total' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

function encoderScopeSql(string $studentAlias, array &$params): string {
    if (($_SESSION['role'] ?? '') !== 'encoder') {
        return '';
    }

    if (empty($_SESSION['assigned_section']) || empty($_SESSION['assigned_grade'])) {
        return ' AND 1=0';
    }

    $params[] = $_SESSION['assigned_section'];
    $params[] = $_SESSION['assigned_grade'];

    return " AND {$studentAlias}.section = ? AND {$studentAlias}.grade_level = ?";
}

// Only run validation if button was clicked OR if it's the first load (to ensure persistency)
if (!isset($_POST['run_validation']) && !isset($_GET['auto_run'])) {
    $_GET['auto_run'] = 1; // Auto-run by default so it doesn't show "All Clear!" incorrectly
}

if (isset($_POST['run_validation']) || isset($_GET['auto_run'])) {
    
    // 1. Students with missing/invalid LRN (HIGH)
    $params = [];
    $stmt = $pdo->prepare("SELECT s.id, CONCAT(s.first_name,' ',s.last_name) as name, s.lrn, 'Missing or invalid LRN' as issue, 'high' as priority, 'student' as fix_type FROM students s WHERE (s.lrn IS NULL OR s.lrn = '' OR LENGTH(s.lrn) != 12)" . encoderScopeSql('s', $params));
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) { $validationResults[] = $row; $stats['total']++; $stats['high']++; }
    
    // 2. Age/Grade mismatch (MEDIUM) - e.g., Grade 1 student age > 9
    $params = [];
    $stmt = $pdo->prepare("SELECT s.id, CONCAT(s.first_name,' ',s.last_name) as name, s.grade_level, s.age, 
        CASE 
            WHEN s.grade_level = 1 AND s.age > 9 THEN 'Age too high for Grade 1'
            WHEN s.grade_level = 2 AND s.age > 10 THEN 'Age too high for Grade 2'
            WHEN s.grade_level = 3 AND s.age > 11 THEN 'Age too high for Grade 3'
            WHEN s.grade_level = 4 AND s.age > 12 THEN 'Age too high for Grade 4'
            WHEN s.grade_level = 5 AND s.age > 13 THEN 'Age too high for Grade 5'
            WHEN s.grade_level = 6 AND s.age > 14 THEN 'Age too high for Grade 6'
            WHEN s.age < 5 THEN 'Age too young for elementary'
            ELSE 'Possible age/grade mismatch'
        END as issue, 'medium' as priority, 'student' as fix_type
        FROM students s
        WHERE ((s.grade_level = 1 AND s.age > 9) OR (s.grade_level = 2 AND s.age > 10) OR (s.grade_level = 3 AND s.age > 11) 
           OR (s.grade_level = 4 AND s.age > 12) OR (s.grade_level = 5 AND s.age > 13) OR (s.grade_level = 6 AND s.age > 14) OR s.age < 5)" . encoderScopeSql('s', $params));
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) { $validationResults[] = $row; $stats['total']++; $stats['medium']++; }
    
    // 3. Measurements with impossible BMI (HIGH) - BMI < 10 or > 50
    $params = [];
    $stmt = $pdo->prepare("SELECT m.id, CONCAT(s.first_name,' ',s.last_name) as name, m.bmi, m.type,
        CASE WHEN m.bmi < 10 THEN 'BMI too low (possible data entry error)' 
             WHEN m.bmi > 50 THEN 'BMI too high (possible data entry error)'
             ELSE 'Invalid BMI value' END as issue, 'high' as priority, 'measurement' as fix_type
        FROM measurements m
        JOIN students s ON m.student_id = s.id
        WHERE m.bmi IS NOT NULL AND (m.bmi < 10 OR m.bmi > 50)" . encoderScopeSql('s', $params));
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) { $validationResults[] = $row; $stats['total']++; $stats['high']++; }
    
    // 4. Students missing baseline measurement (MEDIUM)
    $params = [];
    $stmt = $pdo->prepare("SELECT s.id, CONCAT(s.first_name,' ',s.last_name) as name, NULL as bmi, NULL as type, 
        'Missing baseline measurement' as issue, 'medium' as priority, 'measurement' as fix_type, s.id as student_id
        FROM students s
        LEFT JOIN measurements m ON s.id = m.student_id AND m.type = 'baseline'
        WHERE m.id IS NULL" . encoderScopeSql('s', $params));
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) { $validationResults[] = $row; $stats['total']++; $stats['medium']++; }
    
    // 5. Duplicate LRNs (HIGH) - should never happen but check anyway
    $params = [];
    $stmt = $pdo->prepare("SELECT s1.id, CONCAT(s1.first_name,' ',s1.last_name) as name, s1.lrn, 
        'Duplicate LRN found' as issue, 'high' as priority, 'student' as fix_type
        FROM students s1
        INNER JOIN students s2 ON s1.lrn = s2.lrn AND s1.id < s2.id
        WHERE 1=1" . encoderScopeSql('s1', $params));
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) { $validationResults[] = $row; $stats['total']++; $stats['high']++; }
    
    // 6. Height/weight outliers (LOW) - flag for review
    $params = [];
    $stmt = $pdo->prepare("SELECT m.id, CONCAT(s.first_name,' ',s.last_name) as name, m.height_cm, m.weight_kg,
        CASE 
            WHEN m.height_cm < 80 OR m.height_cm > 200 THEN 'Height outlier (review)'
            WHEN m.weight_kg < 15 OR m.weight_kg > 120 THEN 'Weight outlier (review)'
            ELSE 'Possible measurement error' 
        END as issue, 'low' as priority, 'measurement' as fix_type
        FROM measurements m
        JOIN students s ON m.student_id = s.id
        WHERE (m.height_cm < 80 OR m.height_cm > 200 OR m.weight_kg < 15 OR m.weight_kg > 120)" . encoderScopeSql('s', $params));
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) { $validationResults[] = $row; $stats['total']++; $stats['low']++; }
    
    // Log validation run
    logAudit($pdo, 'insert', 'validation', 0, "Ran data validation: {$stats['total']} issues found");
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="images/logo_feed.png?v=<?= time() ?>">
    <title>FEED System - Data Validation</title>
    <link rel="stylesheet" href="css/sidebar.css?v=20260515" />
    <link rel="stylesheet" href="css/data_validation.css?v=<?= time() ?>" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <script src="js/sidebar.js" defer></script>
  </head>
  <body>
    <div class="app-container">
      <?php include 'includes/sidebar.php'; ?>
      <div class="main-content-wrapper" id="mainWrapper">
        <main class="page-content main-content">
          
          <div class="page-header">
            <div class="page-header-copy">
              <h1>Data Validation</h1>
              <p>Identify and resolve data quality issues</p>
            </div>
            <form method="post" action="" class="page-actions">
              <input type="hidden" name="run_validation" value="1">
              <button type="submit" class="btn btn-primary" id="runValidationBtn">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/>
                  <path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>
                </svg>
                Run Validation
              </button>
            </form>
          </div>

          <!-- Stats (Dynamic) -->
          <div class="stats-grid">
            <div class="stat-card blue animate-in" style="animation-delay: 0.05s">
              <div class="stat-label">Total Issues</div>
              <div class="stat-value" id="statTotal"><?= $stats['total'] ?></div>
            </div>
            <div class="stat-card red animate-in" style="animation-delay: 0.1s">
              <div class="stat-label">High Priority</div>
              <div class="stat-value" id="statHigh"><?= $stats['high'] ?></div>
            </div>
            <div class="stat-card orange animate-in" style="animation-delay: 0.15s">
              <div class="stat-label">Medium Priority</div>
              <div class="stat-value" id="statMedium"><?= $stats['medium'] ?></div>
            </div>
            <div class="stat-card green animate-in" style="animation-delay: 0.2s">
              <div class="stat-label">Low Priority</div>
              <div class="stat-value" id="statLow"><?= $stats['low'] ?></div>
            </div>
          </div>

          <!-- Validation Results -->
          <div class="card">
            <div class="card-header">
              <span class="card-title">Validation Results</span>
            </div>
            <div class="card-body" id="validationResults">
              <?php if (empty($validationResults)): ?>
                <div class="empty-state">
                  <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path d="M9 12l2 2 4-4"/><path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                  </svg>
                  <h3>All Clear!</h3>
                  <p>No data quality issues found</p>
                  <?php if (!isset($_POST['run_validation']) && !isset($_GET['auto_run'])): ?>
                    <p style="margin-top:12px;font-size:13px;color:#9ca3af;">Click "Run Validation" to scan your data.</p>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <?php foreach ($validationResults as $i => $issue): ?>
                <div class="validation-item animate-in" style="animation-delay: <?= 0.05 * ($i + 1) ?>s">
                  <div class="validation-icon <?= $issue['priority'] ?>">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path d="M12 9v4"/><path d="M12 17h.01"/>
                      <circle cx="12" cy="12" r="10"/>
                    </svg>
                  </div>
                  <div class="validation-content">
                    <div class="validation-title"><?= htmlspecialchars($issue['name']) ?></div>
                    <div class="validation-desc"><?= htmlspecialchars($issue['issue']) ?></div>
                    <div class="validation-meta">
                      <span>Record ID: #<?= $issue['id'] ?></span>
                      <?php if (!empty($issue['lrn'])): ?><span>LRN: <?= htmlspecialchars($issue['lrn']) ?></span><?php endif; ?>
                      <?php if (!empty($issue['bmi'])): ?><span>BMI: <?= number_format($issue['bmi'],2) ?></span><?php endif; ?>
                      <?php if (!empty($issue['age'])): ?><span>Age: <?= $issue['age'] ?></span><?php endif; ?>
                      <?php if (!empty($issue['grade_level'])): ?><span>Grade: <?= $issue['grade_level'] ?></span><?php endif; ?>
                      <span class="priority-badge <?= $issue['priority'] ?>"><?= $issue['priority'] ?></span>
                    </div>
                  </div>
                  <div class="validation-actions">
                    <?php if (($issue['fix_type'] ?? 'student') === 'measurement'): ?>
                      <?php if ($issue['issue'] === 'Missing baseline measurement'): ?>
                        <a href="measurement.php?add=1&student_id=<?= $issue['student_id'] ?>" class="btn-sm primary">Fix</a>
                      <?php else: ?>
                        <a href="measurement.php?edit=<?= $issue['id'] ?>" class="btn-sm primary">Fix</a>
                      <?php endif; ?>
                    <?php else: ?>
                      <a href="student_profile.php?edit=<?= $issue['id'] ?>" class="btn-sm primary">Fix</a>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
          
        </main>
      </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer">
      <?php if (isset($_POST['run_validation'])): ?>
        <div class="toast success">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          <span>Validation complete: <?= $stats['total'] ?> issue<?= $stats['total'] !== 1 ? 's' : '' ?> found</span>
          <button type="button" onclick="this.parentElement.remove()">×</button>
        </div>
      <?php endif; ?>
    </div>

    <script>
    // Auto-hide toasts after 5 seconds
    document.querySelectorAll('.toast').forEach(toast => {
      setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
      }, 5000);
      toast.querySelector('button')?.addEventListener('click', () => toast.remove());
    });
    
    // Simple animation fallback if CSS animations not supported
    document.querySelectorAll('.animate-in').forEach((el, i) => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(10px)';
      setTimeout(() => {
        el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
      }, 50 * i);
    });
    
    // Auto-run validation on first visit (optional - remove if not wanted)
    // Uncomment the line below to auto-run when page loads with no results yet
    // if (document.getElementById('validationResults').querySelector('.empty-state') && !location.search.includes('auto_run')) {
    //   document.getElementById('runValidationBtn').click();
    // }
    </script>
  </body>
</html>
