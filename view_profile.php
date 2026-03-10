<?php
session_start();
include("db_connect.php");

/* =====================
   SESSION & VALIDATION
===================== */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.html");
    exit();
}

$profile_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : intval($_SESSION['user_id']);
$viewer_id = intval($_SESSION['user_id']);
$viewer_role = $_SESSION['role'];

/* =====================
   FETCH USER & PROFILE DATA
===================== */
$sql = "SELECT u.user_id, u.email, u.role, u.created_at,
               p.profile_id, p.name, p.phone, p.address, p.bio, p.rating, p.photo_url,
               p.skills, p.experience, p.resume_url, p.university, p.is_student,
               p.date_of_birth, p.gender, p.district
        FROM users u
        LEFT JOIN profiles p ON u.user_id = p.user_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { die("User Not Found"); }

/* =====================
   FETCH RATING DATA
===================== */
$rating_sql = "SELECT 
                p.avg_rating, 
                p.total_ratings,
                COUNT(CASE WHEN f.rating = 5 THEN 1 END) as five_star_count,
                COUNT(CASE WHEN f.would_work_again = 1 THEN 1 END) as would_work_again_count
               FROM profiles p
               LEFT JOIN feedback f ON p.user_id = f.reviewee_id
               WHERE p.user_id = ?
               GROUP BY p.user_id";

$rating_stmt = $conn->prepare($rating_sql);
$rating_stmt->bind_param("i", $profile_user_id);
$rating_stmt->execute();
$rating_data = $rating_stmt->get_result()->fetch_assoc();

/* =====================
   FETCH RECENT FEEDBACK
===================== */
$feedback_sql = "SELECT f.*, 
                        reviewer.name as reviewer_name,
                        reviewer.photo_url as reviewer_photo,
                        j.title as job_title,
                        j.category as job_category
                 FROM feedback f
                 JOIN profiles reviewer ON f.reviewer_id = reviewer.user_id
                 JOIN applications a ON f.application_id = a.application_id
                 JOIN jobs j ON a.job_id = j.job_id
                 WHERE f.reviewee_id = ?
                 ORDER BY f.created_at DESC
                 LIMIT 5";

$feedback_stmt = $conn->prepare($feedback_sql);
$feedback_stmt->bind_param("i", $profile_user_id);
$feedback_stmt->execute();
$recent_feedback = $feedback_stmt->get_result();

/* =====================
   HELPER VARIABLES
===================== */
$is_own_profile = ($profile_user_id === $viewer_id);
$is_viewing_worker = ($user['role'] === 'worker');
$skills_array = !empty($user['skills']) ? array_filter(array_map('trim', explode(',', $user['skills']))) : [];

// Calculate age
$age = null;
if (!empty($user['date_of_birth'])) {
    try {
        $dob = new DateTime($user['date_of_birth']);
        $age = (new DateTime())->diff($dob)->y;
    } catch (Exception $e) { 
        $age = null; 
    }
}

// Fetch stats for own profile
$stats = null;
if ($is_own_profile) {
    if ($user['role'] === 'worker') {
        $stats_sql = "SELECT COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_jobs,
                             COALESCE(AVG(CASE WHEN status = 'completed' THEN rating END), 0) as avg_rating
                      FROM applications WHERE worker_id = ?";
    } else {
        $stats_sql = "SELECT COUNT(*) as active_jobs FROM jobs WHERE employer_id = ? AND status = 'open'";
    }
    $st_stmt = $conn->prepare($stats_sql);
    $st_stmt->bind_param("i", $profile_user_id);
    $st_stmt->execute();
    $stats = $st_stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['name']); ?> | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ================================================
   PROFESSIONAL VIEW PROFILE PAGE
   Neat, Structured, Mobile-Responsive Design
   ================================================ */

/* ===== RESET & BASE ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f5f7fa;
    color: #1f2937;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* ===== HEADER ===== */
header {
    background: white;
    padding: 16px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid #e5e7eb;
}

.header-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 32px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.logo-text {
    font-size: 22px;
    font-weight: 700;
    background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.5px;
}

/* ===== MAIN CONTAINER ===== */
.profile-wrapper {
    max-width: 1280px;
    margin: 0 auto;
    padding: 32px;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 24px;
    padding: 10px 18px;
    background: white;
    color: #6b7280;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.back-button:hover {
    background: #f9fafb;
    border-color: #8a1538;
    color: #8a1538;
    transform: translateX(-2px);
}

