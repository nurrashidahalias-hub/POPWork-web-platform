<?php
session_start();
include("backend/db_connect.php");

// ---------------------------------------------------------
// 🔒 SECURITY CHECK: Robust Admin Verification
// ---------------------------------------------------------

// 1. Basic Session Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: admin-login.html");
    exit();
}

// 2. Role Check (Session Level)
if ($_SESSION['role'] !== 'admin') {
    // If they are logged in but not an admin, kick them out
    session_unset();
    session_destroy();
    header("Location: admin-login.html?error=access_denied");
    exit();
}

// 3. Database Re-verification (Prevent Session Hijacking/Stale Permissions)
// This ensures that even if the session says 'admin', the DB must agree.
$user_id = $_SESSION['user_id'];
$check_sql = "SELECT role FROM users WHERE user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    // User no longer exists
    session_unset();
    session_destroy();
    header("Location: admin-login.html");
    exit();
}

$db_user = $check_result->fetch_assoc();
if ($db_user['role'] !== 'admin') {
    // User was demoted or role changed
    session_unset();
    session_destroy();
    header("Location: admin-login.html?error=access_denied");
    exit();
}
// ---------------------------------------------------------
// End Security Check
// ---------------------------------------------------------

$admin_id = $_SESSION['user_id'];

// Get admin info
$admin_sql = "SELECT u.email, p.name FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();
$admin_name = $admin_data['name'] ?? explode('@', $admin_data['email'])[0];

// Get Statistics
// Total Users
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('worker', 'employer')")->fetch_assoc()['count'];
$total_workers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'worker'")->fetch_assoc()['count'];
$total_employers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employer'")->fetch_assoc()['count'];

// Active Jobs (open status)
$active_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'open'")->fetch_assoc()['count'];
$completed_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'closed'")->fetch_assoc()['count'];

// Total Revenue (sum of all paid payments)
$revenue_result = $conn->query("SELECT SUM(payment_amount) as total FROM applications WHERE payment_status = 'paid'");
$total_revenue = $revenue_result->fetch_assoc()['total'] ?? 0;

// Pending Payments
$pending_payments = $conn->query("SELECT COUNT(*) as count FROM applications WHERE payment_status = 'pending' AND job_completed = 1")->fetch_assoc()['count'];

// Disputed Payments
$disputed_payments = $conn->query("SELECT COUNT(*) as count FROM applications WHERE payment_status = 'disputed'")->fetch_assoc()['count'];

// Total Work Hours
$hours_result = $conn->query("SELECT SUM(total_work_hours) as total FROM applications");
$total_hours = $hours_result->fetch_assoc()['total'] ?? 0;

// Pending Applications
$pending_applications = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'Pending'")->fetch_assoc()['count'];

// Get Recent Activity (last 10 activities)
$recent_activity = [];

// New users (last 7 days)
$new_users_sql = "SELECT u.user_id, u.email, u.role, u.created_at, p.name, p.photo_url, p.profile_picture 
                  FROM users u 
                  LEFT JOIN profiles p ON u.user_id = p.user_id 
                  WHERE u.role IN ('worker', 'employer') 
                  AND u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                  ORDER BY u.created_at DESC LIMIT 5";
$new_users = $conn->query($new_users_sql);

// Recent completed jobs
$completed_jobs_sql = "SELECT j.job_id, j.title, j.updated_at, a.completion_date, p.name as employer_name
                       FROM jobs j
                       JOIN applications a ON j.job_id = a.job_id
                       LEFT JOIN profiles p ON j.employer_id = p.user_id
                       WHERE a.job_completed = 1
                       ORDER BY a.completion_date DESC LIMIT 5";
$completed_jobs_list = $conn->query($completed_jobs_sql);

// Recent payments
$recent_payments_sql = "SELECT a.application_id, a.payment_amount, a.payment_date, j.title, 
                        pw.name as worker_name, pe.name as employer_name
                        FROM applications a
                        JOIN jobs j ON a.job_id = j.job_id
                        LEFT JOIN profiles pw ON a.user_id = pw.user_id
                        LEFT JOIN profiles pe ON j.employer_id = pe.user_id
                        WHERE a.payment_status = 'paid'
                        ORDER BY a.payment_date DESC LIMIT 5";
