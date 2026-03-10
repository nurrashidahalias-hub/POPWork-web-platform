<?php
session_start();
include("backend/db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user and profile information
// Update your existing $sql at the top
$sql = "SELECT u.user_id, u.email, u.role, u.created_at,
               p.profile_id, p.name, p.phone, p.address, p.bio, p.rating, p.photo_url,
               p.skills, p.experience, p.resume_url, p.university, p.is_student,
               p.date_of_birth, p.gender, p.district,
               p.major, p.edu_duration -- ADDED THESE
        FROM users u
        LEFT JOIN profiles p ON u.user_id = p.user_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found";
    exit();
}

// If name is empty but we have first/last name somewhere, combine them
if (empty($user['name']) && !empty($user['email'])) {
    $email_parts = explode('@', $user['email']);
    $user['name'] = ucfirst($email_parts[0]);
}

// Get statistics based on role
$stats = [];
if ($user['role'] === 'worker') {
    $stats_sql = "SELECT 
        COUNT(*) as total_applications,
        SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) as accepted_applications,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_applications,
        SUM(CASE WHEN status = 'Shortlisted' THEN 1 ELSE 0 END) as shortlisted_applications
        FROM applications WHERE user_id = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
} elseif ($user['role'] === 'employer') {
    $stats_sql = "SELECT 
        COUNT(DISTINCT j.job_id) as total_jobs,
        COUNT(DISTINCT a.application_id) as total_applicants,
        COUNT(DISTINCT CASE WHEN a.status = 'Accepted' THEN a.application_id END) as hired_workers
        FROM jobs j
        LEFT JOIN applications a ON j.job_id = a.job_id
        WHERE j.employer_id = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $user_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
}

