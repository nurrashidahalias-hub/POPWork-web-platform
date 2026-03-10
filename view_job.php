<?php
session_start();
include("backend/db_connect.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
    header("Location: login.html");
    exit();
}

$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$employer_id = $_SESSION['user_id'];

// 1. Fetch ALL job content (matching job_post.php fields)
$sql = "SELECT j.*, p.name as employer_name FROM jobs j 
        LEFT JOIN profiles p ON j.employer_id = p.user_id 
        WHERE j.job_id = ? AND j.employer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $job_id, $employer_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) { die("Job not found."); }

// 2. Fetch Applicants Data
$app_sql = "SELECT a.application_id, a.status, a.applied_at, p.name, u.email, p.phone
            FROM applications a 
            JOIN users u ON a.user_id = u.user_id 
            JOIN profiles p ON u.user_id = p.user_id 
            WHERE a.job_id = ? ORDER BY a.applied_at DESC";
$app_stmt = $conn->prepare($app_sql);
$app_stmt->bind_param("i", $job_id);
$app_stmt->execute();
$applicants = $app_stmt->get_result();

// Check if coordinates exist and are valid
$hasValidCoordinates = !empty($job['latitude']) && 
                      !empty($job['longitude']) && 
                      is_numeric($job['latitude']) && 
                      is_numeric($job['longitude']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Job | <?= htmlspecialchars($job['title']) ?> | POP!Work</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #8a1538;
            --secondary: #c91f4d;
            --bg: #f5f7fa;
            --text: #2c3e50;
            --text-light: #65676b;
            --border: #e1e8ed;
            --success: #27ae60;
            --warning: #f39c12;
            --info: #3498db;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        /* Top Navigation */
        .top-nav {
            background: white;
            border-bottom: 2px solid var(--border);
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-btn {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: var(--bg);
            color: var(--primary);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(138, 21, 56, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* Header Card */
        .header-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 16px;
            padding: 40px;
            color: white;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(138, 21, 56, 0.2);
        }

        .job-category {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }

        .job-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .job-location {
            font-size: 16px;
            opacity: 0.95;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            border-left: 4px solid var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-light);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-subtext {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 4px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        /* Cards */
        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: #f8f9fa;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--primary);
        }

        .card-body {
            padding: 24px;
        }

        /* Job Description */
        .description-text {
            font-size: 15px;
            line-height: 1.8;
            color: var(--text);
            white-space: pre-wrap;
        }

        /* Requirements Tags */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0f2f5;
            color: var(--text);
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .tag i {
            color: var(--primary);
            font-size: 12px;
        }

        /* Sidebar Info */
        .info-section {
            margin-bottom: 24px;
        }

        .info-section:last-child {
            margin-bottom: 0;
        }

        .info-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .info-content {
            font-size: 15px;
            color: var(--text);
            font-weight: 600;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .info-details {
            flex: 1;
        }

        .info-label {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-badge.available {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.closed {
            background: #f8d7da;
            color: #721c24;
        }

        /* Applicants Table */
        .applicants-table {
            width: 100%;
            border-collapse: collapse;
        }

        .applicants-table thead {
            background: #f8f9fa;
        }

        .applicants-table th {
            text-align: left;
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }

        .applicants-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .applicants-table tbody tr:hover {
            background: #f8f9fa;
        }

        .applicants-table tbody tr:last-child td {
            border-bottom: none;
        }

        .applicant-name {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
        }

        .applicant-email {
            font-size: 13px;
            color: var(--text-light);
        }

        .status-label {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-label.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-label.accepted {
            background: #d4edda;
            color: #155724;
        }

        .status-label.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 48px;
            opacity: 0.3;
            margin-bottom: 16px;
        }

        .empty-state p {
            font-size: 15px;
        }

        /* Map Section */
        #jobMap {
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
        }

        .map-placeholder {
            text-align: center;
            padding: 80px 24px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border: 2px dashed var(--border);
        }

        .map-placeholder i {
            font-size: 64px;
            color: var(--primary);
            opacity: 0.3;
            margin-bottom: 16px;
        }

        .map-placeholder p {
            margin: 8px 0;
            color: var(--text-light);
        }

        .map-placeholder strong {
            color: var(--text);
        }

        .directions-btn {
            margin-top: 16px;
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .directions-btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .map-info {
            margin-top: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .job-title {
                font-size: 28px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header-card {
                padding: 24px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .applicants-table {
                font-size: 13px;
            }

            .applicants-table th,
            .applicants-table td {
                padding: 12px 8px;
            }
        }

        /* Custom Leaflet Marker */
        .custom-marker {
            background: transparent !important;
            border: none !important;
        }
    </style>
</head>
<body>

<!-- Top Navigation -->
<div class="top-nav">
    <div class="nav-container">
        <a href="employer_jobs.php" class="back-btn">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </a>
        <div class="action-buttons">
            <a href="edit_job.php?job_id=<?= $job['job_id'] ?>" class="btn btn-secondary">
                <i class="fa fa-edit"></i> Edit Job
            </a>
            <a href="backend/view_applicants.php?job_id=<?= $job['job_id'] ?>" class="btn btn-primary">
                <i class="fa fa-users"></i> View All Applicants
            </a>
        </div>
    </div>
</div>

<!-- Main Container -->
<div class="container">
    
    <!-- Header Card -->
    <div class="header-card">
        <span class="job-category"><?= htmlspecialchars($job['category']) ?></span>
        <h1 class="job-title"><?= htmlspecialchars($job['title']) ?></h1>
        <div class="job-location">
            <i class="fa fa-map-marker-alt"></i>
            <?= htmlspecialchars($job['location']) ?>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Pay Rate</div>
            <div class="stat-value">RM <?= number_format($job['pay_rate'], 2) ?></div>
            <div class="stat-subtext">Per Hour</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Work Period</div>
            <div class="stat-value"><?= date('d M Y', strtotime($job['start_date'])) ?> - <?= date('d M Y', strtotime($job['end_date'])) ?></div>
            <div class="stat-subtext">Duration</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Status</div>
            <div class="stat-value">
                <span class="status-badge <?= $job['status'] ?>">
                    <?= $job['status'] === 'available' ? '🟢 Active' : '⚪ Closed' ?>
                </span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Applicants</div>
            <div class="stat-value"><?= $applicants->num_rows ?></div>
            <div class="stat-subtext">applications received</div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        
        <!-- Left Column -->
        <div>
            <!-- Job Description -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fa fa-align-left"></i>
                        Job Description
                    </h2>
                </div>
                <div class="card-body">
                    <div class="description-text"><?= htmlspecialchars($job['description']) ?></div>
                </div>
            </div>

            <!-- Requirements & Tags -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fa fa-clipboard-check"></i>
                        Requirements of the Job
                    </h2>
                </div>
                <div class="card-body">
                    <div class="tags-container">
            <?php 
            // 1. Combine all requirement fields from the job row
            $all_tags_string =
                               $job['languages_required'] . ',' . 
                               $job['required_documents'];

            // 2. Split the string into an array by commas
            $tags_array = explode(',', $all_tags_string);
            
            $has_tags = false;

            foreach($tags_array as $tag) {
                $trimmed_tag = trim($tag); // Remove extra spaces
                if(!empty($trimmed_tag)) {
                    echo "<span class='tag'><i class='fa fa-check-circle'></i> " . htmlspecialchars($trimmed_tag) . "</span>";
                    $has_tags = true;
                }
            }

            // 3. Fallback if no tags were actually entered
            if (!$has_tags) {
                echo "<p style='color: #999; font-style: italic;'>No specific requirements listed.</p>";
            }
            ?>
        </div>
                </div>
            </div>

            <!-- Applicants Section -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fa fa-users"></i>
                        Recent Applications (<?= $applicants->num_rows ?>)
                    </h2>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if ($applicants->num_rows > 0): ?>
                        <table class="applicants-table">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Applied On</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $count = 0;
                                while($app = $applicants->fetch_assoc()): 
                                    if($count >= 5) break; // Show only 5 recent
                                    $count++;
                                ?>
                                <tr>
                                    <td>
                                        <div class="applicant-name"><?= htmlspecialchars($app['name']) ?></div>
                                        <div class="applicant-email"><?= htmlspecialchars($app['email']) ?></div>
                                    </td>
                                    <td>
                                        <i class="fa fa-calendar" style="color: var(--text-light); margin-right: 6px;"></i>
                                        <?= date('M d, Y', strtotime($app['applied_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="status-label <?= strtolower($app['status']) ?>">
                                            <?= htmlspecialchars($app['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php if ($applicants->num_rows > 5): ?>
                        <div style="padding: 16px 24px; border-top: 1px solid var(--border); text-align: center;">
                            <a href="backend/view_applicants.php?job_id=<?= $job['job_id'] ?>" style="color: var(--primary); text-decoration: none; font-weight: 600; font-size: 14px;">
                                View All <?= $applicants->num_rows ?> Applicants <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa fa-inbox"></i>
                            <p>No applications received yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Map Location -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fa fa-map-marked-alt"></i>
                        Job Location
                    </h2>
                </div>
                <div class="card-body">
                    <?php if ($hasValidCoordinates): ?>
                        <div id="jobMap"></div>
                        <button class="directions-btn" onclick="openGoogleMaps(<?= $job['latitude'] ?>, <?= $job['longitude'] ?>)">
                            <i class="fa fa-directions"></i> Get Directions to This Location
                        </button>
                        <div class="map-info">
                            <i class="fa fa-info-circle"></i>
                            <span>Coordinates: <?= round($job['latitude'], 4) ?>, <?= round($job['longitude'], 4) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="map-placeholder">
                            <i class="fa fa-map-marker-alt"></i>
                            <p><strong>Exact location not provided</strong></p>
                            <p style="font-size: 16px; margin-top: 15px; color: #666;">
                                <i class="fa fa-map-pin"></i> General Location: <strong><?= htmlspecialchars($job['location']) ?></strong>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div>
            <!-- Job Information -->
 <div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa fa-info-circle"></i>
            Job Information
        </h2>
    </div>
    <div class="card-body">
        <div class="info-section">
            
            <?php 
            // FIX: Only display start date if it exists and is valid
            if (!empty($job['start_date']) && $job['start_date'] != '0000-00-00'): 
            ?>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fa fa-calendar-alt"></i>
                </div>
                <div class="info-details">
                    <div class="info-label">Start Date</div>
                    <div class="info-value"><?= date('F d, Y', strtotime($job['start_date'])) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php 
            // FIX: Only display end date if it exists and is valid
            if (!empty($job['end_date']) && $job['end_date'] != '0000-00-00'): 
            ?>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fa fa-calendar-check"></i>
                </div>
                <div class="info-details">
                    <div class="info-label">End Date</div>
                    <div class="info-value"><?= date('F d, Y', strtotime($job['end_date'])) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php 
            // Display job duration if neither start nor end date is set
            if ((empty($job['start_date']) || $job['start_date'] == '0000-00-00') && 
                (empty($job['end_date']) || $job['end_date'] == '0000-00-00')): 
            ?>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fa fa-hourglass-half"></i>
                </div>
                <div class="info-details">
                    <div class="info-label">Duration</div>
                    <div class="info-value"><?= htmlspecialchars($job['job_duration'] ?? 'Not specified') ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="info-item">
                <div class="info-icon">
                    <i class="fa fa-clock"></i>
                </div>
                <div class="info-details">
                    <div class="info-label">Posted On</div>
                    <div class="info-value"><?= date('F d, Y', strtotime($job['created_at'])) ?></div>
                </div>
            </div>

            <?php 
            // FIX: Display expiry date if it exists and is valid
            if (!empty($job['expires_at']) && $job['expires_at'] != '0000-00-00 00:00:00'): 
            ?>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fa fa-calendar-times"></i>
                </div>
                <div class="info-details">
                    <div class="info-label">Expires On</div>
                    <div class="info-value"><?= date('F d, Y', strtotime($job['expires_at'])) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="info-item">
                <div class="info-icon">
                    <i class="fa fa-briefcase"></i>
                </div>
                <div class="info-details">
                    <div class="info-label">Category</div>
                    <div class="info-value"><?= htmlspecialchars($job['category']) ?></div>
                </div>
            </div>

            <?php if (!empty($job['employer_name'])): ?>
            <div class="info-item">
                <div class="info-icon">
                    <i class="fa fa-user-tie"></i>
                </div>
                <div class="info-details">
                    <div class="info-label">Posted By</div>
                    <div class="info-value"><?= htmlspecialchars($job['employer_name']) ?></div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fa fa-bolt"></i>
                        Quick Actions
                    </h2>
                </div>
                <div class="card-body">
                    <a href="edit_job.php?job_id=<?= $job['job_id'] ?>" class="btn btn-secondary" style="width: 100%; margin-bottom: 12px; justify-content: center;">
                        <i class="fa fa-edit"></i> Edit Job Details
                    </a>
                    <a href="backend/view_applicants.php?job_id=<?= $job['job_id'] ?>" class="btn btn-primary" style="width: 100%; margin-bottom: 12px; justify-content: center;">
                        <i class="fa fa-users"></i> Manage Applicants
                    </a>
                    <form action="backend/delete_job.php" method="POST" 
                          onsubmit="return confirm('Are you sure you want to delete this job? This action cannot be undone.');" 
                          style="margin: 0;">
                        <input type="hidden" name="job_id" value="<?= $job['job_id'] ?>">
                        <button type="submit" class="btn" style="width: 100%; background: #e74c3c; color: white; justify-content: center;">
                            <i class="fa fa-trash"></i> Delete Job
                        </button>
                    </form>
                </div>
            </div>
            </div>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<?php if ($hasValidCoordinates): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        const jobLat = <?= $job['latitude'] ?>;
        const jobLng = <?= $job['longitude'] ?>;
        
        if (jobLat && jobLng) {
            // Initialize map
            const map = L.map('jobMap').setView([jobLat, jobLng], 15);
            
            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Custom marker icon
            const customIcon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="background:#8a1538; width:40px; height:40px; border-radius:50% 50% 50% 0; transform:rotate(-45deg); display:flex; align-items:center; justify-content:center; border:4px solid white; box-shadow:0 4px 12px rgba(138, 21, 56, 0.4);">
                        <i class="fa fa-briefcase" style="color:white; transform:rotate(45deg); font-size:18px;"></i>
                       </div>`,
                iconSize: [40, 40],
                iconAnchor: [20, 40],
                popupAnchor: [0, -40]
            });
            
            // Add marker with popup
            const marker = L.marker([jobLat, jobLng], { icon: customIcon })
                .addTo(map)
                .bindPopup(`
                    <div style="text-align: center; padding: 8px;">
                        <strong style="font-size: 16px; color: #8a1538;"><?= htmlspecialchars($job['title']) ?></strong><br>
                        <span style="color: #666; font-size: 14px;"><i class="fa fa-map-marker-alt"></i> <?= htmlspecialchars($job['location']) ?></span>
                    </div>
                `)
                .openPopup();
            
            console.log('Map initialized successfully!');
        }
    } catch (error) {
        console.error('Map initialization error:', error);
    }
});

function openGoogleMaps(lat, lng) {
    const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
    window.open(url, '_blank');
}
</script>
<?php endif; ?>

</body>
</html>