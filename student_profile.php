<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

// ========== HANDLE POST ACTIONS (Add/Edit) ==========
$message = '';
$messageType = '';

// ADD or UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'update'])) {
  // Only process LRN if it's explicitly allowed. In Edit mode, we don't update LRN if it might be disabled.
  // Actually, we'll process it but the frontend will send it via a hidden input or active readonly field.
  $lrn = trim($_POST['lrn'] ?? '');
  $first = trim($_POST['first_name'] ?? '');
  $middle = trim($_POST['middle_name'] ?? '');
  $last = trim($_POST['last_name'] ?? '');
  $grade = (int)($_POST['grade_level'] ?? 0);
  $section = trim($_POST['section'] ?? '');
  $age = (int)($_POST['age'] ?? 0);
  $sex = $_POST['sex'] ?? '';
  $schoolYear = trim($_POST['school_year'] ?? '2025-2026');

  if (!$lrn || !$first || !$last || !$grade || !$section || !$age || !$sex) {
    $message = 'Fill all required fields.';
    $messageType = 'error';
  } else {
    if ($_POST['action'] === 'add') {
      $chk = $pdo->prepare("SELECT id FROM students WHERE lrn = ?");
      $chk->execute([$lrn]);
      if ($chk->fetch()) {
        $message = 'LRN already exists.';
        $messageType = 'error';
      } else {
        $stmt = $pdo->prepare("INSERT INTO students (lrn,first_name,middle_name,last_name,grade_level,section,age,sex,school_year) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$lrn, $first, $middle, $last, $grade, $section, $age, $sex, $schoolYear]);
        logAudit($pdo, 'insert', 'students', $pdo->lastInsertId(), "Added: $first $last");
        $message = 'Student added.';
        $messageType = 'success';
      }
    } else {
      $id = (int)($_POST['id'] ?? 0);
      $stmt = $pdo->prepare("UPDATE students SET lrn=?,first_name=?,middle_name=?,last_name=?,grade_level=?,section=?,age=?,sex=?,school_year=? WHERE id=?");
      $stmt->execute([$lrn, $first, $middle, $last, $grade, $section, $age, $sex, $schoolYear, $id]);
      logAudit($pdo, 'update', 'students', $id, "Updated: $first $last");
      $message = 'Student updated.';
      $messageType = 'success';
    }
  }
}

// ========== EXPORT LOGIC (Must run before HTML/Headers) ==========
if (isset($_GET['export'])) {
  $search = trim($_GET['search'] ?? '');
  $gradeFilter = $_GET['grade_filter'] ?? '';
  $sectionFilter = $_GET['section_filter'] ?? '';
  $sexFilter = $_GET['sex_filter'] ?? '';
  $schoolYearFilter = $_GET['school_year_filter'] ?? '';

  $where = [];
  $params = [];

  if ($search) {
    $where[] = "(CONCAT(first_name,' ',COALESCE(middle_name,''),' ',last_name) LIKE ? OR lrn LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
  }
  if ($gradeFilter) {
    $where[] = "grade_level = ?";
    $params[] = $gradeFilter;
  }
  if ($sectionFilter) {
    $where[] = "section = ?";
    $params[] = $sectionFilter;
  }
  if ($sexFilter) {
    $where[] = "sex = ?";
    $params[] = $sexFilter;
  }
  if ($schoolYearFilter) {
    $where[] = "school_year = ?";
    $params[] = $schoolYearFilter;
  }
  if (($_SESSION['role'] ?? '') === 'encoder' && !empty($_SESSION['assigned_section'])) {
    $where[] = "section = ?";
    $params[] = $_SESSION['assigned_section'];
  }

  $sql = "SELECT lrn, first_name, middle_name, last_name, grade_level, section, age, sex, school_year, created_at FROM students";
  if ($where) $sql .= " WHERE " . implode(' AND ', $where);
  $sql .= " ORDER BY grade_level, section, last_name";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $exportStudents = $stmt->fetchAll();

  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="students_roster_' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  
  // Header row
  fputcsv($out, ['LRN', 'First Name', 'Middle Name', 'Last Name', 'Grade', 'Section', 'Age', 'Sex', 'School Year', 'Date Encoded']);
  
  foreach ($exportStudents as $s) {
    fputcsv($out, [
      "=\"{$s['lrn']}\"", 
      $s['first_name'], 
      $s['middle_name'], 
      $s['last_name'], 
      $s['grade_level'], 
      $s['section'], 
      $s['age'], 
      $s['sex'], 
      $s['school_year'],
      date('Y-m-d', strtotime($s['created_at']))
    ]);
  }
  fclose($out);
  exit;
}

