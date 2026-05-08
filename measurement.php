<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
  header('Location: index.php');
  exit;
}

// ========== HELPER: Calculate BMI & Nutritional Status ==========
function calculateBMI($weight, $height)
{
  if ($height <= 0) return 0;
  return round($weight / pow($height / 100, 2), 2);
}

function getNutritionalStatus($bmi)
{
  if ($bmi < 16) return 'Underweight';
  if ($bmi <= 22) return 'Normal';
  if ($bmi <= 26) return 'Overweight';
  return 'Obese';
}

function getBadgeClass($status)
{
  return match ($status) {
    'Underweight' => 'badge--underweight',
    'Normal' => 'badge--normal',
    'Overweight' => 'badge--overweight',
    'Obese' => 'badge--obese',
    default => 'badge--normal'
  };
}

// ========== HANDLE POST ACTIONS ==========
$message = '';
$messageType = '';

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  if ($_SESSION['role'] !== 'admin') {
    $message = 'Only admins can delete records.';
    $messageType = 'error';
  } else {
    $id = (int)($_POST['measurement_id'] ?? 0);
    if ($id > 0) {
      $stmt = $pdo->prepare("DELETE FROM measurements WHERE id = ?");
      $stmt->execute([$id]);
      logAudit($pdo, 'delete', 'measurements', $id, 'Deleted measurement');
      $message = 'Measurement deleted.';
      $messageType = 'success';
    }
  }
}

// ADD or UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'update'])) {
  $studentId = (int)($_POST['student_id'] ?? 0);
  $type = $_POST['type'] ?? '';
  $height = (float)($_POST['height_cm'] ?? 0);
  $weight = (float)($_POST['weight_kg'] ?? 0);
  $date = $_POST['measured_date'] ?? date('Y-m-d');

  if (!$studentId || !$type || !$height || !$weight || !$date) {
    $message = 'Fill all required fields.';
    $messageType = 'error';
  } elseif ($height < 50 || $height > 200 || $weight < 10 || $weight > 150) {
    $message = 'Invalid height/weight range.';
    $messageType = 'error';
  } else {
    $bmi = calculateBMI($weight, $height);
    $status = getNutritionalStatus($bmi);

    try {
      if ($_POST['action'] === 'add') {
        $chk = $pdo->prepare("SELECT id FROM measurements WHERE student_id = ? AND type = ?");
        $chk->execute([$studentId, $type]);
        if ($chk->fetch()) {
          $message = "This student already has a $type measurement.";
          $messageType = 'error';
        } else {
          $stmt = $pdo->prepare("INSERT INTO measurements (student_id,type,height_cm,weight_kg,bmi,nutritional_status,measured_date,recorded_by) VALUES (?,?,?,?,?,?,?,?)");
          $stmt->execute([$studentId, $type, $height, $weight, $bmi, $status, $date, $_SESSION['user_id']]);
          logAudit($pdo, 'insert', 'measurements', $pdo->lastInsertId(), "Added $type measurement");
          header('Location: measurement.php?msg=added');
          exit;
        }
      } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE measurements SET type=?,height_cm=?,weight_kg=?,bmi=?,nutritional_status=?,measured_date=? WHERE id=?");
        $stmt->execute([$type, $height, $weight, $bmi, $status, $date, $id]);
        logAudit($pdo, 'update', 'measurements', $id, "Updated $type measurement");
        header('Location: measurement.php?msg=updated');
        exit;
      }
    } catch (PDOException $e) {
      error_log("Measurement error: " . $e->getMessage());
      $message = 'Database error.';
      $messageType = 'error';
    }
  }
}

if (isset($_GET['msg']) && !$message) {
  if ($_GET['msg'] === 'added') {
    $message = 'Measurement added.';
    $messageType = 'success';
  } elseif ($_GET['msg'] === 'updated') {
    $message = 'Measurement updated.';
    $messageType = 'success';
  }
}

