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

// Handle payment actions
$message = '';
$message_type = '';

// Update Payment Status
if (isset($_POST['update_payment_status'])) {
    $payment_id = intval($_POST['payment_id']);
    $new_status = $_POST['new_status'];
    $admin_notes = trim($_POST['admin_notes']);
    
    $stmt = $conn->prepare("UPDATE payment_records SET payment_status = ?, notes = CONCAT(COALESCE(notes, ''), '\n[Admin] ', ?) WHERE payment_id = ?");
    $stmt->bind_param("ssi", $new_status, $admin_notes, $payment_id);
    
    if ($stmt->execute()) {
        $message = "Payment status updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Error updating payment status.";
        $message_type = 'error';
    }
}

// Resolve Dispute
if (isset($_POST['resolve_dispute'])) {
    $payment_id = intval($_POST['payment_id']);
    $resolution = $_POST['resolution'];
    $admin_notes = trim($_POST['resolution_notes']);
    
    $new_status = ($resolution === 'approve') ? 'paid' : 'cancelled';
    
    $stmt = $conn->prepare("UPDATE payment_records SET payment_status = ?, notes = CONCAT(COALESCE(notes, ''), '\n[Admin Resolution] ', ?) WHERE payment_id = ?");
    $stmt->bind_param("ssi", $new_status, $admin_notes, $payment_id);
    
    if ($stmt->execute()) {
        // Also update application payment status
        $app_stmt = $conn->prepare("UPDATE applications SET payment_status = ? WHERE application_id = (SELECT application_id FROM payment_records WHERE payment_id = ?)");
        $app_stmt->bind_param("si", $new_status, $payment_id);
        $app_stmt->execute();
        
        $message = "Dispute resolved successfully!";
        $message_type = 'success';
    } else {
        $message = "Error resolving dispute.";
        $message_type = 'error';
    }
}

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$payment_method_filter = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT pr.*, 
               j.title as job_title,
               j.category as job_category,
               wp.name as worker_name,
               wu.email as worker_email,
               ep.name as employer_name,
               eu.email as employer_email
        FROM payment_records pr
        JOIN jobs j ON pr.job_id = j.job_id
        JOIN users wu ON pr.user_id = wu.user_id
        LEFT JOIN profiles wp ON wu.user_id = wp.user_id
        JOIN users eu ON pr.employer_id = eu.user_id
        LEFT JOIN profiles ep ON eu.user_id = ep.user_id
        WHERE 1=1";

if ($status_filter != 'all') {
    $sql .= " AND pr.payment_status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($payment_method_filter != 'all') {
    $sql .= " AND pr.payment_method = '" . $conn->real_escape_string($payment_method_filter) . "'";
}

