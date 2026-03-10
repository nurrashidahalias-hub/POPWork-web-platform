<?php
session_start();
include("backend/db_connect.php");

// Ensure only employers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
  header("Location: login.html");
  exit();
}

$employer_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$payment_filter = $_GET['payment'] ?? 'all';

// Fetch all applications for employer's jobs with worker details
$sql = "SELECT 
    a.application_id,
    a.job_id,
    a.user_id,
    a.status as application_status,
    a.total_work_hours,
    a.job_completed,
    a.completion_date,
    a.payment_status,
    a.payment_amount,
    a.payment_date,
    j.title as job_title,
    j.pay_rate,
    j.location,
    j.category,
    u.email as worker_email,
    p.name as worker_name,
    p.photo_url as worker_photo,
    (SELECT COUNT(*) FROM attendance WHERE application_id = a.application_id) as total_sessions,
    (SELECT COUNT(*) FROM attendance WHERE application_id = a.application_id AND clock_out_time IS NULL) as active_sessions
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN profiles p ON a.user_id = p.user_id
    WHERE j.employer_id = ? AND a.status = 'Accepted'";

// Add filters
$params = [$employer_id];
$types = "i";

if ($status_filter === 'completed') {
  $sql .= " AND a.job_completed = 1";
} elseif ($status_filter === 'active') {
  $sql .= " AND a.job_completed = 0";
}

if ($payment_filter !== 'all') {
  $sql .= " AND a.payment_status = ?";
  $params[] = $payment_filter;
  $types .= "s";
}

