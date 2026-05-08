<?php
// 1. START SESSION AT THE VERY TOP
session_start();
// Optional: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
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
</head>
<body>

  <!-- Sidebar -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content Wrapper -->
  <div class="main-content-wrapper" id="mainWrapper">
    <!-- Main Content -->
    <main class="main-content">

    <!-- Page Header -->
    <div class="page-header">
      <div class="page-title-group">
        <h1 class="page-title">Anthropometric Measurements</h1>
        <p class="page-subtitle">Record and track student measurements</p>
      </div>
      <div class="page-actions">
        <button class="btn-export">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export
        </button>
        <button class="btn-add">
          <svg width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Measurement
        </button>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-cards">
      <div class="stat-card stat-card--blue">
        <p class="stat-label">Total Measurements</p>
        <p class="stat-value">10</p>
      </div>
      <div class="stat-card stat-card--green">
        <p class="stat-label">Baseline</p>
        <p class="stat-value">7</p>
      </div>
      <div class="stat-card stat-card--orange">
        <p class="stat-label">Monthly</p>
        <p class="stat-value">3</p>
      </div>
      <div class="stat-card stat-card--purple">
        <p class="stat-label">Endline</p>
        <p class="stat-value">0</p>
      </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="toolbar">
      <div class="search-wrapper">
        <svg class="search-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" class="search-input" placeholder="Search by student name...">
      </div>
      <button class="btn-filter">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
        Show Filters
      </button>
    </div>

    <!-- Results Count -->
    <p class="results-count">Showing 10 of 10 measurements</p>

    <!-- Data Table -->
    <div class="table-card">
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
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>1/20/2024</td>
            <td class="td-student">Juan Dela Cruz</td>
            <td>Baseline</td>
            <td>18.5</td>
            <td>110.0</td>
            <td>15.30</td>
            <td><span class="badge badge--underweight">Underweight</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
          <tr>
            <td>1/20/2024</td>
            <td class="td-student">Maria Santos</td>
            <td>Baseline</td>
            <td>22.0</td>
            <td>120.0</td>
            <td>15.30</td>
            <td><span class="badge badge--normal">Normal</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
          <tr>
            <td>1/20/2024</td>
            <td class="td-student">Pedro Reyes</td>
            <td>Baseline</td>
            <td>17.8</td>
            <td>108.0</td>
            <td>15.30</td>
            <td><span class="badge badge--underweight">Underweight</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
          <tr>
            <td>1/21/2024</td>
            <td class="td-student">Carlos Garcia</td>
            <td>Baseline</td>
            <td>21.5</td>
            <td>118.0</td>
            <td>15.40</td>
            <td><span class="badge badge--normal">Normal</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
          <tr>
            <td>1/21/2024</td>
            <td class="td-student">Sofia Martinez</td>
            <td>Baseline</td>
            <td>19.2</td>
            <td>112.0</td>
            <td>15.30</td>
            <td><span class="badge badge--normal">Normal</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
          <tr>
            <td>1/21/2024</td>
            <td class="td-student">Diego Fernandez</td>
            <td>Baseline</td>
            <td>26.5</td>
            <td>128.0</td>
            <td>16.20</td>
            <td><span class="badge badge--normal">Normal</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
          <tr>
            <td>1/22/2024</td>
            <td class="td-student">Isabella Cruz</td>
            <td>Baseline</td>
            <td>23.0</td>
            <td>122.0</td>
            <td>15.50</td>
            <td><span class="badge badge--normal">Normal</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
          <tr>
            <td>2/20/2024</td>
            <td class="td-student">Juan Dela Cruz</td>
            <td>Monthly</td>
            <td>19.2</td>
            <td>111.0</td>
            <td>15.60</td>
            <td><span class="badge badge--normal">Normal</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
          <tr>
            <td>2/20/2024</td>
            <td class="td-student">Maria Santos</td>
            <td>Monthly</td>
            <td>22.5</td>
            <td>121.0</td>
            <td>15.40</td>
            <td><span class="badge badge--normal">Normal</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
          <tr>
            <td>2/20/2024</td>
            <td class="td-student">Pedro Reyes</td>
            <td>Monthly</td>
            <td>18.5</td>
            <td>109.0</td>
            <td>15.60</td>
            <td><span class="badge badge--normal">Normal</span></td>
            <td class="td-muted">Admin User</td>
          </tr>
        </tbody>
      </table>
    </div>

  </main>
  </div>
</body>
</html>