/* ===== PROFILE HEADER CARD ===== */
.profile-header-card {
    background:  #c91f4d;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 24px;
    border: 1px solid  #c91f4d;
}

.profile-banner {
    height: 120px;
    background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
    position: relative;
}

.profile-header-content {
    padding: 0 32px 32px;
    position: relative;
}

.profile-photo-section {
    display: flex;
    align-items: flex-end;
    gap: 24px;
    margin-top: -60px;
    margin-bottom: 20px;
}

.profile-photo-wrapper {
    width: 120px;
    height: 120px;
    border-radius: 16px;
    overflow: hidden;
    border: 5px solid white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    background:  #c91f4d;
    flex-shrink: 0;
}

.profile-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: 700;
    background: linear-gradient(135deg, #8a1538, #c91f4d);
    color: white;
}

.profile-basic-info {
    flex: 1;
    min-width: 0;
}

.profile-name {
    font-size: 32px;
    font-weight: 700;
    color: #ffffffff; /* FIXED: Was white, now dark */
    margin-bottom: 12px; /* FIXED: Was 30px, now 12px */
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    letter-spacing: -0.5px;
}

.student-badge {
    background: linear-gradient(135deg, #fbbf24, #f59e0b); /* FIXED: Better contrast */
    color: #111827; /* FIXED: Was hard to read */
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(251, 191, 36, 0.3);
}

.profile-meta {
    display: flex;
    align-items: center;
    gap: 20px;
    font-size: 14px;
    color: #ffffffff;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.profile-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
}

.profile-meta-item i {
    color: #e4b1b1ff;
    font-size: 14px;
}

.edit-profile-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #8a1538 0%, #6b1029 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(138,21,56,0.2);
}

.edit-profile-btn:hover {
    background: linear-gradient(135deg, #6b1029 0%, #8a1538 100%);
    box-shadow: 0 4px 12px rgba(138,21,56,0.3);
    transform: translateY(-1px);
}

/* ===== STATS CARDS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
}

.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
    border-color: #8a1538;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #8a1538;
    margin-bottom: 4px;
    letter-spacing: -0.5px;
}

.stat-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ===== MAIN LAYOUT ===== */
.profile-content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    align-items: start;
}

/* ===== SECTION CARDS ===== */
.section-card {
    background: white;
    border-radius: 12px;
    padding: 28px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
}

.section-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f3f4f6;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.3px;
}

.section-icon {
    color: #8a1538;
    font-size: 20px;
}

.section-content {
    color: #4b5563;
    line-height: 1.8;
    font-size: 15px;
}

.section-content p {
    margin-bottom: 12px;
}

.section-content strong {
    color: #111827;
    font-weight: 600;
}

/* ===== SKILLS ===== */
.skills-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.skill-tag {
    background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%);
    color: #0369a1;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid #bae6fd;
    transition: all 0.2s ease;
}

.skill-tag:hover {
    background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%);
    color: white;
    border-color: #0369a1;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(3,105,161,0.3);
}

/* ===== RESUME BUTTON ===== */
.resume-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #8a1538 0%, #6b1029 100%);
    color: white;
    padding: 14px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(138,21,56,0.2);
}

.resume-btn:hover {
    background: linear-gradient(135deg, #6b1029 0%, #8a1538 100%);
    box-shadow: 0 4px 12px rgba(138,21,56,0.3);
    transform: translateY(-1px);
}

/* ===== SIDEBAR ===== */
.sidebar-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border: 1px solid #e5e7eb;
}

.sidebar-title {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 16px;
    letter-spacing: -0.2px;
}

.info-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f3f4f6;
}

.info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 0;
}

.info-label {
    font-size: 11px;
    color: #9ca3af;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 15px;
    color: #111827;
    font-weight: 600;
}

.verified-badge {
    color: #059669;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
}

/* ===== RATINGS SECTION ===== */
.rating-overview-grid {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 28px;
    margin-bottom: 32px;
}