// ========== FETCH & FILTER STUDENTS ==========
function pageUrl($pageParam, $page) {
  $query = $_GET;
  $query[$pageParam] = $page;
  return '?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8');
}

$search = trim($_GET['search'] ?? '');
$gradeFilter = $_GET['grade_filter'] ?? '';
$sectionFilter = $_GET['section_filter'] ?? '';
$sexFilter = $_GET['sex_filter'] ?? '';
$schoolYearFilter = $_GET['school_year_filter'] ?? '';

$where = [];
$params = [];

// Search: name, LRN
if ($search) {
  $where[] = "(CONCAT(first_name,' ',COALESCE(middle_name,''),' ',last_name) LIKE ? OR lrn LIKE ?)";
  $params = array_merge($params, ["%$search%", "%$search%"]);
}
// Filters
if ($gradeFilter) {
  $where[] = "grade_level = ?";
  $params[] = $gradeFilter;
}
if ($sectionFilter) {
  $where[] = "section = ?";
  $params[] = $sectionFilter;
}
if ($sexFilter) {
  $where[] = "sex = ?";
  $params[] = $sexFilter;
}
if ($schoolYearFilter) {
  $where[] = "school_year = ?";
  $params[] = $schoolYearFilter;
}

// Role-based: encoder sees only their assigned_section when assigned.
if (($_SESSION['role'] ?? '') === 'encoder' && !empty($_SESSION['assigned_section'])) {
  $where[] = "section = ?";
  $params[] = $_SESSION['assigned_section'];
}

$studentsPerPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$baseFromSql = "FROM students s
        LEFT JOIN measurements m ON s.id = m.student_id";
$whereSql = $where ? " WHERE " . implode(' AND ', $where) : "";
$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) " . $baseFromSql . $whereSql);
$countStmt->execute($params);
$totalStudents = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalStudents / $studentsPerPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $studentsPerPage;

$sql = "SELECT s.*, 
        MAX(CASE WHEN m.type = 'baseline' THEN m.nutritional_status ELSE NULL END) as baseline_status,
        MAX(CASE WHEN m.type = 'baseline' THEN m.bmi ELSE NULL END) as baseline_bmi,
        MAX(CASE WHEN m.type = 'endline' THEN m.nutritional_status ELSE NULL END) as endline_status,
        MAX(CASE WHEN m.type = 'endline' THEN m.bmi ELSE NULL END) as endline_bmi
        " . $baseFromSql . $whereSql . "
        GROUP BY s.id
        ORDER BY s.grade_level, s.section, s.last_name
        LIMIT $studentsPerPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
$studentsStart = $totalStudents > 0 ? $offset + 1 : 0;
$studentsEnd = min($offset + count($students), $totalStudents);

// Determine if an LRN is currently invalid or duplicated (to unlock for encoders)
function isLrnIssue($pdo, $lrn, $studentId) {
    if (!$lrn || strlen(trim($lrn)) !== 12) return true;
    $chk = $pdo->prepare("SELECT COUNT(*) FROM students WHERE lrn = ?");
    $chk->execute([$lrn]);
    return $chk->fetchColumn() > 1; // Duplicate exists
}

// For Edit modal pre-fill
$edit = null;
$hasLrnIssue = false;
if (isset($_GET['edit'])) {
  $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
  $stmt->execute([(int)$_GET['edit']]);
  $edit = $stmt->fetch();
  if ($edit) {
      $hasLrnIssue = isLrnIssue($pdo, $edit['lrn'], $edit['id']);
  }
}
// For Add modal trigger
$showAddModal = isset($_GET['add']) || (isset($_POST['action']) && $_POST['action'] === 'add' && $messageType === 'error');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="images/logo_feed.png?v=1">
  <title>FEED System - Student Profiles</title>
  <link rel="stylesheet" href="css/sidebar.css?v=20260513" />
  <link rel="stylesheet" href="css/student_profile.css?v=20260513" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <script src="js/sidebar.js" defer></script>
