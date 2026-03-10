<?php
session_start();
include("backend/db_connect.php");

// Ensure only workers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'worker') {
  header("Location: login.html");
  exit();
}

$worker_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$payment_filter = $_GET['payment'] ?? 'all';

// Fetch all worker's applications with payment details
$sql = "SELECT 
    a.application_id,
    a.job_id,
    a.status as application_status,
    a.total_work_hours,
    a.job_completed,
    a.completion_date,
    a.payment_status,
    a.payment_amount,
    a.payment_date,
    a.payment_method,
    a.employer_notes,
    j.title as job_title,
    j.pay_rate,
    j.location,
    j.category,
    j.employer_id,
    e.email as employer_email,
    p.name as employer_name,
    p.photo_url as employer_photo,
    (SELECT COUNT(*) FROM attendance WHERE application_id = a.application_id) as total_sessions
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN users e ON j.employer_id = e.user_id
    LEFT JOIN profiles p ON e.user_id = p.user_id
    WHERE a.user_id = ? AND a.status = 'Accepted'";

// Add filters
$params = [$worker_id];
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
    WHERE a.user_id = ? AND a.status = 'Accepted'";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $worker_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Payments | POP!Work</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/sidebar.css">
  <style>
    :root {
      --primary: #8a1538;
      --primary-dark: #6d1028;
      --primary-light: #c91f4d;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --info: #3b82f6;
      --purple: #8b5cf6;
      --text-primary: #1f2937;
      --text-secondary: #6b7280;
      --border: #e5e7eb;
      --bg-light: #f9fafb;
      --white: #ffffff;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
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

    .main-content {
      margin-left: 280px;
      transition: margin-left 0.3s ease;
      min-height: 100vh;
      position: relative;
      z-index: 1;
    }

    /* Header - Matching Other Pages */
    .dashboard-header {
      background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
      padding: 32px 40px;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.15);
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .header-content {
      max-width: 100%;
    }

    .header-title h1 {
      font-size: 32px;
      font-weight: 800;
      color: white;
      margin-bottom: 8px;
    }

    .header-title p {
      font-size: 16px;
      color: rgba(255, 255, 255, 0.95);
      font-weight: 500;
    }

    /* Stats Section */
    .stats-section {
      padding: 10px 20px;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 10px;
      max-width: 100%;
    }

    .stat-card {
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 5px;
      display: flex;
      align-items: center;
      gap: 10px;
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
      border-color: var(--primary);
    }

    .stat-icon {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      background: linear-gradient(135deg, rgba(138, 21, 56, 0.1), rgba(201, 31, 77, 0.1));
      color: var(--primary);
      flex-shrink: 0;
    }

    .stat-icon i {
      font-size: 24px;
    }

    .stat-info {
      flex: 1;
    }

    .stat-value {
      font-size: 28px;
      font-weight: 800;
      color: var(--text-primary);
      line-height: 1;
      margin-bottom: 6px;
    }

    .stat-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Content Wrapper */
    .content-wrapper {
      padding: 32px 40px;
    }

    /* Filter Section */
    .filter-section {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 24px;
      border-radius: 16px;
      margin-bottom: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 2px solid #e4e6eb;
    }

    .filter-controls {
      display: flex;
      gap: 16px;
      align-items: end;
      flex-wrap: wrap;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
      flex: 1;
      min-width: 200px;
    }

    .filter-group label {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .filter-select {
      padding: 10px 14px;
      border: 2px solid #e4e6eb;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      background: white;
      transition: all 0.2s;
      cursor: pointer;
    }

    .filter-select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
    }

    .filter-btn {
      padding: 10px 24px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 130px;
      white-space: nowrap;
    }

    .filter-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3);
    }

    /* Jobs Grid */
    .jobs-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
      gap: 24px;
    }

    .job-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 2px solid #e4e6eb;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .job-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, #8a1538, #c91f4d);
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .job-card:hover::before {
      transform: scaleX(1);
    }

    .job-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(138, 21, 56, 0.15);
      border-color: var(--primary);
    }

    .job-card-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 16px;
      padding-bottom: 16px;
      border-bottom: 2px solid #f8f9fa;
    }

    .employer-info {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
    }

    .employer-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 18px;
      flex-shrink: 0;
    }

    .employer-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .employer-details h3 {
      font-size: 15px;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 4px;
    }

    .employer-details p {
      font-size: 13px;
      color: var(--text-secondary);
    }

    .job-details {
      margin-bottom: 20px;
    }

    .job-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 12px;
      line-height: 1.3;
    }

    .job-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 12px;
    }

    .meta-item {
      font-size: 13px;
      color: var(--text-secondary);
      display: flex;
      align-items: center;
      gap: 6px;
      background: #f8f9fa;
      padding: 6px 12px;
      border-radius: 8px;
    }

    .meta-item i {
      color: var(--primary);
      font-size: 12px;
    }

    /* Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .badge.active {
      background: linear-gradient(135deg, #dbeafe, #bfdbfe);
      color: #1e40af;
      border: 1px solid #93c5fd;
    }

    .badge.completed {
      background: linear-gradient(135deg, #d1fae5, #a7f3d0);
      color: #065f46;
      border: 1px solid #6ee7b7;
    }

    .badge.pending {
      background: linear-gradient(135deg, #fef3c7, #fde68a);
      color: #92400e;
      border: 1px solid #fcd34d;
      animation: pulse-badge 2s infinite;
    }

    .badge.paid {
      background: linear-gradient(135deg, #d1fae5, #a7f3d0);
      color: #065f46;
      border: 1px solid #6ee7b7;
    }

    @keyframes pulse-badge {
      0%, 100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.4); }
      50% { box-shadow: 0 0 0 6px rgba(251, 191, 36, 0); }
    }

    /* Earnings Info */
    .earnings-info {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-bottom: 16px;
      padding: 16px;
      background: linear-gradient(135deg, #f8f9fa, #ffffff);
      border-radius: 12px;
      border: 1px solid #e4e6eb;
    }

    .info-box {
      text-align: center;
      padding: 12px;
      background: white;
      border-radius: 10px;
      border: 2px solid #e4e6eb;
      transition: all 0.2s;
    }

    .info-box:hover {
      border-color: var(--primary);
      transform: translateY(-2px);
    }

    .info-value {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 4px;
    }

    .info-value.highlight {
      color: var(--success);
      font-size: 22px;
    }

    .info-label {
      font-size: 11px;
      color: var(--text-secondary);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    /* Payment Details */
    .payment-details {
      padding: 16px;
      background: linear-gradient(135deg, #ecfdf5, #d1fae5);
      border: 2px solid #6ee7b7;
      border-radius: 12px;
      margin-bottom: 16px;
    }

    .payment-details.pending {
      background: linear-gradient(135deg, #fef3c7, #fde68a);
      border-color: #fcd34d;
    }

    .payment-details h4 {
      font-size: 13px;
      font-weight: 700;
      color: #065f46;
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .payment-details.pending h4 {
      color: #92400e;
    }

    .payment-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }

    .payment-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: white;
      padding: 10px 12px;
      border-radius: 8px;
      border: 1px solid #e4e6eb;
    }

    .payment-item-label {
      font-size: 12px;
      color: var(--text-secondary);
      font-weight: 600;
    }

    .payment-item-value {
      font-size: 14px;
      font-weight: 700;
      color: var(--text-primary);
    }

    /* Notes Section */
    .notes-section {
      padding: 14px 16px;
      background: linear-gradient(135deg, #fef3c7, #fde68a);
      border-left: 4px solid #fbbf24;
      border-radius: 8px;
      margin-top: 12px;
    }

    .notes-title {
      font-size: 12px;
      font-weight: 700;
      color: #92400e;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .notes-content {
      font-size: 13px;
      color: #78350f;
      line-height: 1.5;
    }

    /* Action Buttons */
    .action-buttons {
      display: flex;
      gap: 10px;
      margin-top: 16px;
    }

    .btn {
      flex: 1;
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
    }

    .btn-secondary {
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 80px 20px;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 2px solid #e4e6eb;
    }

    .empty-state i {
      font-size: 80px;
      color: #e4e6eb;
      margin-bottom: 20px;
    }

    .empty-state h3 {
      font-size: 24px;
      color: var(--text-primary);
      margin-bottom: 12px;
      font-weight: 700;
    }

    .empty-state p {
      font-size: 16px;
      color: var(--text-secondary);
    }

    /* Responsive Design */

    /* Payment Status Box */
    .payment-status-box {
      padding: 16px;
      border-radius: 12px;
      margin-bottom: 16px;
      border: 2px solid;
    }

    .payment-status-box.paid {
      background: linear-gradient(135deg, #ecfdf5, #d1fae5);
      border-color: #6ee7b7;
    }

    .payment-status-box.pending {
      background: linear-gradient(135deg, #fef3c7, #fde68a);
      border-color: #fcd34d;
    }

    .status-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
    }

    .status-icon {
      font-size: 20px;
    }

    .status-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--text-primary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .status-message {
      font-size: 13px;
      color: var(--text-secondary);
      line-height: 1.6;
    }

    .status-message strong {
      color: var(--text-primary);
      font-weight: 700;
    }
    @media (max-width: 1200px) {
      .jobs-grid {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      }
    }

    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
      }

      .dashboard-header {
        padding: 20px;
      }

      .header-title h1 {
        font-size: 24px;
      }

      .header-title p {
        font-size: 14px;
      }

      .stats-section {
        padding: 20px;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .content-wrapper {
        padding: 20px;
      }

      .filter-controls {
        flex-direction: column;
      }

      .filter-group {
        width: 100%;
        min-width: unset;
      }

      .jobs-grid {
        grid-template-columns: 1fr;
      }

      .earnings-info {
        grid-template-columns: repeat(2, 1fr);
      }

      .payment-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .earnings-info {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <?php 
    $page_title = "My Payments";
    include 'includes/sidebar.php';
  ?>

  <div class="main-content">
    <div class="dashboard-header">
      <div class="header-content">
        <div class="header-title">
          <h1>My Payments</h1>
          <p>Track your earnings and payment status</p>
        </div>
      </div>
    </div>

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
            <div class="stat-value"><?= number_format($stats['total_hours_all'] ?? 0, 1); ?>H</div>
            <div class="stat-label">Hours Worked</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
          <div class="stat-info">
            <div class="stat-value">RM <?= number_format($stats['pending_amount'] ?? 0, 0); ?></div>
            <div class="stat-label">Pending</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-check"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['paid_count'] ?? 0); ?></div>
            <div class="stat-label">Paid Jobs</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-wallet"></i></div>
          <div class="stat-info">
            <div class="stat-value">RM <?= number_format($stats['paid_amount'] ?? 0, 0); ?></div>
            <div class="stat-label">Total Earned</div>
          </div>
        </div>
      </div>
    </div>

    <div class="content-wrapper">
      
      <div class="filter-section">
        <form method="GET">
          <div class="filter-controls">
            <div class="filter-group">
              <label>Job Status</label>
              <select name="status" class="filter-select">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
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
                <i class="fas fa-search"></i> Apply
              </button>
            </div>
          </div>
        </form>
      </div>

      <div class="jobs-grid">
        <?php if ($result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <?php
              $calculated_payment = $row['total_work_hours'] * $row['pay_rate'];
              $is_completed = $row['job_completed'];
              $is_paid = $row['payment_status'] === 'paid';
              $final_amount = $is_paid ? $row['payment_amount'] : $calculated_payment;
            ?>
            <div class="job-card">
              <div class="job-card-header">
                <div class="employer-info">
                  <div class="employer-avatar">
                    <?php if (!empty($row['employer_photo']) && file_exists($row['employer_photo'])): ?>
                      <img src="<?= htmlspecialchars($row['employer_photo']) ?>" alt="Employer">
                    <?php else: ?>
                      <?php 
                        $employer_display = $row['employer_name'] ?? $row['employer_email'];
                        echo strtoupper(substr($employer_display, 0, 1));
                      ?>
                    <?php endif; ?>
                  </div>
                  <div class="employer-details">
                    <h3><?= htmlspecialchars($row['employer_name'] ?? 'Employer') ?></h3>
                    <p><?= htmlspecialchars($row['employer_email']) ?></p>
                  </div>
                </div>
              </div>

              <div class="job-details">
                <div class="job-title"><?= htmlspecialchars($row['job_title']) ?></div>
                <div class="job-meta">
                  <span class="meta-item"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($row['location']) ?></span>
                  <span class="meta-item"><i class="fas fa-tag"></i> <?= htmlspecialchars($row['category']) ?></span>
                  <span class="badge <?= $is_completed ? 'completed' : 'active' ?>">
                    <?= $is_completed ? '✓ Completed' : '● Active' ?>
                  </span>
                  <span class="badge <?= $is_paid ? 'paid' : 'pending' ?>">
                    <?= $is_paid ? '✓ Paid' : '⏱ Pending' ?>
                  </span>
                </div>
              </div>

              <div class="earnings-info">
                <div class="info-box">
                  <div class="info-value"><?= number_format($row['total_work_hours'] ?? 0, 1) ?>h</div>
                  <div class="info-label">Hours</div>
                </div>
                <div class="info-box">
                  <div class="info-value"><?= number_format($row['total_sessions'] ?? 0) ?></div>
                  <div class="info-label">Sessions</div>
                </div>
                <div class="info-box">
                  <div class="info-value">RM <?= number_format($row['pay_rate'], 2) ?></div>
                  <div class="info-label">Per Hour</div>
                </div>
                <div class="info-box" style="background: linear-gradient(135deg, rgba(39,174,96,0.1), rgba(46,204,113,0.1));">
                  <div class="info-value" style="color: var(--success);">RM <?= number_format($final_amount, 2) ?></div>
                  <div class="info-label"><?= $is_paid ? 'Total Paid' : 'Expected' ?></div>
                </div>
              </div>

              <?php if ($is_completed): ?>
                <?php if ($is_paid): ?>
                  <div class="payment-status-box paid">
                    <div class="status-header">
                      <span class="status-icon">✅</span>
                      <span class="status-title">Payment Received</span>
                    </div>
                    <div class="status-message">
                      Payment of <strong>RM <?= number_format($row['payment_amount'], 2) ?></strong> was processed on <strong><?= date('F j, Y', strtotime($row['payment_date'])) ?></strong>
                      <?php if ($row['payment_method']): ?>
                        via <strong><?= ucwords(str_replace('_', ' ', $row['payment_method'])) ?></strong>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php if ($row['employer_notes']): ?>
                    <div class="notes-section">
                      <div class="notes-title"><i class="fas fa-sticky-note"></i> Employer Notes</div>
                      <div class="notes-content"><?= nl2br(htmlspecialchars($row['employer_notes'])) ?></div>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="payment-status-box pending">
                    <div class="status-header">
                      <span class="status-icon">⏳</span>
                      <span class="status-title">Payment Pending</span>
                    </div>
                    <div class="status-message">
                      Job completed on <strong><?= $row['completion_date'] ? date('F j, Y', strtotime($row['completion_date'])) : 'recently' ?></strong>. 
                      Expected payment: <strong>RM <?= number_format($calculated_payment, 2) ?></strong>.
                      Waiting for employer to process payment.
                    </div>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <div class="payment-status-box pending">
                  <div class="status-header">
                    <span class="status-icon">🏃</span>
                    <span class="status-title">Job In Progress</span>
                  </div>
                  <div class="status-message">
                    This job is still active. Current hours: <strong><?= number_format($row['total_work_hours'], 1) ?>h</strong>.
                    Expected payment so far: <strong>RM <?= number_format($calculated_payment, 2) ?></strong>.
                  </div>
                </div>
              <?php endif; ?>

              <div class="action-buttons">
                <a href="worker_attendance_history.php?job_id=<?= $row['job_id'] ?>" class="btn btn-secondary" title="View attendance history">
                  <i class="fas fa-history"></i> View Attendance History
                </a>
                <?php if ($is_completed && $is_paid): ?>
                  <a href="payment_receipt.php?application_id=<?= $row['application_id'] ?>" class="btn btn-primary" title="View payment receipt">
                    <i class="fas fa-file-invoice-dollar"></i> View Receipt
                  </a>
                <?php endif; ?>
              </div>
              
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-wallet"></i>
            <h3>No Payment Records</h3>
            <p>You don't have any accepted jobs yet. Start applying to jobs to see your payment history here.</p>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

</body>
</html>