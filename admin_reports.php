<?php
session_start();
include("backend/db_connect.php");

// Security Check
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

// Date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Export functionality
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $export_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    switch($export_type) {
        case 'users':
            fputcsv($output, ['User ID', 'Name', 'Email', 'Role', 'Phone', 'District', 'Rating', 'Total Reviews', 'Joined Date']);
            $query = "SELECT u.user_id, p.name, u.email, u.role, p.phone, p.district, p.avg_rating, p.total_reviews, u.created_at
                      FROM users u
                      LEFT JOIN profiles p ON u.user_id = p.user_id
                      WHERE u.role IN ('worker', 'employer')
                      AND u.created_at BETWEEN ? AND ?
                      ORDER BY u.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            break;
            
        case 'jobs':
            fputcsv($output, ['Job ID', 'Title', 'Category', 'Employer', 'Location', 'Pay Rate', 'Status', 'Applications', 'Created Date']);
            $query = "SELECT j.job_id, j.title, j.category, p.name as employer, j.location, j.pay_rate, j.status,
                      (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as app_count,
                      j.created_at
                      FROM jobs j
                      LEFT JOIN profiles p ON j.employer_id = p.user_id
                      WHERE j.created_at BETWEEN ? AND ?
                      ORDER BY j.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            break;
            
        case 'payments':
            fputcsv($output, ['Payment ID', 'Job', 'Worker', 'Employer', 'Hours', 'Amount', 'Status', 'Method', 'Payment Date']);
            $query = "SELECT pr.payment_id, j.title, pw.name as worker, pe.name as employer, 
                      pr.total_hours, pr.final_amount, pr.payment_status, pr.payment_method, pr.created_at
                      FROM payment_records pr
                      JOIN jobs j ON pr.job_id = j.job_id
                      LEFT JOIN profiles pw ON pr.user_id = pw.user_id
                      LEFT JOIN profiles pe ON pr.employer_id = pe.user_id
                      WHERE pr.created_at BETWEEN ? AND ?
                      ORDER BY pr.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            break;
            
        case 'attendance':
            fputcsv($output, ['Attendance ID', 'Worker', 'Job', 'Employer', 'Clock In', 'Clock Out', 'Hours', 'Location Verified', 'Status']);
            $query = "SELECT a.attendance_id, pw.name as worker, j.title, pe.name as employer,
                      a.clock_in_time, a.clock_out_time, a.total_hours, a.location_verified, a.status
                      FROM attendance a
                      JOIN jobs j ON a.job_id = j.job_id
                      LEFT JOIN profiles pw ON a.user_id = pw.user_id
                      LEFT JOIN profiles pe ON a.employer_id = pe.user_id
                      WHERE a.created_at BETWEEN ? AND ?
                      ORDER BY a.created_at DESC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, $row);
            }
            break;
    }
    
    fclose($output);
    exit();
}

// --- ANALYTICS DATA ---