// ========== FETCH & FILTER ==========
$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type_filter'] ?? '';
$gradeFilter = $_GET['grade_filter'] ?? '';
$sectionFilter = $_GET['section_filter'] ?? '';

$where = [];
$params = [];
if ($search) {
  $where[] = "(CONCAT(s.first_name,' ',s.last_name) LIKE ?)";
  $params[] = "%$search%";
}
if ($typeFilter) {
  $where[] = "m.type = ?";
  $params[] = $typeFilter;
}
if ($gradeFilter) {
  $where[] = "s.grade_level = ?";
  $params[] = $gradeFilter;
}
if ($sectionFilter) {
  $where[] = "s.section = ?";
  $params[] = $sectionFilter;
}
if (in_array($_SESSION['role'], ['teacher', 'encoder']) && !empty($_SESSION['assigned_section'])) {
  $where[] = "s.section = ?";
  $params[] = $_SESSION['assigned_section'];
}

$sql = "SELECT m.*, s.first_name, s.last_name, s.grade_level, s.section, u.full_name as recorder_name
        FROM measurements m
        JOIN students s ON m.student_id = s.id
        LEFT JOIN users u ON m.recorded_by = u.id";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY m.measured_date DESC, s.last_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$measurements = $stmt->fetchAll();

// Students for dropdown
$studentWhere = [];
$studentParams = [];
if (in_array($_SESSION['role'], ['teacher', 'encoder']) && !empty($_SESSION['assigned_section'])) {
  $studentWhere[] = "section = ?";
  $studentParams[] = $_SESSION['assigned_section'];
}
$studentSql = "SELECT id, CONCAT(first_name,' ',last_name) as full_name, grade_level, section FROM students";
if ($studentWhere) $studentSql .= " WHERE " . implode(' AND ', $studentWhere);
$studentSql .= " ORDER BY grade_level, section, last_name";
$students = $pdo->query($studentSql)->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
  $stmt = $pdo->prepare("SELECT * FROM measurements WHERE id = ?");
  $stmt->execute([(int)$_GET['edit']]);
  $edit = $stmt->fetch();
}
$showModal = isset($_GET['add']) || isset($_GET['edit']) || ($messageType === 'error' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'update']));

// Stats
$total = $pdo->query("SELECT COUNT(*) FROM measurements")->fetchColumn();
$baseline = $pdo->query("SELECT COUNT(*) FROM measurements WHERE type='baseline'")->fetchColumn();
$endline = $pdo->query("SELECT COUNT(*) FROM measurements WHERE type='endline'")->fetchColumn();
$monthly = max(0, $total - $baseline - $endline);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Anthropometric Measurements</title>
  <link rel="stylesheet" href="css/measurement.css">
  <link rel="stylesheet" href="css/sidebar.css" />
  <script src="js/sidebar.js" defer></script>
  <style>
    /* ===== MODAL OVERLAY - CENTERED & FIXED ===== */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 9999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .modal-overlay[style*="display: none"] {
      display: none !important;
    }

    .modal {
      background: #fff;
      border-radius: 12px;
      padding: 24px;
      width: 100%;
      max-width: 520px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
      animation: modalIn 0.2s ease;
    }

    @keyframes modalIn {
      from {
        transform: translateY(-20px);
        opacity: 0;
      }

      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    /* ===== LIVE SEARCH STYLES ===== */
    .search-wrapper {
      position: relative;
      flex: 1;
    }

    .search-input {
      width: 100%;
      padding: 10px 12px 10px 36px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.15s;
    }

    .search-input:focus {
      border-color: #00bc7d;
      outline: none;
      box-shadow: 0 0 0 3px rgba(0, 188, 125, 0.1);
    }

    .search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      width: 16px;
      height: 16px;
      color: #6b7280;
      pointer-events: none;
    }

    /* ===== BADGES ===== */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 2px 10px;
      border-radius: 9999px;
      font-size: 10.7px;
      font-weight: 400;
      line-height: 16px;
      white-space: nowrap;
    }

    .badge--underweight {
      background: #fef2f2;
      border: 1px solid #ffc9c9;
      color: #e7000b;
    }

    .badge--normal {
      background: #f0fdf4;
      border: 1px solid #b9f8cf;
      color: #00a63e;
    }

    .badge--overweight {
      background: #fffbeb;
      border: 1px solid #fde68a;
      color: #d97706;
    }

    .badge--obese {
      background: #fff1f2;
      border: 1px solid #fecdd3;
      color: #be123c;
    }

    /* ===== TOAST ===== */
    .toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 12px 20px;
      border-radius: 8px;
      color: white;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 10000;
      animation: toastIn 0.3s;
    }

    .toast.toast-success {
      background: #00bc7d;
    }

    .toast.toast-error {
      background: #e7000b;
    }

    .toast button {
      background: none;
      border: none;
      color: inherit;
      font-size: 18px;
      cursor: pointer;
      margin-left: auto;
    }

    @keyframes toastIn {
      from {
        transform: translateY(100px);
        opacity: 0;
      }

      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    /* ===== FILTER PANEL ===== */
    .filter-panel {
      background: #fff;
      border-radius: 8px;
      padding: 16px;
      margin: 16px 0;
      border: 1px solid #e5e7eb;
    }

    .filter-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
    }

    .filter-row select {
      padding: 8px 12px;
      border-radius: 6px;
      border: 1px solid #e5e7eb;
    }

    .btn-clear {
      color: #6b7280;
      text-decoration: none;
      font-size: 13px;
    }
  </style>
