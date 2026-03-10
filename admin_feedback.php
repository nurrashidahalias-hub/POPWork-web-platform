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

// Handle feedback actions
$message = '';
$message_type = '';

// Delete Feedback
if (isset($_POST['delete_feedback'])) {
    $feedback_id = intval($_POST['feedback_id']);
    
    $stmt = $conn->prepare("DELETE FROM feedback WHERE feedback_id = ?");
    $stmt->bind_param("i", $feedback_id);
    
    if ($stmt->execute()) {
        $message = "Feedback successfully deleted!";
        $message_type = 'success';
    } else {
        $message = "Error deleting feedback.";
        $message_type = 'error';
    }
}

// Get filters
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'all';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT f.*, 
               j.title as job_title,
               j.category as job_category,
               reviewer_profile.name as reviewer_name,
               reviewer_user.email as reviewer_email,
               reviewer_user.role as reviewer_role,
               reviewee_profile.name as reviewee_name,
               reviewee_user.email as reviewee_email,
               reviewee_user.role as reviewee_role
        FROM feedback f
        JOIN applications a ON f.application_id = a.application_id
        JOIN jobs j ON a.job_id = j.job_id
        JOIN users reviewer_user ON f.reviewer_id = reviewer_user.user_id
        LEFT JOIN profiles reviewer_profile ON reviewer_user.user_id = reviewer_profile.user_id
        JOIN users reviewee_user ON f.reviewee_id = reviewee_user.user_id
        LEFT JOIN profiles reviewee_profile ON reviewee_user.user_id = reviewee_profile.user_id
        WHERE 1=1";

if ($rating_filter != 'all') {
    $sql .= " AND f.rating = " . intval($rating_filter);
}

if ($role_filter == 'worker') {
    $sql .= " AND reviewee_user.role = 'worker'";
} elseif ($role_filter == 'employer') {
    $sql .= " AND reviewee_user.role = 'employer'";
}

