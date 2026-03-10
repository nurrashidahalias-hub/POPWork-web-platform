<?php
session_start();
include("backend/db_connect.php");

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Get admin info
$admin_sql = "SELECT u.email, p.name FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();
$admin_name = $admin_data['name'] ?? explode('@', $admin_data['email'])[0];

// Handle actions
$message = '';
$message_type = '';

// Flag suspicious attendance
if (isset($_POST['flag_attendance'])) {
    $attendance_id = intval($_POST['attendance_id']);
    $flag_reason = trim($_POST['flag_reason']);
    
    $stmt = $conn->prepare("UPDATE attendance SET notes = CONCAT(COALESCE(notes, ''), '\n[FLAGGED by Admin ', ?, ' at ', NOW(), '] ', ?) WHERE attendance_id = ?");
    $stmt->bind_param("ssi", $admin_name, $flag_reason, $attendance_id);
    
    if ($stmt->execute()) {
        $message = "Attendance record flagged successfully!";
        $message_type = 'success';
    } else {
        $message = "Error flagging attendance.";
        $message_type = 'error';
    }
}

// Update attendance status
if (isset($_POST['update_attendance_status'])) {
    $attendance_id = intval($_POST['attendance_id']);
    $new_status = $_POST['new_status'];
    $admin_notes = trim($_POST['admin_notes']);
    
    $stmt = $conn->prepare("UPDATE attendance SET status = ?, notes = CONCAT(COALESCE(notes, ''), '\n[Admin ', ?, ' - Status changed to ', ?, '] ', ?) WHERE attendance_id = ?");
    $stmt->bind_param("ssssi", $new_status, $admin_name, $new_status, $admin_notes, $attendance_id);
    
    if ($stmt->execute()) {
        $message = "Attendance status updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Error updating attendance status.";
        $message_type = 'error';
    }
}