$recent_payments = $conn->query($recent_payments_sql);

// Get month comparison
$current_month_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('worker', 'employer') AND MONTH(created_at) = MONTH(NOW())")->fetch_assoc()['count'];
$last_month_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('worker', 'employer') AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))")->fetch_assoc()['count'];
$user_growth = $last_month_users > 0 ? round((($current_month_users - $last_month_users) / $last_month_users) * 100, 1) : 0;

$current_month_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE MONTH(created_at) = MONTH(NOW())")->fetch_assoc()['count'];
$last_month_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))")->fetch_assoc()['count'];
$job_growth = $last_month_jobs > 0 ? round((($current_month_jobs - $last_month_jobs) / $last_month_jobs) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | POP!Work</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        /* Top Bar */
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

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .admin-info span {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .admin-info small {
            font-size: 12px;
            color: #666;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(138, 21, 56, 0.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.users {
            background: #eff6ff;
            color: #3b82f6;
        }

        .stat-icon.jobs {
            background: #f0fdf4;
            color: #10b981;
        }

        .stat-icon.revenue {
            background: #fef3c7;
            color: #f59e0b;
        }

        .stat-icon.hours {
            background: #fce7f3;
            color: #ec4899;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 13px;
            font-weight: 500;
        }

        .stat-change.positive {
            color: #10b981;
        }

        .stat-change.negative {
            color: #ef4444;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--pop-gray);
        }

        .card-header h3 {
            font-size: 18px;
            color: #1f2937;
            font-weight: 600;
        }

        .view-all {
            color: var(--pop-maroon);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        /* Activity Feed */
        .activity-item {
            padding: 16px;
            border-left: 3px solid var(--pop-gray);
            margin-bottom: 12px;
            background: var(--pop-light);
            border-radius: 8px;
            transition: all 0.3s;
        }

        .activity-item:hover {
            border-left-color: var(--pop-maroon);
            background: white;
            box-shadow: 0 2px 8px rgba(138, 21, 56, 0.1);
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 14px;
        }

        .activity-icon.new-user {
            background: #eff6ff;
            color: #3b82f6;
        }

        .activity-icon.completed {
            background: #f0fdf4;
            color: #10b981;
        }

        .activity-icon.payment {
            background: #fef3c7;
            color: #f59e0b;
        }

        .activity-icon.dispute {
            background: #fee2e2;
            color: #ef4444;
        }

        .activity-content {
            display: inline-block;
            vertical-align: middle;
            width: calc(100% - 50px);
        }

        .activity-text {
            font-size: 14px;
            color: #374151;
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 12px;
            color: #9ca3af;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .quick-action-btn {
            padding: 16px;
            border: 2px solid var(--pop-gray);
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            color: #374151;
        }

        .quick-action-btn:hover {
            border-color: var(--pop-maroon);
            background: var(--pop-light);
            transform: translateY(-2px);
        }

        .quick-action-btn i {
            font-size: 24px;
            color: var(--pop-maroon);
            margin-bottom: 8px;
            display: block;
        }

        .quick-action-btn span {
            font-size: 13px;
            font-weight: 500;
            display: block;
        }

        /* Alerts */
        .alerts-section {
            margin-bottom: 24px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert.warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
        }

        .alert.danger {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
        }

        .alert i {
            font-size: 20px;
        }

        .alert.warning i {
            color: #f59e0b;
        }

        .alert.danger i {
            color: #ef4444;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .alert-text {
            font-size: 13px;
            color: #6b7280;
        }

        .alert-action {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            background: white;
            color: #374151;
            transition: all 0.3s;
        }

        .alert-action:hover {
            transform: scale(1.05);
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
            <a href="admin_dashboard.php" class="menu-item active">
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
            <h1>Dashboard Overview</h1>
            
        </div>

        <?php if ($pending_payments > 0 || $disputed_payments > 0): ?>
        <div class="alerts-section">
            <?php if ($disputed_payments > 0): ?>
            <div class="alert danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <div class="alert-title">Payment Disputes Requiring Attention</div>
                    <div class="alert-text"><?= $disputed_payments ?> disputed payment(s) need resolution</div>
                </div>
                <button class="alert-action" onclick="location.href='admin_payments.php?filter=disputed'">Review Now</button>
            </div>
            <?php endif; ?>
            
            <?php if ($pending_payments > 0): ?>
            <div class="alert warning">
                <i class="fas fa-clock"></i>
                <div class="alert-content">
                    <div class="alert-title">Pending Payments</div>
                    <div class="alert-text"><?= $pending_payments ?> completed job(s) awaiting payment</div>
                </div>
                <button class="alert-action" onclick="location.href='admin_payments.php?filter=pending'">View Details</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Total Users</div>
                        <div class="stat-value"><?= number_format($total_users) ?></div>
                        <div class="stat-change <?= $user_growth >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-arrow-<?= $user_growth >= 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($user_growth) ?>% this month
                        </div>
                    </div>
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                    <?= $total_workers ?> Workers • <?= $total_employers ?> Employers
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Active Jobs</div>
                        <div class="stat-value"><?= number_format($active_jobs) ?></div>
                        <div class="stat-change <?= $job_growth >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-arrow-<?= $job_growth >= 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($job_growth) ?>% this month
                        </div>
                    </div>
                    <div class="stat-icon jobs">
                        <i class="fas fa-briefcase"></i>
                    </div>
                </div>
                <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                    <?= $completed_jobs ?> Completed Jobs
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value">RM <?= number_format($total_revenue, 2) ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-check-circle"></i>
                            All time earnings
                        </div>
                    </div>
                    <div class="stat-icon revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                    <?= $pending_payments ?> Pending • <?= $disputed_payments ?> Disputed
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Work Hours</div>
                        <div class="stat-value"><?= number_format($total_hours, 1) ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-clock"></i>
                            Total logged
                        </div>
                    </div>
                    <div class="stat-icon hours">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div style="font-size: 13px; color: #6b7280; margin-top: 8px;">
                    <?= $pending_applications ?> Pending Applications
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                    <a href="#" class="view-all">View All</a>
                </div>

                <?php while ($user = $new_users->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-icon new-user">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">
                            <strong>New <?= ucfirst($user['role']) ?> registered:</strong> 
                            <?= htmlspecialchars($user['name'] ?? $user['email']) ?>
                        </div>
                        <div class="activity-time">
                            <i class="fas fa-clock"></i> 
                            <?= date('M d, Y - h:i A', strtotime($user['created_at'])) ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>

                <?php while ($job = $completed_jobs_list->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">
                            <strong>Job completed:</strong> <?= htmlspecialchars($job['title']) ?>
                            by <?= htmlspecialchars($job['employer_name'] ?? 'Employer') ?>
                        </div>
                        <div class="activity-time">
                            <i class="fas fa-clock"></i> 
                            <?= date('M d, Y - h:i A', strtotime($job['completion_date'])) ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>

                <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-icon payment">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">
                            <strong>Payment processed:</strong> RM <?= number_format($payment['payment_amount'], 2) ?>
                            for <?= htmlspecialchars($payment['title']) ?>
                        </div>
                        <div class="activity-time">
                            <i class="fas fa-clock"></i> 
                            <?= date('M d, Y - h:i A', strtotime($payment['payment_date'])) ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <a href="admin_users.php" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Manage Users</span>
                    </a>
                    <a href="admin_jobs.php" class="quick-action-btn">
                        <i class="fas fa-briefcase"></i>
                        <span>View Jobs</span>
                    </a>
                    <a href="admin_payments.php" class="quick-action-btn">
                        <i class="fas fa-money-check"></i>
                        <span>Payments</span>
                    </a>
                    <a href="admin_payments.php?filter=disputed" class="quick-action-btn">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Disputes</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>