.rating-score-card {
    background: linear-gradient(135deg, #fffbeb, #fef3c7);
    border: 2px solid #fbbf24;
    border-radius: 16px;
    padding: 32px 28px;
    text-align: center;
    min-width: 180px;
    box-shadow: 0 4px 12px rgba(251,191,36,0.2);
}

.rating-number {
    font-size: 56px;
    font-weight: 800;
    color: #111827;
    line-height: 1;
    margin-bottom: 10px;
    letter-spacing: -1px;
}

.rating-stars {
    font-size: 22px;
    color: #fbbf24;
    margin-bottom: 8px;
}

.rating-count {
    font-size: 13px;
    color: #78716c;
    font-weight: 600;
}

.rating-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
}

.rating-stat-item {
    background: #f9fafb;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
}

.rating-stat-item:hover {
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.rating-stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #8a1538;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}

.rating-stat-label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 600;
    line-height: 1.4;
}

/* ===== FEEDBACK ITEMS ===== */
.feedback-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.feedback-item {
    background: #f9fafb;
    border-radius: 12px;
    padding: 24px;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
}

.feedback-item:hover {
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.feedback-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 14px;
}

.reviewer-avatar,
.reviewer-avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid #e5e7eb;
    flex-shrink: 0;
}

.reviewer-avatar-placeholder {
    background: linear-gradient(135deg, #8a1538, #c91f4d);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
}

.reviewer-details {
    flex: 1;
    min-width: 0;
}

.reviewer-details h4 {
    font-size: 15px;
    color: #111827;
    font-weight: 600;
    margin-bottom: 2px;
}

.reviewer-details p {
    font-size: 13px;
    color: #6b7280;
}

.feedback-rating {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.feedback-stars {
    color: #fbbf24;
    font-size: 16px;
}

.feedback-text {
    color: #111827;
    line-height: 1.7;
    font-size: 14px;
    margin-bottom: 12px;
    font-style: italic;
    background: white;
    padding: 14px;
    border-radius: 8px;
    border-left: 3px solid #8a1538;
}

.feedback-meta {
    font-size: 12px;
    color: #9ca3af;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
}

.no-reviews-state {
    text-align: center;
    padding: 60px 20px;
}

.no-reviews-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.view-all-reviews-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #8a1538;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    padding: 12px 24px;
    background: white;
    border-radius: 8px;
    border: 2px solid #8a1538;
    transition: all 0.2s ease;
    margin-top: 20px;
}

.view-all-reviews-btn:hover {
    background: #8a1538;
    color: white;
    box-shadow: 0 4px 12px rgba(138,21,56,0.3);
    transform: translateY(-1px);
}

/* ================================================
   RESPONSIVE DESIGN
   ================================================ */

/* Tablet (1024px and below) */
@media (max-width: 1024px) {
    .profile-content-grid {
        grid-template-columns: 1fr;
    }

    .rating-overview-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .rating-score-card {
        max-width: 100%;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Mobile (768px and below) */
@media (max-width: 768px) {
    .header-container,
    .profile-wrapper {
        padding: 16px 20px;
    }

    .back-button {
        margin-bottom: 16px;
    }

    /* Profile Header */
    .profile-banner {
        height: 100px;
    }

    .profile-header-content {
        padding: 0 20px 24px;
    }

    .profile-photo-section {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 16px;
        margin-top: -50px;
    }

    .profile-photo-wrapper {
        width: 100px;
        height: 100px;
    }

    .default-avatar {
        font-size: 40px;
    }

    .profile-basic-info {
        width: 100%;
    }

    .profile-name {
        font-size: 26px;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        text-align: center;
    }

    .student-badge {
        font-size: 11px;
        padding: 5px 12px;
    }

    .profile-meta {
        flex-direction: column;
        gap: 10px;
        align-items: center;
        text-align: center;
    }

    .edit-profile-btn {
        width: 100%;
        justify-content: center;
    }

    /* Stats */
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-card {
        padding: 18px 20px;
    }

    .stat-value {
        font-size: 28px;
    }

    /* Sections */
    .section-card {
        padding: 20px;
        margin-bottom: 16px;
    }

    .section-title {
        font-size: 18px;
    }

    .section-content {
        font-size: 14px;
    }

    /* Skills */
    .skills-container {
        gap: 8px;
    }

    .skill-tag {
        font-size: 12px;
        padding: 6px 12px;
    }

    /* Rating */
    .rating-score-card {
        padding: 24px 20px;
    }

    .rating-number {
        font-size: 48px;
    }

    .rating-stats-grid {
        grid-template-columns: 1fr;
    }

    .rating-stat-item {
        padding: 16px;
    }

    .rating-stat-number {
        font-size: 28px;
    }

    /* Feedback */
    .feedback-item {
        padding: 18px;
    }

    .reviewer-avatar,
    .reviewer-avatar-placeholder {
        width: 44px;
        height: 44px;
    }

    .feedback-text {
        font-size: 13px;
        padding: 12px;
    }

    /* Sidebar */
    .sidebar-card {
        padding: 20px;
    }
}

/* Small Mobile (576px and below) */
@media (max-width: 576px) {
    .header-container,
    .profile-wrapper {
        padding: 12px 16px;
    }

    .profile-banner {
        height: 80px;
    }

    .profile-header-content {
        padding: 0 16px 20px;
    }

    .profile-photo-section {
        margin-top: -40px;
    }

    .profile-photo-wrapper {
        width: 80px;
        height: 80px;
    }

    .default-avatar {
        font-size: 32px;
    }

    .profile-name {
        font-size: 22px;
    }

    .student-badge {
        font-size: 10px;
        padding: 4px 10px;
    }

    .section-card {
        padding: 16px;
    }

    .section-title {
        font-size: 17px;
        gap: 8px;
    }

    .stat-value {
        font-size: 24px;
    }

    .stat-label {
        font-size: 12px;
    }

    .rating-number {
        font-size: 40px;
    }

    .rating-score-card {
        padding: 20px 16px;
    }

    .feedback-item {
        padding: 16px;
    }

    .sidebar-card {
        padding: 16px;
    }
}
</style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="header-container">
        <div class="logo-container">
            <img src="../assets/images/logo.png" alt="POP!Work Logo" height="44">
            <span class="logo-text">POP!Work</span>
        </div>
    </div>
</header>

<!-- MAIN PROFILE WRAPPER -->
<div class="profile-wrapper">
    
    <!-- BACK BUTTON -->
    <a href="javascript:history.back()" class="back-button">
        <i class="fas fa-arrow-left"></i> Back
    </a>

    <!-- PROFILE HEADER CARD -->
    <div class="profile-header-card">
        <div class="profile-banner"></div>
        <div class="profile-header-content">
            <div class="profile-photo-section">
                <div class="profile-photo-wrapper">
                    <?php if (!empty($user['photo_url'])): ?>
                        <img src="<?= '../' . htmlspecialchars($user['photo_url']); ?>" class="profile-photo" alt="Profile">
                    <?php else: ?>
                        <div class="default-avatar"><?= strtoupper(substr($user['name'], 0, 1)); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-basic-info">
                    <h1 class="profile-name">
                        <?= htmlspecialchars($user['name']); ?>
                        <?php if ($user['is_student']): ?>
                            <span class="student-badge"><i class="fas fa-graduation-cap"></i>Student</span>
                        <?php endif; ?>
                    </h1>
                    
                    <div class="profile-meta">
                        <span class="profile-meta-item">
                            <i class="fas fa-briefcase"></i>
                            <?= ucfirst(htmlspecialchars($user['role'])); ?>
                        </span>
                        <?php if ($user['district']): ?>
                        <span class="profile-meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($user['district']); ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($age): ?>
                        <span class="profile-meta-item">
                            <i class="fas fa-calendar"></i>
                            <?= $age; ?> years old
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_own_profile): ?>
                        <a href="edit_profile.php" class="edit-profile-btn">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- STATS GRID (Only for own profile) -->
    <?php if ($is_own_profile && $stats): ?>
    <div class="stats-grid">
        <?php if ($user['role'] === 'worker'): ?>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['completed_jobs']; ?></div>
                <div class="stat-label">Jobs Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['avg_rating'], 1); ?> ⭐</div>
                <div class="stat-label">Average Rating</div>
            </div>
        <?php else: ?>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['active_jobs']; ?></div>
                <div class="stat-label">Active Job Posts</div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- MAIN CONTENT GRID -->
    <div class="profile-content-grid">
        
        <!-- LEFT COLUMN - Main Content -->
        <div class="main-content">
            
            <!-- ABOUT SECTION -->
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-user section-icon"></i>
                    About
                </h2>
                <div class="section-content">
                    <p><?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : "<em>No bio provided</em>"; ?></p>
                </div>
            </div>

            <!-- EDUCATION SECTION -->
            <?php if (!empty($user['university'])): ?>
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-graduation-cap section-icon"></i>
                    Education
                </h2>
                <div class="section-content">
                    <p><strong>University:</strong> <?= htmlspecialchars($user['university']); ?></p>
                    <?php if ($user['is_student']): ?>
                        <p style="color: #80868b; font-size: 13px; margin-top: 4px;">
                            <i class="fas fa-check-circle" style="color: #1e8e3e;"></i> Currently pursuing studies
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- SKILLS SECTION (Workers only) -->
            <?php if ($is_viewing_worker): ?>
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-tools section-icon"></i>
                    Skills & Expertise
                </h2>
                <div class="skills-container">
                    <?php if ($skills_array): ?>
                        <?php foreach ($skills_array as $skill): ?>
                            <span class="skill-tag"><?= htmlspecialchars($skill); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #80868b;"><em>No skills listed</em></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- EXPERIENCE SECTION (Workers only) -->
            <?php if ($is_viewing_worker): ?>
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-briefcase section-icon"></i>
                    Work Experience
                </h2>
                <div class="section-content">
                    <?php if (!empty($user['experience'])): ?>
                        <?php
                        $exp_labels = [
                            'entry' => 'Entry Level (0-1 years)',
                            'junior' => 'Junior Level (1-3 years)',
                            'intermediate' => 'Intermediate Level (3-5 years)',
                            'senior' => 'Senior Level (5+ years)'
                        ];
                        $exp_desc = [
                            'entry' => 'Ready to learn and grow. Eager to start career and gain valuable work experience.',
                            'junior' => 'Building practical experience with solid foundation. Growing skills through hands-on work.',
                            'intermediate' => 'Proven track record with substantial experience. Capable of working independently and mentoring others.',
                            'senior' => 'Extensive experience with deep expertise. Skilled at complex challenges and team leadership.'
                        ];
                        ?>
                        <p><strong><?= $exp_labels[$user['experience']] ?? htmlspecialchars($user['experience']); ?></strong></p>
                        <p style="color: #80868b; margin-top: 8px;">
                            <?= $exp_desc[$user['experience']] ?? nl2br(htmlspecialchars($user['experience'])); ?>
                        </p>
                    <?php else: ?>
                        <p style="color: #80868b;"><em>Experience level not specified</em></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- RESUME SECTION (Workers only) -->
            <?php if ($is_viewing_worker): ?>
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-file-alt section-icon"></i>
                    Resume / CV
                </h2>
                <div class="section-content">
                    <?php if (!empty($user['resume_url'])): ?>
                        <p style="margin-bottom: 14px;">Download the complete resume for this candidate.</p>
                        <a href="<?= '../' . htmlspecialchars($user['resume_url']); ?>" target="_blank" class="resume-btn">
                            <i class="fas fa-download"></i> Download Resume
                        </a>
                    <?php else: ?>
                        <p style="color: #80868b;"><em>No resume uploaded yet.</em></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- CONTACT SECTION -->
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-address-card section-icon"></i>
                    Contact Information
                </h2>
                <div class="section-content">
                    <p><strong><i class="fas fa-envelope"></i> Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
                    <?php if(!empty($user['phone'])): ?>
                        <p><strong><i class="fas fa-phone"></i> Phone:</strong> <?= htmlspecialchars($user['phone']); ?></p>
                    <?php endif; ?>
                    <?php if(!empty($user['address'])): ?>
                        <p><strong><i class="fas fa-home"></i> Address:</strong> <?= htmlspecialchars($user['address']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RATINGS & REVIEWS SECTION -->
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-star section-icon"></i>
                    Ratings & Reviews
                </h2>
                
                <?php if ($rating_data && $rating_data['total_ratings'] > 0): ?>
                    
                    <!-- Rating Overview -->
                    <div class="rating-overview-grid">
                        <!-- Score Card -->
                        <div class="rating-score-card">
                            <div class="rating-number"><?= number_format($rating_data['avg_rating'], 1); ?></div>
                            <div class="rating-stars">
                                <?php 
                                $full_stars = floor($rating_data['avg_rating']);
                                for($i = 0; $i < $full_stars; $i++) echo '⭐';
                                ?>
                            </div>
                            <div class="rating-count"><?= $rating_data['total_ratings']; ?> review<?= $rating_data['total_ratings'] > 1 ? 's' : ''; ?></div>
                        </div>
                        
                        <!-- Stats Grid -->
                        <div class="rating-stats-grid">
                            <div class="rating-stat-item">
                                <div class="rating-stat-number"><?= $rating_data['five_star_count']; ?></div>
                                <div class="rating-stat-label">5-Star Reviews</div>
                            </div>
                            
                            <div class="rating-stat-item">
                                <div class="rating-stat-number"><?= $rating_data['would_work_again_count']; ?></div>
                                <div class="rating-stat-label">Would Work Again</div>
                            </div>
                            
                            <div class="rating-stat-item">
                                <div class="rating-stat-number"><?= round(($rating_data['would_work_again_count'] / $rating_data['total_ratings']) * 100); ?>%</div>
                                <div class="rating-stat-label">Recommendation Rate</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Reviews -->
                    <h3 style="font-size: 16px; color: #1a1a1a; margin: 24px 0 16px; font-weight: 600;">Recent Reviews</h3>
                    <div class="feedback-list">
                        <?php while ($fb = $recent_feedback->fetch_assoc()): ?>
                        <div class="feedback-item">
                            <div class="feedback-header">
                                <?php if (!empty($fb['reviewer_photo'])): ?>
                                       <div class="reviewer-avatar-placeholder">
                                        <?= strtoupper(substr($fb['reviewer_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="reviewer-details">
                                    <h4><?= htmlspecialchars($fb['reviewer_name']); ?></h4>
                                    <p><i class="fas fa-briefcase"></i> <?= htmlspecialchars($fb['job_title']); ?> • <?= htmlspecialchars($fb['job_category']); ?></p>
                                </div>
                            </div>
                            
                            <div class="feedback-rating">
                                <span class="feedback-stars">
                                    <?php for($i = 0; $i < $fb['rating']; $i++) echo '⭐'; ?>
                                </span>
                                <span style="font-weight: 700; color: #1a1a1a;"><?= $fb['rating']; ?>/5</span>
                            </div>
                            
                            <?php if (!empty($fb['comment'])): ?>
                            <div class="feedback-text">
                                "<?= nl2br(htmlspecialchars($fb['comment'])); ?>"
                            </div>
                            <?php endif; ?>
                            
                            <div class="feedback-meta">
                                <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($fb['created_at'])); ?></span>
                                <?php if ($fb['would_work_again']): ?>
                                    <span><i class="fas fa-check-circle" style="color: #1e8e3e;"></i> Would work again</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <?php if ($rating_data['total_ratings'] > 5): ?>
                    <div style="text-align: center; margin-top: 24px;">
                        <a href="view_user_reviews.php?user_id=<?= $profile_user_id; ?>" class="view-all-reviews-btn">
                            View All <?= $rating_data['total_ratings']; ?> Reviews
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="no-reviews-state">
                        <div class="no-reviews-icon">⭐</div>
                        <h3 style="color: #5f6368; margin-bottom: 8px; font-size: 16px;">No Reviews Yet</h3>
                        <p style="color: #80868b; font-size: 14px;">This user hasn't received any reviews yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- RIGHT COLUMN - Sidebar -->
        <div class="sidebar">
            
            <!-- Quick Actions (Own Profile Only) -->
            <?php if ($is_own_profile): ?>
            <div class="sidebar-card">
                <h3 class="sidebar-title">Quick Actions</h3>
                <ul class="sidebar-list">
                    <li>
                        <a href="edit_profile.php">
                            <i class="fas fa-edit"></i> Update Profile
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i> Account Settings
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Account Info -->
            <div class="sidebar-card">
                <h3 class="sidebar-title">Account Info</h3>
                
                <div class="info-row">
                    <div class="info-label">Member Since</div>
                    <div class="info-value"><?= date('F Y', strtotime($user['created_at'])); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Account Status</div>
                    <div class="info-value verified-badge">
                        <i class="fas fa-check-circle"></i> Verified User
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>
</body>
</html>