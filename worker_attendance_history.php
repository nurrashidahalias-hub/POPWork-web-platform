<?php
session_start();
include("backend/db_connect.php");

// 🔒 Security: Ensure only workers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'worker') {
    header("Location: login.html");
    exit();
}

$worker_id = $_SESSION['user_id'];

// Get filter parameters
$job_filter = $_GET['job'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$status_filter = $_GET['status'] ?? 'all';

// ---------------------------------------------------------
// 1. MAIN QUERY: Fetch Attendance Records
// ---------------------------------------------------------
$sql = "SELECT 
    a.attendance_id,
    a.clock_in_time,
    a.clock_out_time,
    a.total_hours,
    a.status,
    a.location_verified,
    j.job_id,
    j.title as job_title,
    j.location as job_location,
    j.pay_rate,
    e.name as employer_name,
    e.photo_url as employer_photo
    FROM attendance a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN users u_emp ON j.employer_id = u_emp.user_id
    LEFT JOIN profiles e ON u_emp.user_id = e.user_id
    WHERE a.user_id = ? 
    AND DATE(a.clock_in_time) BETWEEN ? AND ?";

$params = [$worker_id, $date_from, $date_to];
$types = "iss";

if ($job_filter !== 'all') {
    $sql .= " AND a.job_id = ?";
    $params[] = $job_filter;
    $types .= "i";
}

if ($status_filter === 'completed') {
    $sql .= " AND a.clock_out_time IS NOT NULL";
} elseif ($status_filter === 'active') {
    $sql .= " AND a.clock_out_time IS NULL";
}

$sql .= " ORDER BY a.clock_in_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ---------------------------------------------------------
// 2. ANALYTICS: Summary Stats
// ---------------------------------------------------------
$analytics_sql = "SELECT 
    COUNT(DISTINCT a.attendance_id) as total_sessions,
    COUNT(DISTINCT j.employer_id) as unique_employers,
    COUNT(DISTINCT a.job_id) as jobs_worked,
    SUM(CASE WHEN a.clock_out_time IS NOT NULL THEN a.total_hours ELSE 0 END) as total_hours,
    AVG(CASE WHEN a.clock_out_time IS NOT NULL THEN a.total_hours ELSE NULL END) as avg_session_hours,
    SUM(CASE WHEN a.location_verified = 1 THEN 1 ELSE 0 END) as verified_sessions,
    SUM(CASE WHEN a.clock_out_time IS NOT NULL THEN (a.total_hours * j.pay_rate) ELSE 0 END) as total_earnings
    FROM attendance a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE a.user_id = ?
    AND DATE(a.clock_in_time) BETWEEN ? AND ?";

$analytics_stmt = $conn->prepare($analytics_sql);
$analytics_stmt->bind_param("iss", $worker_id, $date_from, $date_to);
$analytics_stmt->execute();
$analytics = $analytics_stmt->get_result()->fetch_assoc();

// ---------------------------------------------------------
// 3. FILTERS: Get Jobs list for this worker
// ---------------------------------------------------------
$jobs_sql = "SELECT DISTINCT j.job_id, j.title 
             FROM attendance a
             JOIN jobs j ON a.job_id = j.job_id
             WHERE a.user_id = ?
             ORDER BY j.title ASC";
$jobs_stmt = $conn->prepare($jobs_sql);
$jobs_stmt->bind_param("i", $worker_id);
$jobs_stmt->execute();
$jobs_list = $jobs_stmt->get_result();

// ---------------------------------------------------------
// 4. CHART DATA: Daily Hours
// ---------------------------------------------------------
$daily_sql = "SELECT 
    DATE(a.clock_in_time) as date,
    SUM(CASE WHEN a.clock_out_time IS NOT NULL THEN a.total_hours ELSE 0 END) as hours
    FROM attendance a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE a.user_id = ?
    AND DATE(a.clock_in_time) BETWEEN ? AND ?
    GROUP BY DATE(a.clock_in_time)
    ORDER BY date ASC";
$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->bind_param("iss", $worker_id, $date_from, $date_to);
$daily_stmt->execute();
$daily_data = $daily_stmt->get_result();

// ---------------------------------------------------------
// 5. LEADERBOARD: Top Paying Jobs
// ---------------------------------------------------------
$top_jobs_sql = "SELECT 
    j.title as job_title,
    e.name as employer_name,
    COUNT(*) as sessions,
    SUM(CASE WHEN a.clock_out_time IS NOT NULL THEN a.total_hours ELSE 0 END) as total_hours,
    SUM(CASE WHEN a.clock_out_time IS NOT NULL THEN (a.total_hours * j.pay_rate) ELSE 0 END) as earnings
    FROM attendance a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN users u_emp ON j.employer_id = u_emp.user_id
    LEFT JOIN profiles e ON u_emp.user_id = e.user_id
    WHERE a.user_id = ?
    AND DATE(a.clock_in_time) BETWEEN ? AND ?
    AND a.clock_out_time IS NOT NULL
    GROUP BY j.job_id
    ORDER BY earnings DESC
    LIMIT 5";
$top_jobs_stmt = $conn->prepare($top_jobs_sql);
$top_jobs_stmt->bind_param("iss", $worker_id, $date_from, $date_to);
$top_jobs_stmt->execute();
$top_jobs = $top_jobs_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance History | POP!Work</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Header */
        .dashboard-header {
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            padding: 32px 40px;
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.15);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-content h1 {
            font-size: 32px;
            font-weight: 800;
            color: white;
            margin-bottom: 8px;
        }

        .header-content p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.95);
            font-weight: 500;
        }

        /* Filter Section */
        .filters-form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 24px 40px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid #e4e6eb;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-select,
        .form-input {
            padding: 10px 14px;
            border: 2px solid #e4e6eb;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            background: white;
            transition: all 0.2s;
        }

        .form-select:focus,
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
        }

        .btn-filter {
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
            gap: 8px;
            justify-content: center;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3);
        }

        /* Analytics Stats */
        .analytics-section {
            padding: 32px 40px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            max-width: 100%;
        }

        .analytics-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid #e4e6eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .analytics-card::before {
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

        .analytics-card:hover::before {
            transform: scaleY(1);
        }

        .analytics-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(138, 21, 56, 0.15);
            border-color: var(--primary);
        }

        .analytics-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, rgba(138, 21, 56, 0.1), rgba(201, 31, 77, 0.1));
            color: var(--primary);
        }

        .analytics-icon.blue {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1));
            color: var(--info);
        }

        .analytics-icon.green {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            color: var(--success);
        }

        .analytics-icon.purple {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(124, 58, 237, 0.1));
            color: var(--purple);
        }

        .analytics-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 8px;
        }

        .analytics-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Content Section */
        .content-section {
            padding: 32px 40px;
        }

        .content-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 2px solid #e4e6eb;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 2px solid #e4e6eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: #f8f9fa;
        }

        .data-table th {
            padding: 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e4e6eb;
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid #e4e6eb;
            font-size: 14px;
        }

        .data-table tbody tr {
            transition: all 0.2s;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
        }

        .job-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .job-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .job-location {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .employer-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employer-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .employer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .employer-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .time-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .time-in {
            font-weight: 600;
            color: var(--success);
        }

        .time-out {
            font-weight: 600;
            color: var(--danger);
        }

        .date-text {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .duration-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #bae6fd;
            color: #0369a1;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
        }

        .earnings-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1px solid #6ee7b7;
            color: #047857;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.active {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            border: 1px solid #86efac;
            animation: pulse-badge 2s infinite;
        }

        .status-badge.completed {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: #3730a3;
            border: 1px solid #a5b4fc;
        }

        @keyframes pulse-badge {
            0%, 100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.4); }
            50% { box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); }
        }

        /* Right Column - Top Jobs */
        .right-col .card {
            position: sticky;
            top: 24px;
        }

        .top-job-item {
            padding: 16px;
            border-bottom: 1px solid #e4e6eb;
            transition: all 0.2s;
        }

        .top-job-item:last-child {
            border-bottom: none;
        }

        .top-job-item:hover {
            background: #f8f9fa;
        }

        .top-job-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }

        .top-job-title {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 14px;
        }

        .top-job-rank {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
        }

        .top-job-employer {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .top-job-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .top-job-stat {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 6px;
            font-size: 11px;
        }

        .top-job-stat-label {
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .top-job-stat-value {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 14px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 64px;
            color: #e4e6eb;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .content-container {
                grid-template-columns: 1fr;
            }

            .right-col .card {
                position: relative;
                top: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .dashboard-header {
                padding: 20px;
            }

            .header-content h1 {
                font-size: 24px;
            }

            .header-content p {
                font-size: 14px;
            }

            .filters-form {
                padding: 20px;
                grid-template-columns: 1fr;
            }

            .analytics-section {
                padding: 20px;
            }

            .analytics-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-section {
                padding: 20px;
            }

            .data-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 480px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include("includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="dashboard-header">
            <div class="header-content">
                <h1>Attendance History</h1>
                <p>Track your work hours, earnings, and job performance.</p>
            </div>
        </div>

        <form class="filters-form" method="GET">
            <div class="form-group">
                <label>Job</label>
                <select name="job" class="form-select">
                    <option value="all">All Jobs</option>
                    <?php while($job = $jobs_list->fetch_assoc()): ?>
                        <option value="<?= $job['job_id'] ?>" <?= $job_filter == $job['job_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($job['title']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-select">
                    <option value="all">All Status</option>
                    <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active (Clocked In)</option>
                    <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="form-group">
                <label>From Date</label>
                <input type="date" name="date_from" class="form-input" value="<?= $date_from ?>">
            </div>
            <div class="form-group">
                <label>To Date</label>
                <input type="date" name="date_to" class="form-input" value="<?= $date_to ?>">
            </div>
            <div class="form-group">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply Filters</button>
            </div>
        </form>

        <div class="analytics-section">
            <div class="analytics-container">
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <div class="analytics-icon purple"><i class="fas fa-briefcase"></i></div>
                        <div class="analytics-value"><?= number_format($analytics['jobs_worked']) ?></div>
                        <div class="analytics-label">Jobs Worked</div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-icon blue"><i class="fas fa-clock"></i></div>
                        <div class="analytics-value"><?= number_format($analytics['total_hours'], 1) ?>h</div>
                        <div class="analytics-label">Total Hours</div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-icon green"><i class="fas fa-wallet"></i></div>
                        <div class="analytics-value">RM <?= number_format($analytics['total_earnings'], 2) ?></div>
                        <div class="analytics-label">Total Earnings</div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-icon purple"><i class="fas fa-calendar-check"></i></div>
                        <div class="analytics-value"><?= number_format($analytics['total_sessions']) ?></div>
                        <div class="analytics-label">Sessions</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-section">
            <div class="content-container">
                <div class="left-col">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Attendance Log</h3>
                            <button onclick="exportToCSV()" style="background:none; border:none; color:var(--primary); cursor:pointer;">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="data-table" id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Date / Time</th>
                                        <th>Job Details</th>
                                        <th>Employer</th>
                                        <th>Duration</th>
                                        <th>Earnings</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $earnings = $row['total_hours'] * $row['pay_rate'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="time-cell">
                                                <div class="date-text"><?= date('M d, Y', strtotime($row['clock_in_time'])) ?></div>
                                                <div class="time-in"><?= date('h:i A', strtotime($row['clock_in_time'])) ?></div>
                                                <?php if ($row['clock_out_time']): ?>
                                                    <div class="time-out"><?= date('h:i A', strtotime($row['clock_out_time'])) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="job-cell">
                                                <div class="job-title"><?= htmlspecialchars($row['job_title']) ?></div>
                                                <div class="job-location">
                                                    <i class="fas fa-location-dot"></i>
                                                    <?= htmlspecialchars($row['job_location']) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="employer-cell">
                                                <div class="employer-avatar">
                                                    <?php if($row['employer_photo'] && file_exists($row['employer_photo'])): ?>
                                                        <img src="<?= htmlspecialchars($row['employer_photo']) ?>" alt="Employer">
                                                    <?php else: ?>
                                                        <?= strtoupper(substr($row['employer_name'] ?? 'E', 0, 1)) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="employer-name"><?= htmlspecialchars($row['employer_name'] ?? 'Unknown') ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($row['total_hours']): ?>
                                                <span class="duration-badge">
                                                    <i class="fas fa-clock"></i>
                                                    <?= number_format($row['total_hours'], 2) ?>H
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($earnings > 0): ?>
                                                <span class="earnings-badge">RM <?= number_format($earnings, 2) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['clock_out_time']): ?>
                                                <span class="status-badge completed">Completed
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge active">
                                                    <i class="fas fa-circle-dot"></i> Active
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    
                                    <?php if ($result->num_rows == 0): ?>
                                        <tr><td colspan="6"><div class="empty-state"><i class="fas fa-calendar-xmark"></i><h3>No Records Found</h3><p>No attendance records match your filters. Try adjusting the date range or filters.</p></div></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="right-col">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Daily Hours</h3>
                        </div>
                        <canvas id="hoursChart" height="200"></canvas>
                    </div>

                    <div class="card" style="margin-top: 24px;">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-trophy"></i> Top Paying Jobs
                            </h3>
                        </div>
                        <div>
                            <?php 
                            $rank = 1;
                            while ($job = $top_jobs->fetch_assoc()): 
                            ?>
                            <div class="top-job-item">
                                <div class="top-job-header">
                                    <div>
                                        <div class="top-job-title"><?= htmlspecialchars($job['job_title']) ?></div>
                                        <div class="top-job-employer">
                                            <i class="fas fa-building"></i> <?= htmlspecialchars($job['employer_name']) ?>
                                        </div>
                                    </div>
                                    <div class="top-job-rank"><?= $rank++ ?></div>
                                </div>
                                <div class="top-job-stats">
                                    <div class="top-job-stat">
                                        <div class="top-job-stat-label">Earnings</div>
                                        <div class="top-job-stat-value" style="color: var(--success);">
                                            RM <?= number_format($job['earnings'], 2) ?>
                                        </div>
                                    </div>
                                    <div class="top-job-stat">
                                        <div class="top-job-stat-label">Hours</div>
                                        <div class="top-job-stat-value" style="color: var(--info);">
                                            <?= number_format($job['total_hours'], 2) ?>H
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            
                            <?php if ($top_jobs->num_rows == 0): ?>
                            <div class="empty-state" style="padding: 40px 20px;">
                                <i class="fas fa-briefcase"></i>
                                <h3>No jobs yet</h3>
                                <p style="font-size: 14px; margin-top: 8px;">Complete jobs to see your top earners</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart Config
        const ctx = document.getElementById('hoursChart').getContext('2d');
        const chartData = {
            labels: [<?php while ($day = $daily_data->fetch_assoc()) { echo "'" . date('M d', strtotime($day['date'])) . "',"; $hours[] = $day['hours']; } ?>],
            datasets: [{
                label: 'Hours Worked',
                data: [<?= implode(',', $hours ?? []) ?>],
                backgroundColor: 'rgba(138, 21, 56, 0.1)',
                borderColor: '#8a1538',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#8a1538',
                pointRadius: 4
            }]
        };

        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                }
            }
        });

        function exportToCSV() {
            const table = document.getElementById('attendanceTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length; j++) {
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').trim();
                    row.push('"' + data + '"');
                }
                csv.push(row.join(','));
            }
            
            const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
            const downloadLink = document.createElement('a');
            downloadLink.download = 'my_attendance_' + new Date().toISOString().split('T')[0] + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    </script>
</body>
</html>