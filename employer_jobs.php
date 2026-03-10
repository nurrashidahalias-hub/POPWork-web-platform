<?php
session_start();
include("backend/db_connect.php");

// Ensure only employers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
  header("Location: login.html");
  exit();
}

$employer_id = $_SESSION['user_id'];

// Fetch user info for greeting
$user_sql = "SELECT p.name, u.email FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $employer_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'] ?? explode('@', $user_data['email'])[0];

// Get employer statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT j.job_id) as total_jobs,
    COUNT(DISTINCT a.application_id) as total_applicants,
    COUNT(DISTINCT CASE WHEN a.status = 'Accepted' THEN a.application_id END) as hired_workers,
    COUNT(DISTINCT CASE WHEN a.status = 'Pending' THEN a.application_id END) as pending_applications,
    COUNT(DISTINCT CASE WHEN j.status = 'available' THEN j.job_id END) as active_jobs,
    COUNT(DISTINCT CASE WHEN j.status = 'closed' THEN j.job_id END) as closed_jobs
    FROM jobs j
    LEFT JOIN applications a ON j.job_id = a.job_id
    WHERE j.employer_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $employer_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get jobs by this employer with applicant counts
$sql = "SELECT j.job_id, j.title, j.category, j.status, j.location, j.pay_rate, j.pay_period, j.description, j.created_at, j.positions_available, j.is_urgent,
               COUNT(DISTINCT a.application_id) as applicant_count,
               COUNT(DISTINCT CASE WHEN a.status = 'Pending' THEN a.application_id END) as pending_count,
               COUNT(DISTINCT CASE WHEN a.status = 'Accepted' THEN a.application_id END) as accepted_count,
               COUNT(DISTINCT CASE WHEN a.status = 'Shortlisted' THEN a.application_id END) as shortlisted_count
        FROM jobs j
        LEFT JOIN applications a ON j.job_id = a.job_id
        WHERE j.employer_id = ?
        GROUP BY j.job_id
        ORDER BY j.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Jobs | POP!Work</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/sidebar.css">
  <style>
    :root {
      --primary: #8a1538;
      --primary-dark: #6b1129;
      --primary-light: #c91f4d;
      --text-dark: #1c1e21;
      --text-medium: #4a5568;
      --text-light: #65676b;
      --bg-light: #f5f7fa;
      --border-color: #e1e8ed;
      --white: #ffffff;
      --success: #27ae60;
      --success-bg: #d4edda;
      --danger: #e74c3c;
      --danger-bg: #f8d7da;
      --warning: #f39c12;
      --warning-bg: #fff3cd;
      --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
      --shadow-md: 0 4px 12px rgba(138, 21, 56, 0.15);
      --shadow-lg: 0 8px 20px rgba(138, 21, 56, 0.2);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      background: var(--bg-light);
      min-height: 100vh;
      color: var(--text-dark);
      position: relative;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('uploads/photos/background.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
    }

    .dashboard-wrapper {
      display: flex;
    }

    .main-content {
      flex: 1;
      margin-left: 280px;
      width: calc(100% - 280px);
      min-height: 100vh;
      background: rgba(245, 247, 250, 0.95);
    }

    /* Header */
    .dashboard-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.5);
      padding: 24px 40px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: var(--shadow-sm);
    }

    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 1600px;
      margin: 0 auto;
    }

    .header-title h1 {
      font-size: 28px;
      color: var(--white);
      margin-bottom: 4px;
      font-weight: 700;
    }

    .header-title p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 14px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
      padding: 11px 24px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.25);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(138, 21, 56, 0.35);
    }

    /* Stats Section */
    .stats-section {
      padding: 20px 40px;
      backdrop-filter: blur(10px);
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 16px;
      max-width: 1600px;
      margin: 0 auto;
    }

    .stat-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 12px;
      padding: 16px;
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--primary), var(--primary-light));
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(138, 21, 56, 0.15);
      border-color: var(--primary);
    }

    .stat-card:hover::before {
      transform: scaleX(1);
    }

    .stat-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: var(--white);
      flex-shrink: 0;
      margin-bottom: 12px;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.2);
    }

    .stat-content {
      flex: 1;
    }

    .stat-value {
      font-size: 24px;
      font-weight: 800;
      color: var(--text-dark);
      line-height: 1;
      margin-bottom: 6px;
      letter-spacing: -0.5px;
    }

    .stat-label {
      font-size: 11px;
      color: var(--text-light);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      line-height: 1.3;
    }

    /* Content Section */
    .content-section {
      padding: 32px 40px;
      max-width: 1600px;
      margin: 0 auto;
    }

    /* Filter Bar */
    .filter-bar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 20px 24px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }
    .filter-bar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 20px 24px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }
    .filter-bar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 20px 24px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }
    .filter-bar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 20px 24px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }
    .filter-bar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 20px 24px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }
    .filter-bar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 20px 24px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }
    .filter-bar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 20px 24px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }
    .filter-bar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 20px 24px;
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .filter-controls {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      align-items: center;
    }

    .search-box {
      flex: 1;
      min-width: 250px;
      position: relative;
    }

    .search-box i {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
    }

    .search-input {
      width: 100%;
      padding: 11px 16px 11px 44px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 14px;
      transition: all 0.3s;
    }

    .search-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
    }

    .filter-select {
      padding: 11px 16px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 14px;
      background: var(--white);
      cursor: pointer;
      transition: all 0.3s;
      min-width: 150px;
    }

    .filter-select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
    }

    .showing-text {
      color: var(--text-medium);
      font-size: 14px;
      font-weight: 600;
      white-space: nowrap;
    }

    /* Table Container */
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
      overflow: hidden;
    }
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
      overflow: hidden;
    }
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
      overflow: hidden;
    }
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
      overflow: hidden;
    }
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
      overflow: hidden;
    }
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
      overflow: hidden;
    }
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(255, 255, 255, 0.5);
      overflow: hidden;
    }

    .table-wrapper {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
    }

    th {
      padding: 16px 20px;
      text-align: left;
      font-weight: 700;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      white-space: nowrap;
    }

    tbody tr {
      border-bottom: 1px solid var(--border-color);
      transition: all 0.2s;
    }

    tbody tr:hover {
      background: #fafbfc;
    }

    tbody tr:last-child {
      border-bottom: none;
    }

    td {
      padding: 20px;
      vertical-align: middle;
      font-size: 14px;
    }

    /* Job Info Cell */
    .job-info-cell {
      min-width: 280px;
    }

    .job-title-row {
      font-weight: 700;
      font-size: 15px;
      color: var(--text-dark);
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .urgent-badge {
      background: linear-gradient(135deg, #ff6b6b, #ff8787);
      color: var(--white);
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .job-meta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 6px;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 12px;
      color: var(--text-light);
    }

    .meta-item i {
      color: var(--primary);
      font-size: 11px;
    }

    .category-badge {
      background: #e8f5e9;
      color: #2e7d32;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    /* Pay Rate Cell */
    .pay-cell {
      text-align: center;
      min-width: 140px;
    }

    .pay-amount {
      font-size: 18px;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 2px;
    }

    .pay-period {
      font-size: 11px;
      color: var(--text-light);
      text-transform: uppercase;
      font-weight: 600;
    }

    /* Applicants Cell */
    .applicants-cell {
      min-width: 140px;
    }

    .applicant-stats {
      display: flex;
      gap: 16px;
    }

    .applicant-stat {
      text-align: center;
    }

    .applicant-number {
      font-size: 20px;
      font-weight: 800;
      color: var(--text-dark);
      line-height: 1;
    }

    .applicant-label {
      font-size: 10px;
      color: var(--text-light);
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.3px;
      margin-top: 3px;
    }

    /* Status Cell */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      white-space: nowrap;
    }

    .status-badge.available {
      background: var(--success-bg);
      color: var(--success);
      border: 1px solid #c3e6cb;
    }

    .status-badge.available:hover {
      background: var(--success);
      color: var(--white);
    }

    .status-badge.closed {
      background: var(--danger-bg);
      color: var(--danger);
      border: 1px solid #f5c6cb;
    }

    .status-badge.closed:hover {
      background: var(--danger);
      color: var(--white);
    }

    /* Date Cell */
    .date-cell {
      font-size: 13px;
      color: var(--text-medium);
      white-space: nowrap;
    }

    .date-main {
      font-weight: 600;
      color: var(--text-dark);
    }

    .date-time {
      font-size: 11px;
      color: var(--text-light);
      margin-top: 2px;
    }

    /* Actions Cell */
    .actions-cell {
      min-width: 180px;
    }

    .action-buttons {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .btn-action {
      padding: 9px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      text-decoration: none;
      white-space: nowrap;
    }

    .btn-view {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
    }

    .btn-view:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(138, 21, 56, 0.3);
    }

    .btn-edit {
      background: var(--white);
      color: var(--text-dark);
      border: 1px solid var(--border-color);
    }

    .btn-edit:hover {
      background: var(--bg-light);
      border-color: var(--text-medium);
    }

    .btn-applicants {
      background: #e3f2fd;
      color: #1565c0;
      border: 1px solid #90caf9;
    }

    .btn-applicants:hover {
      background: #1565c0;
      color: var(--white);
    }

    .btn-delete {
      background: #ffebee;
      color: #c62828;
      border: 1px solid #ef9a9a;
    }

    .btn-delete:hover {
      background: #c62828;
      color: var(--white);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 80px 40px;
    }

    .empty-icon {
      font-size: 64px;
      color: var(--border-color);
      margin-bottom: 20px;
    }

    .empty-state h3 {
      font-size: 24px;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 8px;
    }

    .empty-state p {
      color: var(--text-light);
      font-size: 15px;
      margin-bottom: 24px;
    }

    /* Alert Messages */
    .alert {
      padding: 14px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      font-weight: 500;
    }

    .alert i {
      font-size: 18px;
    }

    .alert-success {
      background: var(--success-bg);
      color: var(--success);
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background: var(--danger-bg);
      color: var(--danger);
      border: 1px solid #f5c6cb;
    }

    .alert-info {
      background: #d1ecf1;
      color: #0c5460;
      border: 1px solid #bee5eb;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
        width: 100%;
      }

      .dashboard-header,
      .stats-section,
      .content-section {
        padding-left: 20px;
        padding-right: 20px;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .filter-controls {
        flex-direction: column;
        align-items: stretch;
      }

      .search-box,
      .filter-select {
        width: 100%;
      }
    }

    @media (max-width: 768px) {
      .header-content {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }

      .table-wrapper {
        overflow-x: scroll;
      }

      table {
        min-width: 1200px;
      }
    }
  </style>
</head>
<body>
  <div class="dashboard-wrapper">
    <?php include("includes/sidebar.php"); ?>

    <div class="main-content">
      <!-- Header -->
      <div class="dashboard-header">
        <div class="header-content">
          <div class="header-title">
            <h1>Manage Job Listings</h1>
            <p>Welcome back, <?= htmlspecialchars($user_name); ?>! Manage your job postings and track applications.</p>
          </div>
          <a href="job_post.php" class="btn-primary">
            <i class="fas fa-plus"></i> Post New Job
          </a>
        </div>
      </div>

      <!-- Stats Section -->
      <div class="stats-section">
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-briefcase"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value"><?= number_format($stats['total_jobs'] ?? 0); ?></div>
              <div class="stat-label">Total Jobs</div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value"><?= number_format($stats['active_jobs'] ?? 0); ?></div>
              <div class="stat-label">Active Jobs</div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value"><?= number_format($stats['total_applicants'] ?? 0); ?></div>
              <div class="stat-label">Total Applicants</div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value"><?= number_format($stats['hired_workers'] ?? 0); ?></div>
              <div class="stat-label">Hired Workers</div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value"><?= number_format($stats['pending_applications'] ?? 0); ?></div>
              <div class="stat-label">Pending Reviews</div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
              <div class="stat-value"><?= number_format($stats['closed_jobs'] ?? 0); ?></div>
              <div class="stat-label">Closed Jobs</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Section -->
      <div class="content-section">
        <!-- Alert Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i>
          <?= htmlspecialchars($_GET['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <?= htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
          <div class="filter-controls">
            <div class="search-box">
              <i class="fas fa-search"></i>
              <input 
                type="text" 
                class="search-input" 
                id="searchInput" 
                placeholder="Search by job title, location..."
              >
            </div>
            <select class="filter-select" id="statusFilter">
              <option value="">All Status</option>
              <option value="available">Active</option>
              <option value="closed">Closed</option>
            </select>
            <select class="filter-select" id="categoryFilter">
              <option value="">All Categories</option>
              <option value="Gig">Gig</option>
              <option value="Part-Time">Part-Time</option>
              <option value="Full-Time">Full-Time</option>
            </select>
            <div class="showing-text" id="showingText">
              Showing <?= $result->num_rows; ?> job(s)
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="table-container">
          <div class="table-wrapper">
            <?php if ($result->num_rows > 0): ?>
              <table id="jobsTable">
                <thead>
                  <tr>
                    <th>Job Details</th>
                    <th style="text-align: center;">Pay Rate</th>
                    <th style="text-align: center;">Applicants</th>
                    <th style="text-align: center;">Status</th>
                    <th>Posted Date</th>
                    <th style="text-align: center;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $result->fetch_assoc()): ?>
                  <tr class="job-row" 
                      data-status="<?= htmlspecialchars($row['status']); ?>" 
                      data-title="<?= htmlspecialchars($row['title']); ?>"
                      data-location="<?= htmlspecialchars($row['location']); ?>"
                      data-category="<?= htmlspecialchars($row['category']); ?>">
                    
                    <!-- Job Details -->
                    <td class="job-info-cell">
                      <div class="job-title-row">
                        <?= htmlspecialchars($row['title']); ?>
                        <?php if ($row['is_urgent']): ?>
                          <span class="urgent-badge">
                            <i class="fas fa-fire"></i> URGENT
                          </span>
                        <?php endif; ?>
                      </div>
                      <div class="job-meta-row">
                        <span class="category-badge"><?= htmlspecialchars($row['category']); ?></span>
                        <span class="meta-item">
                          <i class="fas fa-map-marker-alt"></i>
                          <?= htmlspecialchars($row['location']); ?>
                        </span>
                        <span class="meta-item">
                          <i class="fas fa-users"></i>
                          <?= $row['positions_available']; ?> position(s)
                        </span>
                      </div>
                    </td>

                    <!-- Pay Rate -->
                    <td class="pay-cell">
                      <div class="pay-amount">RM <?= number_format($row['pay_rate'], 2); ?></div>
                      <div class="pay-period">per <?= htmlspecialchars($row['pay_period']); ?></div>
                    </td>

                    <!-- Applicants -->
                    <td class="applicants-cell">
                      <div class="applicant-stats">
                        <div class="applicant-stat">
                          <div class="applicant-number"><?= $row['applicant_count']; ?></div>
                          <div class="applicant-label">Total</div>
                        </div>
                        <div class="applicant-stat">
                          <div class="applicant-number"><?= $row['pending_count']; ?></div>
                          <div class="applicant-label">Pending</div>
                        </div>
                        <div class="applicant-stat">
                          <div class="applicant-number"><?= $row['accepted_count']; ?></div>
                          <div class="applicant-label">Hired</div>
                        </div>
                      </div>
                    </td>

                    <!-- Status -->
                    <td style="text-align: center;">
                      <span class="status-badge <?= $row['status']; ?>" 
                            onclick="toggleStatus(<?= $row['job_id']; ?>, '<?= $row['status']; ?>')">
                        <?php if ($row['status'] === 'available'): ?>
                          <i class="fas fa-check-circle"></i> Active
                        <?php else: ?>
                          <i class="fas fa-times-circle"></i> Closed
                        <?php endif; ?>
                      </span>
                    </td>

                    <!-- Date -->
                    <td class="date-cell">
                      <div class="date-main"><?= date('M d, Y', strtotime($row['created_at'])); ?></div>
                      <div class="date-time"><?= date('g:i A', strtotime($row['created_at'])); ?></div>
                    </td>

                    <!-- Actions -->
                    <td class="actions-cell">
                      <div class="action-buttons">
                        <a href="view_job.php?job_id=<?= $row['job_id']; ?>" class="btn-action btn-view">
                          <i class="fas fa-eye"></i> View Details
                        </a>
                        <a href="backend/view_applicants.php?job_id=<?= $row['job_id']; ?>" class="btn-action btn-applicants">
                          <i class="fas fa-users"></i> Applicants (<?= $row['applicant_count']; ?>)
                        </a>
                        <a href="edit_job.php?job_id=<?= $row['job_id']; ?>" class="btn-action btn-edit">
                          <i class="fas fa-edit"></i> Edit Job
                        </a>
                        <form action="backend/delete_job.php" method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this job? This action cannot be undone.');" 
                              style="margin: 0;">
                          <input type="hidden" name="job_id" value="<?= $row['job_id']; ?>">
                          <button type="submit" class="btn-action btn-delete" style="width: 100%;">
                            <i class="fas fa-trash"></i> Delete
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="empty-state">
                <div class="empty-icon">
                  <i class="fas fa-briefcase"></i>
                </div>
                <h3>No Job Listings Yet</h3>
                <p>Start posting jobs to connect with talented workers</p>
                <a href="job_post.php" class="btn-primary">
                  <i class="fas fa-plus"></i> Post Your First Job
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(() => {
          alert.style.transition = 'opacity 0.5s, transform 0.5s';
          alert.style.opacity = '0';
          alert.style.transform = 'translateY(-20px)';
          setTimeout(() => alert.remove(), 500);
        }, 5000);
      });
    });

    // Search and filter functionality
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const jobsTable = document.getElementById('jobsTable');
    const showingText = document.getElementById('showingText');
    const jobRows = jobsTable ? jobsTable.querySelectorAll('.job-row') : [];

    function filterJobs() {
      const searchTerm = searchInput.value.toLowerCase();
      const selectedStatus = statusFilter.value;
      const selectedCategory = categoryFilter.value;
      let visibleCount = 0;

      jobRows.forEach(row => {
        const title = row.dataset.title.toLowerCase();
        const location = row.dataset.location.toLowerCase();
        const status = row.dataset.status;
        const category = row.dataset.category;

        const matchesSearch = title.includes(searchTerm) || location.includes(searchTerm);
        const matchesStatus = !selectedStatus || status === selectedStatus;
        const matchesCategory = !selectedCategory || category === selectedCategory;

        if (matchesSearch && matchesStatus && matchesCategory) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      showingText.textContent = `Showing ${visibleCount} job(s)`;
    }

    if (searchInput) searchInput.addEventListener('input', filterJobs);
    if (statusFilter) statusFilter.addEventListener('change', filterJobs);
    if (categoryFilter) categoryFilter.addEventListener('change', filterJobs);

    // Toggle job status
    function toggleStatus(jobId, currentStatus) {
      const newStatus = currentStatus === 'available' ? 'closed' : 'available';
      const confirmMessage = currentStatus === 'available' 
        ? 'Are you sure you want to close this job posting?' 
        : 'Are you sure you want to reactivate this job posting?';
      
      if (confirm(confirmMessage)) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'backend/toggle_job_status.php';
        
        const jobIdInput = document.createElement('input');
        jobIdInput.type = 'hidden';
        jobIdInput.name = 'job_id';
        jobIdInput.value = jobId;
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'status';
        statusInput.value = newStatus;
        
        form.appendChild(jobIdInput);
        form.appendChild(statusInput);
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>
</body>
</html>