</head>

<body>
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content-wrapper" id="mainWrapper">
    <main class="main-content">

      <?php if ($message): ?>
        <div class="toast toast-<?= $messageType ?>" id="toast">
          <span><?= htmlspecialchars($message) ?></span>
          <button type="button" onclick="document.getElementById('toast').style.display='none'">×</button>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <div class="page-title-group">
          <h1 class="page-title">Anthropometric Measurements</h1>
          <p class="page-subtitle">Record and track student measurements</p>
        </div>
        <div class="page-actions">
          <form method="get" action="" style="display:inline;">
            <input type="hidden" name="export" value="1">
            <button type="submit" class="btn-export">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              Export
            </button>
          </form>
          <a href="?add=1" class="btn-add">
            <svg width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Add Measurement
          </a>
        </div>
      </div>

      <div class="stat-cards">
        <div class="stat-card stat-card--blue">
          <p class="stat-label">Total Measurements</p>
          <p class="stat-value"><?= $total ?></p>
        </div>
        <div class="stat-card stat-card--green">
          <p class="stat-label">Baseline</p>
          <p class="stat-value"><?= $baseline ?></p>
        </div>
        <div class="stat-card stat-card--orange">
          <p class="stat-label">Monthly</p>
          <p class="stat-value"><?= $monthly ?></p>
        </div>
        <div class="stat-card stat-card--purple">
          <p class="stat-label">Endline</p>
          <p class="stat-value"><?= $endline ?></p>
        </div>
      </div>

      <!-- LIVE SEARCH (no button) + Filter Toggle -->
      <div class="toolbar">
        <div class="search-wrapper">
          <svg class="search-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input type="text" id="liveSearch" class="search-input" placeholder="Search by student name..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
        </div>
        <button type="button" class="btn-filter" id="toggleFilters">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <line x1="4" y1="6" x2="20" y2="6" />
            <line x1="8" y1="12" x2="16" y2="12" />
            <line x1="11" y1="18" x2="13" y2="18" />
          </svg>
          Filters
        </button>
      </div>

      <div class="filter-panel" id="filterPanel" style="display:none;">
        <form method="get" action="" class="filter-form" id="filterForm">
          <input type="hidden" name="search" id="filterSearch" value="<?= htmlspecialchars($search) ?>">
          <div class="filter-row">
            <select name="type_filter" onchange="this.form.submit()">
              <option value="">All Types</option>
              <option value="baseline" <?= $typeFilter === 'baseline' ? 'selected' : '' ?>>Baseline</option>
              <option value="endline" <?= $typeFilter === 'endline' ? 'selected' : '' ?>>Endline</option>
            </select>
            <select name="grade_filter" onchange="this.form.submit()">
              <option value="">All Grades</option>
              <?php for ($g = 1; $g <= 6; $g++): ?><option value="<?= $g ?>" <?= $gradeFilter == $g ? 'selected' : '' ?>>Grade <?= $g ?></option><?php endfor; ?>
            </select>
            <select name="section_filter" onchange="this.form.submit()">
              <option value="">All Sections</option>
              <?php foreach (['A', 'B', 'C', 'D', 'E'] as $sec): ?><option value="<?= $sec ?>" <?= $sectionFilter === $sec ? 'selected' : '' ?>>Section <?= $sec ?></option><?php endforeach; ?>
            </select>
            <?php if ($typeFilter || $gradeFilter || $sectionFilter): ?><a href="measurement.php<?= $search ? '?search=' . urlencode($search) : '' ?>" class="btn-clear">Clear</a><?php endif; ?>
          </div>
        </form>
      </div>

      <p class="results-count">Showing <?= count($measurements) ?> measurement<?= count($measurements) !== 1 ? 's' : '' ?></p>

      <div class="table-card">
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Type</th>
                <th>Weight (kg)</th>
                <th>Height (cm)</th>
                <th>BMI</th>
                <th>Status</th>
                <th>Measured By</th>
                <th class="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($measurements)): ?>
                <tr>
                  <td colspan="9" style="text-align:center;padding:2rem;color:#6b7a8d;">No measurements found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($measurements as $m):
                  $badgeClass = getBadgeClass($m['nutritional_status']);
                  $initial = strtoupper(substr($m['first_name'], 0, 1));
                ?>
                  <tr>
                    <td><?= date('n/j/Y', strtotime($m['measured_date'])) ?></td>
                    <td>
                      <div class="student-cell">
                        <div class="student-avatar"><?= htmlspecialchars($initial) ?></div>
                        <div class="student-info">
                          <span class="student-name"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></span>
                        </div>
                      </div>
                    </td>
                    <td><?= htmlspecialchars(ucfirst($m['type'])) ?></td>
                    <td><?= number_format($m['weight_kg'], 1) ?></td>
                    <td><?= number_format($m['height_cm'], 1) ?></td>
                    <td><?= number_format($m['bmi'], 2) ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($m['nutritional_status']) ?></span></td>
                    <td class="td-muted"><?= htmlspecialchars($m['recorder_name'] ?? 'System') ?></td>
                    <td class="right">
                      <div class="action-buttons">
                        <a href="?edit=<?= $m['id'] ?>" class="action-btn edit">
                          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                          </svg>
                          Edit
                        </a>

                        <?php if ($_SESSION['role'] === 'admin'): ?>
                          <form method="post" style="display:inline;" onsubmit="return confirm('Delete this measurement?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="measurement_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="action-btn delete">
                              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                              </svg>
                              Delete
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal -->
  <div class="modal-overlay" id="measurementModal" style="display: <?= $showModal ? 'flex' : 'none' ?>;">
    <div class="modal">
      <h2><?= $edit ? 'Edit Measurement' : 'Add New Measurement' ?></h2>
      <form method="post" action="">
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'add' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>

        <div class="form-group">
          <label>Student *</label>
          <select name="student_id" required>
            <option value="">Select student</option>
            <?php foreach ($students as $s): ?>
              <option value="<?= $s['id'] ?>" <?= (isset($edit['student_id']) && $edit['student_id'] == $s['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['full_name']) ?> (Grade <?= $s['grade_level'] ?>-<?= $s['section'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Type *</label>
            <select name="type" required>
              <option value="baseline" <?= (isset($edit['type']) && $edit['type'] === 'baseline') ? 'selected' : '' ?>>Baseline</option>
              <option value="endline" <?= (isset($edit['type']) && $edit['type'] === 'endline') ? 'selected' : '' ?>>Endline</option>
            </select>
          </div>
          <div class="form-group">
            <label>Date *</label>
            <input type="date" name="measured_date" value="<?= htmlspecialchars($edit['measured_date'] ?? date('Y-m-d')) ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Weight (kg) *</label>
            <input type="number" name="weight_kg" step="0.1" min="10" max="150" value="<?= htmlspecialchars($edit['weight_kg'] ?? '') ?>" required oninput="calculateBMI()">
          </div>
          <div class="form-group">
            <label>Height (cm) *</label>
            <input type="number" name="height_cm" step="0.1" min="50" max="200" value="<?= htmlspecialchars($edit['height_cm'] ?? '') ?>" required oninput="calculateBMI()">
          </div>
        </div>

        <div class="form-group">
          <label>BMI (auto)</label>
          <input type="text" id="bmiPreview" value="<?= htmlspecialchars($edit['bmi'] ?? '') ?>" readonly style="background:#f9fafb;font-weight:600;">
          <small class="field-hint">BMI = weight(kg) ÷ height(m)²</small>
        </div>

        <div class="modal-actions">
          <a href="measurement.php" class="btn-cancel">Cancel</a>
          <button type="submit" class="btn-primary"><?= $edit ? 'Update' : 'Add' ?> Measurement</button>
        </div>
      </form>
    </div>
  </div>

  <!-- CSV Export -->
  <?php if (isset($_GET['export'])):
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="measurements_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Student', 'Type', 'Weight_kg', 'Height_cm', 'BMI', 'Status', 'Recorded_By']);
    foreach ($measurements as $m) {
      fputcsv($out, [date('Y-m-d', strtotime($m['measured_date'])), $m['first_name'] . ' ' . $m['last_name'], ucfirst($m['type']), $m['weight_kg'], $m['height_cm'], $m['bmi'], $m['nutritional_status'], $m['recorder_name'] ?? 'System']);
    }
    fclose($out);
    exit;
  endif; ?>

  <script>
    // ===== LIVE SEARCH (no button, filters as you type) =====
    const searchInput = document.getElementById('liveSearch');
    const filterSearch = document.getElementById('filterSearch');
    let searchTimeout;

    searchInput?.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      filterSearch.value = this.value; // Sync hidden input for form submit
      searchTimeout = setTimeout(() => {
        // Live filter: reload page with search param
        const url = new URL(window.location);
        if (this.value.trim()) {
          url.searchParams.set('search', this.value.trim());
        } else {
          url.searchParams.delete('search');
        }
        window.location.href = url.toString();
      }, 300); // 300ms debounce
    });

    // ===== BMI Calculator =====
    function calculateBMI() {
      const weight = parseFloat(document.querySelector('[name="weight_kg"]').value) || 0;
      const height = parseFloat(document.querySelector('[name="height_cm"]').value) || 0;
      if (weight > 0 && height > 0) {
        document.getElementById('bmiPreview').value = (weight / Math.pow(height / 100, 2)).toFixed(2);
      }
    }
    document.addEventListener('DOMContentLoaded', calculateBMI);

    // ===== Modal Toggle =====
    const modal = document.getElementById('measurementModal');
    document.querySelector('.btn-add')?.addEventListener('click', e => {
      e.preventDefault();
      modal.style.display = 'flex';
    });
    modal?.addEventListener('click', e => {
      if (e.target === modal) modal.style.display = 'none';
    });
    document.querySelector('.btn-cancel')?.addEventListener('click', e => {
      e.preventDefault();
      modal.style.display = 'none';
    });

    // ===== Filter Panel Toggle =====
    document.getElementById('toggleFilters')?.addEventListener('click', function() {
      const panel = document.getElementById('filterPanel');
      panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    });

    // ===== Toast Auto-Hide =====
    setTimeout(() => {
      const t = document.getElementById('toast');
      if (t) {
        t.style.opacity = '0';
        setTimeout(() => t.style.display = 'none', 300);
      }
    }, 4000);
  </script>
</body>

</html>
