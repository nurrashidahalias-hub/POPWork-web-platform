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
$sql = "SELECT j.job_id, j.title, j.category, j.status, j.location, j.pay_rate, j.pay_period, j.description, j.created_at, j.positions_available,
               COUNT(DISTINCT a.application_id) as applicant_count,
               COUNT(DISTINCT CASE WHEN a.status = 'Pending' THEN a.application_id END) as pending_count,
               COUNT(DISTINCT CASE WHEN a.status = 'Accepted' THEN a.application_id END) as accepted_count
        FROM jobs j
        LEFT JOIN applications a ON j.job_id = a.job_id
        WHERE j.employer_id = ?
        GROUP BY j.job_id
        ORDER BY j.created_at DESC
        LIMIT 10";
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
  <title>Employer Dashboard | POP!Work</title>
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
      --shadow-sm: 0 2px 8px rgba(0,0,0,0.06);
      --shadow-md: 0 4px 16px rgba(138, 21, 56, 0.12);
      --shadow-lg: 0 8px 24px rgba(138, 21, 56, 0.18);
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
      padding: 40px 40px 80px 40px;
      position: relative;
      overflow: hidden;
    }

    .dashboard-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      border-radius: 50%;
    }

    .header-content {
      max-width: 1600px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }

    .header-title h1 {
      font-size: 36px;
      color: var(--white);
      margin-bottom: 6px;
      font-weight: 800;
      letter-spacing: -0.5px;
    }

    .header-title p {
      color: rgba(255, 255, 255, 0.9);
      font-size: 16px;
      font-weight: 500;
    }

    /* Stats Section */
    .stats-section {
      padding: 0 40px;
      margin-top: -50px;
      position: relative;
      z-index: 10;
      margin-bottom: 32px;
    }

    .stats-container {
      max-width: 1600px;
      margin: 0 auto;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 16px;
    }

    .stat-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      padding: 20px;
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      border: 1px solid rgba(255, 255, 255, 0.5);
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
      transform: translateY(-4px);
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
      margin-bottom: 12px;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.2);
    }

    .stat-value {
      font-size: 28px;
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
      padding: 0 40px 40px 40px;
      max-width: 1600px;
      margin: 0 auto;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .section-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-dark);
    }

    .view-all-link {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.3s;
    }

    .view-all-link:hover {
      gap: 10px;
      color: var(--primary-dark);
    }

    /* Quick Actions */
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
      margin-bottom: 32px;
    }

    .action-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 12px;
      padding: 20px;
      text-decoration: none;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: var(--shadow-sm);
    }

    .action-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
      border-color: var(--primary);
    }

    .action-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: var(--white);
      flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.2);
    }

    .action-content h3 {
      font-size: 14px;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 3px;
    }

    .action-content p {
      font-size: 12px;
      color: var(--text-light);
      line-height: 1.4;
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

    .table-wrapper {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    }

    th {
      padding: 14px 20px;
      text-align: left;
      font-weight: 700;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--white);
      white-space: nowrap;
    }

    tbody tr {
      border-bottom: 1px solid var(--border-color);
      transition: all 0.2s;
      cursor: pointer;
    }

    tbody tr:hover {
      background: #fafbfc;
    }

    tbody tr:last-child {
      border-bottom: none;
    }

    td {
      padding: 16px 20px;
      vertical-align: middle;
      font-size: 13px;
    }

    /* Job Info Cell */
    .job-info-cell {
      min-width: 250px;
      max-width: 350px;
    }

    .job-title {
      font-weight: 700;
      font-size: 14px;
      color: var(--text-dark);
      margin-bottom: 6px;
      line-height: 1.4;
    }

    .job-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .meta-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11px;
      color: var(--text-light);
      background: var(--bg-light);
      padding: 3px 8px;
      border-radius: 5px;
    }

    .meta-badge i {
      color: var(--primary);
      font-size: 10px;
    }

    .category-badge {
      background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
      color: #2e7d32;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      display: inline-block;
    }

    /* Description Cell */
    .description-cell {
      max-width: 300px;
      min-width: 200px;
    }

    .description-text {
      color: var(--text-medium);
      font-size: 13px;
      line-height: 1.5;
      margin-bottom: 6px;
    }

    .read-more {
      color: var(--primary);
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s;
    }

    .read-more:hover {
      gap: 7px;
      color: var(--primary-dark);
    }

    /* Status Badge */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 6px 12px;
      border-radius: 16px;
      font-size: 11px;
      font-weight: 700;
      white-space: nowrap;
    }

    .status-badge.available {
      background: var(--success-bg);
      color: var(--success);
      border: 1px solid #c3e6cb;
    }

    .status-badge.closed {
      background: var(--danger-bg);
      color: var(--danger);
      border: 1px solid #f5c6cb;
    }

    /* Pay Rate Cell */
    .pay-cell {
      text-align: right;
      white-space: nowrap;
    }

    .pay-rate {
      font-size: 16px;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 2px;
    }

    .pay-period {
      font-size: 10px;
      color: var(--text-light);
      text-transform: uppercase;
      font-weight: 600;
    }

    /* Applicants Cell */
    .applicants-cell {
      min-width: 140px;
    }

    .applicants-stats {
      display: flex;
      gap: 12px;
      justify-content: center;
    }

    .applicant-stat {
      text-align: center;
    }

    .applicant-number {
      font-size: 18px;
      font-weight: 800;
      color: var(--text-dark);
      line-height: 1;
      margin-bottom: 3px;
    }

    .applicant-label {
      font-size: 9px;
      color: var(--text-light);
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.3px;
    }

    /* Date Cell */
    .date-cell {
      white-space: nowrap;
    }

    .date-main {
      font-weight: 600;
      color: var(--text-dark);
      font-size: 13px;
      margin-bottom: 3px;
    }

    .date-time {
      font-size: 11px;
      color: var(--text-light);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 60px 40px;
    }

    .empty-icon {
      font-size: 64px;
      color: var(--border-color);
      margin-bottom: 20px;
    }

    .empty-state h3 {
      font-size: 22px;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 10px;
    }

    .empty-state p {
      color: var(--text-light);
      font-size: 14px;
      margin-bottom: 24px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
      padding: 12px 28px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 700;
      font-size: 14px;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 16px rgba(138, 21, 56, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(138, 21, 56, 0.4);
    }

    /* Modal */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      backdrop-filter: blur(4px);
    }

    .modal-content {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(20px);
      border-radius: 16px;
      max-width: 600px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .modal-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: linear-gradient(to bottom, #ffffff 0%, #fafbfc 100%);
    }

    .modal-header h3 {
      font-size: 18px;
      font-weight: 700;
      color: var(--text-dark);
    }

    .close-btn {
      background: none;
      border: none;
      font-size: 24px;
      color: var(--text-light);
      cursor: pointer;
      transition: all 0.2s;
      padding: 0;
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
    }

    .close-btn:hover {
      background: var(--bg-light);
      color: var(--text-dark);
    }

    .modal-body {
      padding: 24px;
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
        grid-template-columns: repeat(3, 1fr);
      }

      .quick-actions {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 768px) {
      .header-title h1 {
        font-size: 28px;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .quick-actions {
        grid-template-columns: 1fr;
      }

      .table-wrapper {
        overflow-x: scroll;
      }

      table {
        min-width: 900px;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr;
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
            <h1>👋 Welcome back, <?= htmlspecialchars($user_name); ?>!</h1>
            <p>Here's what's happening with your job postings today</p>
          </div>
        </div>
      </div>

      <!-- Stats Section -->
      <div class="stats-section">
        <div class="stats-container">
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-icon">
                <i class="fas fa-briefcase"></i>
              </div>
              <div class="stat-value"><?= number_format($stats['total_jobs'] ?? 0); ?></div>
              <div class="stat-label">Total Jobs</div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
              </div>
              <div class="stat-value"><?= number_format($stats['active_jobs'] ?? 0); ?></div>
              <div class="stat-label">Active Jobs</div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <i class="fas fa-users"></i>
              </div>
              <div class="stat-value"><?= number_format($stats['total_applicants'] ?? 0); ?></div>
              <div class="stat-label">Total Applicants</div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <i class="fas fa-user-check"></i>
              </div>
              <div class="stat-value"><?= number_format($stats['hired_workers'] ?? 0); ?></div>
              <div class="stat-label">Workers Hired</div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <i class="fas fa-clock"></i>
              </div>
              <div class="stat-value"><?= number_format($stats['pending_applications'] ?? 0); ?></div>
              <div class="stat-label">Pending Reviews</div>
            </div>

            <div class="stat-card">
              <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
              </div>
              <div class="stat-value"><?= number_format($stats['closed_jobs'] ?? 0); ?></div>
              <div class="stat-label">Closed Jobs</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Section -->
      <div class="content-section">
        <!-- Quick Actions -->
        <div class="section-header">
          <h2 class="section-title">Quick Actions</h2>
        </div>
        
        <div class="quick-actions">
          <a href="job_post.php" class="action-card">
            <div class="action-icon">
              <i class="fas fa-plus"></i>
            </div>
            <div class="action-content">
              <h3>Post New Job</h3>
              <p>Create a new job listing</p>
            </div>
          </a>

          <a href="employer_jobs.php" class="action-card">
            <div class="action-icon">
              <i class="fas fa-list"></i>
            </div>
            <div class="action-content">
              <h3>Manage All Jobs</h3>
              <p>View and edit postings</p>
            </div>
          </a>

          <a href="job_completion_payment.php" class="action-card">
            <div class="action-icon">
              <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="action-content">
              <h3>Payment Management</h3>
              <p>Track worker payments</p>
            </div>
          </a>

          <a href="attendance_history.php" class="action-card">
            <div class="action-icon">
              <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="action-content">
              <h3>Attendance Records</h3>
              <p>View attendance history</p>
            </div>
          </a>
        </div>

        <!-- Recent Jobs -->
        <div class="section-header">
          <h2 class="section-title">Recent Job Postings</h2>
          <a href="employer_jobs.php" class="view-all-link">
            View All Jobs
            <i class="fas fa-arrow-right"></i>
          </a>
        </div>

        <div class="table-container">
          <?php if ($result->num_rows > 0): ?>
            <div class="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Job Details</th>
                    <th>Description</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right;">Pay Rate</th>
                    <th style="text-align: center;">Applicants</th>
                    <th>Posted Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $result->fetch_assoc()): ?>
                  <tr onclick="window.location.href='view_job.php?job_id=<?= $row['job_id']; ?>'">
                    
                    <!-- Job Details -->
                    <td class="job-info-cell">
                      <div class="job-title">
                        <?= htmlspecialchars($row['title']); ?>
                      </div>
                      <span class="category-badge"><?= htmlspecialchars($row['category']); ?></span>
                      <div class="job-meta">
                        <span class="meta-badge">
                          <i class="fas fa-map-marker-alt"></i>
                          <?= htmlspecialchars($row['location']); ?>
                        </span>
                        <span class="meta-badge">
                          <i class="fas fa-users"></i>
                          <?= $row['positions_available']; ?> pos.
                        </span>
                      </div>
                    </td>

                    <!-- Description -->
                    <td class="description-cell">
                      <div class="description-text">
                        <?= htmlspecialchars(substr($row['description'], 0, 80)) . (strlen($row['description']) > 80 ? '...' : ''); ?>
                      </div>
                      <?php if (strlen($row['description']) > 80): ?>
                      <span class="read-more" onclick="event.stopPropagation(); showFullDescription(<?= htmlspecialchars(json_encode($row['title'])); ?>, <?= htmlspecialchars(json_encode($row['description'])); ?>)">
                        Read More
                        <i class="fas fa-arrow-right"></i>
                      </span>
                      <?php endif; ?>
                    </td>

                    <!-- Status -->
                    <td style="text-align: center;">
                      <span class="status-badge <?= $row['status']; ?>">
                        <?php if ($row['status'] === 'available'): ?>
                          <i class="fas fa-check-circle"></i> Active
                        <?php else: ?>
                          <i class="fas fa-times-circle"></i> Closed
                        <?php endif; ?>
                      </span>
                    </td>

                    <!-- Pay Rate -->
                    <td class="pay-cell">
                      <div class="pay-rate">RM <?= number_format($row['pay_rate'], 2); ?></div>
                      <div class="pay-period">per <?= htmlspecialchars($row['pay_period']); ?></div>
                    </td>

                    <!-- Applicants -->
                    <td class="applicants-cell">
                      <div class="applicants-stats">
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

                    <!-- Date -->
                    <td class="date-cell">
                      <div class="date-main"><?= date('M d, Y', strtotime($row['created_at'])); ?></div>
                      <div class="date-time"><?= date('g:i A', strtotime($row['created_at'])); ?></div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
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

  <!-- Modal -->
  <div id="descriptionModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle">Job Description</h3>
        <button onclick="closeModal()" class="close-btn">&times;</button>
      </div>
      <div class="modal-body">
        <p id="modalDescription" style="white-space: pre-wrap; line-height: 1.8; color: var(--text-medium);"></p>
      </div>
    </div>
  </div>

  <script>
    function showFullDescription(title, description) {
      document.getElementById('modalTitle').textContent = title;
      document.getElementById('modalDescription').textContent = description;
      document.getElementById('descriptionModal').style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function closeModal() {
      document.getElementById('descriptionModal').style.display = 'none';
      document.body.style.overflow = 'auto';
    }

    // Close modal if user clicks outside the box
    window.onclick = function(event) {
      const modal = document.getElementById('descriptionModal');
      if (event.target == modal) {
        closeModal();
      }
    }

    // Smooth scroll on page load
    window.addEventListener('load', function() {
      setTimeout(function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }, 100);
    });
  </script>
</body>
</html>