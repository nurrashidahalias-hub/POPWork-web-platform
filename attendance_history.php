<?php
session_start();
include("backend/db_connect.php");

// Ensure only employers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
    header("Location: login.html");
    exit();
}

$employer_id = $_SESSION['user_id'];

// Get filter parameters
$worker_filter = $_GET['worker'] ?? 'all';
$job_filter = $_GET['job'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$status_filter = $_GET['status'] ?? 'all';

// Build query
$sql = "SELECT 
    a.attendance_id,
    a.clock_in_time,
    a.clock_out_time,
    a.total_hours,
    a.status,
    a.location_verified,
    a.distance_from_job_location,
    j.job_id,
    j.title as job_title,
    j.location as job_location,
    j.category,
    j.pay_rate,
    u.user_id as worker_id,
    u.email as worker_email,
    p.name as worker_name,
    p.photo_url as worker_photo
    FROM attendance a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    WHERE j.employer_id = ?
    AND DATE(a.clock_in_time) BETWEEN ? AND ?";

$params = [$employer_id, $date_from, $date_to];
$types = "iss";

if ($worker_filter !== 'all') {
    $sql .= " AND a.user_id = ?";
    $params[] = $worker_filter;
    $types .= "i";
}

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

// Get analytics data
$analytics_sql = "SELECT 
    COUNT(DISTINCT a.attendance_id) as total_sessions,
    COUNT(DISTINCT a.user_id) as unique_workers,
    COUNT(DISTINCT a.job_id) as jobs_worked,
    SUM(CASE WHEN a.clock_out_time IS NOT NULL THEN a.total_hours ELSE 0 END) as total_hours,
    AVG(CASE WHEN a.clock_out_time IS NOT NULL THEN a.total_hours ELSE NULL END) as avg_session_hours,
    SUM(CASE WHEN a.location_verified = 1 THEN 1 ELSE 0 END) as verified_sessions,
    SUM(CASE WHEN a.clock_out_time IS NOT NULL THEN (a.total_hours * j.pay_rate) ELSE 0 END) as total_cost
    FROM attendance a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE j.employer_id = ?
    AND DATE(a.clock_in_time) BETWEEN ? AND ?";

$analytics_stmt = $conn->prepare($analytics_sql);
$analytics_stmt->bind_param("iss", $employer_id, $date_from, $date_to);
$analytics_stmt->execute();
$analytics = $analytics_stmt->get_result()->fetch_assoc();

// Get workers list for filter
$workers_sql = "SELECT DISTINCT u.user_id, u.email, p.name 
                FROM attendance a
                JOIN users u ON a.user_id = u.user_id
                LEFT JOIN profiles p ON u.user_id = p.user_id
                JOIN jobs j ON a.job_id = j.job_id
                WHERE j.employer_id = ?
                ORDER BY p.name ASC";
$workers_stmt = $conn->prepare($workers_sql);
$workers_stmt->bind_param("i", $employer_id);
$workers_stmt->execute();
$workers_list = $workers_stmt->get_result();

// Get jobs list for filter
$jobs_sql = "SELECT DISTINCT j.job_id, j.title 
             FROM jobs j
             WHERE j.employer_id = ?
             ORDER BY j.title ASC";
$jobs_stmt = $conn->prepare($jobs_sql);
$jobs_stmt->bind_param("i", $employer_id);
$jobs_stmt->execute();
$jobs_list = $jobs_stmt->get_result();

// Get daily breakdown for chart
$daily_sql = "SELECT 
    DATE(a.clock_in_time) as date,
    COUNT(*) as sessions,
    SUM(CASE WHEN a.clock_out_time IS NOT NULL THEN a.total_hours ELSE 0 END) as hours
    FROM attendance a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE j.employer_id = ?
    AND DATE(a.clock_in_time) BETWEEN ? AND ?
    GROUP BY DATE(a.clock_in_time)
    ORDER BY date ASC";
$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->bind_param("iss", $employer_id, $date_from, $date_to);
$daily_stmt->execute();
$daily_data = $daily_stmt->get_result();

// Get top workers
$top_workers_sql = "SELECT 
    u.user_id,
    p.name as worker_name,
    u.email,
    COUNT(*) as sessions,
    SUM(CASE WHEN a.clock_out_time IS NOT NULL THEN a.total_hours ELSE 0 END) as total_hours
    FROM attendance a
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    JOIN jobs j ON a.job_id = j.job_id
    WHERE j.employer_id = ?
    AND DATE(a.clock_in_time) BETWEEN ? AND ?
    AND a.clock_out_time IS NOT NULL
    GROUP BY u.user_id
    ORDER BY total_hours DESC
    LIMIT 10";
$top_workers_stmt = $conn->prepare($top_workers_sql);
$top_workers_stmt->bind_param("iss", $employer_id, $date_from, $date_to);
$top_workers_stmt->execute();
$top_workers = $top_workers_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records & Analytics | POP!Work</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary: #8a1538;
            --primary-dark: #4c1d95;
            --primary-light: #7c3aed;
            --secondary: #8a1538;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-dark: #1c1e21;
            --text-medium: #4a5568;
            --text-light: #65676b;
            --bg-light: #f5f7fa;
            --border: #e1e8ed;
            --white: #ffffff;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(91, 33, 182, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--bg-light);
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

        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            min-height: 100vh;
            background: rgba(245, 247, 250, 0.95);
        }

        /* Header */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 32px 40px;
            box-shadow: var(--shadow-md);
            position: relative;
        }

        .header-content {
            max-width: 1600px;
            margin: 0 auto;
        }

        .header-content h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .header-content p {
            font-size: 15px;
            opacity: 0.9;
        }

        /* Analytics Section */
        .analytics-section {
            padding: 24px 40px;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
        }

        .analytics-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 6px;
            margin-bottom: 2px;
        }

        .analytics-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .analytics-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .analytics-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(138, 21, 56, 0.15);
            border-color: var(--primary);
        }

        .analytics-card:hover::before {
            transform: scaleX(1);
        }

        .analytics-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--white);
            margin: 0 auto 12px;
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.2);
        }

        .analytics-icon.purple { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .analytics-icon.blue { background: linear-gradient(135deg, #8a1538, #3b82f6); }
        .analytics-icon.green { background: linear-gradient(135deg, #10b981, #34d399); }
        .analytics-icon.orange { background: linear-gradient(135deg, #f59e0b, #fbbf24); }

        .analytics-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
            line-height: 1;
            letter-spacing: -0.5px;
        }

        .analytics-label {
            font-size: 11px;
            color: var(--text-medium);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.3;
        }

        /* Charts Section */
        .charts-section {
            padding: 24px 40px;
            background: var(--bg-light);
        }

        .charts-container {
            max-width: 1600px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 24px;
        }

        .chart-header {
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Filters */
        .filter-section {
            padding: 24px 40px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-bottom: 1px solid var(--border);
        }

        .filter-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .export-btn {
            padding: 10px 20px;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .export-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-medium);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select,
        .filter-input {
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(91, 33, 182, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .apply-btn {
            padding: 10px 24px;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .apply-btn:hover {
            background: var(--primary-dark);
        }

        .reset-btn {
            padding: 10px 24px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: var(--text-dark);
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .reset-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Table Section */
        .table-section {
            padding: 24px 40px;
        }

        .table-container {
            max-width: 1600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-bottom: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .record-count {
            font-size: 13px;
            color: var(--text-medium);
            background: var(--bg-light);
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        thead th {
            padding: 14px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color:#ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: all 0.2s;
        }

        tbody tr:hover {
            background: #fafbfc;
        }

        tbody td {
            padding: 16px;
            font-size: 14px;
            color: var(--text-dark);
            vertical-align: middle;
        }

        .worker-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .worker-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .worker-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .worker-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .worker-info p {
            font-size: 12px;
            color: var(--text-light);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.active {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.verified {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge.unverified {
            background: #fee2e2;
            color: #991b1b;
        }

        .number-cell {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        /* Top Workers */
        .top-workers-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .top-worker-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: var(--bg-light);
            border-radius: 10px;
            transition: all 0.3s;
        }

        .top-worker-item:hover {
            background: #e9ecef;
            transform: translateX(4px);
        }

        .worker-rank {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .worker-rank.gold { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .worker-rank.silver { background: linear-gradient(135deg, #9ca3af, #6b7280); }
        .worker-rank.bronze { background: linear-gradient(135deg, #d97706, #b45309); }

        .worker-details {
            flex: 1;
            min-width: 0;
        }

        .worker-details h5 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .worker-details p {
            font-size: 11px;
            color: var(--text-light);
        }

        .worker-stats {
            text-align: right;
        }

        .worker-stats .hours {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
        }

        .worker-stats .sessions {
            font-size: 11px;
            color: var(--text-medium);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: var(--text-light);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-medium);
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 14px;
            line-height: 1.6;
        }

        /* Worker Photo Styles */
        .person-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .person-photo {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .person-placeholder {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            border: 2px solid var(--white);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .person-info {
            flex: 1;
            min-width: 0;
        }

        .person-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            margin-bottom: 2px;
        }

        .person-email {
            font-size: 12px;
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Responsive */
 /* ============================================
   MOBILE-FRIENDLY RESPONSIVE CSS
   For attendance_history.php
   Replace lines 696-741 with this code
   ============================================ */

/* Responsive */
@media (max-width: 1200px) {
    .charts-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 992px) {
    .main-content {
        margin-left: 0;
        width: 100%;
    }

    .dashboard-header,
    .analytics-section,
    .charts-section,
    .filter-section,
    .table-section {
        padding: 20px 16px;
    }

    .analytics-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .analytics-card {
        padding: 16px;
    }

    .analytics-value {
        font-size: 24px;
    }

    .header-content h1 {
        font-size: 26px;
    }

    .filter-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .export-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    /* Header adjustments */
    .dashboard-header {
        padding: 20px 16px;
    }

    .header-content h1 {
        font-size: 22px;
    }

    .header-content p {
        font-size: 14px;
    }

    /* Analytics cards */
    .analytics-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .analytics-card {
        padding: 16px;
    }

    .analytics-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }

    .analytics-value {
        font-size: 22px;
    }

    .analytics-label {
        font-size: 11px;
    }

    /* Filters */
    .filters-grid {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        flex-direction: column;
        width: 100%;
    }

    .apply-btn,
    .reset-btn,
    .export-btn {
        width: 100%;
    }

    /* Charts */
    .chart-card {
        padding: 16px;
    }

    .chart-header h3 {
        font-size: 16px;
    }

    /* TABLE - MOBILE CARD LAYOUT */
    .table-wrapper {
        overflow-x: visible;
    }

    table {
        display: block;
    }

    thead {
        display: none; /* Hide table headers on mobile */
    }

    tbody {
        display: block;
    }

    tbody tr {
        display: block;
        margin-bottom: 16px;
        border: 2px solid var(--border);
        border-radius: 12px;
        padding: 16px;
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    tbody tr:hover {
        background: white;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    tbody td {
        display: block;
        padding: 10px 0;
        border: none;
        text-align: left !important;
    }

    tbody td::before {
        content: attr(data-label);
        display: block;
        font-weight: 700;
        font-size: 11px;
        color: var(--text-medium);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    /* Worker cell - keep flex on mobile */
    .worker-cell {
        display: flex !important;
        align-items: center;
        gap: 12px;
        margin-top: 4px;
    }

    .worker-avatar {
        width: 44px;
        height: 44px;
    }

    .worker-info h4 {
        font-size: 15px;
    }

    .worker-info p {
        font-size: 13px;
    }

    /* Badges larger on mobile */
    .badge {
        font-size: 12px;
        padding: 6px 12px;
    }

    /* Number cells */
    .number-cell {
        font-size: 16px;
    }

    /* Top workers on mobile */
    .top-worker-item {
        padding: 10px;
    }

    .worker-rank {
        width: 36px;
        height: 36px;
        font-size: 15px;
    }
}

@media (max-width: 576px) {
    /* Extra small phones */
    .dashboard-header {
        padding: 16px 12px;
    }

    .header-content h1 {
        font-size: 20px;
    }

    .analytics-section,
    .charts-section,
    .filter-section,
    .table-section {
        padding: 16px 12px;
    }

    .analytics-card {
        padding: 14px;
    }

    .analytics-value {
        font-size: 20px;
    }

    .chart-card {
        padding: 12px;
    }

    tbody tr {
        padding: 12px;
    }

    .worker-avatar {
        width: 40px;
        height: 40px;
    }

    .worker-info h4 {
        font-size: 14px;
    }
}
    </style>
</head>
<body>
    <?php 
        $page_title = "Attendance Records & Analytics";
        include 'includes/sidebar.php';
    ?>

    <div class="main-content">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <h1>📊 Attendance Records & Analytics</h1>
                <p>Historical data analysis and workforce insights</p>
            </div>
        </div>

        <!-- Analytics Cards -->
        <div class="analytics-section">
            <div class="analytics-container">
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <div class="analytics-icon purple">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="analytics-value"><?= number_format($analytics['total_sessions'] ?? 0) ?></div>
                        <div class="analytics-label">Total Sessions</div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="analytics-value"><?= number_format($analytics['unique_workers'] ?? 0) ?></div>
                        <div class="analytics-label">Unique Workers</div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-icon green">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="analytics-value"><?= number_format($analytics['jobs_worked'] ?? 0) ?></div>
                        <div class="analytics-label">Jobs Worked</div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-icon orange">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="analytics-value"><?= number_format($analytics['total_hours'] ?? 0, 1) ?>H</div>
                        <div class="analytics-label">Total Hours</div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-icon purple">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="analytics-value"><?= number_format($analytics['avg_session_hours'] ?? 0, 1) ?>H</div>
                        <div class="analytics-label">Avg Session</div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-icon blue">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="analytics-value"><?= number_format($analytics['verified_sessions'] ?? 0) ?></div>
                        <div class="analytics-label">Verified</div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-icon green">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="analytics-value">RM <?= number_format($analytics['total_cost'] ?? 0, 0) ?></div>
                        <div class="analytics-label">Total Cost</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-section">
            <div class="charts-container">
                <!-- Daily Trend Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-area"></i> Daily Activity Trend</h3>
                    </div>
                    <canvas id="dailyChart" height="80"></canvas>
                </div>

                <!-- Top Workers -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-trophy"></i> Top 10 Workers</h3>
                    </div>
                    <div class="top-workers-list">
                        <?php 
                        $rank = 1;
                        while ($worker = $top_workers->fetch_assoc()): 
                            $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
                        ?>
                            <div class="top-worker-item">
                                <div class="worker-rank <?= $rank_class ?>"><?= $rank ?></div>
                                <div class="worker-details">
                                    <h5><?= htmlspecialchars($worker['worker_name'] ?? 'Worker') ?></h5>
                                    <p><?= $worker['sessions'] ?> sessions</p>
                                </div>
                                <div class="worker-stats">
                                    <div class="hours"><?= number_format($worker['total_hours'], 1) ?>H</div>
                                </div>
                            </div>
                        <?php 
                            $rank++;
                        endwhile; 
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="filter-container">
                <div class="filter-header">
                    <h2>Attendance Records</h2>
                    <button class="export-btn" onclick="exportToCSV()">
                        <i class="fas fa-download"></i> Export to CSV
                    </button>
                </div>
                <form method="GET">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Worker</label>
                            <select name="worker" class="filter-select">
                                <option value="all">All Workers</option>
                                <?php while ($w = $workers_list->fetch_assoc()): ?>
                                    <option value="<?= $w['user_id'] ?>" <?= $worker_filter == $w['user_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($w['name'] ?? $w['email']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Job</label>
                            <select name="job" class="filter-select">
                                <option value="all">All Jobs</option>
                                <?php while ($j = $jobs_list->fetch_assoc()): ?>
                                    <option value="<?= $j['job_id'] ?>" <?= $job_filter == $j['job_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($j['title']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>From Date</label>
                            <input type="date" name="date_from" value="<?= $date_from ?>" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label>To Date</label>
                            <input type="date" name="date_to" value="<?= $date_to ?>" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status" class="filter-select">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="apply-btn">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="employer_attendance_records.php" class="reset-btn">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Records Table -->
        <div class="table-section">
            <div class="table-container">
                <div class="table-header">
                    <h3>Detailed Records</h3>
                    <span class="record-count"><?= $result->num_rows ?> records</span>
                </div>
                <div class="table-wrapper">
                    <?php if ($result->num_rows > 0): ?>
                        <table id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Worker</th>
                                    <th>Job</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th style="text-align: center;">Hours</th>
                                    <th>Status</th>
                                    <th>Location (Clock In)</th>
                                    <th style="text-align: right;">Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <!-- Worker -->
                                        <td>
                                            <div class="person-cell">
                                                <?php if (!empty($row['worker_photo'])): ?>
                                                    <img src="<?= htmlspecialchars($row['worker_photo']) ?>" class="person-photo" alt="Worker Photo">
                                                <?php else: ?>
                                                    <div class="person-placeholder">
                                                        <?= strtoupper(substr($row['worker_name'] ?? $row['worker_email'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="person-info">
                                                    <div class="person-name"><?= htmlspecialchars($row['worker_name'] ?? 'Worker') ?></div>
                                                    <div class="person-email"><?= htmlspecialchars($row['worker_email']) ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Job -->
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($row['job_title']) ?></strong><br>
                                                <small style="color: var(--text-light);">
                                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($row['job_location']) ?>
                                                </small>
                                            </div>
                                        </td>

                                        <!-- Clock In -->
                                        <td>
                                            <div>
                                                <?= date('M d, Y', strtotime($row['clock_in_time'])) ?><br>
                                                <small style="color: var(--text-medium);"><?= date('g:i A', strtotime($row['clock_in_time'])) ?></small>
                                            </div>
                                        </td>

                                        <!-- Clock Out -->
                                        <td>
                                            <?php if ($row['clock_out_time']): ?>
                                                <div>
                                                    <?= date('M d, Y', strtotime($row['clock_out_time'])) ?><br>
                                                    <small style="color: var(--text-medium);"><?= date('g:i A', strtotime($row['clock_out_time'])) ?></small>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--warning);">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Hours -->
                                        <td class="number-cell" style="text-align: center;">
                                            <?= $row['total_hours'] ? number_format($row['total_hours'], 2) . 'h' : '—' ?>
                                        </td>

                                        <!-- Status -->
                                        <td>
                                            <span class="badge <?= $row['clock_out_time'] ? 'completed' : 'active' ?>">
                                                <?= $row['clock_out_time'] ? '✓ Completed' : '⏱ Active' ?>
                                            </span>
                                        </td>

                                        <!-- Location -->
                                        <td>
                                            <?php if ($row['location_verified']): ?>
                                                <span class="badge verified">
                                                    <i class="fas fa-check"></i> Verified
                                                    <?php if ($row['distance_from_job_location']): ?>
                                                        (<?= round($row['distance_from_job_location']) ?>m)
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge unverified">
                                                    <i class="fas fa-times"></i> Unverified
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Cost -->
                                        <td class="number-cell" style="text-align: right;">
                                            <?php if ($row['total_hours']): ?>
                                                RM <?= number_format($row['total_hours'] * $row['pay_rate'], 2) ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <div class="empty-title">No Records Found</div>
                            <div class="empty-text">
                                No attendance records match your current filters.<br>
                                Try adjusting the date range or clearing filters.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Daily Trend Chart
    <?php 
    $daily_labels = [];
    $daily_hours = [];
    $daily_sessions = [];
    $daily_data->data_seek(0);
    while ($day = $daily_data->fetch_assoc()) {
        $daily_labels[] = date('M d', strtotime($day['date']));
        $daily_hours[] = $day['hours'];
        $daily_sessions[] = $day['sessions'];
    }
    ?>

    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    const dailyChart = new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($daily_labels) ?>,
            datasets: [{
                label: 'Hours Worked',
                data: <?= json_encode($daily_hours) ?>,
                borderColor: '#8a1538',
                backgroundColor: 'rgba(91, 33, 182, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Sessions',
                data: <?= json_encode($daily_sessions) ?>,
                borderColor: '#8a1538',
                backgroundColor: 'rgba(179, 27, 75, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Hours'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Sessions'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });

    // Export to CSV
    function exportToCSV() {
        const table = document.getElementById('attendanceTable');
        if (!table) {
            alert('No data to export');
            return;
        }

        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""');
                row.push('"' + data + '"');
            }
            
            csv.push(row.join(','));
        }

        const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
        const downloadLink = document.createElement('a');
        downloadLink.download = 'attendance_records_' + new Date().toISOString().split('T')[0] + '.csv';
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }
    </script>
</body>
</html>