</head>

<body>
  <div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content-wrapper" id="mainWrapper">
      <main class="main-content">

        <!-- Toast Message -->
        <?php if ($message): ?>
          <div class="toast toast-<?= $messageType ?>" id="toast">
            <span><?= htmlspecialchars($message) ?></span>
            <button type="button" onclick="document.getElementById('toast').style.display='none'">×</button>
          </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
          <h1 class="page-title">Student Profiles</h1>
          <p class="page-subtitle">Manage student information and beneficiary status</p>
        </div>

        <!-- Header Actions -->
        <div class="header-actions">
          <?php if ($_SESSION['role'] === 'admin'): ?>
          <!-- Export: triggers CSV download -->
          <form method="get" action="" style="display:inline;">
            <input type="hidden" name="export" value="1">
            <?php foreach (['search', 'grade_filter', 'section_filter', 'sex_filter', 'school_year_filter'] as $k): ?>
              <?php if (!empty($_GET[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($_GET[$k]) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-secondary">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              Export
            </button>
          </form>
          <?php endif; ?>
          <!-- Add Student: opens modal via URL param -->
          <a href="?add=1" class="btn btn-primary">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Add Student
          </a>
        </div>

        <!-- Search & Filters -->
        <div class="controls-bar">
          <div class="search-wrapper">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input type="text" id="liveSearch" class="search-input" placeholder="Search by name or LRN..." value="<?= htmlspecialchars($search) ?>" />
          </div>
          <button type="button" class="filter-btn" id="toggleFilters">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
            </svg>
            Show Filters
          </button>
        </div>

        <!-- Filter Panel (hidden by default, toggled by JS) -->
        <div class="filter-panel" id="filterPanel" style="display:none;">
          <form method="get" action="" class="filter-form">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <div class="filter-row">
              <select name="grade_filter" onchange="this.form.submit()">
                <option value="">All Grades</option>
                <?php for ($g = 1; $g <= 6; $g++): ?>
                  <option value="<?= $g ?>" <?= $gradeFilter === $g ? 'selected' : '' ?>>Grade <?= $g ?></option>
                <?php endfor; ?>
              </select>
              <select name="section_filter" onchange="this.form.submit()">
                <option value="">All Sections</option>
                <?php foreach (['A', 'B', 'C', 'D', 'E'] as $sec): ?>
                  <option value="<?= $sec ?>" <?= $sectionFilter === $sec ? 'selected' : '' ?>>Section <?= $sec ?></option>
                <?php endforeach; ?>
              </select>
              <select name="sex_filter" onchange="this.form.submit()">
                <option value="">All Sex</option>
                <option value="M" <?= $sexFilter === 'M' ? 'selected' : '' ?>>Male</option>
                <option value="F" <?= $sexFilter === 'F' ? 'selected' : '' ?>>Female</option>
              </select>
              <select name="school_year_filter" onchange="this.form.submit()">
                <option value="">All Years</option>
                <option value="2025-2026" <?= $schoolYearFilter === '2025-2026' ? 'selected' : '' ?>>2025-2026</option>
                <option value="2024-2025" <?= $schoolYearFilter === '2024-2025' ? 'selected' : '' ?>>2024-2025</option>
              </select>
              <?php if ($gradeFilter || $sectionFilter || $sexFilter || $schoolYearFilter): ?>
                <a href="student_profile.php<?= $search ? '?search=' . urlencode($search) : '' ?>" class="btn btn-outline">Clear</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!-- Results Info -->
        <div class="results-info">Showing <?= $studentsStart ?> to <?= $studentsEnd ?> of <?= $totalStudents ?> student<?= $totalStudents !== 1 ? 's' : '' ?></div>

        <!-- Student Table -->
        <div class="table-card">
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>LRN</th>
                  <th>Full Name</th>
                  <th>Grade/Section</th>
                  <th>Baseline</th>
                  <th>Endline</th>
                  <th class="right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($students)): ?>
                  <tr>
                    <td colspan="6" style="text-align:center;padding:2rem;color:#6b7a8d;">No students found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($students as $s):
                    $initial = strtoupper(substr($s['first_name'], 0, 1));
                    $fullName = $s['first_name'] . ' ' . (!empty($s['middle_name']) ? $s['middle_name'][0] . '. ' : '') . $s['last_name'];
                    
                    // Helpers for badges
                    $badgeColors = [
                        'Normal' => '#10b981', 
                        'Underweight' => '#ef4444', 
                        'Overweight' => '#f59e0b', 
                        'Obese' => '#f97316'
                    ];
                  ?>
                    <tr>
                      <td><?= htmlspecialchars($s['lrn']) ?></td>
                      <td>
                        <div class="student-cell">
                          <div class="student-avatar"><?= $initial ?></div>
                          <div class="student-info">
                            <span class="student-name"><?= htmlspecialchars($fullName) ?></span>
                          </div>
                        </div>
                      </td>
                      <td>Gr. <?= (int)$s['grade_level'] ?> - <?= htmlspecialchars($s['section']) ?></td>
                      <td>
                        <?php if ($s['baseline_status']): ?>
                            <span style="display:inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 500; background: <?= $badgeColors[$s['baseline_status']] ?? '#6b7280' ?>; color: white;">
                                <?= $s['baseline_status'] ?> (BMI: <?= $s['baseline_bmi'] ?>)
                            </span>
                        <?php else: ?>
                            <span style="font-size: 12px; color: #9ca3af; font-style: italic;">Pending</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($s['endline_status']): ?>
                            <span style="display:inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 500; background: <?= $badgeColors[$s['endline_status']] ?? '#6b7280' ?>; color: white;">
                                <?= $s['endline_status'] ?> (BMI: <?= $s['endline_bmi'] ?>)
                            </span>
                        <?php else: ?>
                            <span style="font-size: 12px; color: #9ca3af; font-style: italic;">Pending</span>
                        <?php endif; ?>
                      </td>
                      <td class="right">
                        <div class="action-buttons">
                          <a href="?edit=<?= $s['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="action-btn edit">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                            Edit
                          </a>
                        </div>
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
            <div class="pagination-controls" aria-label="Student profile pagination">
              <a class="page-btn page-btn-text <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : pageUrl('page', $page - 1) ?>">Previous</a>
              <span class="page-count">Page <?= $page ?> of <?= $totalPages ?></span>
              <a class="page-btn page-btn-text <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : pageUrl('page', $page + 1) ?>">Next</a>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Add/Edit Student Modal -->
  <div class="modal-overlay <?= ($edit || $showAddModal) ? 'active' : '' ?>" id="studentModal">
    <div class="modal">
      <h2><?= $edit ? 'Edit Student' : 'Add New Student' ?></h2>
      <form method="post" action="student_profile.php">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'add' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>

        <div class="form-row">
          <div class="form-group">
            <label>LRN *</label>
            <?php 
              // Admins can always edit. Encoders can edit IF it's a new add, OR if the current LRN has a data validation issue (like a duplicate or missing digits)
              $canEditLrn = (!isset($edit) || $_SESSION['role'] === 'admin' || $hasLrnIssue); 
            ?>
            <?php if (!$canEditLrn): ?>
              <!-- In Edit mode, make LRN read-only for non-admins if it's already valid -->
              <input
                type="text"
                name="lrn"
                value="<?= htmlspecialchars($edit['lrn'] ?? '') ?>"
                readonly
                class="form-input"
                style="background-color: #f3f4f6; cursor: not-allowed;"
                title="Only Administrators can edit an LRN once created">
              <small class="field-hint">Only Administrators can modify a valid LRN.</small>
            <?php else: ?>
              <input
                type="text"
                name="lrn"
                value="<?= htmlspecialchars($edit['lrn'] ?? '') ?>"
                required
                maxlength="12"
                pattern="[0-9]{12}"
                title="Enter exactly 12 digits (no letters or spaces)"
                inputmode="numeric"
                autocomplete="off"
                placeholder="123456789012"
                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,12)"
                class="form-input">
              <?php if ($edit && $hasLrnIssue && $_SESSION['role'] !== 'admin'): ?>
                <small class="field-hint" style="color: #e7000b;">Unlocked to fix data validation issue.</small>
              <?php else: ?>
                <small class="field-hint">DepEd LRN: 12 digits, numbers only</small>
              <?php endif; ?>
            <?php endif; ?>

          </div>
          <div class="form-group">
            <label>School Year</label>
            <select name="school_year" class="form-input">
              <option value="2025-2026" <?= (!isset($edit['school_year']) || $edit['school_year'] === '2025-2026') ? 'selected' : '' ?>>2025-2026</option>
              <option value="2024-2025" <?= (isset($edit['school_year']) && $edit['school_year'] === '2024-2025') ? 'selected' : '' ?>>2024-2025</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>First Name *</label>
            <input
              type="text"
              name="first_name"
              value="<?= htmlspecialchars($edit['first_name'] ?? '') ?>"
              required
              pattern="[A-Za-z\s\.'-]{2,50}"
              title="Letters, spaces, apostrophes, hyphens only (no numbers)"
              maxlength="50"
              placeholder="Juan"
              oninput="this.value = this.value.replace(/[^A-Za-z\s\.'-]/g, '')"
              class="form-input">
            <small class="field-hint">No numbers or special characters</small>
          </div>
          <div class="form-group">
            <label>Middle Name</label>
            <input
              type="text"
              name="middle_name"
              value="<?= htmlspecialchars($edit['middle_name'] ?? '') ?>"
              pattern="[A-Za-z\s\.'-]{0,50}"
              title="Letters, spaces, apostrophes, hyphens only"
              maxlength="50"
              placeholder="Santos"
              oninput="this.value = this.value.replace(/[^A-Za-z\s\.'-]/g, '')"
              class="form-input">
            <small class="field-hint">Optional</small>
          </div>
          <div class="form-group">
            <label>Last Name *</label>
            <input
              type="text"
              name="last_name"
              value="<?= htmlspecialchars($edit['last_name'] ?? '') ?>"
              required
              pattern="[A-Za-z\s\.'-]{2,50}"
              title="Letters, spaces, apostrophes, hyphens only (no numbers)"
              maxlength="50"
              placeholder="Dela Cruz"
              oninput="this.value = this.value.replace(/[^A-Za-z\s\.'-]/g, '')"
              class="form-input">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Grade Level *</label>
            <select name="grade_level" required class="form-input">
              <option value="">Select</option>
              <?php for ($g = 1; $g <= 6; $g++): ?>
                <option value="<?= $g ?>" <?= (isset($edit['grade_level']) && $edit['grade_level'] == $g) ? 'selected' : '' ?>>Grade <?= $g ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Section *</label>
            <select name="section" required class="form-input">
              <option value="">Select Section</option>
              <option value="A" <?= (isset($edit['section']) && $edit['section'] === 'A') ? 'selected' : '' ?>>Section A</option>
              <option value="B" <?= (isset($edit['section']) && $edit['section'] === 'B') ? 'selected' : '' ?>>Section B</option>
              <option value="C" <?= (isset($edit['section']) && $edit['section'] === 'C') ? 'selected' : '' ?>>Section C</option>
              <option value="D" <?= (isset($edit['section']) && $edit['section'] === 'D') ? 'selected' : '' ?>>Section D</option>
              <option value="E" <?= (isset($edit['section']) && $edit['section'] === 'E') ? 'selected' : '' ?>>Section E</option>
            </select>
          </div>
          <div class="form-group">
            <label>Age *</label>
            <input type="number" name="age" value="<?= htmlspecialchars($edit['age'] ?? '') ?>" required min="3" max="20" class="form-input">
          </div>
          <div class="form-group">
            <label>Sex *</label>
            <select name="sex" required class="form-input">
              <option value="">Select</option>
              <option value="M" <?= (isset($edit['sex']) && $edit['sex'] === 'M') ? 'selected' : '' ?>>Male</option>
              <option value="F" <?= (isset($edit['sex']) && $edit['sex'] === 'F') ? 'selected' : '' ?>>Female</option>
            </select>
          </div>
        </div>

    <div class="modal-actions">
      <a href="student_profile.php" class="btn btn-cancel">Cancel</a>
      <button type="submit" class="btn btn-primary"><?= $edit ? 'Update' : 'Add' ?> Student</button>
    </div>
    </form>
  </div>
  </div>



</html>
