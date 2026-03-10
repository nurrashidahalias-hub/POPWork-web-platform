<?php
session_start();
include("db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'worker') {
    header("Location: login.html");
    exit();
}

$worker_id = $_SESSION['user_id'];

// Get job ID
if (!isset($_GET['job_id'])) {
    echo "<script>alert('Invalid job.'); window.location='worker_dashboard.php';</script>";
    exit();
}

$job_id = intval($_GET['job_id']);

// Fetch job details with employer info
$sql = "SELECT j.*, 
               u.email as employer_email,
               p.name as employer_name, p.photo_url as employer_photo, 
               p.bio as employer_bio, p.avg_rating, p.total_reviews
        FROM jobs j
        JOIN users u ON j.employer_id = u.user_id
        LEFT JOIN profiles p ON j.employer_id = p.user_id
        WHERE j.job_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    echo "<script>alert('Job not found.'); window.location='worker_dashboard.php';</script>";
    exit();
}

// Check if worker has already applied
$check_sql = "SELECT application_id, status FROM applications WHERE job_id = ? AND user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $job_id, $worker_id);
$check_stmt->execute();
$application = $check_stmt->get_result()->fetch_assoc();
$has_applied = !empty($application);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($job['title']); ?> | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Poppins', sans-serif; 
            color: #1c1e21; 
            line-height: 1.6;
            padding-bottom: 40px;
        }
        
        header { 
            background: white; 
            padding: 15px 40px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
        }
        
        .header-container { 
            max-width: 1400px; 
            margin: 0 auto; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .logo-container { display: flex; align-items: center; gap: 10px; }
        
        .logo-text {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            padding: 10px 20px;
            background: white;
            color: #8a1538;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            border: 2px solid #8a1538;
            transition: 0.3s;
        }
        
        .back-button:hover {
            background: #8a1538;
            color: white;
        }
        
        .job-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
        }
        
        .job-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .job-header {
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            color: white;
            padding: 40px;
        }
        
        .job-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 15px;
            opacity: 0.95;
        }
        
        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .job-content {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 10px;
        }
        
        .job-description {
            color: #555;
            white-space: pre-wrap;
            line-height: 1.8;
        }
        
        .apply-button {
            display: block;
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            text-align: center;
            text-decoration: none;
        }
        
        .apply-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(138, 21, 56, 0.3);
        }
        
        .apply-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .applied-badge {
            display: block;
            width: 100%;
            padding: 18px;
            background: #e7f3ff;
            color: #1876f2;
            border: 2px solid #1876f2;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
        }
        
        /* Employer Card */
        .employer-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            position: sticky;
            top: 100px;
        }
        
        .employer-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
        }
        
        .employer-photo-wrapper {
            margin-bottom: 15px;
        }
        
        .employer-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #8a1538;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .employer-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #8a1538;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 700;
            border: 4px solid #8a1538;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .employer-name {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .employer-role {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .employer-rating {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #fff9e6;
            border-radius: 20px;
            border: 2px solid #ffd700;
        }
        
        .rating-stars {
            color: #ffd700;
            font-size: 16px;
        }
        
        .rating-number {
            font-weight: 700;
            color: #333;
        }
        
        .employer-bio {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f2f5;
            color: #555;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .view-profile-btn {
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: #8a1538;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .view-profile-btn:hover {
            background: #6d1028;
            transform: translateY(-2px);
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            color: #333;
            font-weight: 600;
        }
        
        @media (max-width: 1024px) {
            .job-layout {
                grid-template-columns: 1fr;
            }
            
            .employer-card {
                position: static;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-container">
        <div class="logo-container">
            <img src="../assets/images/logo.png" alt="POP!Work Logo" height="50">
            <span class="logo-text">POP!Work</span>
        </div>
    </div>
</header>

<div class="container">
    <a href="worker_dashboard.php" class="back-button">← Back to Jobs</a>
    
    <div class="job-layout">
        <div class="job-card">
            <div class="job-header">
                <h1 class="job-title"><?= htmlspecialchars($job['title']); ?></h1>
                <div class="job-meta">
                    <div class="job-meta-item">
                        📍 <?= htmlspecialchars($job['location']); ?>
                    </div>
                    <div class="job-meta-item">
                        💰 RM <?= number_format($job['pay_rate'], 2); ?> <?= htmlspecialchars($job['pay_period'] ?? 'per hour'); ?>
                    </div>
                    <div class="job-meta-item">
                        📋 <?= htmlspecialchars($job['category']); ?>
                    </div>
                    <div class="job-meta-item">
                        📅 Posted <?= date('M j, Y', strtotime($job['created_at'])); ?>
                    </div>
                </div>
            </div>
            
            <div class="job-content">
                <div class="section">
                    <h3>Job Description</h3>
                    <div class="job-description"><?= nl2br(htmlspecialchars($job['description'])); ?></div>
                </div>
                
                <?php if (!empty($job['experience_level'])): ?>
                <div class="section">
                    <h3>Required Experience</h3>
                    <p><?= htmlspecialchars($job['experience_level']); ?> Level</p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($job['job_duration'])): ?>
                <div class="section">
                    <h3>Job Duration</h3>
                    <p><?= htmlspecialchars($job['job_duration']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($job['languages_required'])): ?>
                <div class="section">
                    <h3>Languages Required</h3>
                    <p><?= htmlspecialchars($job['languages_required']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="section" style="margin-top: 40px;">
                    <?php if ($has_applied): ?>
                        <div class="applied-badge">
                            ✓ Applied • Status: <?= htmlspecialchars($application['status']); ?>
                        </div>
                    <?php else: ?>
                        <form action="backend/apply_job.php" method="POST">
                            <input type="hidden" name="job_id" value="<?= $job_id; ?>">
                            <button type="submit" class="apply-button">Apply for this Job</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Employer Info Sidebar -->
        <div class="employer-card">
            <div class="employer-header">
                <div class="employer-photo-wrapper">
                    <?php if (!empty($job['employer_photo'])): ?>
                        <img src="../<?= htmlspecialchars($job['employer_photo']); ?>" class="employer-photo" alt="Employer">
                    <?php else: ?>
                        <div class="employer-placeholder">
                            <?= strtoupper(substr($job['employer_name'] ?? 'E', 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="employer-name"><?= htmlspecialchars($job['employer_name'] ?? 'Employer'); ?></div>
                <div class="employer-role">Job Poster</div>
                
                <?php if (!empty($job['avg_rating']) && $job['total_reviews'] > 0): ?>
                <div class="employer-rating">
                    <span class="rating-stars">
                        <?php
                        $full_stars = floor($job['avg_rating']);
                        for ($i = 0; $i < $full_stars; $i++) echo '⭐';
                        ?>
                    </span>
                    <span class="rating-number"><?= number_format($job['avg_rating'], 1); ?></span>
                    <span style="color: #666; font-size: 13px;">(<?= $job['total_reviews']; ?>)</span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($job['employer_bio'])): ?>
            <div class="employer-bio">
                <strong>About:</strong><br>
                <?= nl2br(htmlspecialchars(substr($job['employer_bio'], 0, 150))); ?>
                <?= strlen($job['employer_bio']) > 150 ? '...' : ''; ?>
            </div>
            <?php endif; ?>
            
            <a href="view_profile.php?user_id=<?= $job['employer_id']; ?>" class="view-profile-btn">
                👤 View Full Profile
            </a>
            
            <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #f0f2f5;">
                <div class="info-item">
                    <span class="info-label">Positions:</span>
                    <span class="info-value"><?= $job['positions_available'] ?? 1; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value" style="color: #10b981;">✓ Active</span>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>