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
    <title>FEED System - Student Profiles</title>
    
    <!-- External CSS -->
    <link rel="stylesheet" href="css/sidebar.css" />
    <link rel="stylesheet" href="css/student_profile.css" />
    
    <!-- External Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <script src="js/sidebar.js" defer></script>
  </head>
  <body>
    <div class="app-container">
      <!-- Sidebar -->
      <?php include 'includes/sidebar.php'; ?>

      <!-- Main Content -->
      <main class="main-content main-content-wrapper" id="mainWrapper">
        <!-- Page Header -->
        <div class="page-header">
          <h1 class="page-title">Student Profiles</h1>
          <p class="page-subtitle">Manage student information and beneficiary status</p>
        </div>

        <!-- Header Actions -->
        <div class="header-actions">
          <button class="btn btn-secondary">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Export
          </button>
          <button class="btn btn-primary">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <line x1="12" y1="5" x2="12" y2="19"/>
              <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Student
          </button>
        </div>

        <!-- Search & Filters -->
        <div class="controls-bar">
          <div class="search-wrapper">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"/>
              <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" class="search-input" placeholder="Search by name, Student ID, or LRN..." />
          </div>
          <button class="filter-btn">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            Show Filters
          </button>
        </div>

        <!-- Results Info -->
        <div class="results-info">Showing 8 of 8 students</div>

        <!-- Student Table -->
        <div class="table-card">
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Student ID</th>
                  <th>LRN</th>
                  <th>Full Name</th>
                  <th>Grade/Section</th>
                  <th>Sex</th>
                  <th>Status</th>
                  <th class="right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <!-- Student 1 -->
                <tr>
                  <td>STU001</td>
                  <td>123456789012</td>
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar">J</div>
                      <div class="student-info">
                        <span class="student-name">Juan Dela Cruz</span>
                        <span class="allergy-badge">
                          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                          </svg>
                          Has allergies
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>Grade 1 / Section A</td>
                  <td>Male</td>
                  <td><span class="status-badge beneficiary">Beneficiary</span></td>
                  <td class="right">
                    <div class="action-buttons">
                      <button class="action-btn edit">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Edit
                      </button>
                      <button class="action-btn delete">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <polyline points="3 6 5 6 21 6"/>
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>

                <!-- Student 2 -->
                <tr>
                  <td>STU002</td>
                  <td>123456789013</td>
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar">M</div>
                      <div class="student-info">
                        <span class="student-name">Maria Santos</span>
                      </div>
                    </div>
                  </td>
                  <td>Grade 2 / Section B</td>
                  <td>Female</td>
                  <td><span class="status-badge beneficiary">Beneficiary</span></td>
                  <td class="right">
                    <div class="action-buttons">
                      <button class="action-btn edit">Edit</button>
                      <button class="action-btn delete">Delete</button>
                    </div>
                  </td>
                </tr>

                <!-- Student 3 -->
                <tr>
                  <td>STU003</td>
                  <td>123456789014</td>
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar">P</div>
                      <div class="student-info">
                        <span class="student-name">Pedro Reyes</span>
                      </div>
                    </div>
                  </td>
                  <td>Grade 1 / Section A</td>
                  <td>Male</td>
                  <td><span class="status-badge beneficiary">Beneficiary</span></td>
                  <td class="right">
                    <div class="action-buttons">
                      <button class="action-btn edit">Edit</button>
                      <button class="action-btn delete">Delete</button>
                    </div>
                  </td>
                </tr>

                <!-- Student 4 -->
                <tr>
                  <td>STU004</td>
                  <td>123456789015</td>
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar">A</div>
                      <div class="student-info">
                        <span class="student-name">Anna Lopez</span>
                        <span class="allergy-badge">
                          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                          </svg>
                          Has allergies
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>Grade 3 / Section C</td>
                  <td>Female</td>
                  <td><span class="status-badge non-beneficiary">Non-Beneficiary</span></td>
                  <td class="right">
                    <div class="action-buttons">
                      <button class="action-btn edit">Edit</button>
                      <button class="action-btn delete">Delete</button>
                    </div>
                  </td>
                </tr>

                <!-- Student 5 -->
                <tr>
                  <td>STU005</td>
                  <td>123456789016</td>
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar">C</div>
                      <div class="student-info">
                        <span class="student-name">Carlos Garcia</span>
                      </div>
                    </div>
                  </td>
                  <td>Grade 2 / Section A</td>
                  <td>Male</td>
                  <td><span class="status-badge beneficiary">Beneficiary</span></td>
                  <td class="right">
                    <div class="action-buttons">
                      <button class="action-btn edit">Edit</button>
                      <button class="action-btn delete">Delete</button>
                    </div>
                  </td>
                </tr>

                <!-- Student 6 -->
                <tr>
                  <td>STU006</td>
                  <td>123456789017</td>
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar">S</div>
                      <div class="student-info">
                        <span class="student-name">Sofia Martinez</span>
                      </div>
                    </div>
                  </td>
                  <td>Grade 1 / Section B</td>
                  <td>Female</td>
                  <td><span class="status-badge beneficiary">Beneficiary</span></td>
                  <td class="right">
                    <div class="action-buttons">
                      <button class="action-btn edit">Edit</button>
                      <button class="action-btn delete">Delete</button>
                    </div>
                  </td>
                </tr>

                <!-- Student 7 -->
                <tr>
                  <td>STU007</td>
                  <td>123456789018</td>
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar">D</div>
                      <div class="student-info">
                        <span class="student-name">Diego Fernandez</span>
                        <span class="allergy-badge">
                          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                          </svg>
                          Has allergies
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>Grade 3 / Section A</td>
                  <td>Male</td>
                  <td><span class="status-badge beneficiary">Beneficiary</span></td>
                  <td class="right">
                    <div class="action-buttons">
                      <button class="action-btn edit">Edit</button>
                      <button class="action-btn delete">Delete</button>
                    </div>
                  </td>
                </tr>

                <!-- Student 8 -->
                <tr>
                  <td>STU008</td>
                  <td>123456789019</td>
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar">I</div>
                      <div class="student-info">
                        <span class="student-name">Isabella Cruz</span>
                      </div>
                    </div>
                  </td>
                  <td>Grade 2 / Section C</td>
                  <td>Female</td>
                  <td><span class="status-badge beneficiary">Beneficiary</span></td>
                  <td class="right">
                    <div class="action-buttons">
                      <button class="action-btn edit">Edit</button>
                      <button class="action-btn delete">Delete</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
  </body>
</html>