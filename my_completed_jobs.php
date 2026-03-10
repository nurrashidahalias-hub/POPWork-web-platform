<?php
session_start();
include("backend/db_connect.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch completed applications where user can give feedback
if ($user_role === 'worker') {
    // Worker: fetch accepted applications
    $sql = "SELECT a.*, j.title as job_title, j.employer_id,
                   emp.name as employer_name, emp.photo_url as employer_photo,
                   emp.avg_rating as employer_rating,
                   f.feedback_id as has_feedback
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            LEFT JOIN profiles emp ON j.employer_id = emp.user_id
            LEFT JOIN feedback f ON (a.application_id = f.application_id AND f.reviewer_id = ?)
            WHERE a.user_id = ? AND a.status IN ('Accepted', 'completed')
            ORDER BY a.applied_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
} else {
    // Employer: fetch accepted applications for their jobs
    $sql = "SELECT a.*, j.title as job_title, a.user_id as worker_id,
                   worker.name as worker_name, worker.photo_url as worker_photo,
                   worker.avg_rating as worker_rating,
                   f.feedback_id as has_feedback
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            LEFT JOIN profiles worker ON a.user_id = worker.user_id
            LEFT JOIN feedback f ON (a.application_id = f.application_id AND f.reviewer_id = ?)
            WHERE j.employer_id = ? AND a.status IN ('Accepted', 'completed')
            ORDER BY a.applied_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
}

$stmt->execute();
$applications = $stmt->get_result();

// Fetch feedback statistics
if ($user_role === 'worker') {
    $feedback_stats_sql = "SELECT 
        COUNT(*) as total_jobs,
        SUM(CASE WHEN f.feedback_id IS NOT NULL THEN 1 ELSE 0 END) as feedback_given,
        SUM(CASE WHEN f.feedback_id IS NULL THEN 1 ELSE 0 END) as pending_feedback,
        COALESCE(AVG(f.rating), 0) as avg_rating_given
        FROM applications a
        LEFT JOIN feedback f ON (a.application_id = f.application_id AND f.reviewer_id = ?)
        WHERE a.user_id = ? AND a.status IN ('Accepted', 'completed')";
    
    $feedback_stats_stmt = $conn->prepare($feedback_stats_sql);
    $feedback_stats_stmt->bind_param("ii", $user_id, $user_id);
    $feedback_stats_stmt->execute();
    $feedback_stats = $feedback_stats_stmt->get_result()->fetch_assoc();
} else {
    // Employer statistics
    $feedback_stats_sql = "SELECT 
        COUNT(*) as total_jobs,
        SUM(CASE WHEN f.feedback_id IS NOT NULL THEN 1 ELSE 0 END) as feedback_given,
        SUM(CASE WHEN f.feedback_id IS NULL THEN 1 ELSE 0 END) as pending_feedback,
        COALESCE(AVG(f.rating), 0) as avg_rating_given
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        LEFT JOIN feedback f ON (a.application_id = f.application_id AND f.reviewer_id = ?)
        WHERE j.employer_id = ? AND a.status IN ('Accepted', 'completed')";
    
    $feedback_stats_stmt = $conn->prepare($feedback_stats_sql);
    $feedback_stats_stmt->bind_param("ii", $user_id, $user_id);
    $feedback_stats_stmt->execute();
    $feedback_stats = $feedback_stats_stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Jobs - Give Feedback | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
<style>
/* ================================================
   MY COMPLETED JOBS - WORKER DASHBOARD STYLE
   Professional Header with Stats Cards
   ================================================ */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary-color: #8a1538;
    --primary-dark: #6d1028;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --border-color: #e5e7eb;
    --bg-light: #f9fafb;
    --white: #ffffff;
}

body {
    background: var(--bg-light);
    font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
    min-height: 100vh;
    color: var(--text-primary);
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

.dashboard-wrapper {
    display: flex;
    min-height: 100vh;
}

.dashboard-header {
    background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    padding: 32px 40px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 4px 12px rgba(138, 21, 56, 0.15);
}

.header-content {
    max-width: 1600px;
    margin: 0 auto;
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1px;
}

.header-title h1 {
    font-size: 32px;
    font-weight: 700;
    color: var(--white);
    margin-bottom: 4px;
}

.header-title p {
    font-size: 15px;
    color: rgba(255, 255, 255, 0.9);
}

/* Stats Section - Outside Header */
.stats-section {
    padding: 24px 24px;
}

.stats-container {
    max-width: 1600px;
    margin: 0 auto;
}

/* Stats Grid - Compact Version */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}

.stat-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    padding: 20px;
    border-radius: 12px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-align: center;
    border: 2px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #8a1538, #c91f4d, #f39c12);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(138, 21, 56, 0.12);
    border-color: #8a1538;
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-card:nth-child(1) {
    border-left: 4px solid #8a1538;
}

.stat-card:nth-child(2) {
    border-left: 4px solid #27ae60;
}

.stat-card:nth-child(3) {
    border-left: 4px solid #f39c12;
}

.stat-card:nth-child(4) {
    border-left: 4px solid #3498db;
}

.stat-card-content {
    position: relative;
    z-index: 1;
}

.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    margin: 0 auto 14px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.stat-card:nth-child(1) .stat-icon {
    background: linear-gradient(135deg, #8a1538, #c91f4d);
}

.stat-card:nth-child(2) .stat-icon {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
}

.stat-card:nth-child(3) .stat-icon {
    background: linear-gradient(135deg, #f39c12, #f1c40f);
}

.stat-card:nth-child(4) .stat-icon {
    background: linear-gradient(135deg, #3498db, #5dade2);
}

.stat-label {
    font-size: 11px;
    color: #6c757d;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stat-value {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 0;
    color: #2c3e50;
    line-height: 1;
    letter-spacing: -0.5px;
}

.main-content {
    margin-left: 280px;
    padding: 0;
    min-height: 100vh;
    transition: margin-left 0.3s ease;
    position: relative;
    z-index: 1;
}

.content-section {
    padding: 32px;
    max-width: 1600px;
    margin: 0 auto;
}

/* ===== ALERTS ===== */
.alert {
    padding: 14px 18px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 500;
}

.alert i {
    font-size: 16px;
}

.alert-success {
    background: #d1e7dd;
    border: 1px solid #badbcc;
    color: #0f5132;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c2c7;
    color: #842029;
}

.alert-info {
    background: #cff4fc;
    border: 1px solid #b6effb;
    color: #055160;
}

/* ===== TABLE CONTAINER ===== */
.jobs-grid {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

/* ===== TABLE STYLES ===== */
.jobs-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.5);
}

.jobs-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.jobs-table thead th {
    padding: 16px 20px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
}

.jobs-table tbody tr {
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s ease;
}

.jobs-table tbody tr:hover {
    background: #f8f9fa;
}

.jobs-table tbody tr:last-child {
    border-bottom: none;
}

.jobs-table tbody td {
    padding: 18px 20px;
    vertical-align: middle;
    font-size: 14px;
    color: #212529;
}

/* ===== PERSON CELL ===== */
.person-cell {
    display: flex;
    align-items: center;
    gap: 14px;
}

.person-photo {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    object-fit: cover;
    border: 2px solid #e9ecef;
    flex-shrink: 0;
}

.person-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 6px;
    background: #8a1538;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    border: 2px solid #e9ecef;
    flex-shrink: 0;
}

.person-info {
    min-width: 0;
}

.person-name {
    font-weight: 600;
    color: #212529;
    margin-bottom: 2px;
    font-size: 14px;
}

.person-role {
    font-size: 12px;
    color: #6c757d;
}

/* ===== JOB TITLE CELL ===== */
.job-title-cell {
    font-weight: 600;
    color: #212529;
    font-size: 14px;
}

/* ===== STATUS BADGES ===== */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.status-accepted {
    background: #d1e7dd;
    color: #0f5132;
}

.status-completed {
    background: #cfe2ff;
    color: #084298;
}

/* ===== RATING BADGE ===== */
.rating-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: #fff3cd;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    color: #997404;
}

.rating-stars {
    color: #ffc107;
}

/* ===== DATE CELL ===== */
.date-cell {
    font-size: 13px;
    color: #6c757d;
}

/* ===== ACTIONS CELL ===== */
.actions-cell {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
}

/* ===== BUTTONS ===== */
.btn {
    padding: 8px 16px;
    border-radius: 5px;
    font-weight: 600;
    font-size: 13px;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    font-family: 'Poppins', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.btn i {
    font-size: 13px;
}

.btn-primary {
    background: #8a1538;
    color: white;
}

.btn-primary:hover {
    background: #6d1029;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    color: #6c757d;
}

.btn-secondary:hover {
    background: #f8f9fa;
    border-color: #8a1538;
    color: #8a1538;
}

.btn-success {
    background: #d1e7dd;
    color: #0f5132;
    cursor: default;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    padding: 80px 40px;
    text-align: center;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.4;
}

.empty-state h3 {
    font-size: 20px;
    color: #212529;
    margin-bottom: 8px;
    font-weight: 600;
}

.empty-state p {
    color: #6c757d;
    font-size: 14px;
}

/* ================================================
   RESPONSIVE DESIGN - MOBILE OPTIMIZED
   ================================================ */

/* Tablet (1024px and below) */
@media (max-width: 1024px) {
    .main-content {
        margin-left: 0;
    }

    .dashboard-header {
        padding: 20px 24px;
    }

    .content-section {
        padding: 24px;
    }

    .jobs-table thead th {
        padding: 14px 16px;
        font-size: 12px;
    }

    .jobs-table tbody td {
        padding: 16px;
        font-size: 13px;
    }
}

/* Mobile (768px and below) - Card Layout */
@media (max-width: 768px) {
    .dashboard-header {
        position: relative; /* Not sticky on mobile */
        padding: 16px;
    }

    .header-title h1 {
        font-size: 22px;
    }

    .header-title p {
        font-size: 13px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .stat-card {
        padding: 12px 14px;
    }

    .stat-value {
        font-size: 22px;
    }

    .stat-icon {
        font-size: 22px;
    }

    .stat-label {
        font-size: 10px;
    }

    .content-section {
        padding: 20px 16px;
    }

    /* Hide table, show cards */
    .jobs-table thead {
        display: none;
    }

    .jobs-table,
    .jobs-table tbody,
    .jobs-table tr,
    .jobs-table td {
        display: block;
        width: 100%;
    }

    .jobs-table tbody tr {
        margin-bottom: 16px;
        border-radius: 8px;
        padding: 16px;
        background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .jobs-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .jobs-table tbody td {
        padding: 8px 0;
        border: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .jobs-table tbody td::before {
        content: attr(data-label);
        font-weight: 700;
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        flex-shrink: 0;
        width: 100px;
    }

    /* Person Cell */
    .person-cell {
        flex: 1;
        justify-content: flex-end;
    }

    /* Actions Cell */
    .actions-cell {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }

    .actions-cell .btn {
        width: 100%;
        justify-content: center;
    }

    /* Job Title */
    .job-title-cell {
        flex: 1;
        text-align: right;
    }

    /* Rating */
    .rating-badge {
        margin-left: auto;
    }

    /* Date */
    .date-cell {
        text-align: right;
    }
}

/* Small Mobile (576px and below) */
@media (max-width: 576px) {
    .dashboard-header {
        padding: 14px;
    }

    .header-title h1 {
        font-size: 20px;
    }

    .stat-card {
        padding: 12px;
    }

    .stat-value {
        font-size: 20px;
    }

    .content-section {
        padding: 16px 12px;
    }

    .jobs-table tbody tr {
        padding: 14px;
    }

    .jobs-table tbody td {
        padding: 6px 0;
        font-size: 13px;
    }

    .jobs-table tbody td::before {
        font-size: 11px;
        width: 90px;
    }

    .person-photo,
    .person-placeholder {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }

    .person-name {
        font-size: 13px;
    }

    .person-role {
        font-size: 11px;
    }

    .btn {
        padding: 10px 14px;
        font-size: 12px;
    }

    .empty-state {
        padding: 60px 24px;
    }

    .empty-state-icon {
        font-size: 48px;
    }

    .empty-state h3 {
        font-size: 18px;
    }

    .empty-state p {
        font-size: 13px;
    }
}
</style>
</head>
<body>
    <?php 
        $page_title = "My Feedback";
        include 'includes/sidebar.php';
    ?>

    <div class="main-content">
        <!-- Dashboard Header (Like Worker Dashboard) -->
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-top">
                    <div class="header-title">
                        <h1>My Jobs - Feedback ⭐</h1>
                        <p>Review your completed jobs and share your experience with <?= $user_role === 'worker' ? 'employers' : 'workers'; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stats-container">
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-briefcase stat-icon"></i>
                        <div class="stat-card-content">
                            <div class="stat-value"><?= $feedback_stats['total_jobs'] ?? 0; ?></div>
                            <div class="stat-label">Total Jobs</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-star stat-icon"></i>
                        <div class="stat-card-content">
                            <div class="stat-value"><?= $feedback_stats['feedback_given'] ?? 0; ?></div>
                            <div class="stat-label">Feedback Given</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-clock stat-icon"></i>
                        <div class="stat-card-content">
                            <div class="stat-value"><?= $feedback_stats['pending_feedback'] ?? 0; ?></div>
                            <div class="stat-label">Pending Feedback</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-chart-line stat-icon"></i>
                        <div class="stat-card-content">
                            <div class="stat-value"><?= number_format($feedback_stats['avg_rating_given'] ?? 0, 1); ?> ⭐</div>
                            <div class="stat-label">Average Rating</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content Section -->
        <div class="content-section">
            <!-- Alert Messages -->
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_GET['success']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($_GET['error']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['info'])): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <?= htmlspecialchars($_GET['info']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Jobs Table -->
            <div class="jobs-grid">
                <?php if ($applications->num_rows > 0): ?>
                    <table class="jobs-table">
                        <thead>
                            <tr>
                                <th><?= $user_role === 'worker' ? 'Employer' : 'Worker'; ?></th>
                                <th>Job Title</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Applied Date</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = $applications->fetch_assoc()): ?>
                                <?php
                                if ($user_role === 'worker') {
                                    $person_name = $app['employer_name'] ?? 'Employer';
                                    $person_photo = $app['employer_photo'];
                                    $person_rating = $app['employer_rating'];
                                    $person_id = $app['employer_id'];
                                } else {
                                    $person_name = $app['worker_name'] ?? 'Worker';
                                    $person_photo = $app['worker_photo'];
                                    $person_rating = $app['worker_rating'];
                                    $person_id = $app['worker_id'];
                                }
                                ?>
                                
                                <tr>
                                    <!-- Person Column -->
                                    <td data-label="<?= $user_role === 'worker' ? 'Employer' : 'Worker'; ?>">
                                        <div class="person-cell">
                                            <?php if (!empty($person_photo)): ?>
                                                <img src="<?= htmlspecialchars($person_photo); ?>" class="person-photo" alt="Profile">
                                            <?php else: ?>
                                                <div class="person-placeholder">
                                                    <?= strtoupper(substr($person_name, 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="person-info">
                                                <div class="person-name"><?= htmlspecialchars($person_name); ?></div>
                                                <div class="person-role"><?= $user_role === 'worker' ? 'Employer' : 'Worker'; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Job Title Column -->
                                    <td data-label="Job Title">
                                        <span class="job-title-cell"><?= htmlspecialchars($app['job_title']); ?></span>
                                    </td>
                                    
                                    <!-- Status Column -->
                                    <td data-label="Status">
                                        <span class="status-badge status-<?= strtolower($app['status']); ?>">
                                            <?= htmlspecialchars($app['status']); ?>
                                        </span>
                                    </td>
                                    
                                    <!-- Rating Column -->
                                    <td data-label="Rating">
                                        <?php if (!empty($person_rating)): ?>
                                            <span class="rating-badge">
                                                <span class="rating-stars">⭐</span>
                                                <span><?= number_format($person_rating, 1); ?></span>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #adb5bd; font-size: 12px;">No rating</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Date Column -->
                                    <td data-label="Applied">
                                        <span class="date-cell"><?= date('M j, Y', strtotime($app['applied_at'])); ?></span>
                                    </td>
                                    
                                    <!-- Actions Column -->
                                    <td data-label="Actions">
                                        <div class="actions-cell">
                                            <?php if ($app['has_feedback']): ?>
                                                <button class="btn btn-success">
                                                    <i class="fas fa-check-circle"></i> Given
                                                </button>
                                            <?php else: ?>
                                                <a href="give_feedback.php?application_id=<?= $app['application_id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-star"></i> Feedback
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="backend/view_profile.php?user_id=<?= $person_id; ?>" class="btn btn-secondary">
                                                <i class="fas fa-user"></i> Profile
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🔭</div>
                        <h3>No Completed Jobs Yet</h3>
                        <p>When you complete jobs, they'll appear here for you to give feedback.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s, transform 0.5s';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>