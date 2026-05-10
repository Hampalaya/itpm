<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

function scalarValue(PDO $pdo, string $sql, array $params = [], $default = 0) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? $default : $value;
}

function rowsValue(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function progressLabel(string $baseline, string $endline): string {
    $score = ['Underweight' => 1, 'Normal' => 3, 'Overweight' => 2, 'Obese' => 1];
    $baseDistance = abs(($score[$baseline] ?? 0) - 3);
    $endDistance = abs(($score[$endline] ?? 0) - 3);

    if ($endDistance < $baseDistance) return 'improved';
    if ($endDistance > $baseDistance) return 'declined';
    return 'maintained';
}

function timeAgo(?string $dateTime): string {
    if (!$dateTime) return 'Recently';
    $timestamp = strtotime($dateTime);
    if (!$timestamp) return 'Recently';

    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
    return date('M j, Y', $timestamp);
}

$roleRestricted = ($_SESSION['role'] ?? '') === 'encoder' && !empty($_SESSION['assigned_section']);
$studentWhere = $roleRestricted ? 'WHERE s.section = ?' : '';
$studentParams = $roleRestricted ? [$_SESSION['assigned_section']] : [];
$measurementWhere = $roleRestricted ? 'WHERE s.section = ?' : '';
$measurementParams = $studentParams;
$measurementAnd = $roleRestricted ? 'AND s.section = ?' : '';

$totalStudents = (int) scalarValue($pdo, "SELECT COUNT(*) FROM students s $studentWhere", $studentParams);
$totalBeneficiaries = (int) scalarValue(
    $pdo,
    "SELECT COUNT(DISTINCT m.student_id)
     FROM measurements m
     JOIN students s ON m.student_id = s.id
     $measurementWhere",
    $measurementParams
);

$currentStatusRows = rowsValue(
    $pdo,
    "SELECT m.nutritional_status, COUNT(*) AS total
     FROM measurements m
     JOIN students s ON m.student_id = s.id
     WHERE NOT EXISTS (
       SELECT 1 FROM measurements newer
       WHERE newer.student_id = m.student_id
         AND (newer.measured_date > m.measured_date OR (newer.measured_date = m.measured_date AND newer.id > m.id))
     )
     $measurementAnd
     GROUP BY m.nutritional_status",
    $measurementParams
);

$statusCounts = ['Normal' => 0, 'Underweight' => 0, 'Overweight' => 0, 'Obese' => 0];
foreach ($currentStatusRows as $row) {
    if (isset($statusCounts[$row['nutritional_status']])) {
        $statusCounts[$row['nutritional_status']] = (int) $row['total'];
    }
}

$attendanceRows = rowsValue(
    $pdo,
    "SELECT COUNT(*) AS total, SUM(fl.is_present = 1) AS present
     FROM feeding_logs fl
     JOIN students s ON fl.student_id = s.id
     WHERE fl.feeding_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     $measurementAnd",
    $measurementParams
);
$attendanceTotal = (int) ($attendanceRows[0]['total'] ?? 0);
$attendancePresent = (int) ($attendanceRows[0]['present'] ?? 0);
$attendanceRate = $attendanceTotal > 0 ? round(($attendancePresent / $attendanceTotal) * 100, 1) : 0;

$progressRows = rowsValue(
    $pdo,
    "SELECT base.nutritional_status AS baseline_status, endline.nutritional_status AS endline_status
     FROM students s
     JOIN measurements base ON s.id = base.student_id AND base.type = 'baseline'
     JOIN measurements endline ON s.id = endline.student_id AND endline.type = 'endline'
     $studentWhere",
    $studentParams
);
$progressCounts = ['improved' => 0, 'maintained' => 0, 'declined' => 0];
foreach ($progressRows as $row) {
    $progressCounts[progressLabel($row['baseline_status'], $row['endline_status'])]++;
}

$baselineRows = rowsValue(
    $pdo,
    "SELECT m.nutritional_status, COUNT(*) AS total
     FROM measurements m
     JOIN students s ON m.student_id = s.id
     WHERE m.type = 'baseline' $measurementAnd
     GROUP BY m.nutritional_status",
    $measurementParams
);
$endlineRows = rowsValue(
    $pdo,
    "SELECT m.nutritional_status, COUNT(*) AS total
     FROM measurements m
     JOIN students s ON m.student_id = s.id
     WHERE m.type = 'endline' $measurementAnd
     GROUP BY m.nutritional_status",
    $measurementParams
);
$baselineCounts = ['Underweight' => 0, 'Normal' => 0, 'Overweight' => 0, 'Obese' => 0];
$endlineCounts = $baselineCounts;
foreach ($baselineRows as $row) {
    if (isset($baselineCounts[$row['nutritional_status']])) $baselineCounts[$row['nutritional_status']] = (int) $row['total'];
}
foreach ($endlineRows as $row) {
    if (isset($endlineCounts[$row['nutritional_status']])) $endlineCounts[$row['nutritional_status']] = (int) $row['total'];
}

$trendRows = rowsValue(
    $pdo,
    "SELECT DATE_FORMAT(m.measured_date, '%Y-%m') AS period,
            DATE_FORMAT(m.measured_date, '%b %Y') AS label,
            SUM(m.nutritional_status = 'Underweight') AS underweight
     FROM measurements m
     JOIN students s ON m.student_id = s.id
     WHERE m.measured_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     $measurementAnd
     GROUP BY period, label
     ORDER BY period",
    $measurementParams
);

// Only fetch recent activities if the user is an admin
$activityRows = [];
if ($_SESSION['role'] === 'admin') {
    try {
        $activityRows = rowsValue(
            $pdo,
            "SELECT a.action, a.table_name, a.description, a.created_at, u.full_name
             FROM audit_logs a
             LEFT JOIN users u ON a.user_id = u.id
             ORDER BY a.created_at DESC
             LIMIT 6"
        );
    } catch (PDOException $e) {
        error_log('Dashboard activity query failed: ' . $e->getMessage());
    }
}

$chartData = [
    'status' => [
        'labels' => array_keys($statusCounts),
        'values' => array_values($statusCounts),
        'colors' => ['#10b981', '#ef4444', '#f59e0b', '#f97316'],
    ],
    'progress' => [
        'labels' => ['Underweight', 'Normal', 'Overweight', 'Obese'],
        'baseline' => array_values($baselineCounts),
        'endline' => array_values($endlineCounts),
    ],
    'trend' => [
        'labels' => array_map(fn($row) => $row['label'], $trendRows),
        'values' => array_map(fn($row) => (int) $row['underweight'], $trendRows),
    ],
];
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FEED System - Dashboard</title>
    <link rel="stylesheet" href="css/dashboard.css" />
    <link rel="stylesheet" href="css/sidebar.css" />
    <link href="https://cdn.fontsource.org/css/google/inter/400,500,600,700.css" rel="stylesheet" />
    <link href="https://cdn.fontsource.org/css/google/figtree/400,500,600,700.css" rel="stylesheet" />
    <script src="js/sidebar.js" defer></script>
  </head>
  <body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper" id="mainWrapper">
      <main class="main-content">
        <div class="header animate-in">
          <div class="header-left">
            <h1>Dashboard</h1>
            <p>Welcome back! Here's your feeding program overview.</p>
          </div>
          <div class="header-right">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <span><?= date('F j, Y') ?></span>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card animate-in delay-1">
            <div class="stat-card-header">
              <span class="stat-label">Total Beneficiaries</span>
              <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></svg></div>
            </div>
            <div class="stat-value"><?= $totalBeneficiaries ?></div>
          </div>
          <div class="stat-card animate-in delay-2">
            <div class="stat-card-header">
              <span class="stat-label">Students Encoded</span>
              <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" /></svg></div>
            </div>
            <div class="stat-value"><?= $totalStudents ?></div>
          </div>
          <div class="stat-card animate-in delay-3">
            <div class="stat-card-header">
              <span class="stat-label">Underweight</span>
              <div class="stat-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6" /><polyline points="17 18 23 18 23 12" /></svg></div>
            </div>
            <div class="stat-value"><?= $statusCounts['Underweight'] ?></div>
            <div class="stat-change positive"><?= $progressCounts['improved'] ?> improved</div>
          </div>
          <div class="stat-card animate-in delay-4">
            <div class="stat-card-header">
              <span class="stat-label">Normal</span>
              <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18" /><polyline points="17 6 23 6 23 12" /></svg></div>
            </div>
            <div class="stat-value"><?= $statusCounts['Normal'] ?></div>
            <div class="stat-change positive"><?= $progressCounts['maintained'] ?> maintained</div>
          </div>
          <div class="stat-card animate-in delay-5">
            <div class="stat-card-header">
              <span class="stat-label">Overweight</span>
              <div class="stat-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6" /><polyline points="17 18 23 18 23 12" /></svg></div>
            </div>
            <div class="stat-value"><?= $statusCounts['Overweight'] ?></div>
            <div class="stat-change neutral"><?= $statusCounts['Obese'] ?> obese</div>
          </div>
          <div class="stat-card animate-in delay-6">
            <div class="stat-card-header">
              <span class="stat-label">Attendance Rate</span>
              <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" /></svg></div>
            </div>
            <div class="stat-value"><?= $attendanceRate ?>%</div>
            <div class="stat-change positive">Last 7 days</div>
          </div>
        </div>

        <div class="quick-actions animate-in delay-3">
          <div class="quick-actions-header">
            <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9" /><polyline points="9 21 3 21 3 15" /><line x1="21" y1="3" x2="14" y2="10" /><line x1="3" y1="21" x2="10" y2="14" /></svg>Quick Actions</h3>
          </div>
          <div class="quick-actions-grid">
            <a class="quick-action-btn" href="student_profile.php?add=1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>Add Student</a>
            <a class="quick-action-btn" href="measurement.php?add=1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" /></svg>Encode Measurement</a>
            <a class="quick-action-btn" href="feeding_log.php?date=<?= date('Y-m-d') ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" /><rect x="8" y="2" width="8" height="4" rx="1" ry="1" /></svg>Record Attendance</a>
            <a class="quick-action-btn" href="report.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" /></svg>Generate Report</a>
          </div>
        </div>

        <div class="charts-grid">
          <div class="chart-card animate-in delay-4">
            <h3>Nutritional Status Distribution</h3>
            <div class="chart-container"><div class="pie-chart-container"><canvas id="pieChart"></canvas></div></div>
            <div class="pie-legend">
              <?php foreach ($chartData['status']['labels'] as $index => $label): ?>
                <div class="pie-legend-item"><div class="pie-legend-dot" style="background: <?= $chartData['status']['colors'][$index] ?>"></div><?= htmlspecialchars($label) ?></div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="chart-card animate-in delay-5">
            <h3>Baseline vs Endline Progress</h3>
            <div class="chart-container"><canvas id="barChart" height="200"></canvas></div>
            <div class="bar-legend">
              <div class="bar-legend-item"><div class="bar-legend-dot" style="background: #3b82f6"></div>Baseline</div>
              <div class="bar-legend-item"><div class="bar-legend-dot" style="background: #10b981"></div>Endline</div>
            </div>
          </div>
        </div>

        <div class="trend-chart animate-in delay-5">
          <h3>Underweight Trend</h3>
          <div class="trend-container"><canvas id="trendChart"></canvas></div>
        </div>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="recent-activities animate-in delay-6">
          <h3>Recent Activities</h3>
          <div class="activity-list">
            <?php if (empty($activityRows)): ?>
              <div class="empty-state">No recent activities yet.</div>
            <?php else: ?>
              <?php foreach ($activityRows as $activity): ?>
                <?php
                  $dot = match ($activity['table_name']) {
                    'students' => 'blue',
                    'measurements' => 'green',
                    default => 'purple',
                  };
                ?>
                <div class="activity-item">
                  <div class="activity-dot <?= $dot ?>"></div>
                  <div class="activity-content">
                    <div class="activity-text">
                      <strong><?= htmlspecialchars($activity['full_name'] ?? 'System') ?></strong>
                      - <?= htmlspecialchars($activity['description'] ?: ucfirst($activity['action']) . ' ' . $activity['table_name']) ?>
                    </div>
                    <div class="activity-time"><?= htmlspecialchars(timeAgo($activity['created_at'] ?? null)) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </main>
    </div>

    <script>
      const dashboardData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

      function sizeCanvas(canvas) {
        const rect = canvas.parentElement.getBoundingClientRect();
        canvas.width = Math.max(280, rect.width) * window.devicePixelRatio;
        canvas.height = Math.max(180, rect.height || 220) * window.devicePixelRatio;
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        return canvas.getContext('2d');
      }

      function drawEmpty(ctx, canvas, text = 'No data yet') {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#9ca3af';
        ctx.font = `${13 * window.devicePixelRatio}px Inter, sans-serif`;
        ctx.textAlign = 'center';
        ctx.fillText(text, canvas.width / 2, canvas.height / 2);
      }

      function drawPie() {
        const canvas = document.getElementById('pieChart');
        const ctx = sizeCanvas(canvas);
        const values = dashboardData.status.values;
        const total = values.reduce((sum, value) => sum + value, 0);
        if (!total) return drawEmpty(ctx, canvas);

        const cx = canvas.width / 2;
        const cy = canvas.height / 2;
        const radius = Math.min(cx, cy) - 10 * window.devicePixelRatio;
        let start = -Math.PI / 2;
        values.forEach((value, index) => {
          const slice = (value / total) * Math.PI * 2;
          ctx.beginPath();
          ctx.moveTo(cx, cy);
          ctx.arc(cx, cy, radius, start, start + slice);
          ctx.closePath();
          ctx.fillStyle = dashboardData.status.colors[index];
          ctx.fill();
          start += slice;
        });
        ctx.beginPath();
        ctx.arc(cx, cy, radius * 0.58, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.fillStyle = '#1f2937';
        ctx.font = `${22 * window.devicePixelRatio}px Inter, sans-serif`;
        ctx.textAlign = 'center';
        ctx.fillText(total, cx, cy + 7 * window.devicePixelRatio);
      }

      function drawBars() {
        const canvas = document.getElementById('barChart');
        const ctx = sizeCanvas(canvas);
        const labels = dashboardData.progress.labels;
        const baseline = dashboardData.progress.baseline;
        const endline = dashboardData.progress.endline;
        const max = Math.max(...baseline, ...endline, 1);
        const pad = 34 * window.devicePixelRatio;
        const gap = 18 * window.devicePixelRatio;
        const groupWidth = (canvas.width - pad * 2) / labels.length;
        const barWidth = Math.max(12 * window.devicePixelRatio, (groupWidth - gap) / 3);

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#e5e7eb';
        ctx.fillStyle = '#6b7280';
        ctx.font = `${10 * window.devicePixelRatio}px Inter, sans-serif`;
        ctx.textAlign = 'center';

        labels.forEach((label, index) => {
          const x = pad + groupWidth * index + groupWidth / 2;
          const bHeight = (baseline[index] / max) * (canvas.height - pad * 2);
          const eHeight = (endline[index] / max) * (canvas.height - pad * 2);
          ctx.fillStyle = '#3b82f6';
          ctx.fillRect(x - barWidth - 2 * window.devicePixelRatio, canvas.height - pad - bHeight, barWidth, bHeight);
          ctx.fillStyle = '#10b981';
          ctx.fillRect(x + 2 * window.devicePixelRatio, canvas.height - pad - eHeight, barWidth, eHeight);
          ctx.fillStyle = '#6b7280';
          ctx.fillText(label, x, canvas.height - 10 * window.devicePixelRatio);
        });
      }

      function drawTrend() {
        const canvas = document.getElementById('trendChart');
        const ctx = sizeCanvas(canvas);
        const labels = dashboardData.trend.labels;
        const values = dashboardData.trend.values;
        if (!values.length) return drawEmpty(ctx, canvas);

        const max = Math.max(...values, 1);
        const pad = 34 * window.devicePixelRatio;
        const width = canvas.width - pad * 2;
        const height = canvas.height - pad * 2;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#e5e7eb';
        ctx.beginPath();
        ctx.moveTo(pad, pad);
        ctx.lineTo(pad, canvas.height - pad);
        ctx.lineTo(canvas.width - pad, canvas.height - pad);
        ctx.stroke();

        const points = values.map((value, index) => ({
          x: pad + (labels.length === 1 ? width / 2 : (width / (labels.length - 1)) * index),
          y: canvas.height - pad - (value / max) * height,
        }));
        ctx.beginPath();
        points.forEach((point, index) => index ? ctx.lineTo(point.x, point.y) : ctx.moveTo(point.x, point.y));
        ctx.strokeStyle = '#ef4444';
        ctx.lineWidth = 3 * window.devicePixelRatio;
        ctx.stroke();

        ctx.fillStyle = '#ef4444';
        points.forEach(point => {
          ctx.beginPath();
          ctx.arc(point.x, point.y, 4 * window.devicePixelRatio, 0, Math.PI * 2);
          ctx.fill();
        });

        ctx.fillStyle = '#6b7280';
        ctx.font = `${10 * window.devicePixelRatio}px Inter, sans-serif`;
        ctx.textAlign = 'center';
        labels.forEach((label, index) => ctx.fillText(label, points[index].x, canvas.height - 9 * window.devicePixelRatio));
      }

      function drawDashboardCharts() {
        drawPie();
        drawBars();
        drawTrend();
      }

      window.addEventListener('load', drawDashboardCharts);
      window.addEventListener('resize', drawDashboardCharts);
    </script>
  </body>
</html>