// Export to CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_records_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Worker', 'Job', 'Employer', 'Clock In', 'Clock Out', 'Hours', 'Location Verified', 'Distance (m)', 'Status']);
    
    $export_sql = "SELECT a.*, 
                   j.title as job_title,
                   wp.name as worker_name, wu.email as worker_email,
                   ep.name as employer_name, eu.email as employer_email
            FROM attendance a
            JOIN jobs j ON a.job_id = j.job_id
            JOIN users wu ON a.user_id = wu.user_id
            LEFT JOIN profiles wp ON wu.user_id = wp.user_id
            JOIN users eu ON a.employer_id = eu.user_id
            LEFT JOIN profiles ep ON eu.user_id = ep.user_id
            ORDER BY a.clock_in_time DESC";
    
    $export_result = $conn->query($export_sql);
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['attendance_id'],
            $row['worker_name'] ?? $row['worker_email'],
            $row['job_title'],
            $row['employer_name'] ?? $row['employer_email'],
            $row['clock_in_time'],
            $row['clock_out_time'] ?? 'Still Working',
            $row['total_hours'] ?? 'N/A',
            $row['location_verified'] ? 'Yes' : 'No',
            $row['distance_from_job_location'] ?? 'N/A',
            $row['status']
        ]);
    }
    fclose($output);
    exit();
}

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$verification_filter = isset($_GET['verification']) ? $_GET['verification'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT a.*, 
               j.title as job_title,
               j.location as job_location,
               j.latitude as job_latitude,
               j.longitude as job_longitude,
               wp.name as worker_name,
               wu.email as worker_email,
               ep.name as employer_name,
               eu.email as employer_email,
               app.application_id
        FROM attendance a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN users wu ON a.user_id = wu.user_id
        LEFT JOIN profiles wp ON wu.user_id = wp.user_id
        JOIN users eu ON a.employer_id = eu.user_id
        LEFT JOIN profiles ep ON eu.user_id = ep.user_id
        JOIN applications app ON a.application_id = app.application_id
        WHERE 1=1";

if ($status_filter != 'all') {
    $sql .= " AND a.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($verification_filter == 'verified') {
    $sql .= " AND a.location_verified = 1";
} elseif ($verification_filter == 'unverified') {
    $sql .= " AND a.location_verified = 0";
}

if ($date_from != '') {
    $sql .= " AND DATE(a.clock_in_time) >= '" . $conn->real_escape_string($date_from) . "'";
}

if ($date_to != '') {
    $sql .= " AND DATE(a.clock_in_time) <= '" . $conn->real_escape_string($date_to) . "'";
}

if ($search != '') {
    $search_term = $conn->real_escape_string($search);
    $sql .= " AND (j.title LIKE '%$search_term%' OR wp.name LIKE '%$search_term%' OR ep.name LIKE '%$search_term%' OR wu.email LIKE '%$search_term%')";
}

$sql .= " ORDER BY a.clock_in_time DESC LIMIT 100";

$attendance_records = $conn->query($sql);

// Get statistics
$total_records = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];
$clocked_in = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'clocked_in'")->fetch_assoc()['count'];
$verified_locations = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE location_verified = 1")->fetch_assoc()['count'];
$suspicious = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE location_verified = 0 AND distance_from_job_location > 50")->fetch_assoc()['count'];
$total_hours = $conn->query("SELECT SUM(total_hours) as total FROM attendance WHERE status = 'clocked_out'")->fetch_assoc()['total'] ?? 0;
$disputed_records = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'disputed'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Tracking | POP!Work Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pop-maroon: #8a1538;
            --pop-light: #fdf2f4;
            --pop-gray: #f8f9fa;
            --pop-maroon-dark: #6d1029;
            --pop-maroon-light: #a71d47;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--pop-gray);
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: white;
        }

        .menu-item.active {
            background: rgba(255,255,255,0.15);
            border-left-color: white;
        }

        .menu-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 18px;
        }

        .menu-item span {
            font-size: 14px;
            font-weight: 500;
        }

        .main-content {
            margin-left: 260px;
            padding: 24px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .top-bar h1 {
            font-size: 28px;
            color: var(--pop-maroon);
            font-weight: 700;
        }

        .top-bar-actions {
            display: flex;
            gap: 12px;
        }

        .message {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        .message.success {
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #10b981;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }

        .stat-box:hover {
            transform: translateY(-4px);
        }

        .stat-box-label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .stat-box-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--pop-maroon);
        }

        .filters-bar {
            background: white;
            padding: 20px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .filters-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--pop-maroon);
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .filter-select, .date-input {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-select:focus, .date-input:focus {
            outline: none;
            border-color: var(--pop-maroon);
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 24px;
            border-bottom: 2px solid var(--pop-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 18px;
            color: var(--pop-maroon);
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--pop-light);
        }

        th {
            padding: 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--pop-maroon);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }

        tr:hover {
            background: var(--pop-light);
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.clocked_in {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.clocked_out {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.disputed {
            background: #fee2e2;
            color: #991b1b;
        }

        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
        }

        .location-badge.verified {
            background: #d1fae5;
            color: #065f46;
        }

        .location-badge.unverified {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .action-btn.view {
            background: #dbeafe;
            color: #1e40af;
        }

        .action-btn.flag {
            background: #fef3c7;
            color: #92400e;
        }

        .action-btn.update {
            background: #e0e7ff;
            color: #4338ca;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 24px;
            border-bottom: 2px solid var(--pop-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }

        .modal-header h3 {
            font-size: 20px;
            color: var(--pop-maroon);
        }

        .close-modal {
            width: 36px;
            height: 36px;
            border: none;
            background: var(--pop-gray);
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            color: #6b7280;
            transition: all 0.3s;
        }

        .close-modal:hover {
            background: #e5e7eb;
            color: var(--pop-maroon);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 24px;
        }

        .info-section {
            margin-bottom: 24px;
        }

        .info-section h4 {
            font-size: 16px;
            color: var(--pop-maroon);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--pop-gray);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .info-label {
            font-weight: 500;
            color: #6b7280;
        }

        .info-value {
            color: #1f2937;
            font-weight: 500;
            text-align: right;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--pop-maroon);
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 2px solid var(--pop-gray);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            position: sticky;
            bottom: 0;
            background: white;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .map-container {
            width: 100%;
            height: 350px;
            border-radius: 12px;
            overflow: hidden;
            margin: 16px 0;
            border: 2px solid #e5e7eb;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
            color: #d1d5db;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .filters-row {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .action-btns {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>POP!Work</h2>
            <p>Admin Panel</p>
        </div>
        <nav class="sidebar-menu">
            <a href="admin_dashboard.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin_users.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="admin_jobs.php" class="menu-item">
                <i class="fas fa-briefcase"></i>
                <span>Job Management</span>
            </a>
            <a href="admin_payments.php" class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <span>Payments</span>
            </a>
            <a href="admin_attendance.php" class="menu-item active">
                <i class="fas fa-clock"></i>
                <span>Attendance</span>
            </a>
            <a href="admin_messages.php" class="menu-item">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>
            <a href="admin_feedback.php" class="menu-item">
                <i class="fas fa-star"></i>
                <span>Feedback</span>
            </a>
            <a href="admin_reports.php" class="menu-item">
                <i class="fas fa-chart-bar"></i><span>Reports & Analytics</span>
            </a>
            <a href="admin_profile.php" class="menu-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="backend/logout.php" class="menu-item" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <h1><i class="fas fa-clock"></i> Attendance Tracking</h1>
            <div class="top-bar-actions">
                <a href="?export=csv" class="btn btn-success">
                    <i class="fas fa-download"></i> Export CSV
                </a>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="message <?= $message_type ?>">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-label">Total Records</div>
                <div class="stat-box-value"><?= number_format($total_records) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Currently Clocked In</div>
                <div class="stat-box-value" style="color: #f59e0b;"><?= $clocked_in ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Verified Locations</div>
                <div class="stat-box-value" style="color: #10b981;"><?= $verified_locations ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Suspicious</div>
                <div class="stat-box-value" style="color: #ef4444;"><?= $suspicious ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Disputed</div>
                <div class="stat-box-value" style="color: #dc2626;"><?= $disputed_records ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Total Hours</div>
                <div class="stat-box-value"><?= number_format($total_hours, 1) ?></div>
            </div>
        </div>

        <div class="filters-bar">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by job, worker, or employer..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="status" class="filter-select">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="clocked_in" <?= $status_filter == 'clocked_in' ? 'selected' : '' ?>>Clocked In</option>
                        <option value="clocked_out" <?= $status_filter == 'clocked_out' ? 'selected' : '' ?>>Clocked Out</option>
                        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="disputed" <?= $status_filter == 'disputed' ? 'selected' : '' ?>>Disputed</option>
                    </select>
                    <select name="verification" class="filter-select">
                        <option value="all" <?= $verification_filter == 'all' ? 'selected' : '' ?>>All Locations</option>
                        <option value="verified" <?= $verification_filter == 'verified' ? 'selected' : '' ?>>Verified</option>
                        <option value="unverified" <?= $verification_filter == 'unverified' ? 'selected' : '' ?>>Unverified</option>
                    </select>
                    <input type="date" name="date_from" class="date-input" value="<?= $date_from ?>" placeholder="From">
                    <input type="date" name="date_to" class="date-input" value="<?= $date_to ?>" placeholder="To">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-table"></i> Attendance Records</h3>
                <span style="color: #6b7280; font-size: 14px;">Showing last 100 records</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Worker</th>
                            <th>Job & Employer</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Hours</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendance_records->num_rows > 0): ?>
                            <?php while ($record = $attendance_records->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= $record['attendance_id'] ?></strong></td>
                                <td>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($record['worker_name'] ?? 'N/A') ?></div>
                                    <small style="color: #6b7280;"><?= htmlspecialchars($record['worker_email']) ?></small>
                                </td>
                                <td>
                                    <div style="font-weight: 500; margin-bottom: 4px;"><?= htmlspecialchars($record['job_title']) ?></div>
                                    <small style="color: #6b7280;">
                                        <i class="fas fa-building"></i> <?= htmlspecialchars($record['employer_name'] ?? $record['employer_email']) ?>
                                    </small>
                                </td>
                                <td>
                                    <div><?= date('M d, Y', strtotime($record['clock_in_time'])) ?></div>
                                    <small style="color: #6b7280;"><?= date('h:i A', strtotime($record['clock_in_time'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($record['clock_out_time']): ?>
                                        <div><?= date('M d, Y', strtotime($record['clock_out_time'])) ?></div>
                                        <small style="color: #6b7280;"><?= date('h:i A', strtotime($record['clock_out_time'])) ?></small>
                                    <?php else: ?>
                                        <span style="color: #f59e0b; font-weight: 600;">
                                            <i class="fas fa-circle" style="font-size: 8px;"></i> Working
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['total_hours']): ?>
                                        <strong style="color: var(--pop-maroon);"><?= number_format($record['total_hours'], 2) ?></strong> hrs
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="location-badge <?= $record['location_verified'] ? 'verified' : 'unverified' ?>">
                                        <i class="fas fa-<?= $record['location_verified'] ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                                        <?= $record['location_verified'] ? 'Verified' : 'Unverified' ?>
                                    </span>
                                    <?php if ($record['distance_from_job_location']): ?>
                                        <br><small style="color: <?= $record['distance_from_job_location'] > 50 ? '#ef4444' : '#6b7280' ?>;">
                                            <i class="fas fa-map-marker-alt"></i> <?= round($record['distance_from_job_location']) ?>m away
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $record['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $record['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn view" onclick='viewAttendance(<?= htmlspecialchars(json_encode($record), ENT_QUOTES) ?>)'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!$record['location_verified'] || $record['distance_from_job_location'] > 50): ?>
                                            <button class="action-btn flag" onclick="flagAttendance(<?= $record['attendance_id'] ?>)">
                                                <i class="fas fa-flag"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="action-btn update" onclick="updateStatus(<?= $record['attendance_id'] ?>, '<?= $record['status'] ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="no-data">
                                    <i class="fas fa-clock"></i>
                                    <div>No attendance records found</div>
                                    <small>Try adjusting your filters</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Attendance Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-clock"></i> Attendance Details</h3>
                <button class="close-modal" onclick="closeModal('viewModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Flag Attendance Modal -->
    <div id="flagModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3><i class="fas fa-flag"></i> Flag Suspicious Attendance</h3>
                    <button type="button" class="close-modal" onclick="closeModal('flagModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="attendance_id" id="flag_attendance_id">
                    
                    <div class="form-group">
                        <label><i class="fas fa-exclamation-triangle"></i> Reason for Flagging *</label>
                        <textarea name="flag_reason" required placeholder="Describe why this attendance record is suspicious (e.g., location too far from job site, unusual hours, etc.)"></textarea>
                    </div>

                    <div style="background: #fef3c7; padding: 12px; border-radius: 8px; border-left: 4px solid #f59e0b;">
                        <strong style="color: #92400e;">Note:</strong>
                        <p style="margin: 4px 0 0 0; color: #92400e; font-size: 13px;">
                            This will add a flag note to the attendance record for review. The worker and employer will not be notified.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('flagModal')">Cancel</button>
                    <button type="submit" name="flag_attendance" class="btn btn-danger">
                        <i class="fas fa-flag"></i> Flag Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Update Attendance Status</h3>
                    <button type="button" class="close-modal" onclick="closeModal('updateModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="attendance_id" id="update_attendance_id">
                    
                    <div class="form-group">
                        <label><i class="fas fa-list"></i> New Status *</label>
                        <select name="new_status" id="update_status" required>
                            <option value="clocked_in">Clocked In</option>
                            <option value="clocked_out">Clocked Out</option>
                            <option value="approved">Approved</option>
                            <option value="disputed">Disputed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Admin Notes</label>
                        <textarea name="admin_notes" placeholder="Add any notes about this status change (optional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('updateModal')">Cancel</button>
                    <button type="submit" name="update_attendance_status" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewAttendance(record) {
            const modal = document.getElementById('viewModal');
            const body = document.getElementById('viewModalBody');
            
            const clockOut = record.clock_out_time 
                ? new Date(record.clock_out_time).toLocaleString('en-US', {year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'})
                : '<span style="color: #f59e0b; font-weight: 600;"><i class="fas fa-circle" style="font-size: 8px;"></i> Still Working</span>';
            
            const distance = record.distance_from_job_location 
                ? Math.round(record.distance_from_job_location) + ' meters'
                : 'Not Available';
            
            const distanceColor = record.distance_from_job_location > 50 ? '#ef4444' : '#10b981';
            
            body.innerHTML = `
                <div class="info-section">
                    <h4><i class="fas fa-user"></i> Worker Information</h4>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value">${record.worker_name || 'N/A'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value">${record.worker_email}</span>
                    </div>
                </div>

                <div class="info-section">
                    <h4><i class="fas fa-briefcase"></i> Job Information</h4>
                    <div class="info-row">
                        <span class="info-label">Job Title:</span>
                        <span class="info-value">${record.job_title}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Employer:</span>
                        <span class="info-value">${record.employer_name || record.employer_email}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Job Location:</span>
                        <span class="info-value">${record.job_location || 'N/A'}</span>
                    </div>
                </div>

                <div class="info-section">
                    <h4><i class="fas fa-clock"></i> Attendance Details</h4>
                    <div class="info-row">
                        <span class="info-label">Clock In Time:</span>
                        <span class="info-value">${new Date(record.clock_in_time).toLocaleString('en-US', {year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'})}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Clock Out Time:</span>
                        <span class="info-value">${clockOut}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Hours:</span>
                        <span class="info-value">${record.total_hours ? parseFloat(record.total_hours).toFixed(2) + ' hours' : 'In Progress'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value"><span class="badge ${record.status}">${record.status.replace('_', ' ').toUpperCase()}</span></span>
                    </div>
                </div>

                <div class="info-section">
                    <h4><i class="fas fa-map-marked-alt"></i> Location Verification</h4>
                    <div class="info-row">
                        <span class="info-label">Location Verified:</span>
                        <span class="info-value">${record.location_verified ? '<span style="color: #10b981;">✅ Verified</span>' : '<span style="color: #ef4444;">❌ Not Verified</span>'}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Distance from Job Site:</span>
                        <span class="info-value" style="color: ${distanceColor}; font-weight: 600;">${distance}</span>
                    </div>
                    ${record.clock_in_latitude && record.clock_in_longitude ? `
                        <div class="info-row">
                            <span class="info-label">Clock In GPS:</span>
                            <span class="info-value" style="font-family: monospace; font-size: 12px;">${parseFloat(record.clock_in_latitude).toFixed(6)}, ${parseFloat(record.clock_in_longitude).toFixed(6)}</span>
                        </div>
                    ` : ''}
                    ${record.clock_in_location ? `
                        <div class="info-row">
                            <span class="info-label">Clock In Address:</span>
                            <span class="info-value">${record.clock_in_location}</span>
                        </div>
                    ` : ''}
                    ${record.clock_out_latitude && record.clock_out_longitude ? `
                        <div class="info-row">
                            <span class="info-label">Clock Out GPS:</span>
                            <span class="info-value" style="font-family: monospace; font-size: 12px;">${parseFloat(record.clock_out_latitude).toFixed(6)}, ${parseFloat(record.clock_out_longitude).toFixed(6)}</span>
                        </div>
                    ` : ''}
                </div>

                ${record.notes ? `
                    <div class="info-section">
                        <h4><i class="fas fa-sticky-note"></i> Notes & Flags</h4>
                        <div style="padding: 16px; background: #f9fafb; border-radius: 8px; white-space: pre-wrap; font-size: 13px; line-height: 1.6; border-left: 4px solid var(--pop-maroon);">${record.notes}</div>
                    </div>
                ` : ''}

                ${record.clock_in_latitude && record.clock_in_longitude ? `
                    <div class="info-section">
                        <h4><i class="fas fa-map"></i> Map View</h4>
                        <div class="map-container">
                            <iframe 
                                width="100%" 
                                height="100%" 
                                frameborder="0" 
                                style="border:0"
                                src="https://maps.google.com/maps?q=${record.clock_in_latitude},${record.clock_in_longitude}&output=embed"
                                allowfullscreen>
                            </iframe>
                        </div>
                        <div style="text-align: center; margin-top: 12px;">
                            <a href="https://www.google.com/maps?q=${record.clock_in_latitude},${record.clock_in_longitude}" target="_blank" style="color: var(--pop-maroon); text-decoration: none; font-weight: 500;">
                                <i class="fas fa-external-link-alt"></i> Open in Google Maps
                            </a>
                        </div>
                    </div>
                ` : ''}
            `;
            
            modal.classList.add('active');
        }

        function flagAttendance(attendanceId) {
            document.getElementById('flag_attendance_id').value = attendanceId;
            document.getElementById('flagModal').classList.add('active');
        }

        function updateStatus(attendanceId, currentStatus) {
            document.getElementById('update_attendance_id').value = attendanceId;
            document.getElementById('update_status').value = currentStatus;
            document.getElementById('updateModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // ESC key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>