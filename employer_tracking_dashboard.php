<?php
session_start();
include("backend/db_connect.php");

// Ensure only employers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
    header("Location: login.html");
    exit();
}

$employer_id = $_SESSION['user_id'];

// Fetch employer info
$user_sql = "SELECT p.name, u.email FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $employer_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'] ?? explode('@', $user_data['email'])[0];

// Get all active workers for this employer's jobs
$workers_sql = "SELECT DISTINCT
    a.attendance_id,
    a.clock_in_time,
    a.clock_in_latitude,
    a.clock_in_longitude,
    a.distance_from_job_location,
    a.location_verified,
    j.job_id,
    j.title as job_title,
    j.location as job_location,
    j.job_latitude,
    j.job_longitude,
    j.geofence_radius,
    j.require_onsite_attendance,
    u.user_id as worker_id,
    u.email as worker_email,
    p.name as worker_name,
    p.profile_picture as worker_photo,
    p.phone as worker_phone
    FROM attendance a
    JOIN applications app ON a.application_id = app.application_id
    JOIN jobs j ON a.job_id = j.job_id
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    WHERE j.employer_id = ? 
    AND a.clock_out_time IS NULL
    AND a.status = 'clocked_in'
    ORDER BY a.clock_in_time DESC";

$workers_stmt = $conn->prepare($workers_sql);
$workers_stmt->bind_param("i", $employer_id);
$workers_stmt->execute();
$active_workers = $workers_stmt->get_result();

// Get summary statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT a.attendance_id) as active_workers,
    COUNT(DISTINCT j.job_id) as active_jobs,
    SUM(TIMESTAMPDIFF(HOUR, a.clock_in_time, NOW())) as total_hours_today
    FROM attendance a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE j.employer_id = ? 
    AND a.clock_out_time IS NULL
    AND DATE(a.clock_in_time) = CURDATE()";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $employer_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Store workers data for JavaScript