$sql .= " ORDER BY a.job_completed ASC, a.applied_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get summary statistics
$stats_sql = "SELECT 
    COUNT(*) as total_jobs,
    SUM(CASE WHEN a.job_completed = 1 THEN 1 ELSE 0 END) as completed_jobs,
    SUM(CASE WHEN a.job_completed = 0 THEN 1 ELSE 0 END) as active_jobs,
    SUM(CASE WHEN a.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
    SUM(CASE WHEN a.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
    SUM(a.total_work_hours) as total_hours_all,
    SUM(CASE WHEN a.payment_status = 'pending' THEN (a.total_work_hours * j.pay_rate) ELSE 0 END) as pending_amount,
    SUM(CASE WHEN a.payment_status = 'paid' THEN a.payment_amount ELSE 0 END) as paid_amount
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE j.employer_id = ? AND a.status = 'Accepted'";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $employer_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Completion & Payments | POP!Work</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/sidebar.css">
  <style>
    :root {
      --primary: #8a1538;
      --primary-dark: #6b1129;
      --primary-light: #c91f4d;
      --success: #27ae60;
      --warning: #f39c12;
      --danger: #e74c3c;
      --info: #3498db;
      --text-dark: #1c1e21;
      --text-medium: #4a5568;
      --text-light: #65676b;
      --bg-light: #f5f7fa;
      --border-color: #e1e8ed;
      --white: #ffffff;
      --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
      --shadow-md: 0 4px 12px rgba(138, 21, 56, 0.15);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

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

    .main-content {
      margin-left: 280px;
      width: calc(100% - 280px);
      min-height: 100vh;
      position: relative;
    }

    /* Header */
    .dashboard-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
      padding: 32px 40px;
      position: relative;
      z-index: 10;
      box-shadow: var(--shadow-md);
    }

    .header-title h1 {
      font-size: 32px;
      font-weight: 700;
      color: var(--white);
      margin-bottom: 6px;
    }

    .header-title p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 15px;
      font-weight: 500;
    }

    /* Stats Section */
    .stats-section {
      padding: 16px 40px;
      backdrop-filter: blur(10px);
      border-bottom: 0.5px solid rgba(255, 255, 255, 0.5);
    }

    .stats-grid {
      display: flex;
      gap: 12px;
      overflow-x: auto;
    }

    .stat-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 10px;
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: var(--shadow-sm);
      transition: transform 0.2s;
      flex: 1;
      min-width: 150px;
      white-space: nowrap;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .stat-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: var(--white);
      flex-shrink: 0;
    }

    .stat-value {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-dark);
      line-height: 1;
      margin-bottom: 3px;
    }

    .stat-label {
      font-size: 11px;
      color: var(--text-medium);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    /* Content */
    .content-wrapper {
      padding: 32px 40px;
    }

    /* Filter Section */
    .filter-section {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: var(--shadow-sm);
    }

    .filter-controls {
      display: flex;
      gap: 16px;
      align-items: flex-end;
      flex-wrap: wrap;
    }

    .filter-group {
      flex: 1;
      min-width: 200px;
    }

    .filter-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-medium);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .filter-select {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid var(--border-color);
      border-radius: 10px;
      font-size: 15px;
      font-weight: 500;
      color: var(--text-dark);
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      transition: all 0.3s;
    }

    .filter-select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(138, 21, 56, 0.1);
    }

    .filter-btn {
      padding: 12px 32px;
      background: var(--primary);
      color: var(--white);
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .filter-btn:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    /* Table Section */
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 16px;
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .table-header {
      padding: 24px 28px;
      border-bottom: 2px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .table-header h2 {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-dark);
    }

    .table-wrapper {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      position: sticky;
      top: 0;
      z-index: 10;
    }

    thead th {
      padding: 16px 20px;
      text-align: left;
      font-size: 13px;
      font-weight: 700;
      color: #ffffff;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid var(--border-color);
      white-space: nowrap;
    }

    tbody tr {
      border-bottom: 1px solid var(--border-color);
      transition: all 0.2s;
    }

    tbody tr:hover {
      background: #fafbfc;
    }

    tbody td {
      padding: 20px;
      font-size: 14px;
      color: var(--text-dark);
      vertical-align: middle;
    }

    /* Worker Cell */
    .worker-cell {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 220px;
    }

    .worker-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 18px;
      flex-shrink: 0;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .worker-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .worker-info h4 {
      font-size: 15px;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 2px;
    }

    .worker-info p {
      font-size: 13px;
      color: var(--text-light);
    }

    /* Job Cell */
    .job-cell {
      min-width: 200px;
    }

    .job-title {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 4px;
      font-size: 14px;
    }

    .job-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      font-size: 12px;
      color: var(--text-medium);
    }

    .job-meta span {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    /* Badge */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
    }

    .badge.active {
      background: #e3f2fd;
      color: #1976d2;
    }

    .badge.completed {
      background: #e8f5e9;
      color: #388e3c;
    }

    .badge.pending {
      background: #fff3e0;
      color: #f57c00;
    }

    .badge.paid {
      background: #e8f5e9;
      color: #2e7d32;
    }

    /* Worker Photo Styles */
    .person-cell {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .person-photo {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--white);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .person-placeholder {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 16px;
      border: 2px solid var(--white);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .person-info {
      flex: 1;
      min-width: 0;
    }

    .person-name {
      font-weight: 600;
      color: var(--text-dark);
      font-size: 14px;
      margin-bottom: 2px;
    }

    .person-email {
      font-size: 12px;
      color: var(--text-light);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Number Cell */
    .number-cell {
      font-weight: 600;
      color: var(--text-dark);
      text-align: right;
      font-variant-numeric: tabular-nums;
    }

    .number-cell.highlight {
      color: var(--primary);
      font-size: 16px;
    }

    /* Action Cell */
    .action-cell {
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-width: 200px;
    }

    .btn {
      padding: 10px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      border: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none;
      text-align: center;
      white-space: nowrap;
    }

    .btn-primary {
      background: var(--primary);
      color: var(--white);
      box-shadow: 0 2px 6px rgba(138, 21, 56, 0.2);
    }

    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(138, 21, 56, 0.3);
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      color: var(--text-dark);
      border: 2px solid var(--border-color);
    }

    .btn-secondary:hover {
      border-color: var(--primary);
      color: var(--primary);
    }

    .btn-success {
      background: var(--success);
      color: var(--white);
    }

    .btn-success:hover {
      background: #229954;
    }

    /* Alert Inline */
    .alert-inline {
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
      border-left: 4px solid;
    }

    .alert-warning {
      background: #fff8e1;
      border-color: #ffa000;
      color: #ff6f00;
    }

    .alert-danger {
      background: #ffebee;
      border-color: #d32f2f;
      color: #c62828;
    }

    .alert-success {
      background: #e8f5e9;
      border-color: #388e3c;
      color: #2e7d32;
    }

    /* Empty State */
    .empty-state {
      padding: 80px 40px;
      text-align: center;
      color: var(--text-light);
    }

    .empty-state i {
      font-size: 64px;
      color: var(--border-color);
      margin-bottom: 20px;
    }

    .empty-state h3 {
      font-size: 24px;
      color: var(--text-medium);
      margin-bottom: 8px;
    }

    .empty-state p {
      font-size: 15px;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
        width: 100%;
      }

      .dashboard-header,
      .stats-section,
      .content-wrapper {
        padding-left: 20px;
        padding-right: 20px;
      }

      .stats-grid {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .stat-card {
        min-width: 140px;
      }

      .table-wrapper {
        overflow-x: scroll;
      }

      table {
        min-width: 1200px;
      }
    }

    @media (max-width: 576px) {
      .stats-grid {
        gap: 8px;
      }

      .stat-card {
        min-width: 130px;
        padding: 10px 12px;
      }

      .stat-icon {
        width: 36px;
        height: 36px;
        font-size: 16px;
      }

      .stat-value {
        font-size: 18px;
      }

      .stat-label {
        font-size: 10px;
      }

      .filter-controls {
        flex-direction: column;
      }

      .filter-group {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <?php 
    $page_title = "Job Completion & Payments";
    include 'includes/sidebar.php';
  ?>

  <div class="main-content">
    <!-- Header -->
    <div class="dashboard-header">
      <div class="header-title">
        <h1>Job Completion & Payments</h1>
        <p>Manage job completion and process worker payments</p>
      </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-section">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['total_jobs'] ?? 0); ?></div>
            <div class="stat-label">Total Jobs</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['completed_jobs'] ?? 0); ?></div>
            <div class="stat-label">Completed</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-clock"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['pending_payments'] ?? 0); ?></div>
            <div class="stat-label">Pending Payment</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
          <div class="stat-info">
            <div class="stat-value">RM <?= number_format($stats['pending_amount'] ?? 0, 0); ?></div>
            <div class="stat-label">Amount Due</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-check-double"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['paid_count'] ?? 0); ?></div>
            <div class="stat-label">Paid</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-sack-dollar"></i></div>
          <div class="stat-info">
            <div class="stat-value">RM <?= number_format($stats['paid_amount'] ?? 0, 0); ?></div>
            <div class="stat-label">Total Paid</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="content-wrapper">
      
      <!-- Filter Section -->
      <div class="filter-section">
        <form method="GET">
          <div class="filter-controls">
            <div class="filter-group">
              <label>Job Status</label>
              <select name="status" class="filter-select">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Jobs</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed Jobs</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Payment Status</label>
              <select name="payment" class="filter-select">
                <option value="all" <?= $payment_filter === 'all' ? 'selected' : '' ?>>All Payments</option>
                <option value="pending" <?= $payment_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
              </select>
            </div>
            <div class="filter-group">
              <button type="submit" class="filter-btn">
                <i class="fas fa-search"></i> Apply Filters
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Table -->
      <div class="table-container">
        <div class="table-header">
          <h2>📋 Job Applications</h2>
        </div>
        
        <div class="table-wrapper">
          <?php if ($result->num_rows > 0): ?>
            <table>
              <thead>
                <tr>
                  <th>Worker</th>
                  <th>Job Details</th>
                  <th>Status</th>
                  <th style="text-align: center;">Hours</th>
                  <th style="text-align: center;">Sessions</th>
                  <th style="text-align: right;">Pay Rate</th>
                  <th style="text-align: right;">Total Payment</th>
                  <th>Completion Date</th>
                  <th style="text-align: center;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                  <?php
                    $calculated_payment = $row['total_work_hours'] * $row['pay_rate'];
                    $is_completed = $row['job_completed'];
                    $has_active = $row['active_sessions'] > 0;
                  ?>
                  <tr>
                    <!-- Worker -->
                    <td>
                      <div class="person-cell">
                        <?php if (!empty($row['worker_photo'])): ?>
                          <img src="<?= htmlspecialchars($row['worker_photo']) ?>" class="person-photo" alt="Worker Photo">
                        <?php else: ?>
                          <div class="person-placeholder">
                            <?= strtoupper(substr($row['worker_name'] ?? $row['worker_email'], 0, 1)) ?>
                          </div>
                        <?php endif; ?>
                        <div class="person-info">
                          <div class="person-name"><?= htmlspecialchars($row['worker_name'] ?? 'Worker') ?></div>
                          <div class="person-email"><?= htmlspecialchars($row['worker_email']) ?></div>
                        </div>
                      </div>
                    </td>

                    <!-- Job Details -->
                    <td>
                      <div class="job-cell">
                        <div class="job-title"><?= htmlspecialchars($row['job_title']) ?></div>
                        <div class="job-meta">
                          <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($row['location']) ?></span>
                          <span><i class="fas fa-tag"></i> <?= htmlspecialchars($row['category']) ?></span>
                        </div>
                      </div>
                    </td>

                    <!-- Status -->
                    <td>
                      <div style="display: flex; flex-direction: column; gap: 6px;">
                        <span class="badge <?= $is_completed ? 'completed' : 'active' ?>">
                          <?= $is_completed ? '✓ Completed' : '● Active' ?>
                        </span>
                        <span class="badge <?= $row['payment_status'] === 'paid' ? 'paid' : 'pending' ?>">
                          <?= $row['payment_status'] === 'paid' ? '✓ Paid' : '⏱ Pending' ?>
                        </span>
                      </div>
                    </td>

                    <!-- Hours -->
                    <td class="number-cell" style="text-align: center;">
                      <?= number_format($row['total_work_hours'] ?? 0, 1) ?>H
                    </td>

                    <!-- Sessions -->
                    <td class="number-cell" style="text-align: center;">
                      <?= number_format($row['total_sessions'] ?? 0) ?>
                    </td>

                    <!-- Pay Rate -->
                    <td class="number-cell">
                      RM <?= number_format($row['pay_rate'], 2) ?>
                    </td>

                    <!-- Total Payment -->
                    <td class="number-cell highlight">
                      RM <?= number_format($calculated_payment, 2) ?>
                    </td>

                    <!-- Completion Date -->
                    <td>
                      <?php if ($row['completion_date']): ?>
                        <div style="font-size: 13px;">
                          <div style="font-weight: 600; color: var(--text-dark);">
                            <?= date('M d, Y', strtotime($row['completion_date'])) ?>
                          </div>
                          <div style="color: var(--text-light);">
                            <?= date('g:i A', strtotime($row['completion_date'])) ?>
                          </div>
                        </div>
                      <?php else: ?>
                        <span style="color: var(--text-light); font-size: 13px;">—</span>
                      <?php endif; ?>
                    </td>

                    <!-- Actions -->
                    <td>
                      <div class="action-cell">
                        <?php if (!$is_completed && !$has_active): ?>
                          <!-- Can mark complete -->
                          <?php if ($row['total_sessions'] > 0 && $row['total_work_hours'] > 0): ?>
                            <form method="POST" action="backend/mark_job_complete.php">
                              <input type="hidden" name="application_id" value="<?= $row['application_id'] ?>">
                              <button type="submit" class="btn btn-primary" onclick="return confirm('Mark this job as complete?\n\nWorker: <?= htmlspecialchars($row['worker_name'] ?? $row['worker_email']) ?>\nTotal Hours: <?= number_format($row['total_work_hours'], 2) ?>h\nPayment: RM <?= number_format($calculated_payment, 2) ?>')">
                                <i class="fas fa-check"></i> Mark Complete
                              </button>
                            </form>
                          <?php else: ?>
                            <div class="alert-inline alert-warning">
                              <i class="fas fa-exclamation-triangle"></i>
                              <span style="font-size: 11px;"><strong>No attendance</strong></span>
                            </div>
                          <?php endif; ?>
                          
                        <?php elseif (!$is_completed && $has_active): ?>
                          <!-- Worker clocked in -->
                          <div class="alert-inline alert-danger">
                            <i class="fas fa-user-clock"></i>
                            <span style="font-size: 11px;"><strong>Clocked in</strong> (<?= $row['active_sessions'] ?>)</span>
                          </div>
                          
                        <?php elseif ($is_completed && $row['payment_status'] !== 'paid'): ?>
                          <!-- Process payment -->
                          <a href="process_payment.php?application_id=<?= $row['application_id'] ?>" class="btn btn-success">
                            <i class="fas fa-dollar-sign"></i> Process Payment
                          </a>
                          
                        <?php elseif ($is_completed && $row['payment_status'] === 'paid'): ?>
                          <div class="alert-inline alert-success">
                            <i class="fas fa-check-double"></i>
                            <span style="font-size: 11px;"><strong>Paid</strong></span>
                          </div>
                        <?php endif; ?>
                        
                        <a href="attendance_history.php?job_id=<?= $row['job_id'] ?>&worker_id=<?= $row['user_id'] ?>" class="btn btn-secondary">
                          <i class="fas fa-history"></i> View History
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-briefcase"></i>
              <h3>No Jobs Found</h3>
              <p>No accepted job applications match your current filters</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

</body>
</html>