// Calculate age and profile completion
$age = null;
if (!empty($user['date_of_birth'])) {
    $dob = new DateTime($user['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
}
// Calculate profile completion percentage based on Role
if ($user['role'] === 'worker') {
    // Worker requires full details
    $completion_items = [
        'Name' => !empty($user['name']),
        'Phone' => !empty($user['phone']),
        'Bio' => !empty($user['bio']),
        'Skills' => !empty($user['skills']),
        'Experience' => !empty($user['experience']),
        'Photo' => !empty($user['photo_url']),
        'Resume' => !empty($user['resume_url']),
        'Address' => !empty($user['address'])
    ];
} else {
    // Employer/Admin requires only organizational details
    $completion_items = [
        'Company Name' => !empty($user['name']),
        'Contact Phone' => !empty($user['phone']),
        'Company Bio' => !empty($user['bio']),
        'Logo' => !empty($user['photo_url']),
        'Location' => !empty($user['address']),
        'District' => !empty($user['district'])
    ];
}

$completed = count(array_filter($completion_items));
$total = count($completion_items);
$completion_percentage = round(($completed / $total) * 100);

// Parse skills array
$skills_array = !empty($user['skills']) ? array_map('trim', explode(',', $user['skills'])) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Profile | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background: #f0f2f5;
        min-height: 100vh;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        color: #1c1e21;
        line-height: 1.5;
        display: flex;
        overflow-x: hidden;
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

    /* Dashboard Wrapper for Sidebar Integration */
    .dashboard-wrapper {
        display: flex;
        width: 100%;
        min-height: 100vh;
    }

    .main-content-wrapper {
        flex: 1;
        margin-left: 280px;
        transition: margin-left 0.3s ease;
        width: calc(100% - 280px);
        position: relative;
        z-index: 1;
    }

    .profile-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 24px;
    }

    /* Profile Completion Banner */
    .completion-banner {
        background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
        border-radius: 16px;
        padding: 24px 32px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 20px rgba(138, 21, 56, 0.25);
        color: white;
    }

    .completion-info h3 {
        font-size: 18px;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .completion-bar-container {
        background: rgba(255,255,255,0.2);
        height: 8px;
        border-radius: 4px;
        width: 300px;
        overflow: hidden;
    }

    .completion-bar {
        height: 100%;
        background: linear-gradient(90deg, #27ae60, #2ecc71);
        border-radius: 4px;
        transition: width 0.5s ease;
    }

    .completion-percentage {
        font-size: 24px;
        font-weight: 700;
        margin-left: 20px;
    }

    /* Professional Header - Full Width */
    .profile-header {
        background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
        backdrop-filter: blur(5px);
        border-radius: 20px;
        padding: 0;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 40px;
        border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .header-content {
        padding: 40px;
        position: relative;
        display: flex;
        gap: 32px;
        align-items: flex-start;
    }

    .profile-photo-wrapper {
        margin-top: 0;
        position: relative;
        width: 150px;
        flex-shrink: 0;
    }

    .profile-photo-container {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        overflow: hidden;
        background: #f0f2f5;
        border: 6px solid white;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        position: relative;
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
        font-size: 60px;
        background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
        color: white;
        font-weight: 700;
    }

    .change-photo-btn {
        position: absolute;
        bottom: 8px;
        right: 8px;
        background: white;
        border: 2px solid #e4e6eb;
        color: #8a1538;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .change-photo-btn:hover {
        background: #780c2d;
        color: white;
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(138, 21, 56, 0.3);
    }

    .header-info {
        flex: 1;
        padding-top: 0;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
    }

    .profile-details {
        width: 100%;
    }

    .profile-name {
        font-size: 48px;
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 24px;
        letter-spacing: -1.5px;
        line-height: 1.1;
    }

    .profile-badges {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #f8f9fa, #ffffff);
        color: #1c1e21;
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 14px;
        font-weight: 600;
        border: 2px solid #e4e6eb;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        border-color: #8a1538;
    }

    .badge.student {
        background: linear-gradient(135deg, #e7f3ff, #cce7ff);
        color: #1876f2;
        border-color: #90caf9;
    }

    .badge.rating {
        background: linear-gradient(135deg, #fff8e1, #ffecb3);
        color: #f57c00;
        border-color: #ffd54f;
    }

    .badge i {
        font-size: 16px;
    }

    .profile-meta {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        font-size: 15px;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 14px;
        font-weight: 500;
        padding: 16px 20px;
        background: linear-gradient(135deg, #ffffff, #f8f9fa);
        border-radius: 12px;
        transition: all 0.3s;
        border: 1px solid #e4e6eb;
        color: #1c1e21;
        min-height: 72px;
    }

    .meta-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(138, 21, 56, 0.1);
        border-color: #8a1538;
    }

    .meta-item span:first-child {
        font-size: 20px;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(138, 21, 56, 0.1), rgba(201, 31, 77, 0.1));
        border-radius: 10px;
        flex-shrink: 0;
    }
    .stats-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 16px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border-left: 4px solid #667eea;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .stat-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateX(-2px);
    }

    .stat-card:nth-child(1) { border-left-color: #667eea; }
    .stat-card:nth-child(2) { border-left-color: #f39c12; }
    .stat-card:nth-child(3) { border-left-color: #27ae60; }
    .stat-card:nth-child(4) { border-left-color: #e67e22; }

    .stat-content {
        flex: 1;
    }

    .stat-label {
        font-size: 11px;
        color: #65676b;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        display: block;
    }

    .stat-icon {
        font-size: 28px;
        opacity: 0.6;
        margin-left: 16px;
    }

    .stat-number {
        font-size: 36px;
        font-weight: 700;
        color: #1c1e21;
        line-height: 1;
    }

    /* Content Layout */
    .content-layout {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 24px;
    }

    /* Section Cards */
    .section-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f2f5;
    }

    .section-title {
        font-size: 20px;
        font-weight: 700;
        color: #1c1e21;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-icon {
        font-size: 22px;
    }

    .edit-btn {
        background: #f0f2f5;
        border: none;
        color: #050505;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
    }

    .edit-btn:hover {
        background: #e4e6eb;
    }

    /* Bio Suggestions */
    .bio-suggestions {
        background: #f7f9fc;
        border: 1px solid #e4e6eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
    }

    .suggestions-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .suggestions-title {
        font-size: 13px;
        font-weight: 600;
        color: #65676b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .suggestion-templates {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .template-btn {
        background: white;
        border: 1px solid #e4e6eb;
        padding: 12px;
        border-radius: 6px;
        text-align: left;
        cursor: pointer;
        font-size: 13px;
        color: #1c1e21;
        transition: all 0.2s;
        line-height: 1.4;
    }

    .template-btn:hover {
        background: #f0f2f5;
        border-color: #667eea;
    }

    .template-label {
        font-weight: 600;
        color: #667eea;
        display: block;
        margin-bottom: 4px;
        font-size: 12px;
    }

    /* Skills Section */
    .skills-suggestions {
        background: #f7f9fc;
        border: 1px solid #e4e6eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
    }

    .skill-categories {
        display: flex;
        gap: 8px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .category-btn {
        background: white;
        border: 1px solid #e4e6eb;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        color: #65676b;
    }

    .category-btn.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .suggested-skills {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .skill-suggestion {
        background: white;
        border: 1px solid #e4e6eb;
        padding: 6px 12px;
        border-radius: 16px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
        color: #1c1e21;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .skill-suggestion:hover {
        background: #e7f3ff;
        border-color: #1876f2;
        color: #1876f2;
    }

    .skill-suggestion.added {
        background: #d4edda;
        border-color: #27ae60;
        color: #155724;
    }

    .skills-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .skill-tag {
        background: #e7f3ff;
        color: #1876f2;
        padding: 8px 16px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #1876f2;
    }

    .skill-tag .remove-skill {
        cursor: pointer;
        opacity: 0.7;
        transition: opacity 0.2s;
    }

    .skill-tag .remove-skill:hover {
        opacity: 1;
    }

    .skills-grid.empty {
        text-align: center;
        padding: 40px;
        color: #65676b;
        font-style: italic;
        background: #f7f9fc;
        border-radius: 8px;
        border: 2px dashed #e4e6eb;
    }

    /* Experience Descriptions */
    .experience-item {
        padding: 16px;
        background: #f7f9fc;
        border-radius: 8px;
        border-left: 3px solid #667eea;
    }

    .experience-level {
        font-size: 16px;
        font-weight: 700;
        color: #1c1e21;
        margin-bottom: 6px;
    }

    .experience-description {
        font-size: 14px;
        color: #65676b;
        line-height: 1.6;
    }

    /* Education */
    .education-item {
        padding: 20px;
        background: #f7f9fc;
        border-radius: 8px;
        border-left: 3px solid #667eea;
    }

    .education-icon {
        font-size: 32px;
        margin-bottom: 12px;
    }

    .university-name {
        font-size: 18px;
        font-weight: 700;
        color: #1c1e21;
        margin-bottom: 8px;
    }

    .education-status {
        display: inline-block;
        background: #d4edda;
        color: #155724;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    /* Resume Section */
    .resume-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 24px;
        border-radius: 12px;
        text-align: center;
    }

    .resume-icon {
        font-size: 48px;
        margin-bottom: 12px;
        opacity: 0.95;
    }

    .resume-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .resume-subtitle {
        font-size: 14px;
        opacity: 0.9;
        margin-bottom: 16px;
    }

    .resume-actions {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .resume-btn {
        background: white;
        color: #667eea;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .resume-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    .resume-btn.secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .no-resume {
        text-align: center;
        padding: 32px;
        background: #f7f9fc;
        border-radius: 12px;
        border: 2px dashed #e4e6eb;
    }

    .no-resume-icon {
        font-size: 48px;
        opacity: 0.4;
        margin-bottom: 12px;
    }

    .no-resume-text {
        color: #65676b;
        margin-bottom: 16px;
    }

    /* Contact Grid */
    .contact-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .contact-item {
        padding: 16px;
        background: #f7f9fc;
        border-radius: 8px;
        border-left: 3px solid #667eea;
    }

    .contact-label {
        font-size: 11px;
        text-transform: uppercase;
        color: #65676b;
        font-weight: 700;
        margin-bottom: 6px;
        letter-spacing: 0.5px;
    }

    .contact-value {
        font-size: 15px;
        color: #1c1e21;
        font-weight: 600;
    }

    .contact-value.empty {
        color: #95a5a6;
        font-style: italic;
    }

    /* Sidebar */
    .sidebar-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    }

    .sidebar-title {
        font-size: 16px;
        font-weight: 700;
        color: #1c1e21;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f0f2f5;
    }

    .action-links {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .action-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px;
        color: #1c1e21;
        text-decoration: none;
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 14px;
        font-weight: 500;
        background: transparent;
    }

    .action-link:hover {
        background: #f0f2f5;
    }

    .action-link.logout {
        color: #e74c3c;
        margin-top: 8px;
        border-top: 1px solid #f0f2f5;
        padding-top: 16px;
    }

    .action-link.logout:hover {
        background: #fee;
    }

    .quick-info-item {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f0f2f5;
        font-size: 14px;
    }

    .quick-info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        color: #65676b;
        font-weight: 500;
    }

    .info-value {
        color: #1c1e21;
        font-weight: 700;
    }

    .status-active {
        color: #27ae60;
    }

    /* Forms */
    .edit-mode {
        display: none;
    }

    .edit-mode.active {
        display: block;
    }

    .view-mode.editing {
        display: none;
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: #1c1e21;
        margin-bottom: 8px;
        font-size: 14px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e4e6eb;
        border-radius: 6px;
        font-size: 14px;
        font-family: inherit;
        transition: all 0.2s;
        background: white;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 180px;
        line-height: 1.5;
    }

    .form-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        margin-top: 16px;
    }

    .btn {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }

    .btn-primary {
        background: #667eea;
        color: white;
    }

    .btn-primary:hover {
        background: #5568d3;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary {
        background: #f0f2f5;
        color: #1c1e21;
    }

    .btn-secondary:hover {
        background: #e4e6eb;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.65);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        padding: 32px;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    }

    .modal-header {
        font-size: 24px;
        color: #1c1e21;
        margin-bottom: 20px;
        font-weight: 700;
    }

    .file-upload-area {
        border: 2px dashed #e4e6eb;
        padding: 40px;
        text-align: center;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        margin-bottom: 16px;
        background: #f7f9fc;
    }

    .file-upload-area:hover {
        border-color: #667eea;
        background: white;
    }

    .file-upload-area input[type="file"] {
        display: none;
    }

    .preview-image {
        max-width: 100%;
        max-height: 300px;
        margin-top: 16px;
        border-radius: 8px;
    }

    /* About Content */
    .about-content {
        line-height: 1.7;
        color: #1c1e21;
        font-size: 15px;
    }

    .about-content.empty {
        color: #65676b;
        font-style: italic;
        text-align: center;
        padding: 32px;
        background: #f7f9fc;
        border-radius: 8px;
        border: 2px dashed #e4e6eb;
    }

   /* ============================================
   MOBILE RESPONSIVE CSS
   ============================================ */

/* Tablet & Small Desktop (1024px and below) */
@media (max-width: 1024px) {
    .content-layout {
        grid-template-columns: 1fr;
    }

    .stats-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .completion-banner {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }

    .completion-bar-container {
        width: 100%;
    }

    .completion-percentage {
        margin-left: 0;
        font-size: 20px;
    }
}

/* Large Mobile / Tablet (992px and below) */
@media (max-width: 992px) {
    /* Remove sidebar margin on mobile */
    .main-content-wrapper {
        margin-left: 0;
        width: 100%;
    }

    .profile-container {
        padding: 20px 16px;
    }

    .header-content {
        padding: 0 20px 20px 20px;
    }

    /* Stats grid 2 columns */
    .stats-container {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .stat-card {
        padding: 16px;
    }

    .stat-number {
        font-size: 28px;
    }

    .stat-icon {
        font-size: 24px;
    }

    .profile-meta {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Mobile (768px and below) */
@media (max-width: 768px) {
    /* Container padding */
    .profile-container {
        padding: 16px 12px;
    }

    /* Completion Banner */
    .completion-banner {
        padding: 16px 20px;
        flex-direction: column;
        align-items: stretch;
    }

    .completion-info h3 {
        font-size: 16px;
        margin-bottom: 10px;
    }

    .completion-bar-container {
        width: 100%;
        margin-bottom: 8px;
    }

    .completion-percentage {
        margin-left: 0;
        font-size: 22px;
        text-align: center;
    }

    /* Profile Header */
    /* Profile Header */
    .header-content {
        flex-direction: column;
        gap: 20px;
        padding: 24px 20px;
    }

    .profile-name {
        font-size: 32px;
    }

    .profile-photo-wrapper {
        margin-top: 0;
    }




    .profile-meta {
        grid-template-columns: 1fr;
    }

    .profile-photo-wrapper {
        margin-top: -40px; /* Adjust for shorter banner */
    }

    .profile-photo-container {
        width: 80px;
        height: 80px;
    }

    .change-photo-btn {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }

    .header-content {
        padding: 0 16px 20px 16px;
    }

    .header-info {
        flex-direction: column;
        gap: 16px;
    }

    .profile-details h1 {
        font-size: 22px;
        margin-bottom: 6px;
    }

    .profile-subtitle {
        font-size: 13px;
    }

    .profile-meta {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
    }

    /* Action buttons stack vertically */
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }

    .action-buttons button,
    .action-buttons .btn {
        width: 100%;
        justify-content: center;
    }

    /* Stats - Single column on mobile */
    .stats-container {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .stat-card {
        padding: 16px;
    }

    .stat-number {
        font-size: 28px;
    }

    .stat-label {
        font-size: 10px;
    }

    .stat-icon {
        font-size: 22px;
        margin-left: 12px;
    }

    /* Content Cards */
    .content-card {
        padding: 20px 16px;
        margin-bottom: 16px;
    }

    .card-header h2 {
        font-size: 18px;
    }

    .card-header .edit-btn {
        font-size: 13px;
        padding: 6px 12px;
    }

    /* Contact Grid */
    .contact-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    /* Skills */
    .skills-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }

    .skill-item {
        font-size: 12px;
        padding: 6px 10px;
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 14px;
    }

    .form-group label {
        font-size: 13px;
        margin-bottom: 6px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px;
        font-size: 14px;
    }

    .form-actions {
        flex-direction: column-reverse;
        gap: 8px;
    }

    .form-actions .btn {
        width: 100%;
    }

    /* Sidebar Cards */
    .sidebar-card {
        padding: 16px;
        margin-bottom: 16px;
    }

    .sidebar-card h3 {
        font-size: 16px;
        margin-bottom: 14px;
    }

    /* Quick Actions */
    .action-link {
        padding: 10px 12px;
        font-size: 13px;
    }

    /* Modal */
    .modal-content {
        padding: 24px 20px;
        width: 95%;
        margin: 20px;
    }

    .modal-header {
        font-size: 20px;
        margin-bottom: 16px;
    }

    .file-upload-area {
        padding: 30px 20px;
    }
}

/* Small Mobile (576px and below) */
@media (max-width: 576px) {
    /* Extra compact spacing */
    .profile-container {
        padding: 12px 8px;
    }

    .completion-banner {
        padding: 14px 16px;
    }

    .completion-info h3 {
        font-size: 15px;
    }

    .completion-percentage {
        font-size: 20px;
    }

    /* Header */
    .header-banner {
        height: 70px;
    }

    .profile-photo-wrapper {
        margin-top: -35px;
    }

    .profile-photo-container {
        width: 70px;
        height: 70px;
    }

    .default-avatar {
        font-size: 30px;
    }

    .header-content {
        padding: 0 12px 16px 12px;
    }

    .profile-details h1 {
        font-size: 20px;
    }

    .profile-subtitle {
        font-size: 12px;
    }

    .badge {
        font-size: 11px;
        padding: 3px 8px;
    }

    /* Stats */
    .stat-card {
        padding: 14px 12px;
    }

    .stat-number {
        font-size: 24px;
    }

    .stat-label {
        font-size: 9px;
    }

    .stat-icon {
        font-size: 20px;
    }

    /* Content Cards */
    .content-card {
        padding: 16px 12px;
    }

    .card-header h2 {
        font-size: 17px;
    }

    .card-header .edit-btn {
        font-size: 12px;
        padding: 5px 10px;
    }

    /* Skills - Single column on very small screens */
    .skills-grid {
        grid-template-columns: 1fr;
    }

    .skill-item {
        font-size: 13px;
        padding: 8px 12px;
        justify-content: center;
    }

    /* Contact Info */
    .contact-item {
        font-size: 13px;
        padding: 10px;
    }

    /* Sidebar */
    .sidebar-card {
        padding: 14px 12px;
    }

    .sidebar-card h3 {
        font-size: 15px;
    }

    .quick-info-item {
        padding: 10px 0;
        font-size: 13px;
    }

    /* Modal */
    .modal-content {
        padding: 20px 16px;
    }

    .modal-header {
        font-size: 18px;
    }

    .file-upload-area {
        padding: 24px 16px;
    }

    /* Buttons */
    .btn {
        padding: 10px 16px;
        font-size: 13px;
    }
}

/* Extra Small Phones (480px and below) */
@media (max-width: 480px) {
    .profile-container {
        padding: 10px 6px;
    }

    .profile-details h1 {
        font-size: 18px;
    }

    .stat-number {
        font-size: 22px;
    }

    .card-header h2 {
        font-size: 16px;
    }
}
</style>
</head>
<body>
<div class="dashboard-wrapper">
    <?php include("includes/sidebar.php"); ?>
    <div class="main-content-wrapper">
        <?php 
        $page_title = "Professional Profile";
        ?>

        <div class="profile-container">
        <!-- Profile Completion Banner -->
        <?php if ($completion_percentage < 100): ?>
        <div class="completion-banner">
            <div class="completion-info">
                <h3>Complete Your Profile</h3>
                <div class="completion-bar-container">
                    <div class="completion-bar" style="width: <?= $completion_percentage; ?>%"></div>
                </div>
            </div>
            <div class="completion-percentage"><?= $completion_percentage; ?>%</div>
        </div>
        <?php endif; ?>

        <!-- Professional Profile Header - Full Width -->
        <div class="profile-header">
            <div class="header-content">
                <div class="profile-photo-wrapper">
                    <div class="profile-photo-container">
                        <?php if (!empty($user['photo_url']) && file_exists($user['photo_url'])): ?>
                            <img src="<?= htmlspecialchars($user['photo_url']); ?>" alt="Profile Photo" class="profile-photo">
                        <?php else: ?>
                            <div class="default-avatar">
                                <?= strtoupper(substr($user['name'] ?? $user['email'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button class="change-photo-btn" onclick="openPhotoModal()" title="Change Photo">📷</button>
                </div>

                <div class="header-info">
                    <div class="profile-details">
                        <h1 class="profile-name"><?= htmlspecialchars($user['name'] ?? 'User'); ?></h1>
                        <div class="profile-badges">
                            <span class="badge">
                                <?= $user['role'] === 'worker' ? '👤' : ($user['role'] === 'employer' ? '🏢' : '⚙️'); ?>
                                <?= htmlspecialchars(ucfirst($user['role'])); ?>
                            </span>
                            <?php if ($user['is_student']): ?>
                                <span class="badge student">🎓 Student</span>
                            <?php endif; ?>
                            <?php if ($user['rating']): ?>
                                <span class="badge rating">⭐ <?= number_format($user['rating'], 1); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="profile-meta">
                            <div class="meta-item">
                                <span>📧</span> <?= htmlspecialchars($user['email']); ?>
                            </div>
                            <div class="meta-item">
                                <span>📱</span> <?= htmlspecialchars($user['phone'] ?: 'Not set'); ?>
                            </div>
                            <?php if ($age): ?>
                                <div class="meta-item">
                                    <span>🎂</span> <?= $age; ?> years old
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($user["district"])): ?>
                            <div class="meta-item">
                                <span>📍</span> <?= htmlspecialchars($user["district"]); ?>
                            </div>
                            <?php endif; ?>
                            <div class="meta-item">
                                <span>📅</span> Member since <?= date('M Y', strtotime($user['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Layout -->
        <div class="content-layout">
            <!-- Main Content -->
            <div class="main-content">
                <!-- About Section with Suggestions -->
                <div class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">
                            <span class="section-icon">ℹ️</span>
                            About <?= $user['role'] === 'employer' ? 'Company' : 'Me'; ?>
                        </h2>
                        <button class="edit-btn" onclick="toggleEditMode('about')">Edit</button>
                    </div>

                    <div class="view-mode" id="about-view">
                        <?php if (!empty($user['bio'])): ?>
                            <div class="about-content"><?= nl2br(htmlspecialchars($user['bio'])); ?></div>
                        <?php else: ?>
                            <div class="about-content empty">
                                <strong>No bio added yet</strong><br>
                                Click "Edit" to add information about yourself. Use our templates to get started quickly!
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="edit-mode" id="about-edit">
                        <!-- Bio Template Suggestions -->
                        <div class="bio-suggestions">
                            <div class="suggestions-header">
                                <span class="suggestions-title">💡 Quick Start Templates</span>
                            </div>
                            <div class="suggestion-templates">
                                <?php if ($user['role'] === 'worker'): ?>
                                    <button type="button" class="template-btn" onclick="useBioTemplate(0)">
                                        <span class="template-label">Entry Level Worker</span>
                                        Motivated and eager to learn, I am seeking opportunities to gain practical experience in the hospitality industry. I am a reliable team player with strong communication skills and a positive attitude.
                                    </button>
                                    <button type="button" class="template-btn" onclick="useBioTemplate(1)">
                                        <span class="template-label">Experienced Worker</span>
                                        Experienced professional with <?= $age ? ($age - 18) . '+' : '3+'; ?> years in customer service and operations. Known for reliability, attention to detail, and ability to work effectively in fast-paced environments. Committed to delivering excellent results.
                                    </button>
                                    <?php if ($user['is_student']): ?>
                                    <button type="button" class="template-btn" onclick="useBioTemplate(2)">
                                        <span class="template-label">Student Worker</span>
                                        Currently pursuing my studies at <?= htmlspecialchars($user['university'] ?? '[Your University]'); ?>, seeking flexible part-time opportunities to gain work experience while managing my academic commitments. Quick learner with strong time management skills.
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button type="button" class="template-btn" onclick="useBioTemplate(0)">
                                        <span class="template-label">Company Profile</span>
                                        We are a growing company in the [industry] sector, committed to providing quality [products/services]. We value our employees and offer a supportive work environment with opportunities for growth and development.
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form id="aboutForm" onsubmit="saveSection(event, 'about')">
                            <div class="form-group">
                                <label>About / Bio</label>
                                <textarea name="bio" id="bioTextarea" rows="6" placeholder="Tell employers about yourself, your experience, goals, and what makes you unique..."><?= htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('about')">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Skills Section with Suggestions -->
                <?php if ($user['role'] === 'worker'): ?>
                <div class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">
                            <span class="section-icon">💡</span>
                            Skills & Expertise
                        </h2>
                        <button class="edit-btn" onclick="toggleEditMode('skills')">Edit</button>
                    </div>

                    <div class="view-mode" id="skills-view">
                        <?php if (!empty($skills_array)): ?>
                            <div class="skills-grid">
                                <?php foreach ($skills_array as $skill): ?>
                                    <span class="skill-tag"><?= htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="skills-grid empty">
                                <strong>No skills added yet</strong><br>
                                Click "Edit" to add your skills. We have suggestions to help you get started!
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="edit-mode" id="skills-edit">
                        <!-- Skills Suggestions by Category -->
                        <div class="skills-suggestions">
                            <div class="suggestions-header">
                                <span class="suggestions-title">💡 Popular Skills</span>
                            </div>
                            <div class="skill-categories">
                                <button type="button" class="category-btn active" data-category="general">General</button>
                                <button type="button" class="category-btn" data-category="hospitality">Hospitality</button>
                                <button type="button" class="category-btn" data-category="retail">Retail</button>
                                <button type="button" class="category-btn" data-category="technical">Technical</button>
                            </div>
                            <div class="suggested-skills" id="suggestedSkills">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>

                        <form id="skillsForm" onsubmit="saveSection(event, 'skills')">
                            <div class="form-group">
                                <label>Your Skills</label>
                                <textarea name="skills" id="skillsTextarea" rows="4" placeholder="Click suggestions above or type skills separated by commas"><?= htmlspecialchars($user['skills'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('skills')">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Experience Section -->
                <div class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">
                            <span class="section-icon">💼</span>
                            Work Experience
                        </h2>
                        <button class="edit-btn" onclick="toggleEditMode('experience')">Edit</button>
                    </div>

                    <div class="view-mode" id="experience-view">
                        <?php if (!empty($user['experience'])): ?>
                            <div class="experience-item">
                                <div class="experience-level">
                                    <?php
                                    $exp_labels = [
                                        'entry' => 'Entry Level (0-1 years)',
                                        'junior' => 'Junior Level (1-3 years)',
                                        'intermediate' => 'Intermediate Level (3-5 years)',
                                        'senior' => 'Senior Level (5+ years)'
                                    ];
                                    echo $exp_labels[$user['experience']] ?? htmlspecialchars($user['experience']);
                                    ?>
                                </div>
                                <div class="experience-description">
                                    <?php
                                    $exp_desc = [
                                        'entry' => 'Ready to learn and grow. Eager to start my career and gain valuable work experience.',
                                        'junior' => 'Building practical experience with solid foundation in my field. Growing my skills through hands-on work.',
                                        'intermediate' => 'Proven track record with substantial experience. Capable of working independently and mentoring others.',
                                        'senior' => 'Extensive experience with deep expertise. Skilled at complex challenges and team leadership.'
                                    ];
                                    echo $exp_desc[$user['experience']] ?? 'Professional experience in various roles and industries';
                                    ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="about-content empty">
                                <strong>No experience level specified</strong><br>
                                Add your experience level to help employers understand your background
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="edit-mode" id="experience-edit">
                        <form id="experienceForm" onsubmit="saveSection(event, 'experience')">
                            <div class="form-group">
                                <label>Experience Level</label>
                                <select name="experience">
                                    <option value="">-- Select Experience Level --</option>
                                    <option value="entry" <?= $user['experience'] === 'entry' ? 'selected' : ''; ?>>Entry Level (0-1 years)</option>
                                    <option value="junior" <?= $user['experience'] === 'junior' ? 'selected' : ''; ?>>Junior Level (1-3 years)</option>
                                    <option value="intermediate" <?= $user['experience'] === 'intermediate' ? 'selected' : ''; ?>>Intermediate Level (3-5 years)</option>
                                    <option value="senior" <?= $user['experience'] === 'senior' ? 'selected' : ''; ?>>Senior Level (5+ years)</option>
                                </select>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('experience')">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Education Section -->
                <?php if ($user['is_student']): ?>
<div class="section-card">
    <div class="section-header">
        <h2 class="section-title">
            <span class="section-icon">🎓</span> Education
        </h2>
        <button class="edit-btn" onclick="toggleEditMode('education')">Edit</button>
    </div>

    <div class="view-mode" id="education-view">
        <?php if (!empty($user['university'])): ?>
            <div class="education-item">
                <div class="university-name"><?= htmlspecialchars($user['university']); ?></div>
                <div class="education-meta" style="color: #65676b; font-size: 14px; margin-bottom: 8px;">
                    <strong>Major:</strong> <?= !empty($user['major']) ? htmlspecialchars($user['major']) : 'Not specified'; ?> <br>
                    <strong>Duration:</strong> <?= !empty($user['edu_duration']) ? htmlspecialchars($user['edu_duration']) : 'Not specified'; ?>
                </div>
                <div class="education-status">
                    <?= $user['is_student'] ? '🟢 Currently Studying' : '⚪ Graduated'; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="about-content empty">No education details added.</div>
        <?php endif; ?>
    </div>

<div class="edit-mode" id="education-edit">
    <form id="educationForm" onsubmit="saveSection(event, 'education')">
        <div class="form-group">
            <label>University / School</label>
            <input type="text" name="university" value="<?= htmlspecialchars($user['university'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Major / Course</label>
            <input type="text" name="major" value="<?= htmlspecialchars($user['major'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Duration (e.g., 2021 - 2025)</label>
            <input type="text" name="edu_duration" value="<?= htmlspecialchars($user['edu_duration'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Are you currently a student?</label>
            <select name="is_student">
                <option value="1" <?= ($user['is_student'] == 1) ? 'selected' : ''; ?>>Yes, I am a student</option>
                <option value="0" <?= ($user['is_student'] == 0) ? 'selected' : ''; ?>>No, I have graduated</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="toggleEditMode('education')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>
</div>
                <?php endif; ?>

                <!-- Resume Section -->
                <div class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">
                            <span class="section-icon">📄</span>
                            Resume / CV
                        </h2>
                    </div>

                    <?php if (!empty($user['resume_url']) && file_exists($user['resume_url'])): ?>
                        <div class="resume-card">
                            <div class="resume-icon">📄</div>
                            <div class="resume-title">Resume Available</div>
                            <div class="resume-subtitle">Your resume is ready for employers to review</div>
                            <div class="resume-actions">
                                <a href="<?= htmlspecialchars($user['resume_url']); ?>" class="resume-btn" target="_blank">
                                    <span>👁️</span> View Resume
                                </a>
                                <a href="<?= htmlspecialchars($user['resume_url']); ?>" class="resume-btn secondary" download>
                                    <span>⬇️</span> Download
                                </a>
                                <button onclick="deleteResume()" type="button" class="resume-btn" style="background-color: #dc3545; color: white; border: none;">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-resume">
                            <div class="no-resume-icon">📄</div>
                            <div class="no-resume-text">
                                <strong>No resume uploaded yet</strong><br>
                                Upload your resume to stand out to employers
                            </div>
                            <button class="btn btn-primary" onclick="openResumeModal()">
                                Upload Resume
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Contact Information -->
                <div class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">
                            <span class="section-icon">📞</span>
                            Contact Information
                        </h2>
                        <button class="edit-btn" onclick="toggleEditMode('contact')">Edit</button>
                    </div>

                    <div class="view-mode" id="contact-view">
                        <div class="contact-grid">
                            <div class="contact-item">
                                <div class="contact-label">Full Name</div>
                                <div class="contact-value"><?= htmlspecialchars($user['name'] ?? 'Not set'); ?></div>
                            </div>
                            <div class="contact-item">
                                <div class="contact-label">Email</div>
                                <div class="contact-value"><?= htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div class="contact-item">
                                <div class="contact-label">Phone</div>
                                <div class="contact-value <?= empty($user['phone']) ? 'empty' : ''; ?>">
                                    <?= htmlspecialchars($user['phone'] ?: 'Not provided'); ?>
                                </div>
                            </div>
                            <div class="contact-item">
                                <div class="contact-label">District</div>
                                <div class="contact-value <?= empty($user['district']) ? 'empty' : ''; ?>">
                                    <?= htmlspecialchars($user['district'] ?: 'Not provided'); ?>
                                </div>
                            </div>
                            <div class="contact-item" style="grid-column: 1 / -1;">
                                <div class="contact-label">Full Address</div>
                                <div class="contact-value <?= empty($user['address']) ? 'empty' : ''; ?>">
                                    <?= htmlspecialchars($user['address'] ?: 'Not provided'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="edit-mode" id="contact-edit">
                        <form id="contactForm" onsubmit="saveSection(event, 'contact')">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="010-1234567">
                            </div>
                            <div class="form-group">
                                <label>District</label>
                                <input type="text" name="district" value="<?= htmlspecialchars($user['district'] ?? ''); ?>" placeholder="e.g., Kota Kinabalu">
                            </div>
                            <div class="form-group">
                                <label>Full Address</label>
                                <textarea name="address" rows="3"><?= htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('contact')">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Statistics - Stacked Vertically -->
                <?php if (!empty($stats)): ?>
                <div class="stats-container">
                    <?php if ($user['role'] === 'worker'): ?>
                        <div class="stat-card">
                            <div class="stat-content">
                                <span class="stat-label">Total Applications</span>
                                <div class="stat-number"><?= $stats['total_applications']; ?></div>
                            </div>
                            <div class="stat-icon">📝</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <span class="stat-label">Shortlisted</span>
                                <div class="stat-number"><?= $stats['shortlisted_applications']; ?></div>
                            </div>
                            <div class="stat-icon">⭐</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <span class="stat-label">Accepted</span>
                                <div class="stat-number"><?= $stats['accepted_applications']; ?></div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <span class="stat-label">Pending</span>
                                <div class="stat-number"><?= $stats['pending_applications']; ?></div>
                            </div>
                            <div class="stat-icon">⏳</div>
                        </div>
                    <?php elseif ($user['role'] === 'employer'): ?>
                        <div class="stat-card">
                            <div class="stat-content">
                                <span class="stat-label">Jobs Posted</span>
                                <div class="stat-number"><?= $stats['total_jobs']; ?></div>
                            </div>
                            <div class="stat-icon">💼</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <span class="stat-label">Total Applicants</span>
                                <div class="stat-number"><?= $stats['total_applicants']; ?></div>
                            </div>
                            <div class="stat-icon">👥</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-content">
                                <span class="stat-label">Workers Hired</span>
                                <div class="stat-number"><?= $stats['hired_workers']; ?></div>
                            </div>
                            <div class="stat-icon">✅</div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">Quick Actions</h3>
                    <div class="action-links">
                        <?php if ($user['role'] === 'worker'): ?>
                            <a href="worker_dashboard.php" class="action-link">
                                <span>🔍</span> Browse Jobs
                            </a>
                            <a href="my_applications.php" class="action-link">
                                <span>📋</span> My Applications
                            </a>
                        <?php elseif ($user['role'] === 'employer'): ?>
                            <a href="job_post.php" class="action-link">
                                <span>➕</span> Post New Job
                            </a>
                            <a href="employer_jobs.php" class="action-link">
                                <span>💼</span> Manage Jobs
                            </a>
                        <?php elseif ($user['role'] === 'admin'): ?>
                            <a href="admin_dashboard.php" class="action-link">
                                <span>⚙️</span> Admin Dashboard
                            </a>
                        <?php endif; ?>
                        <a href="backend/logout.php" class="action-link logout">
                            <span>🚪</span> Sign Out
                        </a>
                    </div>
                </div>

                <!-- Account Details -->
                <div class="sidebar-card">
                    <h3 class="sidebar-title">Account Details</h3>
                    <div class="quick-info-item">
                        <span class="info-label">User ID</span>
                        <span class="info-value">#<?= str_pad($user['user_id'], 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="quick-info-item">
                        <span class="info-label">Role</span>
                        <span class="info-value"><?= htmlspecialchars(ucfirst($user['role'])); ?></span>
                    </div>
                    <div class="quick-info-item">
                        <span class="info-label">Member Since</span>
                        <span class="info-value"><?= date('M d, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="quick-info-item">
                        <span class="info-label">Status</span>
                        <span class="info-value status-active">● Active</span>
                    </div>
                </div>

                <!-- Photo Modal -->
                <div class="modal" id="photoModal">
                <div class="modal-content">
                    <h3 class="modal-header">Change Profile Photo</h3>
                    <form id="photoUploadForm" enctype="multipart/form-data">
                    
                        <input type="file" id="photoInput" name="photo" accept="image/*" onchange="previewPhoto(this)">
                        <p style="color: #65676b; font-size: 16px; margin-bottom: 8px;">📷 Click to select a photo</p>
                        <p style="color: #95a5a6; font-size: 13px;">JPG, PNG, GIF or WEBP (Max 2MB)</p>
                        <img id="photoPreview" class="preview-image" style="display: none;">
                    </label>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closePhotoModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Photo</button>
                    </div>
                    </form>
                </div>
                </div>

                <!-- Resume Upload Modal -->
                <div class="modal" id="resumeModal">
                    <div class="modal-content">
                        <h3 class="modal-header">Upload Resume</h3>
                        <form id="resumeUploadForm" enctype="multipart/form-data">
                            <div class="file-upload-area" onclick="document.getElementById('resumeInput').click()">
                                <input type="file" id="resumeInput" name="resume" accept=".pdf,.doc,.docx" onchange="displayResumeFileName(this)">
                                <p style="color: #65676b; font-size: 16px; margin-bottom: 8px;">📄 Click to select your resume</p>
                                <p style="color: #95a5a6; font-size: 13px;">PDF, DOC, DOCX (Max 5MB)</p>
                                <p id="resumeFileName" style="color: #27ae60; font-weight: 700; margin-top: 15px;"></p>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeResumeModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary">Upload Resume</button>
                            </div>
                        </form>
                    </div>  
                </div>


    <script>
        // Skills database by category
        const skillsDatabase = {
            general: ['Communication', 'Teamwork', 'Time Management', 'Problem Solving', 'Leadership', 'Adaptability', 'Work Ethic', 'Attention to Detail', 'Organization', 'Critical Thinking'],
            hospitality: ['Customer Service', 'Food Handling', 'Bartending', 'Housekeeping', 'Front Desk', 'Reservation Management', 'Event Coordination', 'Banquet Service', 'Room Service'],
            retail: ['Sales', 'Cash Handling', 'Inventory Management', 'Product Knowledge', 'Visual Merchandising', 'POS Systems', 'Stock Management', 'Customer Relations'],
            technical: ['Microsoft Office', 'Data Entry', 'Social Media', 'Basic IT', 'Email Management', 'Scheduling Software', 'Point of Sale', 'Inventory Software']
        };

        // Initialize skills suggestions
        function initSkillsSuggestions() {
            const categories = document.querySelectorAll('.category-btn');
            categories.forEach(btn => {
                btn.addEventListener('click', function() {
                    categories.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    displaySkillSuggestions(this.dataset.category);
                });
            });
            displaySkillSuggestions('general');
        }

        function displaySkillSuggestions(category) {
            const container = document.getElementById('suggestedSkills');
            const skills = skillsDatabase[category];
            const currentSkills = document.getElementById('skillsTextarea').value.split(',').map(s => s.trim().toLowerCase());
            
            container.innerHTML = skills.map(skill => {
                const isAdded = currentSkills.includes(skill.toLowerCase());
                return `<span class="skill-suggestion ${isAdded ? 'added' : ''}" onclick="addSkill('${skill}')">
                    ${isAdded ? '✓' : '+'} ${skill}
                </span>`;
            }).join('');
        }

        function addSkill(skill) {
            const textarea = document.getElementById('skillsTextarea');
            const skills = textarea.value.split(',').map(s => s.trim()).filter(s => s);
            const skillLower = skill.toLowerCase();
            
            // Check if skill already exists (case insensitive)
            const exists = skills.some(s => s.toLowerCase() === skillLower);
            
            if (!exists) {
                skills.push(skill);
                textarea.value = skills.join(', ');
            } else {
                // Remove skill if already added
                const filtered = skills.filter(s => s.toLowerCase() !== skillLower);
                textarea.value = filtered.join(', ');
            }
            
            // Refresh suggestions to update visual state
            const activeCategory = document.querySelector('.category-btn.active').dataset.category;
            displaySkillSuggestions(activeCategory);
        }

        // Bio templates
        function useBioTemplate(index) {
            const templates = [
                `Motivated and eager to learn, I am seeking opportunities to gain practical experience in the hospitality industry. I am a reliable team player with strong communication skills and a positive attitude.`,
                `Experienced professional with <?= $age ? ($age - 18) . '+' : '3+'; ?> years in customer service and operations. Known for reliability, attention to detail, and ability to work effectively in fast-paced environments. Committed to delivering excellent results.`,
                `Currently pursuing my studies at <?= htmlspecialchars($user['university'] ?? '[Your University]'); ?>, seeking flexible part-time opportunities to gain work experience while managing my academic commitments. Quick learner with strong time management skills.`
            ];
            
            document.getElementById('bioTextarea').value = templates[index];
        }

        // Edit mode functions
        function toggleEditMode(section) {
            const viewMode = document.getElementById(section + '-view');
            const editMode = document.getElementById(section + '-edit');
            
            viewMode.classList.toggle('editing');
            editMode.classList.toggle('active');
            
            // Initialize skills suggestions when opening skills edit mode
            if (section === 'skills' && editMode.classList.contains('active')) {
                initSkillsSuggestions();
            }
        }

        function cancelEdit(section) {
            const viewMode = document.getElementById(section + '-view');
            const editMode = document.getElementById(section + '-edit');
            
            viewMode.classList.remove('editing');
            editMode.classList.remove('active');
        }

        function saveSection(event, section) {
            event.preventDefault();
            const form = document.getElementById(section + 'Form');
            const formData = new FormData(form);
            formData.append('section', section);

            fetch('backend/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text()) // Changed to .text() temporarily to debug
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Backend Error: ' + data.message);
                    }
                } catch (e) {
                    console.error('Server returned non-JSON:', text);
                    alert('Server Error: Check Console');
                }
            });
        }

        // Photo modal functions
        function openPhotoModal() {
            document.getElementById('photoModal').classList.add('active');
        }

        function closePhotoModal() {
            document.getElementById('photoModal').classList.remove('active');
            document.getElementById('photoInput').value = '';
            document.getElementById('photoPreview').style.display = 'none';
        }

        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                const preview = document.getElementById('photoPreview');
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Resume modal functions
        function openResumeModal() {
            document.getElementById('resumeModal').classList.add('active');
        }

        function closeResumeModal() {
            document.getElementById('resumeModal').classList.remove('active');
            document.getElementById('resumeInput').value = '';
            document.getElementById('resumeFileName').textContent = '';
        }

        function displayResumeFileName(input) {
            const fileName = document.getElementById('resumeFileName');
            if (input.files && input.files[0]) {
                fileName.textContent = '✓ ' + input.files[0].name;
            } else {
                fileName.textContent = '';
            }
        }

        // Form submissions
        document.getElementById('photoUploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('user_id', <?= $user_id; ?>);

            try {
                const response = await fetch('backend/upload_photo.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Photo uploaded successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
                console.error(error);
            }
        });

        document.getElementById('resumeUploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('resumeInput');
            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select a file to upload');
                return;
            }

            const file = fileInput.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            // Validate file size
            if (file.size > maxSize) {
                alert('File size exceeds 5MB limit. Please choose a smaller file.');
                return;
            }

            // Validate file type
            const allowedExtensions = ['pdf', 'doc', 'docx'];
            const extension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedExtensions.includes(extension)) {
                alert('Invalid file type. Please upload a PDF, DOC, or DOCX file.');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('user_id', <?= $user_id; ?>);

            // Show loading state
            const submitBtn = this.querySelector('.btn-primary');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Uploading...';
            submitBtn.disabled = true;

            try {
                const response = await fetch('backend/upload_resume.php', {
                    method: 'POST',
                    body: formData
                });

                // Check if response is JSON
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned invalid response. Please check error logs.');
                }

                const result = await response.json();

                if (result.success) {
                    alert('Resume uploaded successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('An error occurred: ' + error.message);
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        });

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
        // Function to handle Resume Deletion
function deleteResume() {
    if (!confirm("Are you sure you want to permanently delete your resume?")) {
        return;
    }

    // Show loading state (optional)
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
    btn.disabled = true;

    fetch('backend/delete_resume.php', { // Ensure this path points to where you saved delete_resume.php
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Resume deleted successfully!');
            location.reload(); // Refresh to show the "Upload" button again
        } else {
            alert('Error: ' + data.message);
            // Reset button if error
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
    </script>
        </div>
    </div>
</div>
</body>
</html>