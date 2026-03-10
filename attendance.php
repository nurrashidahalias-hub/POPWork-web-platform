<?php
session_start();
include("backend/db_connect.php");

// Ensure only workers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'worker') {
  header("Location: login.html");
  exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$user_sql = "SELECT p.name, u.email FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'] ?? explode('@', $user_data['email'])[0];

// Get unread messages count for sidebar
$unread_query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'];

// Get accepted jobs with current clock status
$sql = "SELECT 
    a.application_id,
    a.job_id,
    a.status,
    a.total_work_hours,
    a.last_clock_in,
    j.title,
    j.location,
    j.pay_rate,
    j.category,
    j.employer_id,
    j.description,
    j.require_onsite_attendance,
    j.job_latitude,
    j.job_longitude,
    j.geofence_radius,
    u.email AS employer_email,
    p.name AS employer_name,
    p.photo_url AS employer_photo,
    (SELECT attendance_id FROM attendance 
     WHERE application_id = a.application_id 
     AND clock_out_time IS NULL 
     ORDER BY clock_in_time DESC LIMIT 1) as active_attendance_id,
    (SELECT clock_in_time FROM attendance 
     WHERE application_id = a.application_id 
     AND clock_out_time IS NULL 
     ORDER BY clock_in_time DESC LIMIT 1) as current_clock_in
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN users u ON j.employer_id = u.user_id
    LEFT JOIN profiles p ON j.employer_id = p.user_id
    WHERE a.user_id = ? AND a.status = 'Accepted'
    ORDER BY a.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Get attendance statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT a.attendance_id) as total_shifts,
    SUM(a.total_hours) as total_hours_worked,
    COUNT(CASE WHEN a.clock_out_time IS NULL THEN 1 END) as active_shifts
    FROM attendance a
    WHERE a.user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance | POP!Work</title>
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
      --danger: #ef4444;
      --info: #3b82f6;
      --warning: #f59e0b;
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
    /* Header */
    /* Header */
    .dashboard-header {
      background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
      border: none;
      padding: 24px 40px;
      position: relative;
      z-index: 10;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.15);
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .header-content {
      max-width: 100%;
      margin: 0 auto;
    }

    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 32px;
    }

    .header-left {
      flex: 0 0 auto;
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

    /* Header Right Section - Clock and Actions */
    .header-right {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 20px;
    }

    /* Live Clock Widget on Right */
    .live-clock-widget {
      display: flex;
      align-items: center;
      gap: 14px;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 12px 20px;
      border-radius: 16px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    }

    .clock-icon {
      font-size: 32px;
      color: rgba(255, 255, 255, 0.95);
      animation: pulse-icon 2s infinite;
    }

    @keyframes pulse-icon {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.8; transform: scale(1.05); }
    }

    .clock-details {
      text-align: left;
    }

    .current-time {
      font-size: 28px;
      font-weight: 800;
      color: white;
      line-height: 1;
      font-variant-numeric: tabular-nums;
      letter-spacing: -0.5px;
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .current-date {
      font-size: 12px;
      color: rgba(255, 255, 255, 0.9);
      font-weight: 500;
      margin-top: 2px;
    }

    /* Active Shifts Badge */
    .active-shifts-badge {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(16, 185, 129, 0.2);
      border: 2px solid rgba(16, 185, 129, 0.6);
      padding: 8px 16px;
      border-radius: 20px;
      backdrop-filter: blur(10px);
    }

    .pulse-dot {
      width: 10px;
      height: 10px;
      background: #10b981;
      border-radius: 50%;
      animation: pulse-dot 2s infinite;
      box-shadow: 0 0 12px #10b981;
    }

    @keyframes pulse-dot {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.2); opacity: 0.7; }
    }

    .badge-text {
      color: white;
      font-weight: 600;
      font-size: 14px;
    }

   /* Stats Section */
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
      text-align:left;
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

    /* Jobs Grid */
    .content-section {
      padding: 32px 40px;
      max-width: 100%;
      margin: 0 auto;
    }

    .section-header {
      margin-bottom: 24px;
    }

    .section-title {
      font-size: 24px;
      font-weight: 700;
      color: var(--bg-light);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .jobs-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
      gap: 24px;
    }

    /* Job Card - Professional Design */
    .job-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 0;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 2px solid #e4e6eb;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
    }

    .job-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 32px rgba(138, 21, 56, 0.15);
      border-color: #8a1538;
    }

    .job-card-header {
      background: linear-gradient(135deg, #8a1538, #c91f4d);
      padding: 24px;
      color: white;
      position: relative;
      overflow: hidden;
    }

    .job-card-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 200px;
      height: 200px;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      border-radius: 50%;
    }

    .job-header-content {
      position: relative;
      z-index: 2;
    }

    .job-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 8px;
      color: white;
    }

    .employer-info {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 12px;
    }

    .employer-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 16px;
      border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .employer-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .employer-details {
      flex: 1;
    }

    .employer-name {
      font-weight: 600;
      font-size: 14px;
      color: rgba(255, 255, 255, 0.95);
    }

    .job-meta {
      display: flex;
      gap: 16px;
      font-size: 13px;
      color: rgba(255, 255, 255, 0.8);
      margin-top: 4px;
    }

    .job-meta-item {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .job-card-body {
      padding: 24px;
    }

    .clock-status {
      margin-bottom: 20px;
      padding: 16px;
      background: linear-gradient(135deg, #f8f9fa, #ffffff);
      border-radius: 12px;
      border: 2px solid #e4e6eb;
    }

    .status-clocked-in {
      background: linear-gradient(135deg, #ecfdf5, #d1fae5);
      border-color: #10b981;
    }

    .status-clocked-out {
      background: linear-gradient(135deg, #fef3f2, #fee2e2);
      border-color: #ef4444;
    }

    .clock-status-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .status-label {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #65676b;
    }

    .status-badge {
      padding: 6px 16px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    .status-badge.active {
      background: #10b981;
      color: white;
      box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
      animation: pulse-badge 2s infinite;
    }

    .status-badge.inactive {
      background: #ef4444;
      color: white;
    }

    @keyframes pulse-badge {
      0%, 100% { box-shadow: 0 0 20px rgba(16, 185, 129, 0.3); }
      50% { box-shadow: 0 0 30px rgba(16, 185, 129, 0.5); }
    }

    .clock-time-info {
      font-size: 14px;
      color: #1c1e21;
      font-weight: 600;
    }

    .clock-time-info i {
      margin-right: 8px;
      color: #8a1538;
    }

    .elapsed-time {
      margin-top: 8px;
      font-size: 24px;
      font-weight: 800;
      color: #8a1538;
      font-variant-numeric: tabular-nums;
    }

    .job-info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 20px;
    }

    .info-item {
      background: #f8f9fa;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #e4e6eb;
    }

    .info-label {
      font-size: 11px;
      color: #65676b;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .info-value {
      font-size: 16px;
      font-weight: 700;
      color: #1c1e21;
    }

    /* Clock In/Out Buttons */
    .clock-actions {
      display: flex;
      gap: 12px;
    }

    .clock-btn {
      flex: 1;
      padding: 16px;
      border-radius: 12px;
      border: none;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      position: relative;
      overflow: hidden;
    }

    .clock-btn i {
      font-size: 20px;
    }

    .clock-in-btn {
      background: linear-gradient(135deg, #10b981, #059669);
      color: white;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .clock-in-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
    }

    .clock-in-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      transition: left 0.5s;
    }

    .clock-in-btn:hover::before {
      left: 100%;
    }

    .clock-out-btn {
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: white;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .clock-out-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
    }

    .clock-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none !important;
    }

    /* Empty State */
    .empty-state {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 60px 40px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 2px solid #e4e6eb;
    }

    .empty-state-icon {
      font-size: 72px;
      color: #e4e6eb;
      margin-bottom: 20px;
    }

    .empty-state h3 {
      font-size: 24px;
      font-weight: 700;
      color: #1c1e21;
      margin-bottom: 12px;
    }

    .empty-state p {
      font-size: 16px;
      color: #65676b;
      margin-bottom: 24px;
    }

    .empty-state-btn {
      padding: 14px 32px;
      background: linear-gradient(135deg, #8a1538, #c91f4d);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }

    .empty-state-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(138, 21, 56, 0.3);
    }


    /* Spinner for loading state */
    .spinner {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Alert Messages */
    .alert {
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      position: relative;
    }

    .alert i {
      font-size: 20px;
    }

    .alert-error {
      background: linear-gradient(135deg, #fee2e2, #fecaca);
      border: 2px solid #ef4444;
      color: #991b1b;
    }

    .alert-error i {
      color: #ef4444;
    }

    .alert-success {
      background: linear-gradient(135deg, #d1fae5, #a7f3d0);
      border: 2px solid #10b981;
      color: #065f46;
    }

    .alert-success i {
      color: #10b981;
    }

    .alert-close {
      margin-left: auto;
      cursor: pointer;
      font-size: 20px;
      opacity: 0.7;
      transition: opacity 0.2s;
    }

    .alert-close:hover {
      opacity: 1;
    }
    /* Responsive Design */

    /* Responsive Design */

    /* Responsive Design */
    @media (max-width: 1200px) {
      .header-right {
        flex-wrap: wrap;
        gap: 12px;
      }
      
      .live-clock-widget {
        padding: 10px 16px;
      }
      
      .current-time {
        font-size: 24px;
      }
      
      .clock-icon {
        font-size: 28px;
      }
    }

    @media (max-width: 992px) {
      .jobs-grid {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      }

      .header-top {
        flex-wrap: wrap;
      }

      .header-left {
        flex: 1 1 100%;
      }

      .header-right {
        flex: 1 1 100%;
        justify-content: space-between;
        margin-top: 16px;
      }
    }

    @media (max-width: 768px) {
      .dashboard-header {
        padding: 20px;
      }

      .header-top {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
      }

      .header-left {
        flex: 1 1 100%;
      }

      .header-right {
        flex: 1 1 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
      }

      .live-clock-widget {
        justify-content: center;
      }

      .active-shifts-badge {
        justify-content: center;
      }

      .header-actions {
        width: 100%;
        flex-direction: column;
      }

      .header-btn {
        width: 100%;
        justify-content: center;
      }

      .current-time {
        font-size: 24px;
      }

      .clock-icon {
        font-size: 24px;
      }

      .stats-section {
        padding: 20px;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .content-section {
        padding: 20px;
      }

      .section-title {
        font-size: 20px;
      }

      .jobs-grid {
        grid-template-columns: 1fr;
      }

      .job-info-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 480px) {
      .current-time {
        font-size: 20px;
      }

      .current-date {
        font-size: 10px;
      }

      .clock-icon {
        font-size: 20px;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }

      .job-title {
        font-size: 18px;
      }

      .clock-btn {
        padding: 14px;
        font-size: 14px;
      }

      .live-clock-widget {
        gap: 10px;
        padding: 8px 14px;
      }
    }

  </style>
</head>
<body>
  <div class="dashboard-wrapper">
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
      <div class="dashboard-header">
        <div class="header-content">
          <div class="header-top">
            <div class="header-left">
              <div class="header-title">
                <h1>⏰ Attendance Tracker</h1>
                <p>Clock in and out for your accepted jobs</p>
              </div>
            </div>
            
            <div class="header-right">
              <div class="live-clock-widget">
                <div class="clock-icon">
                  <i class="fa-solid fa-clock"></i>
                </div>
                <div class="clock-details">
                  <div class="current-time" id="currentTime">00:00:00</div>
                  <div class="current-date" id="currentDate">Loading...</div>
                </div>
              </div>
              
              <div class="active-shifts-badge">
                <div class="pulse-dot"></div>
                <span class="badge-text">
                  <span id="activeShiftsCount"><?= $stats['active_shifts'] ?? 0; ?></span> Active
                </span>
              </div>
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
                <div class="stat-label">Total Shifts</div>
                <div class="stat-value"><?= $stats['total_shifts'] ?? 0; ?></div>
              </div>
              <i class="fa-solid fa-calendar-check stat-icon"></i>
            </div>
            <div class="stat-card">
              <div class="stat-card-content">
                <div class="stat-label">Hours Worked</div>
                <div class="stat-value"><?= number_format($stats['total_hours_worked'] ?? 0, 1); ?></div>
              </div>
              <i class="fa-solid fa-clock stat-icon"></i>
            </div>
            <div class="stat-card">
              <div class="stat-card-content">
                <div class="stat-label">Active Now</div>
                <div class="stat-value"><?= $stats['active_shifts'] ?? 0; ?></div>
              </div>
              <i class="fa-solid fa-user-clock stat-icon"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Section -->
      <div class="content-section">
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-error">
            <i class="fa-solid fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($_SESSION['error']); ?></span>
            <span class="alert-close" onclick="this.parentElement.remove()">✕</span>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success">
            <i class="fa-solid fa-check-circle"></i>
            <span><?= htmlspecialchars($_SESSION['success']); ?></span>
            <span class="alert-close" onclick="this.parentElement.remove()">✕</span>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="section-header">
          <h2 class="section-title">
            <i class="fa-solid fa-briefcase"></i>
            My Accepted Jobs
          </h2>
        </div>

        <!-- Jobs Grid -->
        <?php if ($result->num_rows > 0): ?>
          <div class="jobs-grid">
            <?php while ($row = $result->fetch_assoc()): ?>
              <?php
                $is_clocked_in = !is_null($row['active_attendance_id']);
                $clock_in_time = $row['current_clock_in'];
                $requires_location = $row['require_onsite_attendance'] == 1;
                $geofence_radius = $row['geofence_radius'] ?? 100;
              ?>
              <div class="job-card">
                <!-- Job Card Header -->
                <div class="job-card-header">
                  <div class="job-header-content">
                    <h3 class="job-title"><?= htmlspecialchars($row['title']); ?></h3>
                    <div class="employer-info">
                      <div class="employer-avatar">
                        <?php if (!empty($row['employer_photo']) && file_exists($row['employer_photo'])): ?>
                          <img src="<?= htmlspecialchars($row['employer_photo']); ?>" alt="Employer">
                        <?php else: ?>
                          <?= strtoupper(substr($row['employer_name'] ?? $row['employer_email'], 0, 1)); ?>
                        <?php endif; ?>
                      </div>
                      <div class="employer-details">
                        <div class="employer-name"><?= htmlspecialchars($row['employer_name'] ?? $row['employer_email']); ?></div>
                        <div class="job-meta">
                          <div class="job-meta-item">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= htmlspecialchars($row['location']); ?>
                          </div>
                          <div class="job-meta-item">
                            <i class="fa-solid fa-tag"></i>
                            <?= htmlspecialchars($row['category']); ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Job Card Body -->
                <div class="job-card-body">
                  <!-- Clock Status -->
                  <div class="clock-status <?= $is_clocked_in ? 'status-clocked-in' : 'status-clocked-out'; ?>">
                    <div class="clock-status-header">
                      <div class="status-label">Current Status</div>
                      <div class="status-badge <?= $is_clocked_in ? 'active' : 'inactive'; ?>">
                        <i class="fa-solid fa-circle"></i>
                        <?= $is_clocked_in ? 'Clocked In' : 'Clocked Out'; ?>
                      </div>
                    </div>
                    <?php if ($is_clocked_in): ?>
                      <div class="clock-time-info">
                        <i class="fa-solid fa-sign-in-alt"></i>
                        Clocked in at: <?= date('h:i A', strtotime($clock_in_time)); ?>
                      </div>
                      <div class="elapsed-time" id="elapsed_<?= $row['application_id']; ?>" data-clockin="<?= strtotime($clock_in_time); ?>">
                        00:00:00
                      </div>
                    <?php else: ?>
                      <div class="clock-time-info">
                        <i class="fa-solid fa-info-circle"></i>
                        Ready to clock in
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Job Info Grid -->
                  <div class="job-info-grid">
                    <div class="info-item">
                      <div class="info-label">Pay Rate</div>
                      <div class="info-value">RM <?= number_format($row['pay_rate'], 2); ?></div>
                    </div>
                    <div class="info-item">
                      <div class="info-label">Total Hours</div>
                      <div class="info-value"><?= number_format($row['total_work_hours'] ?? 0, 2); ?>H</div>
                    </div>
                  </div>

                  <!-- Geofence Notice -->
                  <?php if ($requires_location && !$is_clocked_in): ?>
                    <div class="info-item" style="grid-column: 1 / -1; background: #fef3cd; border-color: #ffc107; margin-bottom: 16px;">
                      <div class="info-label" style="color: #856404;">
                        <i class="fa-solid fa-location-crosshairs"></i> Location Required
                      </div>
                      <div class="info-value" style="color: #856404; font-size: 13px; font-weight: 600;">
                        Must be within <?= $geofence_radius; ?>m of job site
                      </div>
                    </div>
                  <?php endif; ?>

                  <!-- Clock Actions -->
                  <div class="clock-actions">
                    <?php if (!$is_clocked_in): ?>
                      <!-- Clock In Form -->
                      <form action="backend/process_attendance.php" method="POST" 
                            class="clock-form" 
                            style="flex: 1; margin: 0;"
                            data-requires-location="<?= $requires_location ? '1' : '0'; ?>"
                            data-job-lat="<?= $row['job_latitude'] ?? ''; ?>"
                            data-job-lng="<?= $row['job_longitude'] ?? ''; ?>"
                            data-geofence-radius="<?= $geofence_radius; ?>">
                        <input type="hidden" name="action" value="clock_in">
                        <input type="hidden" name="application_id" value="<?= $row['application_id']; ?>">
                        <input type="hidden" name="job_id" value="<?= $row['job_id']; ?>">
                        <input type="hidden" name="employer_id" value="<?= $row['employer_id']; ?>">
                        <button type="submit" class="clock-btn clock-in-btn">
                          <i class="fa-solid fa-sign-in-alt"></i>
                          Clock In
                        </button>
                      </form>
                      <button class="clock-btn view-history-btn" onclick="window.location.href='worker_attendance_history.php?job_id=<?= $row['job_id']; ?>'">
                        <i class="fa-solid fa-history"></i>
                        History
                      </button>
                    <?php else: ?>
                      <!-- Clock Out Form -->
                      <form action="backend/process_attendance.php" method="POST" 
                            class="clock-form" 
                            style="flex: 1; margin: 0;"
                            data-requires-location="0"
                            data-job-requires-location="<?= $requires_location ? '1' : '0'; ?>"
                            data-job-lat="<?= $row['job_latitude'] ?? ''; ?>"
                            data-job-lng="<?= $row['job_longitude'] ?? ''; ?>"
                            data-geofence-radius="<?= $geofence_radius; ?>">
                        <input type="hidden" name="action" value="clock_out">
                        <input type="hidden" name="attendance_id" value="<?= $row['active_attendance_id']; ?>">
                        <input type="hidden" name="application_id" value="<?= $row['application_id']; ?>">
                        <button type="submit" class="clock-btn clock-out-btn">
                          <i class="fa-solid fa-sign-out-alt"></i>
                          Clock Out
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-state-icon">
              <i class="fa-solid fa-briefcase"></i>
            </div>
            <h3>No Accepted Jobs Yet</h3>
            <p>You don't have any accepted jobs at the moment. Apply to jobs to start tracking your attendance.</p>
            <a href="jobs.php" class="empty-state-btn">
              <i class="fa-solid fa-search"></i>
              Browse Jobs
            </a>
          </div>
        <?php endif; ?>
      </div>
  <script>
    // Update duration for clocked in jobs
    function updateDurations() {
      document.querySelectorAll('[id^="duration-"]').forEach(element => {
        const startTime = parseInt(element.dataset.start);
        const now = Math.floor(Date.now() / 1000);
        const diff = now - startTime;
        
        const hours = Math.floor(diff / 3600);
        const minutes = Math.floor((diff % 3600) / 60);
        
        element.textContent = `Duration: ${hours}h ${minutes}m`;
      });
    }

    // Update every minute
    setInterval(updateDurations, 60000);
    // Initial update
    updateDurations();

    // ========================================
    // GEOLOCATION HANDLING
    // ========================================

    // Calculate distance between two coordinates (Haversine formula)
    function calculateDistance(lat1, lon1, lat2, lon2) {
      const R = 6371000; // Earth radius in meters
      const dLat = (lat2 - lat1) * Math.PI / 180;
      const dLon = (lon2 - lon1) * Math.PI / 180;
      
      const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon/2) * Math.sin(dLon/2);
      
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
      const distance = R * c;
      
      return distance; // Returns distance in meters
    }

    // Get user's current location
    function getCurrentLocation() {
      return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
          reject(new Error('Geolocation is not supported by your browser'));
          return;
        }

        navigator.geolocation.getCurrentPosition(
          (position) => {
            resolve({
              latitude: position.coords.latitude,
              longitude: position.coords.longitude,
              accuracy: position.coords.accuracy
            });
          },
          (error) => {
            let errorMessage = 'Location access denied';
            switch(error.code) {
              case error.PERMISSION_DENIED:
                errorMessage = 'Location access denied. Please enable location services in your browser settings.';
                break;
              case error.POSITION_UNAVAILABLE:
                errorMessage = 'Location information unavailable. Please try again.';
                break;
              case error.TIMEOUT:
                errorMessage = 'Location request timed out. Please try again.';
                break;
            }
            reject(new Error(errorMessage));
          },
          {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
          }
        );
      });
    }

      // Handle form submission with geolocation
      async function handleClockFormSubmit(event, form) {
        const requiresLocation = form.dataset.requiresLocation === '1';
        const action = form.querySelector('input[name="action"]').value;
        const isClockOut = action === 'clock_out';
        
        // For clock-out, check if the job requires location
        let needsLocationCheck = requiresLocation;
        if (isClockOut) {
          needsLocationCheck = form.dataset.jobRequiresLocation === '1';
        }
        
        if (!needsLocationCheck) {
          // No location required, allow normal submission
          return true;
        }

        event.preventDefault();
        
        const button = form.querySelector('button[type="submit"]');
        const originalHtml = button.innerHTML;
        
        // Disable button and show loading state
        button.disabled = true;
        button.innerHTML = '<span class="spinner"></span> Verifying Location...';

        try {
          // Get current location
          const location = await getCurrentLocation();
          
          // Get job location from form data
          const jobLat = parseFloat(form.dataset.jobLat);
          const jobLng = parseFloat(form.dataset.jobLng);
          const geofenceRadius = parseFloat(form.dataset.geofenceRadius);

          // Validate job coordinates exist
          if (!jobLat || !jobLng) {
            throw new Error('Job location is not configured. Please contact the employer.');
          }

          // Calculate distance from job site
          const distance = calculateDistance(
            location.latitude,
            location.longitude,
            jobLat,
            jobLng
          );

          const actionText = isClockOut ? 'clock out' : 'clock in';
          console.log(`${actionText} - Distance from job: ${distance.toFixed(2)}m (allowed: ${geofenceRadius}m)`);

          // Check if within geofence
          if (distance > geofenceRadius) {
            throw new Error(
              `You are ${Math.round(distance)}m away from the job location. ` +
              `You must be within ${geofenceRadius}m to ${actionText}.`
            );
          }

          // Location verified, add coordinates to form
          const latInput = document.createElement('input');
          latInput.type = 'hidden';
          latInput.name = 'latitude';
          latInput.value = location.latitude;
          form.appendChild(latInput);
          
          const lngInput = document.createElement('input');
          lngInput.type = 'hidden';
          lngInput.name = 'longitude';
          lngInput.value = location.longitude;
          form.appendChild(lngInput);

          // Update button to show success
          button.innerHTML = '<i class="fa-solid fa-check"></i> Location Verified';
          
          // Submit form
          setTimeout(() => {
            form.submit();
          }, 500);

        } catch (error) {
          // Show error and restore button
          alert(error.message);
          button.disabled = false;
          button.innerHTML = originalHtml;
        }
      }

    // Attach event listeners to all clock forms
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.clock-form').forEach(form => {
        form.addEventListener('submit', function(event) {
          handleClockFormSubmit(event, this);
        });
      });
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(() => {
      document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.3s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
      });
    }, 5000);

    // Real-time clock display
    function updateClock() {
      const now = new Date();
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const seconds = String(now.getSeconds()).padStart(2, '0');
      const timeString = `${hours}:${minutes}:${seconds}`;
      
      const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      const dateString = now.toLocaleDateString('en-US', options);
      
      document.getElementById('currentTime').textContent = timeString;
      document.getElementById('currentDate').textContent = dateString;
    }

    // Update elapsed time for clocked-in jobs
    function updateElapsedTimes() {
      const elapsedElements = document.querySelectorAll('[id^="elapsed_"]');
      elapsedElements.forEach(element => {
        const clockInTimestamp = parseInt(element.dataset.clockin);
        if (clockInTimestamp) {
          const now = Math.floor(Date.now() / 1000);
          const elapsed = now - clockInTimestamp;
          
          const hours = Math.floor(elapsed / 3600);
          const minutes = Math.floor((elapsed % 3600) / 60);
          const seconds = elapsed % 60;
          
          const timeString = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
          element.textContent = timeString;
        }
      });
    }

    // Initialize clocks
    updateClock();
    updateElapsedTimes();
    setInterval(updateClock, 1000);
    setInterval(updateElapsedTimes, 1000);
  </script>
</body>
</html>