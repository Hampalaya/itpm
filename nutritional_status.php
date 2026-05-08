<?php
// 1. START SESSION AT THE VERY TOP
session_start();
// Optional: Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FEED System - Nutritional Status</title>
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/nutritional_status.css" />
    <link rel="stylesheet" href="css/sidebar.css" />
    
    <!-- External Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <script src="js/sidebar.js" defer></script>
  </head>
  <body>
    <div class="app-container">
      <!-- Navbar removed as requested -->
       <?php include 'includes/sidebar.php'; ?>

      <!-- Page Content -->
      <div class="page-content">
        <!-- Page Header -->
        <div class="page-header">
          <div>
            <h1>Nutritional Status</h1>
            <p>Track student nutritional progress and improvements</p>
          </div>
          <button class="btn btn-secondary">
            <svg
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7,10 12,15 17,10" />
              <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            Export Report
          </button>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
          <div class="stat-card blue">
            <div class="stat-label">Total Monitored</div>
            <div class="stat-value-row">
              <div class="stat-value" id="statTotal">0</div>
              <div class="stat-icon-wrapper blue">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                  <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
              </div>
            </div>
          </div>
          <div class="stat-card green">
            <div class="stat-label">Improved</div>
            <div class="stat-value-row">
              <div class="stat-value" id="statImproved">0</div>
              <div class="stat-icon-wrapper green">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <polyline points="23,6 13.5,15.5 8.5,10.5 1,18" />
                  <polyline points="17,6 23,6 23,12" />
                </svg>
              </div>
            </div>
          </div>
          <div class="stat-card gray">
            <div class="stat-label">Maintained</div>
            <div class="stat-value-row">
              <div class="stat-value" id="statMaintained">0</div>
              <div class="stat-icon-wrapper gray">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
              </div>
            </div>
          </div>
          <div class="stat-card red">
            <div class="stat-label">Declined</div>
            <div class="stat-value-row">
              <div class="stat-value" id="statDeclined">0</div>
              <div class="stat-icon-wrapper red">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <polyline points="23,18 13.5,8.5 8.5,13.5 1,6" />
                  <polyline points="17,18 23,18 23,12" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 24px">
          <div class="filter-bar" style="border-bottom: none">
            <div class="filter-field">
              <div class="filter-label">Search Student</div>
              <div class="filter-input-wrapper">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <circle cx="11" cy="11" r="8" />
                  <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input
                  type="text"
                  class="filter-input"
                  placeholder="Name or ID..."
                />
              </div>
            </div>
            <div class="filter-field">
              <div class="filter-label">Current Status</div>
              <select class="filter-select">
                <option value="">All Statuses</option>
                <option value="underweight">Underweight</option>
                <option value="normal">Normal</option>
                <option value="overweight">Overweight</option>
                <option value="obese">Obese</option>
              </select>
            </div>
            <div class="filter-field">
              <div class="filter-label">Progress</div>
              <select class="filter-select">
                <option value="">All Progress</option>
                <option value="improved">Improved</option>
                <option value="maintained">Maintained</option>
                <option value="declined">Declined</option>
              </select>
            </div>
          </div>
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
                <!-- Table rows would be populated by JavaScript -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Detail Modal -->
    <div class="modal-overlay" id="detailModal">
      <div class="modal">
        <div class="modal-header">
          <div class="modal-title">Student Nutritional Details</div>
          <button class="modal-close">
            <svg
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>
        <div class="modal-body" id="modalBody"></div>
      </div>
    </div>

    <!-- Optional: Add your JavaScript here for nutritional status logic -->
    <script src="js/nutritional_status.js"></script>
  </body>
</html>