$workers_array = [];
while ($worker = $active_workers->fetch_assoc()) {
    $workers_array[] = $worker;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Worker Tracking | POP!Work</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        :root {
            --primary: #8a1538;
            --primary-dark: #6b1129;
            --primary-light: #c91f4d;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --text-dark: #1c1e21;
            --text-medium: #4a5568;
            --text-light: #65676b;
            --bg-light: #f5f7fa;
            --border: #e1e8ed;
            --white: #ffffff;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(138, 21, 56, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
        }

        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            min-height: 100vh;
        }

        /* Header */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 32px 40px;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1600px;
            margin: 0 auto;
        }

        .header-left h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .header-left p {
            font-size: 15px;
            opacity: 0.9;
        }

        .header-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .refresh-btn {
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            color: var(--white);
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .refresh-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .auto-refresh {
            font-size: 13px;
            opacity: 0.85;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pulse {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Stats Section */
        .stats-section {
            padding: 20px 40px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .stats-container {
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            gap: 16px;
        }

        .stat-card {
            flex: 1;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--white);
            flex-shrink: 0;
        }

        .stat-icon.workers { background: linear-gradient(135deg, var(--success), #059669); }
        .stat-icon.jobs { background: linear-gradient(135deg, var(--info), #2563eb); }
        .stat-icon.hours { background: linear-gradient(135deg, var(--warning), #dc2626); }

        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .stat-info p {
            font-size: 13px;
            color: var(--text-medium);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Main Content Area */
        .content-wrapper {
            max-width: 1600px;
            margin: 0 auto;
            padding: 32px 40px;
        }

        .tracking-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
            align-items: start;
        }

        /* Map Container */
        .map-container {
            background: var(--white);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .map-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-bottom: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .map-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .live-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--success);
            font-weight: 600;
        }

        #tracking-map {
            height: 600px;
            width: 100%;
        }

        /* Workers List */
        .workers-container {
            background: var(--white);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            max-height: 680px;
            display: flex;
            flex-direction: column;
        }

        .workers-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-bottom: 2px solid var(--border);
        }

        .workers-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .workers-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .workers-count {
            font-size: 13px;
            color: var(--text-medium);
            background: var(--bg-light);
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Search & Filter */
        .search-box {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 14px;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .filter-tab {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 2px solid var(--border);
            background: var(--white);
            color: var(--text-medium);
            white-space: nowrap;
            transition: all 0.3s;
        }

        .filter-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-tab.active {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--white);
        }

        .workers-list {
            overflow-y: auto;
            flex: 1;
            padding: 12px;
        }

        /* Compact Worker Card */
        .worker-card {
            background: var(--white);
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .worker-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
            transform: translateX(4px);
        }

        .worker-card.active {
            border-color: var(--primary);
            background: #fdf2f4;
            box-shadow: var(--shadow-md);
        }

        .worker-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .worker-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            overflow: hidden;
        }

        .worker-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .worker-info {
            flex: 1;
            min-width: 0;
        }

        .worker-info h3 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .worker-info p {
            font-size: 12px;
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .worker-status {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .status-dot.verified { background: var(--success); }
        .status-dot.unverified { background: var(--warning); }

        .status-text {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-medium);
        }

        .worker-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 8px;
        }

        .detail-item {
            font-size: 11px;
        }

        .detail-label {
            color: var(--text-light);
            display: block;
            margin-bottom: 2px;
        }

        .detail-value {
            color: var(--text-dark);
            font-weight: 700;
            font-size: 12px;
        }

        .worker-actions {
            display: flex;
            gap: 6px;
            margin-top: 10px;
        }

        .action-btn {
            flex: 1;
            padding: 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            text-decoration: none;
        }

        .action-btn.primary {
            background: var(--primary);
            color: var(--white);
        }

        .action-btn.primary:hover {
            background: var(--primary-dark);
        }

        .action-btn.secondary {
            background: var(--bg-light);
            color: var(--text-dark);
            border: 1px solid var(--border);
        }

        .action-btn.secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Group Headers */
        .job-group-header {
            position: sticky;
            top: 0;
            background: var(--bg-light);
            padding: 10px 12px;
            margin: 0 -12px 8px -12px;
            border-bottom: 2px solid var(--border);
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .job-group-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .job-group-count {
            font-size: 11px;
            background: var(--white);
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
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
            opacity: 0.5;
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

        /* Responsive */
        @media (max-width: 1200px) {
            .tracking-layout {
                grid-template-columns: 1fr;
            }

            .workers-container {
                max-height: 400px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .dashboard-header,
            .stats-section,
            .content-wrapper {
                padding-left: 20px;
                padding-right: 20px;
            }

            .stats-container {
                flex-direction: column;
            }

            #tracking-map {
                height: 400px;
            }
        }

        @media (max-width: 576px) {
            .header-content {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .header-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <?php 
        $page_title = "Live Worker Tracking";
        include 'includes/sidebar.php';
    ?>

    <div class="main-content">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-left">
                    <h1>📍 Live Worker Tracking</h1>
                    <p>Real-time location monitoring for active workers</p>
                </div>
                <div class="header-right">
                    <div class="auto-refresh">
                        <div class="pulse"></div>
                        Auto-refresh: 30s
                    </div>
                    <button class="refresh-btn" onclick="refreshTracking()">
                        <i class="fas fa-sync-alt"></i> Refresh Now
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon workers">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= count($workers_array) ?></h3>
                        <p>Active Workers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon jobs">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $stats['active_jobs'] ?? 0 ?></h3>
                        <p>Active Jobs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon hours">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($stats['total_hours_today'] ?? 0, 1) ?>H</h3>
                        <p>Total Hours Today</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-wrapper">
            <div class="tracking-layout">
                <!-- Map Section -->
                <div class="map-container">
                    <div class="map-header">
                        <h2>
                            <i class="fas fa-map-marked-alt"></i>
                            Location Map
                        </h2>
                        <div class="live-indicator">
                            <div class="pulse"></div>
                            Live Tracking
                        </div>
                    </div>
                    <div id="tracking-map"></div>
                </div>

                <!-- Workers List -->
                <div class="workers-container">
                    <div class="workers-header">
                        <div class="workers-header-top">
                            <h2>Active Workers</h2>
                            <span class="workers-count"><?= count($workers_array) ?></span>
                        </div>
                        
                        <!-- Search Box -->
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" 
                                   class="search-input" 
                                   id="workerSearch" 
                                   placeholder="Search workers or jobs..."
                                   onkeyup="filterWorkers()">
                        </div>
                        
                        <!-- Filter Tabs -->
                        <div class="filter-tabs">
                            <button class="filter-tab active" onclick="filterByStatus('all')">
                                All (<?= count($workers_array) ?>)
                            </button>
                            <button class="filter-tab" onclick="filterByStatus('verified')">
                                ✓ Verified
                            </button>
                            <button class="filter-tab" onclick="filterByStatus('unverified')">
                                ⚠ Unverified
                            </button>
                        </div>
                    </div>
                    
                    <div class="workers-list" id="workersList">
                        <?php if (count($workers_array) > 0): ?>
                            <?php 
                            // Group workers by job
                            $workers_by_job = [];
                            foreach ($workers_array as $worker) {
                                $job_id = $worker['job_id'];
                                if (!isset($workers_by_job[$job_id])) {
                                    $workers_by_job[$job_id] = [
                                        'job_title' => $worker['job_title'],
                                        'workers' => []
                                    ];
                                }
                                $workers_by_job[$job_id]['workers'][] = $worker;
                            }
                            ?>
                            
                            <?php foreach ($workers_by_job as $job_id => $job_group): ?>
                                <div class="job-group" data-job-id="<?= $job_id ?>">
                                    <div class="job-group-header">
                                        <span class="job-group-title">
                                            <i class="fas fa-briefcase"></i>
                                            <?= htmlspecialchars($job_group['job_title']) ?>
                                        </span>
                                        <span class="job-group-count"><?= count($job_group['workers']) ?></span>
                                    </div>
                                    
                                    <?php foreach ($job_group['workers'] as $index => $worker): ?>
                                        <?php
                                            // Find global index
                                            $global_index = array_search($worker, $workers_array);
                                            $now = new DateTime();
                                            $clock_in = new DateTime($worker['clock_in_time']);
                                            $diff = $now->diff($clock_in);
                                            $hours_worked = $diff->h + ($diff->days * 24);
                                            $minutes_worked = $diff->i;
                                            $is_verified = $worker['location_verified'] ? 'verified' : 'unverified';
                                        ?>
                                        <div class="worker-card" 
                                             data-worker-index="<?= $global_index ?>" 
                                             data-worker-name="<?= strtolower($worker['worker_name'] ?? $worker['worker_email']) ?>"
                                             data-job-title="<?= strtolower($worker['job_title']) ?>"
                                             data-status="<?= $is_verified ?>"
                                             onclick="focusWorker(<?= $global_index ?>)">
                                            
                                            <div class="worker-header">
                                                <div class="worker-avatar">
                                                    <?php if ($worker['worker_photo']): ?>
                                                        <img src="<?= htmlspecialchars($worker['worker_photo']) ?>" alt="">
                                                    <?php else: ?>
                                                        <?= strtoupper(substr($worker['worker_name'] ?? $worker['worker_email'], 0, 1)) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="worker-info">
                                                    <h3><?= htmlspecialchars($worker['worker_name'] ?? 'Worker') ?></h3>
                                                    <div class="worker-status">
                                                        <span class="status-dot <?= $is_verified ?>"></span>
                                                        <span class="status-text">
                                                            <?= $worker['location_verified'] ? 'Verified' : 'Unverified' ?>
                                                            <?php if ($worker['location_verified']): ?>
                                                                (<?= round($worker['distance_from_job_location']) ?>m)
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="worker-details">
                                                <div class="detail-item">
                                                    <span class="detail-label">⏱️ Working</span>
                                                    <span class="detail-value"><?= $hours_worked ?>h <?= $minutes_worked ?>m</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">🕐 Started</span>
                                                    <span class="detail-value"><?= date('g:i A', strtotime($worker['clock_in_time'])) ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="worker-actions">
                                                <button class="action-btn primary" onclick="event.stopPropagation(); focusWorker(<?= $global_index ?>)">
                                                    <i class="fas fa-map-marker-alt"></i> View on Map
                                                </button>
                                                <a href="attendance_history.php?worker_id=<?= $worker['worker_id'] ?>&job_id=<?= $worker['job_id'] ?>" 
                                                   class="action-btn secondary"
                                                   onclick="event.stopPropagation()">
                                                    <i class="fas fa-history"></i> History
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">👥</div>
                                <div class="empty-title">No Active Workers</div>
                                <div class="empty-text">
                                    There are currently no workers clocked in for your jobs.<br>
                                    Workers will appear here when they clock in.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const workersData = <?= json_encode($workers_array) ?>;
    let trackingMap = null;
    let markers = [];
    let geofenceCircles = [];

    // Initialize map
    function initMap() {
        const defaultCenter = [5.9804, 116.0735]; // Sabah center
        
        trackingMap = L.map('tracking-map').setView(defaultCenter, 10);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(trackingMap);
        
        if (workersData.length > 0) {
            plotWorkers();
        }
    }

    // Plot all workers on map
    function plotWorkers() {
        // Clear existing markers
        markers.forEach(marker => marker.remove());
        geofenceCircles.forEach(circle => circle.remove());
        markers = [];
        geofenceCircles = [];
        
        const bounds = [];
        
        workersData.forEach((worker, index) => {
            if (worker.clock_in_latitude && worker.clock_in_longitude) {
                const workerLatLng = [parseFloat(worker.clock_in_latitude), parseFloat(worker.clock_in_longitude)];
                bounds.push(workerLatLng);
                
                // Worker marker
                const workerIcon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background: #8a1538; width: 40px; height: 40px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.4);"><i class="fas fa-user" style="color: white; transform: rotate(45deg); font-size: 18px;"></i></div>`,
                    iconSize: [40, 40],
                    iconAnchor: [20, 40],
                    popupAnchor: [0, -40]
                });
                
                const workerMarker = L.marker(workerLatLng, { 
                    icon: workerIcon,
                    zIndexOffset: 1000 
                }).addTo(trackingMap);
                
                workerMarker.bindPopup(`
                    <div style="min-width: 200px;">
                        <h3 style="margin: 0 0 10px 0; color: #8a1538;">
                            ${worker.worker_name || 'Worker'}
                        </h3>
                        <p style="margin: 5px 0;"><strong>Job:</strong> ${worker.job_title}</p>
                        <p style="margin: 5px 0;"><strong>Clocked In:</strong> ${new Date(worker.clock_in_time).toLocaleTimeString()}</p>
                        ${worker.location_verified ? 
                            `<p style="margin: 5px 0; color: #10b981;"><strong>✓ Location Verified</strong><br>Distance: ${Math.round(worker.distance_from_job_location)}m</p>` 
                            : ''}
                    </div>
                `);
                
                markers.push(workerMarker);
                
                // Job location marker
                if (worker.job_latitude && worker.job_longitude) {
                    const jobLatLng = [parseFloat(worker.job_latitude), parseFloat(worker.job_longitude)];
                    bounds.push(jobLatLng);
                    
                    const jobIcon = L.divIcon({
                        className: 'custom-marker',
                        html: `<div style="background: #3b82f6; width: 35px; height: 35px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><i class="fas fa-briefcase" style="color: white; transform: rotate(45deg); font-size: 14px;"></i></div>`,
                        iconSize: [35, 35],
                        iconAnchor: [17, 35],
                        popupAnchor: [0, -35]
                    });
                    
                    const jobMarker = L.marker(jobLatLng, { icon: jobIcon }).addTo(trackingMap);
                    jobMarker.bindPopup(`
                        <div style="min-width: 180px;">
                            <h3 style="margin: 0 0 10px 0; color: #3b82f6;">📍 Job Site</h3>
                            <p style="margin: 5px 0;"><strong>${worker.job_title}</strong></p>
                            <p style="margin: 5px 0;">${worker.job_location}</p>
                            ${worker.geofence_radius ? 
                                `<p style="margin: 5px 0; font-size: 12px; color: #666;">Geofence: ${worker.geofence_radius}m radius</p>` 
                                : ''}
                        </div>
                    `);
                    
                    markers.push(jobMarker);
                    
                    // Geofence circle
                    if (worker.require_onsite_attendance && worker.geofence_radius) {
                        const circle = L.circle(jobLatLng, {
                            radius: worker.geofence_radius,
                            color: '#3b82f6',
                            fillColor: '#3b82f6',
                            fillOpacity: 0.1,
                            weight: 2,
                            dashArray: '5, 5'
                        }).addTo(trackingMap);
                        
                        geofenceCircles.push(circle);
                    }
                    
                    // Line between worker and job
                    const polyline = L.polyline([workerLatLng, jobLatLng], {
                        color: worker.location_verified ? '#10b981' : '#f59e0b',
                        weight: 2,
                        opacity: 0.5,
                        dashArray: '5, 10'
                    }).addTo(trackingMap);
                    
                    markers.push(polyline);
                }
            }
        });
        
        // Fit map to markers
        if (bounds.length > 0) {
            trackingMap.fitBounds(bounds, { padding: [50, 50] });
        }
    }

    // Focus on specific worker
    function focusWorker(index) {
        document.querySelectorAll('.worker-card').forEach(card => {
            card.classList.remove('active');
        });
        
        document.querySelector(`[data-worker-index="${index}"]`).classList.add('active');
        
        const worker = workersData[index];
        
        if (worker.clock_in_latitude && worker.clock_in_longitude) {
            const workerLatLng = [parseFloat(worker.clock_in_latitude), parseFloat(worker.clock_in_longitude)];
            trackingMap.setView(workerLatLng, 16);
            
            if (markers[index * 2]) {
                markers[index * 2].openPopup();
            }
        }
    }

    // Search workers
    function filterWorkers() {
        const searchValue = document.getElementById('workerSearch').value.toLowerCase();
        const cards = document.querySelectorAll('.worker-card');
        const groups = document.querySelectorAll('.job-group');
        
        cards.forEach(card => {
            const workerName = card.getAttribute('data-worker-name') || '';
            const jobTitle = card.getAttribute('data-job-title') || '';
            const searchText = workerName + ' ' + jobTitle;
            
            if (searchText.includes(searchValue)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Hide empty job groups
        groups.forEach(group => {
            const visibleCards = group.querySelectorAll('.worker-card[style="display: block;"], .worker-card:not([style*="display: none"])');
            if (visibleCards.length === 0) {
                group.style.display = 'none';
            } else {
                group.style.display = 'block';
            }
        });
    }

    // Filter by status
    function filterByStatus(status) {
        // Update active tab
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.target.classList.add('active');
        
        const cards = document.querySelectorAll('.worker-card');
        const groups = document.querySelectorAll('.job-group');
        
        cards.forEach(card => {
            const cardStatus = card.getAttribute('data-status');
            
            if (status === 'all') {
                card.style.display = 'block';
            } else if (status === cardStatus) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
        
        // Hide empty job groups
        groups.forEach(group => {
            const visibleCards = group.querySelectorAll('.worker-card[style="display: block;"], .worker-card:not([style*="display: none"])');
            if (visibleCards.length === 0) {
                group.style.display = 'none';
            } else {
                group.style.display = 'block';
            }
        });
    }

    // Refresh tracking
    function refreshTracking() {
        location.reload();
    }

    // Auto-refresh every 30 seconds
    setInterval(refreshTracking, 30000);

    // Initialize on load
    window.addEventListener('load', initMap);
    </script>
</body>
</html>