// User Growth
$user_growth = $conn->query("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM users
    WHERE role IN ('worker', 'employer')
    AND created_at BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetch_all(MYSQLI_ASSOC);

// Job Statistics
$job_stats = [
    'total' => $conn->query("SELECT COUNT(*) as c FROM jobs WHERE created_at BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['c'],
    'by_category' => $conn->query("
        SELECT category, COUNT(*) as count
        FROM jobs
        WHERE created_at BETWEEN '$start_date' AND '$end_date'
        GROUP BY category
    ")->fetch_all(MYSQLI_ASSOC),
    'by_status' => $conn->query("
        SELECT status, COUNT(*) as count
        FROM jobs
        WHERE created_at BETWEEN '$start_date' AND '$end_date'
        GROUP BY status
    ")->fetch_all(MYSQLI_ASSOC)
];

// Payment Analytics
$payment_stats = [
    'total_revenue' => $conn->query("SELECT SUM(final_amount) as total FROM payment_records WHERE payment_status = 'completed' AND created_at BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'] ?? 0,
    'total_payments' => $conn->query("SELECT COUNT(*) as c FROM payment_records WHERE created_at BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['c'],
    'by_status' => $conn->query("
        SELECT payment_status, COUNT(*) as count
        FROM payment_records
        WHERE created_at BETWEEN '$start_date' AND '$end_date'
        GROUP BY payment_status
    ")->fetch_all(MYSQLI_ASSOC)
];

// Top Employers
$top_employers = $conn->query("
    SELECT p.name, u.email, COUNT(j.job_id) as jobs_posted, 
    SUM(pr.final_amount) as total_paid,
    (SELECT COUNT(*) FROM applications a WHERE a.job_id IN (SELECT job_id FROM jobs WHERE employer_id = u.user_id)) as total_applications
    FROM users u
    LEFT JOIN profiles p ON u.user_id = p.user_id
    LEFT JOIN jobs j ON u.user_id = j.employer_id AND j.created_at BETWEEN '$start_date' AND '$end_date'
    LEFT JOIN payment_records pr ON u.user_id = pr.employer_id AND pr.payment_status = 'completed'
    WHERE u.role = 'employer'
    GROUP BY u.user_id
    ORDER BY jobs_posted DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Top Workers
$top_workers = $conn->query("
    SELECT p.name, u.email, p.avg_rating, p.total_reviews,
    COUNT(a.application_id) as jobs_completed,
    SUM(a.total_work_hours) as total_hours,
    SUM(pr.final_amount) as total_earned
    FROM users u
    LEFT JOIN profiles p ON u.user_id = p.user_id
    LEFT JOIN applications a ON u.user_id = a.user_id AND a.job_completed = 1 AND a.created_at BETWEEN '$start_date' AND '$end_date'
    LEFT JOIN payment_records pr ON u.user_id = pr.user_id AND pr.payment_status = 'completed'
    WHERE u.role = 'worker'
    GROUP BY u.user_id
    ORDER BY jobs_completed DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Attendance Statistics
$attendance_stats = [
    'total_hours' => $conn->query("SELECT SUM(total_hours) as total FROM attendance WHERE created_at BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'] ?? 0,
    'total_sessions' => $conn->query("SELECT COUNT(*) as c FROM attendance WHERE created_at BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['c']
];

// District Distribution
$district_stats = $conn->query("
    SELECT p.district, COUNT(*) as worker_count
    FROM profiles p
    JOIN users u ON p.user_id = u.user_id
    WHERE u.role = 'worker' AND p.district IS NOT NULL AND p.district != ''
    GROUP BY p.district
    ORDER BY worker_count DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | POP!Work Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --pop-maroon: #8a1538; --pop-light: #fdf2f4; --pop-gray: #f8f9fa; --pop-maroon-dark: #6d1029; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: var(--pop-gray); min-height: 100vh; }
        
        /* Sidebar */
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0; width: 260px;
            background: linear-gradient(180deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white; overflow-y: auto; z-index: 1000;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
        .sidebar-header p { font-size: 12px; opacity: 0.8; }

        .sidebar-menu { padding: 20px 0; }
        .menu-item {
            display: flex; align-items: center; padding: 14px 24px;
            color: white; text-decoration: none; transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .menu-item:hover { background: rgba(255,255,255,0.1); border-left-color: white; }
        .menu-item.active { background: rgba(255,255,255,0.15); border-left-color: white; }
        .menu-item i { width: 24px; margin-right: 12px; font-size: 18px; }
        .menu-item span { font-size: 14px; font-weight: 500; }
        
        .main-content { margin-left: 260px; padding: 24px; }
        .top-bar { background: white; padding: 16px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .top-bar h1 { font-size: 28px; color: var(--pop-maroon); font-weight: 700; }
        
        /* Filters */
        .filters-section {
            background: white; padding: 20px 24px; border-radius: 12px;
            margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .filters-row { display: flex; gap: 16px; flex-wrap: wrap; align-items: end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .filter-group input {
            width: 100%; padding: 10px 14px; border: 2px solid #e5e7eb;
            border-radius: 8px; font-size: 14px; font-family: 'Poppins';
        }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; font-family: 'Poppins'; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--pop-maroon), var(--pop-maroon-dark)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 12px; }
        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.green { background: #f0fdf4; color: #10b981; }
        .stat-icon.yellow { background: #fef3c7; color: #f59e0b; }
        .stat-icon.purple { background: #f3e8ff; color: #a855f7; }
        
        .stat-value { font-size: 28px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
        .stat-label { font-size: 13px; color: #6b7280; }
        
        /* Charts */
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 24px; margin-bottom: 24px; }
        .chart-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .chart-card h3 { font-size: 18px; color: #1f2937; margin-bottom: 20px; font-weight: 600; }
        .chart-container { position: relative; height: 300px; }
        
        /* Tables */
        .table-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 24px; overflow: hidden; }
        .table-header { padding: 20px 24px; border-bottom: 2px solid var(--pop-gray); display: flex; justify-content: space-between; align-items: center; }
        .table-header h3 { font-size: 18px; color: #1f2937; font-weight: 600; }
        
        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--pop-light); }
        th { padding: 14px 24px; text-align: left; font-size: 12px; font-weight: 600; color: var(--pop-maroon); text-transform: uppercase; }
        td { padding: 14px 24px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        tr:hover { background: var(--pop-light); }
        
        .export-buttons { display: flex; gap: 12px; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .charts-grid { grid-template-columns: 1fr; }
            .filters-row { flex-direction: column; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>POP!Work</h2><p>Admin Panel</p></div>
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
            <a href="admin_feedback.php" class="menu-item">
                <i class="fas fa-star"></i>
                <span>Feedback</span>
            </a>
                        <a href="admin_reports.php" class="menu-item active">
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
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
        </div>

        <div class="filters-section">
            <form method="GET">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= $start_date ?>" required>
                    </div>
                    <div class="filter-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= $end_date ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-value">RM <?= number_format($payment_stats['total_revenue'], 2) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="stat-value"><?= number_format($job_stats['total']) ?></div>
                <div class="stat-label">Jobs Posted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-value"><?= number_format($payment_stats['total_payments']) ?></div>
                <div class="stat-label">Total Payments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?= number_format($attendance_stats['total_hours'], 1) ?></div>
                <div class="stat-label">Work Hours Logged</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> User Growth Trend</h3>
                <div class="chart-container">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Jobs by Category</h3>
                <div class="chart-container">
                    <canvas id="jobCategoryChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3><i class="fas fa-chart-bar"></i> Payment Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h3><i class="fas fa-map-marker-alt"></i> Workers by District</h3>
                <div class="chart-container">
                    <canvas id="districtChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-download"></i> Export Reports</h3>
                <div class="export-buttons">
                    <a href="?export=users&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-success">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <a href="?export=jobs&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-success">
                        <i class="fas fa-briefcase"></i> Jobs
                    </a>
                    <a href="?export=payments&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-success">
                        <i class="fas fa-dollar-sign"></i> Payments
                    </a>
                    <a href="?export=attendance&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-success">
                        <i class="fas fa-clock"></i> Attendance
                    </a>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-trophy"></i> Top Employers</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Employer</th>
                        <th>Email</th>
                        <th>Jobs Posted</th>
                        <th>Applications</th>
                        <th>Total Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($top_employers) > 0): ?>
                        <?php foreach ($top_employers as $emp): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($emp['name'] ?? $emp['email']) ?></strong></td>
                            <td><?= htmlspecialchars($emp['email']) ?></td>
                            <td><?= $emp['jobs_posted'] ?></td>
                            <td><?= $emp['total_applications'] ?></td>
                            <td><strong>RM <?= number_format($emp['total_paid'] ?? 0, 2) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-star"></i> Top Workers</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Worker</th>
                        <th>Email</th>
                        <th>Rating</th>
                        <th>Jobs Completed</th>
                        <th>Total Hours</th>
                        <th>Total Earned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($top_workers) > 0): ?>
                        <?php foreach ($top_workers as $worker): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($worker['name'] ?? $worker['email']) ?></strong></td>
                            <td><?= htmlspecialchars($worker['email']) ?></td>
                            <td>
                                <span style="color: #f59e0b;">★ <?= number_format($worker['avg_rating'], 1) ?></span>
                                <span style="font-size: 11px; color: #888;">(<?= $worker['total_reviews'] ?>)</span>
                            </td>
                            <td><?= $worker['jobs_completed'] ?></td>
                            <td><?= number_format($worker['total_hours'], 1) ?> hrs</td>
                            <td><strong>RM <?= number_format($worker['total_earned'] ?? 0, 2) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
// Prepare Data from PHP
const userGrowthData = <?= json_encode($user_growth) ?>;
const jobCategoryData = <?= json_encode($job_stats['by_category']) ?>;
const paymentStatusData = <?= json_encode($payment_stats['by_status']) ?>;
const districtData = <?= json_encode($district_stats) ?>;

// 1. User Growth Chart - CHANGED TO HISTOGRAM (BAR CHART)
new Chart(document.getElementById('userGrowthChart'), {
    type: 'bar',
    data: {
        labels: userGrowthData.map(d => d.date),
        datasets: [{
            label: 'New Users',
            data: userGrowthData.map(d => d.count),
            backgroundColor: 'rgba(138, 21, 56, 0.8)',
            borderColor: '#8a1538',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: true
            }
        }
    }
});

// 2. Job Categories Chart - KEEP AS DOUGHNUT (NO CHANGE)
new Chart(document.getElementById('jobCategoryChart'), {
    type: 'doughnut',
    data: {
        labels: jobCategoryData.map(d => d.category),
        datasets: [{
            data: jobCategoryData.map(d => d.count),
            backgroundColor: ['#8a1538', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#64748b']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// 3. Payment Status Chart - ALREADY A HISTOGRAM (ENHANCED)
new Chart(document.getElementById('paymentStatusChart'), {
    type: 'bar',
    data: {
        labels: paymentStatusData.map(d => d.payment_status),
        datasets: [{
            label: 'Payment Count',
            data: paymentStatusData.map(d => d.count),
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280'],
            borderColor: ['#059669', '#d97706', '#dc2626', '#4b5563'],
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: true
            }
        }
    }
});

// 4. District Chart - CHANGED TO VERTICAL HISTOGRAM
new Chart(document.getElementById('districtChart'), {
    type: 'bar',
    data: {
        labels: districtData.map(d => d.district),
        datasets: [{
            label: 'Workers',
            data: districtData.map(d => d.worker_count),
            backgroundColor: 'rgba(138, 21, 56, 0.8)',
            borderColor: '#8a1538',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: true
            }
        }
    }
});
    </script>
</body>
</html>