<?php
session_start();
include("backend/db_connect.php");

// Ensure only workers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'worker') {
  header("Location: login.html");
  exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info for greeting
$user_sql = "SELECT p.name, u.email FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'] ?? explode('@', $user_data['email'])[0];

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_applications,
    SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) as accepted,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Shortlisted' THEN 1 ELSE 0 END) as shortlisted
    FROM applications WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Count available jobs
$jobs_count_sql = "SELECT COUNT(*) as available_jobs FROM jobs WHERE status = 'available'";
$jobs_count_result = $conn->query($jobs_count_sql);
$jobs_count = $jobs_count_result->fetch_assoc()['available_jobs'];

// Get unread messages count
$unread_query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'];

// Fetch all available jobs with employer profile info
$sql = "SELECT j.job_id, j.title, j.category, j.location, j.pay_rate, j.description, j.employer_id, j.created_at,
               u.email AS employer_email, p.name AS employer_name, p.photo_url AS employer_photo
        FROM jobs j
        JOIN users u ON j.employer_id = u.user_id
        LEFT JOIN profiles p ON j.employer_id = p.user_id
        WHERE j.status = 'available'
        ORDER BY j.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Worker Dashboard | POP!Work</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/sidebar.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --primary-color: #8a1538;
      --primary-dark: #6d1028;
      --primary-light: #fdf2f4;
      --text-primary: #1f2937;
      --text-secondary: #6b7280;
      --border-color: #e5e7eb;
      --bg-light: #f9fafb;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --info: #3b82f6;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #f0f2f5;
      color: var(--text-primary);
      position: relative;
      min-height: 100vh;
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
      min-height: 100vh;
    }

    .main-content {
      flex: 1;
      margin-left: 280px;
      transition: margin-left 0.3s ease;      
      position: relative;
      z-index: 1;
    }

    /* Header */
    .dashboard-header {
      background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
      border: none;
      padding: 32px 40px;
      position: relative;
      z-index: 10;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .header-content {
      max-width: 1600px;
      margin: 0 auto;
    }

    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header-title h1 {
      font-size: 28px;
      font-weight: 700;
      color: white;
      margin-bottom: 4px;
    }

    .header-title p {
      font-size: 14px;
      color: rgba(255, 255, 255, 0.9);
    }

    .header-actions {
      display: flex;
      gap: 12px;
    }

    .header-btn {
      padding: 10px 20px;
      border-radius: 8px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      color: white;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .header-btn:hover {
      background: rgba(255, 255, 255, 0.2);
      border-color: rgba(255, 255, 255, 0.5);
      transform: translateY(-2px);
    }

    .header-btn.primary {
      background: white;
      color: #8a1538;
      border-color: white;
    }

    .header-btn.primary:hover {
      background: rgba(255, 255, 255, 0.95);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
    }

    /* Stats Section - Outside Header */
    .stats-section {
      padding: 25px 40px;
    }

    .stats-section-inner {
      max-width: 1600px;
      margin: 0 auto;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
    }

    .stat-card {
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 20px;
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 2px solid #e4e6eb;
      box-shadow: 0 2px 8px rgba(161, 12, 72, 0.05);
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 4px;
      height: 100%;
      background: linear-gradient(180deg, #8a1538, #c91f4d);
      transform: scaleY(0);
      transform-origin: top;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stat-card:hover::before {
      transform: scaleY(1);
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(138, 21, 56, 0.15);
      border-color: #8a1538;
    }

    .stat-card-content {
      position: relative;
      z-index: 2;
      text-align: left;
    }

    .stat-label {
      font-size: 11px;
      font-weight: 600;
      color: #65676b;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
    }

    .stat-value {
      font-size: 32px;
      font-weight: 800;
      color: #96083e;
      line-height: 1;
    }

    .stat-icon {
      position: absolute;
      right: 20px;
      top: 20px;
      font-size: 40px;
      color: #8a1538;
      background-clip: text;
      transition: all 0.3s;
    }

    .stat-card:hover .stat-icon {
      transform: scale(1.1);
      opacity: 1;
    }

    /* Main Content */

    /* Content Section */
    .content-section {
      padding: 32px 40px;
      max-width: 1600px;
      margin: 0 auto;
    }

    .section-header {
      margin-bottom: 24px;
    }

    .section-title {
      font-size: 24px;
      font-weight: 700;
      color: #ffffff;
    }

    /* Table Container */
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 0;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid rgba(255, 255, 255, 0.5);
      overflow: hidden;
    }

    .table-toolbar {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
      background: #fafafa;
    }

    .toolbar-left {
      display: flex;
      gap: 12px;
      flex: 1;
      flex-wrap: wrap;
    }

    .search-box {
      position: relative;
      flex: 1;
      min-width: 300px;
      max-width: 400px;
    }

    .search-box i {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-secondary);
      font-size: 14px;
    }

    .search-box input {
      width: 100%;
      padding: 10px 16px 10px 44px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 14px;
      outline: none;
      transition: all 0.2s;
    }

    .search-box input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
    }

    .filter-select {
      padding: 10px 16px;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-size: 14px;
      background: white;
      color: var(--text-primary);
      cursor: pointer;
      outline: none;
      transition: all 0.2s;
    }

    .filter-select:focus {
      border-color: var(--primary-color);
    }

    .showing-count {
      color: var(--text-secondary);
      font-size: 14px;
      font-weight: 500;
    }

    /* Table Styles */
    .jobs-table {
      width: 100%;
      border-collapse: collapse;
    }

    .jobs-table thead {
      background: var(--primary-color);
      border-bottom: 2px solid var(--border-color);
    }

    .jobs-table th {
      padding: 16px 20px;
      text-align: left;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--primary-light);
      white-space: nowrap;
    }

    .jobs-table tbody tr {
      border-bottom: 1px solid var(--border-color);
      transition: background 0.2s;
    }

    .jobs-table tbody tr:hover {
      background: #fafafa;
    }

    .jobs-table td {
      padding: 20px;
      font-size: 14px;
      color: var(--text-primary);
      vertical-align: middle;
    }

    /* Job Column */
    .job-info {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 300px;
    }

    .employer-avatar {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      font-weight: 600;
      flex-shrink: 0;
      overflow: hidden;
    }

    .employer-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .job-details {
      flex: 1;
      min-width: 0;
    }

    .job-title {
      font-size: 15px;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 4px;
      display: -webkit-box;
      overflow: hidden;
    }

    .employer-name {
      font-size: 13px;
      color: var(--text-secondary);
      display: -webkit-box;
      overflow: hidden;
    }

    /* Category Badge */
    .category-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
    }

    .category-badge.gig {
      background: #eff6ff;
      color: #1e40af;
    }

    .category-badge.part-time {
      background: #f0fdf4;
      color: #15803d;
    }

    .category-badge.full-time {
      background: #fef3c7;
      color: #92400e;
    }

    /* Location */
    .location-cell {
      display: flex;
      align-items: center;
      gap: 6px;
      color: var(--text-secondary);
      font-size: 13px;
    }

    .location-cell i {
      color: var(--primary-color);
      font-size: 12px;
    }

    /* Pay Rate */
    .pay-cell {
      font-weight: 700;
      color: var(--success);
      font-size: 16px;
      white-space: nowrap;
    }

    /* Date */
    .date-cell {
      color: var(--text-secondary);
      font-size: 13px;
      white-space: nowrap;
    }

    /* Actions */
    .action-buttons {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
    }

    .btn-action {
      padding: 8px 16px;
      border-radius: 6px;
      border: none;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
    }

    .btn-apply {
      background: var(--primary-color);
      color: white;
    }

    .btn-apply:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(138, 21, 56, 0.2);
    }

    .btn-details {
      background: white;
      color: var(--primary-color);
      border: 1px solid var(--primary-color);
    }

    .btn-details:hover {
      background: var(--primary-light);
    }

    .btn-message {
      background: white;
      color: var(--text-secondary);
      border: 1px solid var(--primary-color);
      padding: 8px 12px;
    }

    .btn-message:hover {
      background: var(--bg-light);
      color: var(--primary-color);
      border-color: var(--primary-color);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 80px 20px;
    }

    .empty-icon {
      width: 120px;
      height: 120px;
      margin: 0 auto 24px;
      background: var(--primary-light);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
      color: var(--primary-color);
    }

    .empty-state h3 {
      font-size: 20px;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 8px;
    }

    .empty-state p {
      font-size: 14px;
      color: var(--text-secondary);
    }

    /* Confirmation Modal */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(4px);
    }

    .modal-overlay.show {
      display: flex;
    }

    .modal-content {
      background: white;
      border-radius: 16px;
      max-width: 480px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
      from {
        transform: translateY(-30px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .modal-header {
      padding: 32px 32px 24px;
      text-align: center;
    }

    .modal-icon {
      font-size: 48px;
      margin-bottom: 16px;
    }

    .modal-header h3 {
      font-size: 24px;
      font-weight: 700;
      color: var(--text-primary);
    }

    .modal-body {
      padding: 0 32px 32px;
    }

    .modal-message {
      font-size: 15px;
      color: var(--text-secondary);
      margin-bottom: 16px;
      text-align: center;
    }

    .modal-job-title {
      background: var(--primary-light);
      color: var(--primary-color);
      padding: 12px 20px;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 600;
      text-align: center;
      margin-bottom: 16px;
    }

    .modal-note {
      font-size: 13px;
      color: var(--text-secondary);
      text-align: center;
      margin-bottom: 24px;
      padding: 12px;
      background: #fffbeb;
      border-radius: 8px;
    }

    .modal-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .modal-btn {
      padding: 14px 24px;
      border-radius: 8px;
      border: none;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    .modal-btn-no {
      background: white;
      color: var(--text-primary);
      border: 2px solid var(--border-color);
    }

    .modal-btn-no:hover {
      background: var(--bg-light);
    }

    .modal-btn-yes {
      background: var(--primary-color);
      color: white;
    }

    .modal-btn-yes:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3);
    }

    /* Responsive */
@media (max-width: 992px) {
  .main-content {
    margin-left: 0;
  }

  .jobs-table {
    display: block;
    overflow-x: auto;
  }

  .jobs-table thead,
  .jobs-table tbody,
  .jobs-table tr,
  .jobs-table td,
  .jobs-table th {
    display: block;
  }

  .jobs-table thead {
    display: none;
  }

  .jobs-table tbody tr {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 16px;
    padding: 16px;
    background: white;
  }

  .jobs-table td {
    padding: 12px 0;
    border: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .jobs-table td::before {
    content: attr(data-label);
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 12px;
    text-transform: uppercase;
  }

  .job-info {
    min-width: auto;
  }

  .action-buttons {
    justify-content: flex-start;
    flex-wrap: wrap;
  }
}

@media (max-width: 768px) {
  /* FIX: Make header non-sticky on mobile */
  .dashboard-header {
    position: relative !important; /* Remove sticky positioning */
    padding: 16px;
  }

  .header-content {
    padding: 0;
  }

  .content-section {
    padding: 20px;
  }

  .stats-section {
    padding: 20px;
  }


  .header-top {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px; /* Reduced gap */
  }

  .header-title h1 {
    font-size: 22px; /* Smaller title on mobile */
  }

  .header-title p {
    font-size: 13px;
  }

  .header-actions {
    width: 100%;
    flex-direction: column;
  }

  .header-btn {
    width: 100%;
    justify-content: center;
  }

  /* Stat cards - make them smaller and 2 columns on mobile */
  .stats-grid {
    grid-template-columns: repeat(2, 1fr); /* 2 columns instead of 1 */
    gap: 12px; /* Smaller gap */
  }

  .stat-card {
    padding: 16px; /* Smaller padding */
  }

  .stat-label {
    font-size: 11px; /* Smaller text */
  }

  .stat-value {
    font-size: 24px; /* Smaller number */
  }

  .stat-icon {
    font-size: 24px; /* Smaller icon */
    right: 12px;
    top: 12px;
  }

  /* Search and filters */
  .search-box {
    min-width: 100%;
  }

  .table-toolbar {
    padding: 16px;
    flex-direction: column;
    align-items: stretch;
  }

  .toolbar-left {
    flex-direction: column;
  }

  .filter-select {
    width: 100%;
  }

  /* Modal adjustments for mobile */
  .modal-content {
    width: 95%;
    margin: 20px;
  }

  .modal-header {
    padding: 24px 20px 16px;
  }

  .modal-body {
    padding: 0 20px 24px;
  }

  .modal-icon {
    font-size: 36px;
  }

  .modal-header h3 {
    font-size: 20px;
  }
}

/* Extra small phones */
@media (max-width: 480px) {
  .dashboard-header {
    padding: 12px;
  }

  .header-title h1 {
    font-size: 20px;
  }

  .stats-grid {
    grid-template-columns: 1fr; /* Single column on very small screens */
  }

  .stat-card {
    padding: 14px;
  }

  .stat-value {
    font-size: 20px;
  }
}
  </style>
</head>
<body>
  <div class="dashboard-wrapper">
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
      <!-- Header -->
      <div class="dashboard-header">
        <div class="header-content">
          <div class="header-top">
            <div class="header-title">
              <h1>Welcome back, <?= htmlspecialchars($user_name); ?>! 👋</h1>
              <p>Find your next opportunity and start working today</p>
            </div>
            <div class="header-actions">
              <a href="my_applications.php" class="header-btn">
                <i class="fa-solid fa-file-alt"></i>
                My Applications
              </a>
              <a href="jobs.php" class="header-btn primary">
                <i class="fa-solid fa-search"></i>
                Browse Jobs
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats Section - Outside Header -->
      <div class="stats-section">
        <div class="stats-section-inner">
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-card-content">
                <div class="stat-label">Available Jobs</div>
                <div class="stat-value"><?= $jobs_count; ?></div>
              </div>
              <i class="fa-solid fa-briefcase stat-icon"></i>
            </div>
            <div class="stat-card">
              <div class="stat-card-content">
                <div class="stat-label">Total Applications</div>
                <div class="stat-value"><?= $stats['total_applications']; ?></div>
              </div>
              <i class="fa-solid fa-paper-plane stat-icon"></i>
            </div>
            <div class="stat-card">
              <div class="stat-card-content">
                <div class="stat-label">Accepted</div>
                <div class="stat-value"><?= $stats['accepted']; ?></div>
              </div>
              <i class="fa-solid fa-check-circle stat-icon"></i>
            </div>
            <div class="stat-card">
              <div class="stat-card-content">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= $stats['pending']; ?></div>
              </div>
              <i class="fa-solid fa-clock stat-icon"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Section -->
      <div class="content-section">
        <div class="section-header">
          <h2 class="section-title">Available Jobs</h2>
        </div>

        <!-- Table Container -->
        <div class="table-container">
          <!-- Table Toolbar -->
          <div class="table-toolbar">
            <div class="toolbar-left">
              <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input 
                  type="text" 
                  id="searchInput" 
                  placeholder="Search jobs by title, location, or employer..."
                >
              </div>
              <select class="filter-select" id="categoryFilter">
                <option value="">All Categories</option>
                <option value="Gig">Gig</option>
                <option value="Part-Time">Part-Time</option>
                <option value="Full-Time">Full-Time</option>
              </select>
            </div>
            <div class="showing-count" id="showingCount">
              Showing <?= $result->num_rows; ?> jobs
            </div>
          </div>

          <!-- Jobs Table -->
          <table class="jobs-table">
            <thead>
              <tr>
                <th>Job & Employer</th>
                <th>Category</th>
                <th>Location</th>
                <th>Pay Rate</th>
                <th>Posted</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody id="jobsTableBody">
              <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                  <tr class="job-row" 
                      data-category="<?= htmlspecialchars($row['category']); ?>" 
                      data-title="<?= htmlspecialchars(strtolower($row['title'])); ?>"
                      data-location="<?= htmlspecialchars(strtolower($row['location'])); ?>"
                      data-employer="<?= htmlspecialchars(strtolower($row['employer_name'] ?? $row['employer_email'])); ?>">
                    
                    <!-- Job & Employer -->
                    <td data-label="Job & Employer">
                      <div class="job-info">
                        <?php if (!empty($row['employer_photo']) && file_exists($row['employer_photo'])): ?>
                          <div class="employer-avatar">
                            <img src="<?= htmlspecialchars($row['employer_photo']); ?>" alt="Employer">
                          </div>
                        <?php else: ?>
                          <div class="employer-avatar">
                            <?= strtoupper(substr($row['employer_name'] ?? $row['employer_email'], 0, 1)); ?>
                          </div>
                        <?php endif; ?>
                        <div class="job-details">
                          <div class="job-title"><?= htmlspecialchars($row['title']); ?></div>
                          <div class="employer-name"><?= htmlspecialchars($row['employer_name'] ?? $row['employer_email']); ?></div>
                        </div>
                      </div>
                    </td>

                    <!-- Category -->
                    <td data-label="Category">
                      <span class="category-badge <?= strtolower(str_replace('-', '', $row['category'])); ?>">
                        <?= htmlspecialchars($row['category']); ?>
                      </span>
                    </td>

                    <!-- Location -->
                    <td data-label="Location">
                      <div class="location-cell">
                        <i class="fa-solid fa-location-dot"></i>
                        <?= htmlspecialchars($row['location']); ?>
                      </div>
                    </td>

                    <!-- Pay Rate -->
                    <td data-label="Pay Rate">
                      <div class="pay-cell">
                        RM <?= number_format($row['pay_rate'], 2); ?>
                      </div>
                    </td>

                    <!-- Posted Date -->
                    <td data-label="Posted">
                      <div class="date-cell">
                        <?php
                          $posted_date = strtotime($row['created_at']);
                          $diff = time() - $posted_date;
                          if ($diff < 86400) {
                            echo "Today";
                          } elseif ($diff < 172800) {
                            echo "Yesterday";
                          } else {
                            echo date('M d, Y', $posted_date);
                          }
                        ?>
                      </div>
                    </td>

                    <!-- Actions -->
                    <td data-label="Actions">
                      <div class="action-buttons">
                        <form action="backend/apply_job.php" method="POST" style="margin: 0;">
                          <input type="hidden" name="job_id" value="<?= $row['job_id']; ?>">
                          <button type="button" 
                                  class="btn-action btn-apply apply-btn-confirm" 
                                  data-job-id="<?= $row['job_id']; ?>"
                                  data-job-title="<?= htmlspecialchars($row['title']); ?>">
                            <i class="fa-solid fa-paper-plane"></i>
                            Apply
                          </button>
                        </form>
                        <a href="job_detail.php?id=<?= $row['job_id'] ?>" class="btn-action btn-details">
                          <i class="fa-solid fa-eye"></i>
                          View
                        </a>
                        <a href="chat.php?user_id=<?= $row['employer_id']; ?>&job_id=<?= $row['job_id']; ?>" 
                           class="btn-action btn-message" 
                           title="Message employer">
                          <i class="fa-solid fa-comment"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6">
                    <div class="empty-state">
                      <div class="empty-icon">
                        <i class="fa-solid fa-briefcase"></i>
                      </div>
                      <h3>No Jobs Available</h3>
                      <p>Check back soon for exciting new opportunities!</p>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-icon">✨</div>
        <h3>Confirm Application</h3>
      </div>
      <div class="modal-body">
        <p class="modal-message">Are you sure you want to apply for this position?</p>
        <div class="modal-job-title" id="confirmJobTitle"></div>
        <p class="modal-note">💡 Make sure your profile is complete before applying</p>
        <div class="modal-actions">
          <button class="modal-btn modal-btn-no" onclick="closeConfirmModal()">Cancel</button>
          <button class="modal-btn modal-btn-yes" onclick="confirmApplication()">Yes, Apply Now!</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Search and Filter functionality
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const jobsTableBody = document.getElementById('jobsTableBody');
    const showingCount = document.getElementById('showingCount');
    const jobRows = jobsTableBody ? jobsTableBody.querySelectorAll('.job-row') : [];

    function filterJobs() {
      const searchTerm = searchInput.value.toLowerCase();
      const selectedCategory = categoryFilter.value;
      let visibleCount = 0;

      jobRows.forEach(row => {
        const title = row.dataset.title;
        const location = row.dataset.location;
        const employer = row.dataset.employer;
        const category = row.dataset.category;

        const matchesSearch = title.includes(searchTerm) || 
                            location.includes(searchTerm) || 
                            employer.includes(searchTerm);
        const matchesCategory = !selectedCategory || category === selectedCategory;

        if (matchesSearch && matchesCategory) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      showingCount.textContent = `Showing ${visibleCount} job${visibleCount !== 1 ? 's' : ''}`;
    }

    if (searchInput) searchInput.addEventListener('input', filterJobs);
    if (categoryFilter) categoryFilter.addEventListener('change', filterJobs);

    // Confirmation Modal Functions
    let currentApplyForm = null;

    function openConfirmModal(jobTitle, form) {
      document.getElementById('confirmJobTitle').textContent = jobTitle;
      currentApplyForm = form;
      
      const modal = document.getElementById('confirmModal');
      modal.classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    function closeConfirmModal() {
      const modal = document.getElementById('confirmModal');
      modal.classList.remove('show');
      document.body.style.overflow = 'auto';
      currentApplyForm = null;
    }

    function confirmApplication() {
      if (currentApplyForm) {
        currentApplyForm.submit();
      }
    }

    // Add click event listeners
    document.addEventListener('DOMContentLoaded', function() {
      const applyButtons = document.querySelectorAll('.apply-btn-confirm');
      
      applyButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const jobTitle = this.dataset.jobTitle;
          const form = this.closest('form');
          openConfirmModal(jobTitle, form);
        });
      });
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
      const confirmModal = document.getElementById('confirmModal');
      if (event.target === confirmModal) {
        closeConfirmModal();
      }
    }

    // Close modal on ESC key
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        closeConfirmModal();
      }
    });
  </script>
</body>
</html>