if ($date_from != '') {
    $sql .= " AND DATE(f.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}

if ($date_to != '') {
    $sql .= " AND DATE(f.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

if ($search != '') {
    $search_term = $conn->real_escape_string($search);
    $sql .= " AND (j.title LIKE '%$search_term%' OR reviewer_profile.name LIKE '%$search_term%' OR reviewee_profile.name LIKE '%$search_term%' OR f.comment LIKE '%$search_term%')";
}

$sql .= " ORDER BY f.created_at DESC";

$feedbacks = $conn->query($sql);

// Get feedback statistics
$total_feedbacks = $conn->query("SELECT COUNT(*) as count FROM feedback")->fetch_assoc()['count'];
$avg_rating = $conn->query("SELECT AVG(rating) as avg FROM feedback")->fetch_assoc()['avg'] ?? 0;
$worker_feedbacks = $conn->query("SELECT COUNT(*) as count FROM feedback f JOIN users u ON f.reviewee_id = u.user_id WHERE u.role = 'worker'")->fetch_assoc()['count'];
$employer_feedbacks = $conn->query("SELECT COUNT(*) as count FROM feedback f JOIN users u ON f.reviewee_id = u.user_id WHERE u.role = 'employer'")->fetch_assoc()['count'];

// Get rating distribution
$rating_dist = $conn->query("SELECT rating, COUNT(*) as count FROM feedback GROUP BY rating ORDER BY rating DESC");
$distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
while ($row = $rating_dist->fetch_assoc()) {
    $distribution[$row['rating']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback & Ratings | POP!Work Admin</title>
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

        .stat-box-subtext {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .rating-stars {
            color: #fbbf24;
            font-size: 18px;
        }

        /* Rating Distribution */
        .rating-distribution {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .rating-distribution h3 {
            font-size: 18px;
            color: var(--pop-maroon);
            margin-bottom: 20px;
        }

        .rating-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .rating-label {
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 60px;
            font-size: 14px;
            font-weight: 500;
        }

        .rating-progress {
            flex: 1;
            height: 24px;
            background: #f3f4f6;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .rating-fill {
            height: 100%;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
            transition: width 0.3s ease;
        }

        .rating-count {
            min-width: 40px;
            text-align: right;
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
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

        /* Feedback Table */
        .feedback-table-container {
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

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .user-name {
            font-weight: 600;
            color: #1f2937;
        }

        .user-role {
            font-size: 12px;
            color: #6b7280;
        }

        .stars {
            color: #fbbf24;
            font-size: 16px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.worker {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge.employer {
            background: #fef3c7;
            color: #92400e;
        }

        .comment-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #6b7280;
            font-size: 13px;
        }

        .action-btns {
            display: flex;
            gap: 8px;
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

        .action-btn.delete {
            background: #fee2e2;
            color: #991b1b;
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

        .rating-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .rating-item {
            background: var(--pop-gray);
            padding: 16px;
            border-radius: 8px;
        }

        .rating-item-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .rating-item-value {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 600;
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

        .comment-box {
            background: var(--pop-gray);
            padding: 16px;
            border-radius: 8px;
            margin-top: 12px;
            line-height: 1.6;
            color: #374151;
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
            <a href="admin_feedback.php" class="menu-item active">
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
            <h1><i class="fas fa-star"></i> Feedback & Ratings Management</h1>
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
                <div class="stat-box-label">Total Feedback</div>
                <div class="stat-box-value"><?= $total_feedbacks ?></div>
                <div class="stat-box-subtext">All time</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Average Rating</div>
                <div class="stat-box-value">
                    <?= number_format($avg_rating, 1) ?>
                    <span class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?= $i <= round($avg_rating) ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                    </span>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Worker Reviews</div>
                <div class="stat-box-value" style="color: #3b82f6;"><?= $worker_feedbacks ?></div>
                <div class="stat-box-subtext">Reviews for workers</div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Employer Reviews</div>
                <div class="stat-box-value" style="color: #f59e0b;"><?= $employer_feedbacks ?></div>
                <div class="stat-box-subtext">Reviews for employers</div>
            </div>
        </div>

        <!-- Rating Distribution -->
        <div class="rating-distribution">
            <h3>Rating Distribution</h3>
            <?php foreach ([5, 4, 3, 2, 1] as $rating): 
                $count = $distribution[$rating];
                $percentage = $total_feedbacks > 0 ? ($count / $total_feedbacks) * 100 : 0;
            ?>
            <div class="rating-bar">
                <div class="rating-label">
                    <span><?= $rating ?></span>
                    <i class="fas fa-star" style="color: #fbbf24;"></i>
                </div>
                <div class="rating-progress">
                    <div class="rating-fill" style="width: <?= $percentage ?>%"></div>
                </div>
                <div class="rating-count"><?= $count ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by job, reviewer, or comment..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="rating" class="filter-select">
                        <option value="all" <?= $rating_filter == 'all' ? 'selected' : '' ?>>All Ratings</option>
                        <option value="5" <?= $rating_filter == '5' ? 'selected' : '' ?>>5 Stars</option>
                        <option value="4" <?= $rating_filter == '4' ? 'selected' : '' ?>>4 Stars</option>
                        <option value="3" <?= $rating_filter == '3' ? 'selected' : '' ?>>3 Stars</option>
                        <option value="2" <?= $rating_filter == '2' ? 'selected' : '' ?>>2 Stars</option>
                        <option value="1" <?= $rating_filter == '1' ? 'selected' : '' ?>>1 Star</option>
                    </select>
                    <select name="role" class="filter-select">
                        <option value="all" <?= $role_filter == 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="worker" <?= $role_filter == 'worker' ? 'selected' : '' ?>>Workers</option>
                        <option value="employer" <?= $role_filter == 'employer' ? 'selected' : '' ?>>Employers</option>
                    </select>
                    <input type="date" name="date_from" class="filter-date" value="<?= htmlspecialchars($date_from) ?>">
                    <input type="date" name="date_to" class="filter-date" value="<?= htmlspecialchars($date_to) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Feedback Table -->
        <div class="feedback-table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Reviewer</th>
                            <th>Reviewee</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($feedbacks->num_rows > 0): ?>
                            <?php while ($feedback = $feedbacks->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <span class="user-name"><?= htmlspecialchars($feedback['job_title']) ?></span>
                                        <span class="user-role"><?= htmlspecialchars($feedback['job_category']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <span class="user-name"><?= htmlspecialchars($feedback['reviewer_name'] ?? 'N/A') ?></span>
                                        <span class="badge <?= $feedback['reviewer_role'] ?>">
                                            <?= ucfirst($feedback['reviewer_role']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <span class="user-name"><?= htmlspecialchars($feedback['reviewee_name'] ?? 'N/A') ?></span>
                                        <span class="badge <?= $feedback['reviewee_role'] ?>">
                                            <?= ucfirst($feedback['reviewee_role']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?= $i <= $feedback['rating'] ? '' : '-o' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                        <?= $feedback['rating'] ?>/5
                                    </div>
                                </td>
                                <td>
                                    <div class="comment-preview" title="<?= htmlspecialchars($feedback['comment'] ?? 'No comment') ?>">
                                        <?= htmlspecialchars($feedback['comment'] ?? 'No comment') ?>
                                    </div>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($feedback['created_at'])) ?><br>
                                    <small style="color: #6b7280;"><?= date('h:i A', strtotime($feedback['created_at'])) ?></small>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn view" onclick="viewFeedback(<?= $feedback['feedback_id'] ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="action-btn delete" onclick="deleteFeedback(<?= $feedback['feedback_id'] ?>, '<?= htmlspecialchars($feedback['job_title']) ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <i class="fas fa-star"></i>
                                    No feedback found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Feedback Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-star"></i> Feedback Details</h3>
                <button class="close-modal" onclick="closeModal('viewModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Delete Feedback Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3><i class="fas fa-trash"></i> Delete Feedback</h3>
                    <button type="button" class="close-modal" onclick="closeModal('deleteModal')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="feedback_id" id="delete_feedback_id">
                    <p id="delete_message" style="font-size: 16px; color: #374151; line-height: 1.6;"></p>
                    <div style="background: #fee2e2; padding: 16px; border-radius: 8px; margin-top: 16px;">
                        <p style="color: #991b1b; font-weight: 500;">
                            <i class="fas fa-exclamation-triangle"></i> Warning: This action cannot be undone!
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" name="delete_feedback" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewFeedback(feedbackId) {
            // Fetch feedback details via AJAX
            fetch(`backend/get_feedback_details.php?feedback_id=${feedbackId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const f = data.feedback;
                        
                        // Generate star rating HTML
                        let starsHtml = '';
                        for (let i = 1; i <= 5; i++) {
                            starsHtml += `<i class="fas fa-star${i <= f.rating ? '' : '-o'}" style="color: #fbbf24;"></i>`;
                        }
                        
                        // Build rating details for worker/employer specific ratings
                        let ratingDetailsHtml = '';
                        if (f.reviewer_role === 'employer' && f.reviewee_role === 'worker') {
                            // Employer rating worker
                            ratingDetailsHtml = `
                                <div class="rating-details">
                                    ${f.work_quality ? `
                                    <div class="rating-item">
                                        <div class="rating-item-label">Work Quality</div>
                                        <div class="rating-item-value">
                                            <span>${f.work_quality}/5</span>
                                            <span class="stars">${'★'.repeat(f.work_quality)}${'☆'.repeat(5-f.work_quality)}</span>
                                        </div>
                                    </div>` : ''}
                                    ${f.reliability ? `
                                    <div class="rating-item">
                                        <div class="rating-item-label">Reliability</div>
                                        <div class="rating-item-value">
                                            <span>${f.reliability}/5</span>
                                            <span class="stars">${'★'.repeat(f.reliability)}${'☆'.repeat(5-f.reliability)}</span>
                                        </div>
                                    </div>` : ''}
                                    ${f.professionalism ? `
                                    <div class="rating-item">
                                        <div class="rating-item-label">Professionalism</div>
                                        <div class="rating-item-value">
                                            <span>${f.professionalism}/5</span>
                                            <span class="stars">${'★'.repeat(f.professionalism)}${'☆'.repeat(5-f.professionalism)}</span>
                                        </div>
                                    </div>` : ''}
                                </div>
                            `;
                        } else if (f.reviewer_role === 'worker' && f.reviewee_role === 'employer') {
                            // Worker rating employer
                            ratingDetailsHtml = `
                                <div class="rating-details">
                                    ${f.job_accuracy ? `
                                    <div class="rating-item">
                                        <div class="rating-item-label">Job Accuracy</div>
                                        <div class="rating-item-value">
                                            <span>${f.job_accuracy}/5</span>
                                            <span class="stars">${'★'.repeat(f.job_accuracy)}${'☆'.repeat(5-f.job_accuracy)}</span>
                                        </div>
                                    </div>` : ''}
                                    ${f.payment_timeliness ? `
                                    <div class="rating-item">
                                        <div class="rating-item-label">Payment Timeliness</div>
                                        <div class="rating-item-value">
                                            <span>${f.payment_timeliness}/5</span>
                                            <span class="stars">${'★'.repeat(f.payment_timeliness)}${'☆'.repeat(5-f.payment_timeliness)}</span>
                                        </div>
                                    </div>` : ''}
                                    ${f.communication ? `
                                    <div class="rating-item">
                                        <div class="rating-item-label">Communication</div>
                                        <div class="rating-item-value">
                                            <span>${f.communication}/5</span>
                                            <span class="stars">${'★'.repeat(f.communication)}${'☆'.repeat(5-f.communication)}</span>
                                        </div>
                                    </div>` : ''}
                                </div>
                            `;
                        }
                        
                        document.getElementById('viewModalBody').innerHTML = `
                            <div class="info-section">
                                <h4>Overall Rating</h4>
                                <div style="text-align: center; padding: 20px;">
                                    <div style="font-size: 48px; font-weight: 700; color: var(--pop-maroon); margin-bottom: 8px;">
                                        ${f.rating}.0
                                    </div>
                                    <div style="font-size: 32px;">
                                        ${starsHtml}
                                    </div>
                                </div>
                            </div>
                            
                            ${ratingDetailsHtml ? `
                            <div class="info-section">
                                <h4>Detailed Ratings</h4>
                                ${ratingDetailsHtml}
                            </div>` : ''}
                            
                            <div class="info-section">
                                <h4>Job Information</h4>
                                <div class="info-row">
                                    <span class="info-label">Job Title:</span>
                                    <span class="info-value">${f.job_title}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Category:</span>
                                    <span class="info-value">${f.job_category}</span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Reviewer Information</h4>
                                <div class="info-row">
                                    <span class="info-label">Name:</span>
                                    <span class="info-value">${f.reviewer_name || 'N/A'}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value">${f.reviewer_email}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Role:</span>
                                    <span class="info-value"><span class="badge ${f.reviewer_role}">${f.reviewer_role.charAt(0).toUpperCase() + f.reviewer_role.slice(1)}</span></span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Reviewee Information</h4>
                                <div class="info-row">
                                    <span class="info-label">Name:</span>
                                    <span class="info-value">${f.reviewee_name || 'N/A'}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Email:</span>
                                    <span class="info-value">${f.reviewee_email}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Role:</span>
                                    <span class="info-value"><span class="badge ${f.reviewee_role}">${f.reviewee_role.charAt(0).toUpperCase() + f.reviewee_role.slice(1)}</span></span>
                                </div>
                            </div>
                            
                            <div class="info-section">
                                <h4>Additional Information</h4>
                                <div class="info-row">
                                    <span class="info-label">Would Work Again:</span>
                                    <span class="info-value" style="color: ${f.would_work_again == 1 ? '#10b981' : '#ef4444'};">
                                        ${f.would_work_again == 1 ? '✓ Yes' : '✗ No'}
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Submitted:</span>
                                    <span class="info-value">${new Date(f.created_at).toLocaleString()}</span>
                                </div>
                            </div>
                            
                            ${f.comment ? `
                            <div class="info-section">
                                <h4>Comment</h4>
                                <div class="comment-box">
                                    ${f.comment || 'No comment provided'}
                                </div>
                            </div>` : ''}
                        `;
                        
                        document.getElementById('viewModal').classList.add('active');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load feedback details');
                });
        }

        function deleteFeedback(feedbackId, jobTitle) {
            document.getElementById('delete_feedback_id').value = feedbackId;
            document.getElementById('delete_message').innerHTML = `Are you sure you want to delete this feedback for <strong>"${jobTitle}"</strong>?`;
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