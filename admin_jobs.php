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

// Handle job actions
$message = '';
$message_type = '';

// Close Job
if (isset($_POST['close_job'])) {
    $job_id = intval($_POST['job_id']);
    
    $stmt = $conn->prepare("UPDATE jobs SET status = 'completed', job_status = 'completed' WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    
    if ($stmt->execute()) {
        $message = "Job successfully closed!";
        $message_type = 'success';
    } else {
        $message = "Error closing job.";
        $message_type = 'error';
    }
}

// Reopen Job
if (isset($_POST['reopen_job'])) {
    $job_id = intval($_POST['job_id']);
    
    $stmt = $conn->prepare("UPDATE jobs SET status = 'available', job_status = 'available' WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    
    if ($stmt->execute()) {
        $message = "Job successfully reopened!";
        $message_type = 'success';
    } else {
        $message = "Error reopening job.";
        $message_type = 'error';
    }
}

// Delete Job
if (isset($_POST['delete_job'])) {
    $job_id = intval($_POST['job_id']);
    
    $stmt = $conn->prepare("DELETE FROM jobs WHERE job_id = ?");
    $stmt->bind_param("i", $job_id);
    
    if ($stmt->execute()) {
        $message = "Job successfully deleted!";
        $message_type = 'success';
    } else {
        $message = "Error deleting job.";
        $message_type = 'error';
    }
}

// Get filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT j.*, 
               p.name as employer_name,
               u.email as employer_email,
               COUNT(DISTINCT a.application_id) as total_applications,
               COUNT(DISTINCT CASE WHEN a.status = 'Accepted' THEN a.application_id END) as accepted_applications,
               COUNT(DISTINCT CASE WHEN a.job_completed = 1 THEN a.application_id END) as completed_applications
        FROM jobs j
        LEFT JOIN users u ON j.employer_id = u.user_id
        LEFT JOIN profiles p ON u.user_id = p.user_id
        LEFT JOIN applications a ON j.job_id = a.job_id
        WHERE 1=1";

if ($status_filter != 'all') {
    $sql .= " AND j.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($category_filter != 'all') {
    $sql .= " AND j.category = '" . $conn->real_escape_string($category_filter) . "'";
}

if ($search != '') {
    $search_term = $conn->real_escape_string($search);
    $sql .= " AND (j.title LIKE '%$search_term%' OR j.location LIKE '%$search_term%' OR p.name LIKE '%$search_term%')";
}

$sql .= " GROUP BY j.job_id ORDER BY j.created_at DESC";

$jobs = $conn->query($sql);

// Get job counts
$total_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs")->fetch_assoc()['count'];
$available_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'available'")->fetch_assoc()['count'];
$completed_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'completed'")->fetch_assoc()['count'];
$urgent_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE is_urgent = 1 AND status = 'available'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Management | POP!Work Admin</title>
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

        .filter-select {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-select:focus {
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

        /* Jobs Table */
        .jobs-table-container {
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

        .job-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .job-employer {
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

        .badge.available {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge.expired {
            background: #f3f4f6;
            color: #6b7280;
        }

        .badge.gig {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.part-time {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge.full-time {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.urgent {
            background: #fee2e2;
            color: #991b1b;
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

        .action-btn.close {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btn.reopen {
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Applications Table in Modal */
        .applications-table {
            margin-top: 16px;
        }

        .applications-table table {
            font-size: 13px;
        }

        .applications-table th {
            background: var(--pop-gray);
            padding: 12px;
        }

        .applications-table td {
            padding: 12px;
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
            <a href="admin_jobs.php" class="menu-item active">
                <i class="fas fa-briefcase"></i>
                <span>Job Management</span>
            </a>
            <a href="admin_payments.php" class="menu-item">
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
            <h1><i class="fas fa-briefcase"></i> Job Management</h1>
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
                <div class="stat-box-label">Total Jobs</div>
                <div class="stat-box-value"><?= $total_jobs ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Available</div>
                <div class="stat-box-value" style="color: #10b981;"><?= $available_jobs ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Completed</div>
                <div class="stat-box-value" style="color: #3b82f6;"><?= $completed_jobs ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Urgent</div>
                <div class="stat-box-value" style="color: #ef4444;"><?= $urgent_jobs ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by title, location, or employer..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="status" class="filter-select">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="available" <?= $status_filter == 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="expired" <?= $status_filter == 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                    <select name="category" class="filter-select">
                        <option value="all" <?= $category_filter == 'all' ? 'selected' : '' ?>>All Categories</option>
                        <option value="Gig" <?= $category_filter == 'Gig' ? 'selected' : '' ?>>Gig</option>
                        <option value="Part-Time" <?= $category_filter == 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                        <option value="Full-Time" <?= $category_filter == 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Jobs Table -->
        <div class="jobs-table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Job Details</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Pay Rate</th>
                            <th>Applications</th>
                            <th>Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($jobs->num_rows > 0): ?>
                            <?php while ($job = $jobs->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="job-title">
                                        <?= htmlspecialchars($job['title']) ?>
                                        <?php if ($job['is_urgent']): ?>
                                            <span class="badge urgent"><i class="fas fa-exclamation-circle"></i> URGENT</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="job-employer">
                                        <i class="fas fa-building"></i>
                                        <?= htmlspecialchars($job['employer_name'] ?? $job['employer_email']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= strtolower(str_replace('-', '-', $job['category'])) ?>">
                                        <?= htmlspecialchars($job['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $job['status'] ?>">
                                        <?= ucfirst($job['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($job['location']) ?></td>
                                <td>
                                    <strong>RM <?= number_format($job['pay_rate'], 2) ?></strong><br>
                                    <small style="color: #6b7280;"><?= htmlspecialchars($job['pay_period']) ?></small>
                                </td>
                                <td>
                                    <strong><?= $job['total_applications'] ?></strong> total<br>
                                    <small style="color: #10b981;"><?= $job['accepted_applications'] ?> accepted</small><br>
                                    <small style="color: #3b82f6;"><?= $job['completed_applications'] ?> completed</small>
                                </td>
                                <td><?= date('M d, Y', strtotime($job['created_at'])) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn view" onclick="viewJob(<?= $job['job_id'] ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($job['status'] == 'available'): ?>
                                            <button class="action-btn close" onclick="closeJob(<?= $job['job_id'] ?>, '<?= htmlspecialchars($job['title']) ?>')">
                                                <i class="fas fa-times-circle"></i> Close
                                            </button>
                                        <?php else: ?>
                                            <button class="action-btn reopen" onclick="reopenJob(<?= $job['job_id'] ?>, '<?= htmlspecialchars($job['title']) ?>')">
                                                <i class="fas fa-check-circle"></i> Reopen
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    <i class="fas fa-briefcase"></i>
                                    No jobs found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Job Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-briefcase"></i> Job Details</h3>
                <button class="close-modal" onclick="closeModal('viewModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Close Job Confirmation Modal -->
    <div id="closeModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3><i class="fas fa-times-circle"></i> Close Job</h3>
                    <button type="button" class="close-modal" onclick="closeModal('closeModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="job_id" id="close_job_id">
                    <p id="close_message" style="font-size: 16px; color: #374151; line-height: 1.6;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('closeModal')">Cancel</button>
                    <button type="submit" name="close_job" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i> Close Job
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reopen Job Confirmation Modal -->
    <div id="reopenModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3><i class="fas fa-check-circle"></i> Reopen Job</h3>
                    <button type="button" class="close-modal" onclick="closeModal('reopenModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="job_id" id="reopen_job_id">
                    <p id="reopen_message" style="font-size: 16px; color: #374151; line-height: 1.6;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reopenModal')">Cancel</button>
                    <button type="submit" name="reopen_job" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Reopen Job
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Job Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3><i class="fas fa-trash"></i> Delete Job</h3>
                    <button type="button" class="close-modal" onclick="closeModal('deleteModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="job_id" id="delete_job_id">
                    <p id="delete_message" style="font-size: 16px; color: #374151; line-height: 1.6;"></p>
                    <div style="background: #fee2e2; padding: 16px; border-radius: 8px; margin-top: 16px;">
                        <p style="color: #991b1b; font-weight: 500;">
                            <i class="fas fa-exclamation-triangle"></i> Warning: This action cannot be undone!
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" name="delete_job" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Job
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewJob(jobId) {
            // Fetch job details via AJAX
            fetch(`backend/get_job_details.php?job_id=${jobId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const job = data.job;
                        const applications = data.applications;
                        
                        let applicationsHtml = '';
                        if (applications.length > 0) {
                            applicationsHtml = `
                                <div class="applications-table">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Worker</th>
                                                <th>Status</th>
                                                <th>Applied</th>
                                                <th>Hours</th>
                                                <th>Completed</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            `;
                            
                            applications.forEach(app => {
                                applicationsHtml += `
                                    <tr>
                                        <td>${app.worker_name || 'N/A'}</td>
                                        <td><span class="badge ${app.status.toLowerCase()}">${app.status}</span></td>
                                        <td>${new Date(app.applied_at).toLocaleDateString()}</td>
                                        <td>${parseFloat(app.total_work_hours).toFixed(2)}h</td>
                                        <td>${app.job_completed == 1 ? '<span style="color: #10b981;">✓ Yes</span>' : '<span style="color: #6b7280;">✗ No</span>'}</td>
                                    </tr>
                                `;
                            });
                            
                            applicationsHtml += '</tbody></table></div>';
                        } else {
                            applicationsHtml = '<p style="text-align: center; color: #9ca3af; padding: 20px;">No applications yet</p>';
                        }
                        
                        const skills = job.skills ? JSON.parse(job.skills) : [];
                        const responsibilities = job.responsibilities ? JSON.parse(job.responsibilities) : [];
                        
                        document.getElementById('viewModalBody').innerHTML = `
                            <div class="info-section">
                                <h4>Basic Information</h4>
                                <div class="info-row">
                                    <span class="info-label">Title:</span>
                                    <span class="info-value">${job.title}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Employer:</span>
                                    <span class="info-value">${job.employer_name || job.employer_email}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Category:</span>
                                    <span class="info-value">${job.category}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value"><span class="badge ${job.status}">${job.status}</span></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Location:</span>
                                    <span class="info-value">${job.location}</span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Compensation & Duration</h4>
                                <div class="info-row">
                                    <span class="info-label">Pay Rate:</span>
                                    <span class="info-value">RM ${parseFloat(job.pay_rate).toFixed(2)} ${job.pay_period}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Duration:</span>
                                    <span class="info-value">${job.job_duration || 'N/A'}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Start Date:</span>
                                    <span class="info-value">${job.start_date ? new Date(job.start_date).toLocaleDateString() : 'N/A'}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">End Date:</span>
                                    <span class="info-value">${job.end_date ? new Date(job.end_date).toLocaleDateString() : 'N/A'}</span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Requirements</h4>
                                <div class="info-row">
                                    <span class="info-label">Experience Level:</span>
                                    <span class="info-value">${job.experience_level}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Positions Available:</span>
                                    <span class="info-value">${job.positions_available}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Required Documents:</span>
                                    <span class="info-value">${job.required_documents || 'N/A'}</span>
                                </div>
                            </div>
                            
                            ${skills.length > 0 ? `
                            <div class="info-section">
                                <h4>Required Skills</h4>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    ${skills.map(skill => `<span class="badge" style="background: #dbeafe; color: #1e40af;">${skill}</span>`).join('')}
                                </div>
                            </div>` : ''}
                            
                            ${responsibilities.length > 0 ? `
                            <div class="info-section">
                                <h4>Responsibilities</h4>
                                <ul style="margin-left: 20px; line-height: 1.8;">
                                    ${responsibilities.map(resp => `<li>${resp}</li>`).join('')}
                                </ul>
                            </div>` : ''}
                            
                            <div class="info-section">
                                <h4>Applications (${applications.length})</h4>
                                ${applicationsHtml}
                            </div>
                            
                            <div class="info-section">
                                <h4>Description</h4>
                                <p style="line-height: 1.8; color: #374151;">${job.description || 'No description provided'}</p>
                            </div>
                        `;
                        
                        document.getElementById('viewModal').classList.add('active');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load job details');
                });
        }

        function closeJob(jobId, jobTitle) {
            document.getElementById('close_job_id').value = jobId;
            document.getElementById('close_message').innerHTML = `Are you sure you want to close the job <strong>"${jobTitle}"</strong>? This will prevent new applications.`;
            document.getElementById('closeModal').classList.add('active');
        }

        function reopenJob(jobId, jobTitle) {
            document.getElementById('reopen_job_id').value = jobId;
            document.getElementById('reopen_message').innerHTML = `Are you sure you want to reopen the job <strong>"${jobTitle}"</strong>? This will allow new applications again.`;
            document.getElementById('reopenModal').classList.add('active');
        }

        function deleteJob(jobId, jobTitle) {
            document.getElementById('delete_job_id').value = jobId;
            document.getElementById('delete_message').innerHTML = `Are you sure you want to permanently delete the job <strong>"${jobTitle}"</strong>?`;
            document.getElementById('deleteModal').classList.add('active');
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
    </script>
</body>
</html>