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
    <title>FEED System - Data Validation</title>
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/data_validation.css" />
    
    <!-- External Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
  </head>
  <body>
    <div class="app-container">
      <!-- Navbar removed as requested -->

      <div class="page-content">
        <div class="page-header">
          <div>
            <h1>Data Validation</h1>
            <p>Identify and resolve data quality issues</p>
          </div>
          <button class="btn btn-primary" id="runValidationBtn">
            <svg
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path d="M21 2v6h-6" />
              <path d="M3 12a9 9 0 0 1 15-6.7L21 8" />
              <path d="M3 22v-6h6" />
              <path d="M21 12a9 9 0 0 1-15 6.7L3 16" />
            </svg>
            Run Validation
          </button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
          <div class="stat-card blue animate-in" style="animation-delay: 0.05s">
            <div class="stat-label">Total Issues</div>
            <div class="stat-value" id="statTotal">0</div>
          </div>
          <div class="stat-card red animate-in" style="animation-delay: 0.1s">
            <div class="stat-label">High Priority</div>
            <div class="stat-value" id="statHigh">0</div>
          </div>
          <div class="stat-card orange animate-in" style="animation-delay: 0.15s">
            <div class="stat-label">Medium Priority</div>
            <div class="stat-value" id="statMedium">0</div>
          </div>
          <div class="stat-card green animate-in" style="animation-delay: 0.2s">
            <div class="stat-label">Low Priority</div>
            <div class="stat-value" id="statLow">0</div>
          </div>
        </div>

        <!-- Validation Results -->
        <div class="card">
          <div class="card-header">
            <span class="card-title">Validation Results</span>
          </div>
          <div class="card-body" id="validationResults">
            <div class="empty-state">
              <svg
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                viewBox="0 0 24 24"
              >
                <path d="M9 12l2 2 4-4" />
                <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
              </svg>
              <h3>All Clear!</h3>
              <p>No data quality issues found</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <!-- Optional: Add your JavaScript here for validation logic -->
    <script src="js/data_validation.js"></script>
  </body>
</html>