if ($date_from != '') {
    $sql .= " AND DATE(pr.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}

if ($date_to != '') {
    $sql .= " AND DATE(pr.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

if ($search != '') {
    $search_term = $conn->real_escape_string($search);
    $sql .= " AND (j.title LIKE '%$search_term%' OR wp.name LIKE '%$search_term%' OR ep.name LIKE '%$search_term%' OR wu.email LIKE '%$search_term%' OR eu.email LIKE '%$search_term%')";
}

$sql .= " ORDER BY pr.created_at DESC";

$payments = $conn->query($sql);

// Get payment statistics
$total_payments = $conn->query("SELECT COUNT(*) as count FROM payment_records")->fetch_assoc()['count'];
$pending_payments = $conn->query("SELECT COUNT(*) as count FROM payment_records WHERE payment_status = 'pending'")->fetch_assoc()['count'];
$paid_payments = $conn->query("SELECT COUNT(*) as count FROM payment_records WHERE payment_status = 'paid'")->fetch_assoc()['count'];
$disputed_payments = $conn->query("SELECT COUNT(*) as count FROM payment_records WHERE payment_status IN ('disputed', 'processing')")->fetch_assoc()['count'];

// Get total amounts
$total_amount = $conn->query("SELECT SUM(final_amount) as total FROM payment_records WHERE payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;
$pending_amount = $conn->query("SELECT SUM(final_amount) as total FROM payment_records WHERE payment_status = 'pending'")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management | POP!Work Admin</title>
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

        /* Main Content */
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

        .export-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3);
        }

        /* Message Alert */
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

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stat-box-label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .stat-box-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--pop-maroon);
        }

        .stat-box-subtext {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }

        /* Filters */
        .filters-bar {
            background: white;
            padding: 20px 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .filters-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
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

        .filter-select, .filter-date {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-select:focus, .filter-date:focus {
            outline: none;
            border-color: var(--pop-maroon);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3);
        }

        /* Payments Table */
        .payments-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
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
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }

        tr:hover {
            background: var(--pop-light);
        }

        .payment-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .payment-title {
            font-weight: 600;
            color: #1f2937;
        }

        .payment-subtitle {
            font-size: 12px;
            color: #6b7280;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.approved {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge.processing {
            background: #e0e7ff;
            color: #4338ca;
        }

        .badge.paid {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge.disputed {
            background: #fef08a;
            color: #854d0e;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 12px;
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

        .action-btn.update {
            background: #fef3c7;
            color: #92400e;
        }

        .action-btn.resolve {
            background: #d1fae5;
            color: #065f46;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Modal */
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
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
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
        }

        .modal-header h3 {
            font-size: 20px;
            color: var(--pop-maroon);
        }

        .close-modal {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--pop-gray);
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            color: #6b7280;
            transition: all 0.3s;
        }

        .close-modal:hover {
            background: #e5e7eb;
            color: var(--pop-maroon);
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
            font-weight: 600;
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
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        .notes-box {
            background: var(--pop-gray);
            padding: 16px;
            border-radius: 8px;
            margin-top: 12px;
            font-size: 13px;
            line-height: 1.6;
            color: #374151;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
            <a href="admin_payments.php" class="menu-item active">
                <i class="fas fa-money-bill-wave"></i>
                <span>Payments</span>
            </a>
            <a href="admin_attendance.php" class="menu-item">
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

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1><i class="fas fa-money-bill-wave"></i> Payment Management</h1>
            <button class="export-btn" onclick="exportPayments()">
                <i class="fas fa-file-export"></i> Export Report
            </button>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
        <div class="message <?= $message_type ?>">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-label">Total Payments</div>
                <div class="stat-box-value"><?= $total_payments ?></div>
                <div class="stat-box-subtext">All time</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Pending</div>
                <div class="stat-box-value" style="color: #f59e0b;"><?= $pending_payments ?></div>
                <div class="stat-box-subtext">RM <?= number_format($pending_amount, 2) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Paid</div>
                <div class="stat-box-value" style="color: #10b981;"><?= $paid_payments ?></div>
                <div class="stat-box-subtext">RM <?= number_format($total_amount, 2) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Disputed</div>
                <div class="stat-box-value" style="color: #ef4444;"><?= $disputed_payments ?></div>
                <div class="stat-box-subtext">Needs attention</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by job, worker, or employer..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="status" class="filter-select">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="processing" <?= $status_filter == 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="paid" <?= $status_filter == 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="disputed" <?= $status_filter == 'disputed' ? 'selected' : '' ?>>Disputed</option>
                    </select>
                    <select name="payment_method" class="filter-select">
                        <option value="all" <?= $payment_method_filter == 'all' ? 'selected' : '' ?>>All Methods</option>
                        <option value="cash" <?= $payment_method_filter == 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="bank_transfer" <?= $payment_method_filter == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="e-wallet" <?= $payment_method_filter == 'e-wallet' ? 'selected' : '' ?>>E-Wallet</option>
                    </select>
                    <input type="date" name="date_from" class="filter-date" placeholder="From" value="<?= htmlspecialchars($date_from) ?>">
                    <input type="date" name="date_to" class="filter-date" placeholder="To" value="<?= htmlspecialchars($date_to) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="payments-table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Job & Worker</th>
                            <th>Employer</th>
                            <th>Hours</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments->num_rows > 0): ?>
                            <?php while ($payment = $payments->fetch_assoc()): ?>
                            <tr>
                                <!-- Column 1: Payment ID -->
                                <td>
                                    <strong style="color: var(--pop-maroon);">#<?= $payment['payment_id'] ?></strong>
                                </td>
                                
                                <!-- Column 2: Job & Worker -->
                                <td>
                                    <div style="margin-bottom: 4px;">
                                        <strong><?= htmlspecialchars($payment['job_title'] ?? 'N/A') ?></strong>
                                    </div>
                                    <small style="color: #6b7280;">
                                        <i class="fas fa-user"></i> 
                                        <?= htmlspecialchars($payment['worker_name'] ?? $payment['worker_email']) ?>
                                    </small>
                                </td>
                                
                                <!-- Column 3: Employer -->
                                <td>
                                    <div style="color: #374151;">
                                        <i class="fas fa-building"></i>
                                        <?= htmlspecialchars($payment['employer_name'] ?? $payment['employer_email']) ?>
                                    </div>
                                </td>
                                
                                <!-- Column 4: Hours -->
                                <td>
                                    <strong><?= number_format($payment['total_hours'] ?? 0, 2) ?></strong> hrs
                                </td>
                                
                                <!-- Column 5: Amount -->
                                <td>
                                    <strong style="color: var(--pop-maroon); font-size: 16px;">RM <?= number_format($payment['final_amount'], 2) ?></strong>
                                    <?php if (($payment['bonus_amount'] ?? 0) > 0 || ($payment['deduction_amount'] ?? 0) > 0): ?>
                                        <br><small style="color: #6b7280;">
                                            Base: RM<?= number_format($payment['total_amount'], 2) ?>
                                            <?php if (($payment['bonus_amount'] ?? 0) > 0): ?>
                                                <br>+Bonus: RM<?= number_format($payment['bonus_amount'], 2) ?>
                                            <?php endif; ?>
                                            <?php if (($payment['deduction_amount'] ?? 0) > 0): ?>
                                                <br>-Deduction: RM<?= number_format($payment['deduction_amount'], 2) ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Column 6: Method -->
                                <td>
                                    <?php if ($payment['payment_method']): ?>
                                        <span class="badge" style="background: #dbeafe; color: #1e40af;">
                                            <?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Column 7: Status -->
                                <td>
                                    <span class="badge <?= $payment['payment_status'] ?>">
                                        <?= ucfirst($payment['payment_status']) ?>
                                    </span>
                                </td>
                                
                                <!-- Column 8: Date -->
                                <td>
                                    <?= date('M d, Y', strtotime($payment['created_at'])) ?><br>
                                    <small style="color: #6b7280;"><?= date('h:i A', strtotime($payment['created_at'])) ?></small>
                                </td>
                                
                                <!-- Column 9: Actions -->
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn view" onclick="viewPayment(<?= $payment['payment_id'] ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if (in_array($payment['payment_status'], ['pending', 'approved', 'processing'])): ?>
                                            <button class="action-btn update" onclick="updatePayment(<?= $payment['payment_id'] ?>, '<?= $payment['payment_status'] ?>')">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($payment['payment_status'] == 'disputed'): ?>
                                            <button class="action-btn resolve" onclick="resolveDispute(<?= $payment['payment_id'] ?>)">
                                                <i class="fas fa-gavel"></i> Resolve
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="no-data">
                                    <i class="fas fa-money-bill-wave"></i>
                                    No payments found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Payment Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-receipt"></i> Payment Details</h3>
                <button class="close-modal" onclick="closeModal('viewModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Update Payment Status Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Update Payment Status</h3>
                    <button type="button" class="close-modal" onclick="closeModal('updateModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="payment_id" id="update_payment_id">
                    
                    <div class="form-group">
                        <label>New Status</label>
                        <select name="new_status" id="update_status" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="processing">Processing</option>
                            <option value="paid">Paid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Notes</label>
                        <textarea name="admin_notes" placeholder="Add any notes about this status change..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('updateModal')">Cancel</button>
                    <button type="submit" name="update_payment_status" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resolve Dispute Modal -->
    <div id="disputeModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3><i class="fas fa-gavel"></i> Resolve Dispute</h3>
                    <button type="button" class="close-modal" onclick="closeModal('disputeModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="payment_id" id="dispute_payment_id">
                    
                    <div class="form-group">
                        <label>Resolution Decision</label>
                        <select name="resolution" id="dispute_resolution" required>
                            <option value="">-- Select Decision --</option>
                            <option value="approve">Approve Payment (Mark as Paid)</option>
                            <option value="reject">Reject Payment (Cancel)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Resolution Notes</label>
                        <textarea name="resolution_notes" placeholder="Explain the reason for this decision..." required></textarea>
                    </div>
                    
                    <div style="background: #fef08a; padding: 16px; border-radius: 8px; border-left: 4px solid #facc15;">
                        <p style="color: #854d0e; font-weight: 500; margin-bottom: 8px;">
                            <i class="fas fa-exclamation-triangle"></i> Important
                        </p>
                        <p style="color: #854d0e; font-size: 13px; line-height: 1.6;">
                            This action will resolve the dispute and update the payment status accordingly. 
                            Make sure to review all details before proceeding.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('disputeModal')">Cancel</button>
                    <button type="submit" name="resolve_dispute" class="btn btn-success">
                        <i class="fas fa-check"></i> Resolve Dispute
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewPayment(paymentId) {
            // Fetch payment details via AJAX
            fetch(`backend/get_payment_details.php?payment_id=${paymentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const p = data.payment;
                        
                        let notesHtml = '';
                        if (p.notes) {
                            notesHtml = `
                                <div class="info-section">
                                    <h4>Notes & Comments</h4>
                                    <div class="notes-box">${p.notes}</div>
                                </div>
                            `;
                        }
                        
                        document.getElementById('viewModalBody').innerHTML = `
                            <div class="info-section">
                                <h4>Payment Information</h4>
                                <div class="info-row">
                                    <span class="info-label">Payment ID:</span>
                                    <span class="info-value">#${p.payment_id}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value"><span class="badge ${p.payment_status}">${p.payment_status.charAt(0).toUpperCase() + p.payment_status.slice(1)}</span></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Created:</span>
                                    <span class="info-value">${new Date(p.created_at).toLocaleString()}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Payment Date:</span>
                                    <span class="info-value">${p.payment_date ? new Date(p.payment_date).toLocaleString() : 'Not paid yet'}</span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Job Details</h4>
                                <div class="info-row">
                                    <span class="info-label">Job Title:</span>
                                    <span class="info-value">${p.job_title}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Category:</span>
                                    <span class="info-value">${p.job_category}</span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Worker Information</h4>
                                <div class="info-row">
                                    <span class="info-label">Name:</span>
                                    <span class="info-value">${p.worker_name || 'N/A'}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value">${p.worker_email}</span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Employer Information</h4>
                                <div class="info-row">
                                    <span class="info-label">Name:</span>
                                    <span class="info-value">${p.employer_name || 'N/A'}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value">${p.employer_email}</span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Payment Breakdown</h4>
                                <div class="info-row">
                                    <span class="info-label">Total Hours:</span>
                                    <span class="info-value">${parseFloat(p.total_hours).toFixed(2)} hours</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Hourly Rate:</span>
                                    <span class="info-value">RM ${parseFloat(p.hourly_rate).toFixed(2)}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Base Amount:</span>
                                    <span class="info-value">RM ${parseFloat(p.total_amount).toFixed(2)}</span>
                                </div>
                                ${parseFloat(p.bonus_amount) > 0 ? `
                                <div class="info-row">
                                    <span class="info-label" style="color: #10b981;">Bonus:</span>
                                    <span class="info-value" style="color: #10b981;">+RM ${parseFloat(p.bonus_amount).toFixed(2)}</span>
                                </div>` : ''}
                                ${parseFloat(p.deduction_amount) > 0 ? `
                                <div class="info-row">
                                    <span class="info-label" style="color: #ef4444;">Deduction:</span>
                                    <span class="info-value" style="color: #ef4444;">-RM ${parseFloat(p.deduction_amount).toFixed(2)}</span>
                                </div>` : ''}
                                <div class="info-row" style="border-top: 2px solid var(--pop-maroon); padding-top: 16px; margin-top: 8px;">
                                    <span class="info-label" style="font-size: 16px; color: var(--pop-maroon);">Final Amount:</span>
                                    <span class="info-value" style="font-size: 18px; color: var(--pop-maroon); font-weight: 700;">RM ${parseFloat(p.final_amount).toFixed(2)}</span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Payment Method</h4>
                                <div class="info-row">
                                    <span class="info-label">Method:</span>
                                    <span class="info-value">${p.payment_method ? p.payment_method.replace('_', ' ').toUpperCase() : 'Not specified'}</span>
                                </div>
                                ${p.payment_reference ? `
                                <div class="info-row">
                                    <span class="info-label">Reference:</span>
                                    <span class="info-value">${p.payment_reference}</span>
                                </div>` : ''}
                            </div>
                            
                            ${notesHtml}
                        `;
                        
                        document.getElementById('viewModal').classList.add('active');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load payment details');
                });
        }

        function updatePayment(paymentId, currentStatus) {
            document.getElementById('update_payment_id').value = paymentId;
            document.getElementById('update_status').value = currentStatus;
            document.getElementById('updateModal').classList.add('active');
        }

        function resolveDispute(paymentId) {
            document.getElementById('dispute_payment_id').value = paymentId;
            document.getElementById('disputeModal').classList.add('active');
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

        function exportPayments() {
            // Get current filters
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            // Open export in new window
            window.open('backend/export_payments.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>