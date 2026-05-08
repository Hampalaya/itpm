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
    <title>FEED System - Feeding Monitoring</title>
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/feeding_log.css" />
    
    <!-- External Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
  </head>
  <body>
    <div class="app-container">
      <!-- Top Navigation -->
      <nav class="top-nav">
        <div class="top-nav-left">
          <div class="nav-logo">FE</div>
          <div class="nav-brand">
            <a class="nav-link">Dashboard</a>
            <a class="nav-link">Students</a>
            <a class="nav-link">Records</a>
            <a class="nav-link active">Feeding Log</a>
            <a class="nav-link">Reports</a>
            <a class="nav-link">Settings</a>
          </div>
        </div>
        <div class="top-nav-right">
          <button class="icon-btn" title="Notifications">
            <svg
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <span class="notification-badge"></span>
          </button>
          <button class="icon-btn" title="Help">
            <svg
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              viewBox="0 0 24 24"
            >
              <circle cx="12" cy="12" r="10" />
              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
          </button>
          <div class="user-pill">
            <div class="user-avatar-sm">RB</div>
            <span class="user-pill-text">Regie B.</span>
          </div>
        </div>
      </nav>

      <!-- Page Content -->
      <div class="page-content">
        <!-- Page Header -->
        <div class="page-header">
          <h1>Feeding Log</h1>
          <p>
            Track daily attendance and meal distribution for the School-Based
            Feeding Program
          </p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-label">Total Present</span>
              <div class="stat-icon green">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                  <polyline points="22,4 12,14.01 9,11.01" />
                </svg>
              </div>
            </div>
            <div class="stat-value" id="statPresent">0</div>
            <div class="stat-change positive">
              <svg
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <polyline points="23,6 13.5,15.5 8.5,10.5 1,18" />
              </svg>
              <span>Today's attendance</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-label">Total Absent</span>
              <div class="stat-icon red">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <circle cx="12" cy="12" r="10" />
                  <line x1="15" y1="9" x2="9" y2="15" />
                  <line x1="9" y1="9" x2="15" y2="18" />
                </svg>
              </div>
            </div>
            <div class="stat-value" id="statAbsent">0</div>
            <div class="stat-change negative">
              <svg
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <polyline points="23,18 13.5,8.5 8.5,13.5 1,6" />
              </svg>
              <span>Absent students</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-label">Meals Served</span>
              <div class="stat-icon teal">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                  <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                  <line x1="6" y1="1" x2="6" y2="4" />
                  <line x1="10" y1="1" x2="10" y2="4" />
                  <line x1="14" y1="1" x2="14" y2="4" />
                </svg>
              </div>
            </div>
            <div class="stat-value" id="statMeals">0</div>
            <div class="stat-change positive">
              <svg
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <polyline points="23,6 13.5,15.5 8.5,10.5 1,18" />
              </svg>
              <span>Meals distributed</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-header">
              <span class="stat-label">Attendance Rate</span>
              <div class="stat-icon blue">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <line x1="18" y1="20" x2="18" y2="10" />
                  <line x1="12" y1="20" x2="12" y2="4" />
                  <line x1="6" y1="20" x2="6" y2="14" />
                </svg>
              </div>
            </div>
            <div class="stat-value" id="statRate">0%</div>
            <div class="stat-change positive">
              <svg
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <polyline points="23,6 13.5,15.5 8.5,10.5 1,18" />
              </svg>
              <span>Overall rate</span>
            </div>
          </div>
        </div>

        <!-- Main Card -->
        <div class="card">
          <div class="card-header">
            <div class="card-header-left">
              <div>
                <div class="card-title" id="attendanceTitle">
                  Attendance Sheet
                </div>
                <div class="card-subtitle" id="attendanceSubtitle">
                  Select a date to view records
                </div>
              </div>
            </div>
            <div style="display: flex; gap: 8px">
              <button class="btn btn-secondary btn-sm">
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
                Export
              </button>
              <button class="btn btn-success btn-sm">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <path
                    d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"
                  />
                  <polyline points="17,21 17,13 7,13 7,21" />
                  <polyline points="7,3 7,8 15,8" />
                </svg>
                Save Attendance
              </button>
            </div>
          </div>

          <!-- Controls -->
          <div class="controls-bar">
            <div class="controls-left">
              <div class="date-picker-wrapper">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                  <line x1="16" y1="2" x2="16" y2="6" />
                  <line x1="8" y1="2" x2="8" y2="6" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                </svg>
                <input
                  type="date"
                  class="date-input"
                  id="datePicker"
                  value="2026-03-18"
                />
              </div>
              <div class="filter-group">
                <button class="filter-chip active">All</button>
                <button class="filter-chip">Present</button>
                <button class="filter-chip">Absent</button>
                <button class="filter-chip">No Meal</button>
              </div>
            </div>
            <div class="controls-right">
              <div class="search-wrapper">
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
                  class="search-input"
                  placeholder="Search students..."
                />
              </div>
            </div>
          </div>

          <!-- Table -->
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th style="width: 60px">#</th>
                  <th>Student</th>
                  <th>Grade/Section</th>
                  <th class="center">Present</th>
                  <th class="center">Meal</th>
                  <th>Status</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody id="studentTableBody">
                <!-- Table rows would be populated by JavaScript -->
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="pagination">
            <div class="pagination-info">
              Showing <span id="showingStart">1</span> to
              <span id="showingEnd">10</span> of
              <span id="totalRecords">12</span> entries
            </div>
            <div class="pagination-controls">
              <button class="page-btn" id="prevBtn" disabled>
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <polyline points="15,18 9,12 15,6" />
                </svg>
              </button>
              <button class="page-btn active" id="page1">1</button>
              <button class="page-btn" id="page2" style="display: none">2</button>
              <button class="page-btn" id="nextBtn">
                <svg
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  viewBox="0 0 24 24"
                >
                  <polyline points="9,18 15,12 9,6" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
  </body>
</html>