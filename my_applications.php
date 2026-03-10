<?php
session_start();
include("backend/db_connect.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'worker') {
  header("Location: login.html");
  exit();
}

$worker_id = $_SESSION['user_id'];

// Fetch user info for greeting
$user_sql = "SELECT p.name, u.email FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $worker_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'] ?? explode('@', $user_data['email'])[0];

// Get unread messages count for sidebar
$unread_query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $worker_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'];

// Get application statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Shortlisted' THEN 1 ELSE 0 END) as shortlisted,
    SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) as accepted,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM applications WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $worker_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Fetch all applications with employer details
$sql = "SELECT a.application_id, a.job_id, a.status, a.created_at as applied_at,
               j.title, j.location, j.pay_rate, j.category, j.description, j.employer_id,
               u.email AS employer_email, p.name AS employer_name, p.photo_url AS employer_photo
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN users u ON j.employer_id = u.user_id
        LEFT JOIN profiles p ON j.employer_id = p.user_id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $worker_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Applications | POP!Work</title>
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
      --purple: #8b5cf6;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #f0f2f5;
      color: var(--text-primary);
      overflow-x: hidden;
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
      width: 100%;
    }

    .main-content {
      flex: 1;
      margin-left: 280px;
      transition: margin-left 0.3s ease;
      width: 100%;
      overflow-x: hidden;
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
      max-width: 100%;
      margin: 0 auto;
    }

    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
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
      flex-wrap: wrap;
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
      padding: 32px 40px;
    }

    .stats-section-inner {
      max-width: 100%;
      margin: 0 auto;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 7px;
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
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
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
      color: #6b6568;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
    }

    .stat-value {
      font-size: 32px;
      font-weight: 800;
      color: var(--primary-color);
      line-height: 1;
    }

    .stat-icon {
      position: absolute;
      right: 20px;
      top: 20px;
      font-size: 40px;
      background: var(--primary-dark);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      opacity: 0.8;
      transition: all 0.3s;
    }

    .stat-card:hover .stat-icon {
      transform: scale(1.1);
      opacity: 1;
    }


    /* Main Content */
    .content-section {
      padding: 32px 40px;
      max-width: 100%;
      margin: 0 auto;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }

    .section-title {
      font-size: 24px;
      font-weight: 700;
      color: var(--bg-light);
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
      min-width: 0;
    }

    .search-box {
      position: relative;
      flex: 1;
      min-width: 200px;
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
      min-width: 150px;
    }

    .filter-select:focus {
      border-color: var(--primary-color);
    }

    .showing-count {
      color: var(--text-secondary);
      font-size: 14px;
      font-weight: 500;
      white-space: nowrap;
    }

    /* Table Wrapper for Scroll */
    .table-wrapper {
      width: 100%;
      overflow: hidden;
    }

    /* Table Styles */
    .applications-table {
      width: 100%;
      border-collapse: collapse;
    }

    .applications-table thead {
      background:var(--primary-color);
      border-bottom: 2px solid var(--border-color);
    }

    .applications-table th {
      padding: 16px 20px;
      text-align: left;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--bg-light);
      white-space: nowrap;
    }

    .applications-table tbody tr {
      border-bottom: 1px solid var(--border-color);
      transition: background 0.2s;
    }

    .applications-table tbody tr:hover {
      background: #fafafa;
    }

    .applications-table td {
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
      min-width: 250px;
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
      -webkit-line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .employer-name {
      font-size: 13px;
      color: var(--text-secondary);
      display: -webkit-box;
      -webkit-line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    /* Status Badge */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
    }

    .status-badge.pending {
      background: #fef3c7;
      color: #92400e;
    }

    .status-badge.shortlisted {
      background: #ede9fe;
      color: #6b21a8;
    }

    .status-badge.accepted {
      background: #d1fae5;
      color: #065f46;
    }

    .status-badge.rejected {
      background: #fee2e2;
      color: #991b1b;
    }

    /* Category Badge */
    .category-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
      background: #f3f4f6;
      color: var(--text-secondary);
    }

    /* Location */
    .location-cell {
      display: flex;
      align-items: center;
      gap: 6px;
      color: var(--text-secondary);
      font-size: 13px;
      white-space: nowrap;
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
      flex-wrap: wrap;
    }

    .btn-action {
      padding: 8px 8px;
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

    .btn-message {
      background: var(--primary-color);
      color: white;
    }

    .btn-message:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(138, 21, 56, 0.2);
    }

    .btn-view {
      background: white;
      color: var(--primary-color);
      border: 1px solid var(--primary-color);
    }

    .btn-view:hover {
      background: var(--primary-light);
    }

    .btn-cancel {
      background: white;
      color: var(--danger);
      border: 1px solid var(--danger);
    }

    .btn-cancel:hover {
      background: #fee2e2;
    }

    .btn-clock {
      background: var(--success);
      color: white;
    }

    .btn-clock:hover {
      background: #059669;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(16, 185, 129, 0.2);
    }

    .btn-disabled {
      background: var(--bg-light);
      color: var(--text-secondary);
      border: 1px solid var(--border-color);
      cursor: not-allowed;
      opacity: 0.6;
    }

    .attendance-disabled {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      background: #f3f4f6;
      border-radius: 6px;
      font-size: 13px;
      color: var(--text-secondary);
      white-space: nowrap;
    }

    .attendance-disabled i {
      font-size: 12px;
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
      margin-bottom: 24px;
    }

    .empty-btn {
      padding: 12px 24px;
      background: var(--primary-color);
      color: white;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-block;
    }

    .empty-btn:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
    }

    /* Responsive Design */
    @media (max-width: 1400px) {
      /* Switch to card layout earlier to prevent horizontal scroll */
      .table-wrapper {
        overflow-x: visible;
      }

      .applications-table {
        display: block;
      }

      .applications-table thead {
        display: none;
      }

      .applications-table tbody {
        display: block;
      }

      .applications-table tbody tr {
        display: block;
        margin-bottom: 16px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      }

      .applications-table td {
        display: block;
        padding: 12px 0;
        border: none;
        text-align: left !important;
      }

      .applications-table td::before {
        content: attr(data-label);
        font-weight: 700;
        color: var(--text-secondary);
        font-size: 11px;
        text-transform: uppercase;
        display: block;
        margin-bottom: 8px;
        letter-spacing: 0.5px;
      }

      .job-info {
        min-width: auto;
      }

      .action-buttons {
        justify-content: flex-start;
        margin-top: 8px;
      }

      .location-cell,
      .pay-cell,
      .date-cell {
        white-space: normal;
      }
    }

    @media (max-width: 1200px) {
      .stats-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .dashboard-header {
        padding: 20px;
      }

      .content-section {
        padding: 20px;
      }

      .header-title h1 {
        font-size: 24px;
      }

      .applications-table tbody tr {
        padding: 16px;
      }
    }

    @media (max-width: 768px) {
      .dashboard-header {
        padding: 16px 20px;
      }

      .stats-section {
        padding: 20px;
      }

      .content-section {
        padding: 20px;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .header-top {
        flex-direction: column;
        align-items: flex-start;
      }

      .header-actions {
        width: 100%;
      }

      .header-btn {
        flex: 1;
        justify-content: center;
      }

      .search-box {
        min-width: 100%;
        max-width: 100%;
      }

      .table-toolbar {
        padding: 16px;
      }

      .toolbar-left {
        width: 100%;
      }

      .filter-select {
        width: 100%;
      }

      .showing-count {
        width: 100%;
        text-align: center;
      }

      .action-buttons {
          display: flex;
          gap: 10px; /* Space between the two buttons */
          justify-content: flex-start; /* Aligns them to the left of the cell */
      }

      .btn-action {
        width: 100%;
        justify-content: center;
      }
    }

    @media (max-width: 480px) {
      .dashboard-header {
        padding: 16px;
      }

      .content-section {
        padding: 16px;
      }

      .header-title h1 {
        font-size: 20px;
      }

      .stat-value {
        font-size: 28px;
      }

      .stat-label {
        font-size: 11px;
      }

      .stat-icon {
        font-size: 24px;
      }

      .employer-avatar {
        width: 40px;
        height: 40px;
        font-size: 16px;
      }

      .job-title {
        font-size: 14px;
      }

      .employer-name {
        font-size: 12px;
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
              <h1>My Applications 📋</h1>
              <p>Track and manage all your job applications</p>
            </div>
            <div class="header-actions">
              <a href="worker_dashboard.php" class="header-btn">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Dashboard
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
            <div class="stat-card" onclick="filterByStatus('all')">
              <div class="stat-card-content">
                <div class="stat-label">Total</div>
                <div class="stat-value"><?= $stats['total']; ?></div>
              </div>
              <i class="fa-solid fa-file-alt stat-icon"></i>
            </div>
            <div class="stat-card" onclick="filterByStatus('pending')">
              <div class="stat-card-content">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= $stats['pending']; ?></div>
              </div>
              <i class="fa-solid fa-clock stat-icon"></i>
            </div>
            <div class="stat-card" onclick="filterByStatus('shortlisted')">
              <div class="stat-card-content">
                <div class="stat-label">Shortlisted</div>
                <div class="stat-value"><?= $stats['shortlisted']; ?></div>
              </div>
              <i class="fa-solid fa-star stat-icon"></i>
            </div>
            <div class="stat-card" onclick="filterByStatus('accepted')">
              <div class="stat-card-content">
                <div class="stat-label">Accepted</div>
                <div class="stat-value"><?= $stats['accepted']; ?></div>
              </div>
              <i class="fa-solid fa-check-circle stat-icon"></i>
            </div>
            <div class="stat-card" onclick="filterByStatus('rejected')">
              <div class="stat-card-content">
                <div class="stat-label">Rejected</div>
                <div class="stat-value"><?= $stats['rejected']; ?></div>
              </div>
              <i class="fa-solid fa-times-circle stat-icon"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Section -->
      <div class="content-section">
        <div class="section-header">
          <h2 class="section-title">All Applications</h2>
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
                  placeholder="Search by job title, employer, or location..."
                >
              </div>
              <select class="filter-select" id="statusFilter">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="shortlisted">Shortlisted</option>
                <option value="accepted">Accepted</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
            <div class="showing-count" id="showingCount">
              Showing <?= $result->num_rows; ?> applications
            </div>
          </div>

          <!-- Table Wrapper -->
          <div class="table-wrapper">
            <!-- Applications Table -->
            <table class="applications-table">
              <thead>
                <tr>
                  <th>Job & Employer</th>
                  <th>Status</th>
                  <th>Category</th>
                  <th>Location</th>
                  <th>Pay Rate</th>
                  <th>Applied</th>
                  <th>Attendance</th>
                  <th style="text-align: right;">Actions</th>
                </tr>
              </thead>
              <tbody id="applicationsTableBody">
                <?php if ($result->num_rows > 0): ?>
                  <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                      $status = strtolower($row['status']);
                      $statusDisplay = ucfirst($status);
                      $statusIcon = match($status) {
                        'pending' => 'fa-clock',
                        'shortlisted' => 'fa-star',
                        'accepted' => 'fa-check-circle',
                        'rejected' => 'fa-times-circle',
                        default => 'fa-file-alt'
                      };
                    ?>
                    <tr class="application-row" 
                        data-status="<?= $status; ?>" 
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

                      <!-- Status -->
                      <td data-label="Status">
                        <span class="status-badge <?= $status; ?>">
                          <i class="fa-solid <?= $statusIcon; ?>"></i>
                          <?= $statusDisplay; ?>
                        </span>
                      </td>

                      <!-- Category -->
                      <td data-label="Category">
                        <span class="category-badge">
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

                      <!-- Applied Date -->
                      <td data-label="Applied">
                        <div class="date-cell">
                          <?php
                            $applied_date = strtotime($row['applied_at']);
                            $diff = time() - $applied_date;
                            if ($diff < 86400) {
                              echo "Today";
                            } elseif ($diff < 172800) {
                              echo "Yesterday";
                            } else {
                              echo date('M d, Y', $applied_date);
                            }
                          ?>
                        </div>
                      </td>

                      <!-- Attendance Column -->
                      <td data-label="Attendance">
                        <?php if ($status === 'accepted'): ?>
                          <a href="attendance.php" class="btn-action btn-clock" style="margin: 0;">
                            <i class="fa-solid fa-clock"></i>
                            Clock In
                          </a>
                        <?php else: ?>
                          <span class="attendance-disabled">
                            <i class="fa-solid fa-lock"></i>
                            Not Available
                          </span>
                        <?php endif; ?>
                      </td>

                      <!-- Actions -->
                      <td data-label="Actions">
                        <div class="action-buttons">
                          <a href="chat.php?user_id=<?= $row['employer_id']; ?>&job_id=<?= $row['job_id']; ?>" 
                             class="btn-action btn-message" 
                             title="Message employer">
                            <i class="fa-solid fa-comment"></i>
                            Message 
                          </a>
                          <a href="job_detail.php?id=<?= $row['job_id'] ?>" 
                             class="btn-action btn-view">
                            <i class="fa-solid fa-eye"></i>
                          View Job
                          </a>
                          <?php if ($status === 'pending'): ?>
                            <a href="backend/cancel_application.php?id=<?= $row['application_id'] ?>" 
                               class="btn-action btn-cancel" 
                               onclick="return confirm('Are you sure you want to cancel this application?')">
                              <i class="fa-solid fa-times"></i>     
                                   Cancel
                            </a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8">
                      <div class="empty-state">
                        <div class="empty-icon">
                          <i class="fa-solid fa-inbox"></i>
                        </div>
                        <h3>No Applications Yet</h3>
                        <p>You haven't applied to any jobs yet. Start exploring opportunities!</p>
                        <a href="worker_dashboard.php" class="empty-btn">
                          <i class="fa-solid fa-search"></i>
                          Browse Available Jobs
                        </a>
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
  </div>

  <script>
    // Search and Filter functionality
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const applicationsTableBody = document.getElementById('applicationsTableBody');
    const showingCount = document.getElementById('showingCount');
    const applicationRows = applicationsTableBody ? applicationsTableBody.querySelectorAll('.application-row') : [];

    function filterApplications() {
      const searchTerm = searchInput.value.toLowerCase();
      const selectedStatus = statusFilter.value;
      let visibleCount = 0;

      applicationRows.forEach(row => {
        const title = row.dataset.title;
        const location = row.dataset.location;
        const employer = row.dataset.employer;
        const status = row.dataset.status;

        const matchesSearch = title.includes(searchTerm) || 
                            location.includes(searchTerm) || 
                            employer.includes(searchTerm);
        const matchesStatus = !selectedStatus || status === selectedStatus;

        if (matchesSearch && matchesStatus) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      showingCount.textContent = `Showing ${visibleCount} application${visibleCount !== 1 ? 's' : ''}`;
    }

    function filterByStatus(status) {
      statusFilter.value = status === 'all' ? '' : status;
      filterApplications();
    }

    if (searchInput) searchInput.addEventListener('input', filterApplications);
    if (statusFilter) statusFilter.addEventListener('change', filterApplications);
  </script>
</body>
</html>