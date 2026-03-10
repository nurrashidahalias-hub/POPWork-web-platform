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
               p.date_of_birth, p.gender, p.district, p.avg_rating, p.total_reviews
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
   ACCESS & VIEW LOGIC
===================== */
$is_own_profile = ($profile_user_id === $viewer_id);
$is_viewing_worker = ($user['role'] === 'worker');
$skills_array = !empty($user['skills']) ? array_filter(array_map('trim', explode(',', $user['skills']))) : [];

$age = null;
if (!empty($user['date_of_birth'])) {
    try {
        $dob = new DateTime($user['date_of_birth']);
        $age = (new DateTime())->diff($dob)->y;
    } catch (Exception $e) { $age = null; }
}

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

/* =====================
   FETCH FEEDBACK/REVIEWS
===================== */
$feedback_sql = "SELECT f.*, 
                        j.title as job_title,
                        reviewer.name as reviewer_name,
                        reviewer_prof.photo_url as reviewer_photo
                 FROM feedback f
                 JOIN jobs j ON f.job_id = j.job_id
                 JOIN users reviewer ON f.reviewer_id = reviewer.user_id
                 LEFT JOIN profiles reviewer_prof ON f.reviewer_id = reviewer_prof.user_id
                 WHERE f.reviewee_id = ? AND f.is_visible = 1
                 ORDER BY f.created_at DESC";

$feedback_stmt = $conn->prepare($feedback_sql);
$feedback_stmt->bind_param("i", $profile_user_id);
$feedback_stmt->execute();
$feedbacks = $feedback_stmt->get_result();

// Calculate rating breakdown
$rating_breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$total_ratings = 0;
$feedbacks->data_seek(0);
while ($fb = $feedbacks->fetch_assoc()) {
    $rating_floor = floor($fb['rating']);
    if (isset($rating_breakdown[$rating_floor])) {
        $rating_breakdown[$rating_floor]++;
    }
    $total_ratings++;
}
$feedbacks->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['name']); ?> | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Poppins', sans-serif; color: #1c1e21; line-height: 1.5; }
        header { background: white; padding: 15px 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        .header-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: flex-start; align-items: center; padding-left: 100px; }
        .logo-container { display: flex; align-items: center;}

        .logo-text {
        font-size: 24px;
        font-weight: 700;
        background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        }

        nav a { text-decoration: none; color: #333; font-weight: 600; padding: 8px 16px; transition: 0.3s; }
        
        .profile-container { max-width: 1200px; margin: 0 auto; padding: 40px 24px; }
        .back-button { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 24px; padding: 10px 20px; background: white; color: #8a1538; text-decoration: none; border-radius: 8px; font-weight: 600; border: 2px solid #8a1538; }
        
        .profile-header { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.12); margin-bottom: 24px; }
        .header-banner { height: 60px; background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%); }
        .header-content { padding: 0 30px 24px 30px; position: relative; }
        .profile-photo-wrapper { margin-top: -50px; margin-bottom: 16px; width: 100px; }
        .profile-photo-container { width: 100px; height: 100px; border-radius: 50%; overflow: hidden; background: #f0f2f5; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .profile-photo { width: 100%; height: 100%; object-fit: cover; }
        .default-avatar { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 40px; background: #8a1538; color: white; }
        
        .student-tag { background: #ffd700; color: #000; padding: 2px 10px; border-radius: 5px; font-size: 12px; font-weight: 700; vertical-align: middle; margin-left: 10px; text-transform: uppercase; }

        /* Rating Display */
        .rating-display {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            padding: 8px 16px;
            background: #fff9e6;
            border-radius: 20px;
            border: 2px solid #ffd700;
        }
        
        .rating-stars {
            color: #ffd700;
            font-size: 18px;
            letter-spacing: 2px;
        }
        
        .rating-number {
            font-weight: 700;
            color: #333;
            font-size: 18px;
        }
        
        .rating-count {
            color: #666;
            font-size: 14px;
        }

        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.12); border-left: 4px solid #8a1538; }
        
        .content-layout { display: grid; grid-template-columns: 1fr 320px; gap: 24px; }
        .section-card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.12); }
        .section-card h2 { font-size: 20px; margin-bottom: 15px; border-bottom: 2px solid #f0f2f5; padding-bottom: 10px; }
        
        .skill-tag { background: #e7f3ff; color: #1876f2; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; margin: 4px; border: 1px solid #1876f2; }
        .resume-btn { display: inline-flex; align-items: center; gap: 10px; background: #8a1538; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.3s; }

        /* FEEDBACK SECTION STYLES */
        .rating-overview {
            background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            border: 2px solid #ffd700;
        }
        
        .rating-summary {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 30px;
            align-items: center;
        }
        
        .rating-main {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
        }
        
        .rating-main-number {
            font-size: 56px;
            font-weight: 700;
            color: #8a1538;
            line-height: 1;
        }
        
        .rating-main-stars {
            font-size: 24px;
            color: #ffd700;
            margin: 10px 0;
        }
        
        .rating-main-count {
            color: #666;
            font-size: 14px;
        }
        
        .rating-bars {
            flex: 1;
        }
        
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .rating-bar-label {
            min-width: 60px;
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }
        
        .rating-bar-container {
            flex: 1;
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .rating-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #8a1538 0%, #c91f4d 100%);
            border-radius: 5px;
            transition: width 0.3s ease;
        }
        
        .rating-bar-count {
            min-width: 40px;
            text-align: right;
            font-size: 13px;
            color: #666;
        }
        
        .feedback-item {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            border-left: 4px solid #8a1538;
        }
        
        .feedback-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .reviewer-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }
        
        .reviewer-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #8a1538;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            border: 2px solid #e9ecef;
        }
        
        .feedback-info {
            flex: 1;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .feedback-job {
            color: #666;
            font-size: 13px;
            margin-top: 2px;
        }
        
        .feedback-date {
            color: #999;
            font-size: 12px;
        }
        
        .feedback-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0;
        }
        
        .feedback-stars {
            color: #ffd700;
            font-size: 18px;
        }
        
        .feedback-rating-number {
            font-weight: 700;
            color: #333;
        }
        
        .feedback-comment {
            color: #444;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .detailed-ratings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detailed-rating-item {
            text-align: center;
        }
        
        .detailed-rating-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .detailed-rating-value {
            font-weight: 700;
            color: #8a1538;
            font-size: 16px;
        }
        
        .no-reviews {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-reviews-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .sidebar-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.12); }
        footer { background: white; padding: 30px; text-align: center; margin-top: 60px; color: #666; }

        @media (max-width: 1024px) { .content-layout { grid-template-columns: 1fr; } }
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

<div class="profile-container">
    <a href="javascript:history.back()" class="back-button">← Back</a>

    <div class="profile-header">
        <div class="header-banner"></div>
        <div class="header-content">
            <div class="profile-photo-wrapper">
                <div class="profile-photo-container">
                    <?php if (!empty($user['photo_url'])): ?>
                        <img src="<?= '../' . htmlspecialchars($user['photo_url']); ?>" class="profile-photo">
                    <?php else: ?>
                        <div class="default-avatar"><?= strtoupper(substr($user['name'], 0, 1)); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <h1>
                <?= htmlspecialchars($user['name']); ?>
                <?php if ($user['is_student']): ?>
                    <span class="student-tag">Student</span>
                <?php endif; ?>
            </h1>
            <p class="profile-subtitle">
                <?= ucfirst(htmlspecialchars($user['role'])); ?> 
                <?= $user['district'] ? "• ".htmlspecialchars($user['district']) : "" ?>
                <?= $age ? "• $age years old" : "" ?>
            </p>
            
            <?php if (!empty($user['avg_rating']) && $user['total_reviews'] > 0): ?>
            <div class="rating-display">
                <span class="rating-stars">
                    <?php
                    $full_stars = floor($user['avg_rating']);
                    $half_star = ($user['avg_rating'] - $full_stars) >= 0.5;
                    for ($i = 0; $i < $full_stars; $i++) echo '⭐';
                    if ($half_star) echo '⭐';
                    ?>
                </span>
                <span class="rating-number"><?= number_format($user['avg_rating'], 1); ?></span>
                <span class="rating-count">(<?= $user['total_reviews']; ?> review<?= $user['total_reviews'] != 1 ? 's' : ''; ?>)</span>
            </div>
            <?php endif; ?>

            <?php if ($is_own_profile): ?>
                <a href="edit_profile.php" style="display:inline-block; margin-top:15px; padding:10px 25px; background:#8a1538; color:white; text-decoration:none; border-radius:8px; font-weight:600;">Edit Profile</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_own_profile && $stats): ?>
    <div class="stats-container">
        <?php if ($user['role'] === 'worker'): ?>
            <div class="stat-card"><h3><?= $stats['completed_jobs']; ?></h3><p>Jobs Completed</p></div>
            <div class="stat-card"><h3><?= number_format($user['avg_rating'] ?? 0, 1); ?> ⭐</h3><p>Average Rating</p></div>
        <?php else: ?>
            <div class="stat-card"><h3><?= $stats['active_jobs']; ?></h3><p>Active Job Posts</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="content-layout">
        <div class="main-content">
            
            <div class="section-card">
                <h2>About</h2>
                <p><?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : "<em>No bio provided</em>"; ?></p>
            </div>

            <?php if (!empty($user['university'])): ?>
            <div class="section-card">
                <h2>Education</h2>
                <p><strong>University:</strong> <?= htmlspecialchars($user['university']); ?></p>
                <?php if ($user['is_student']): ?>
                    <p style="color: #666; font-size: 14px;">Currently pursuing studies</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($is_viewing_worker): ?>
            <div class="section-card">
                <h2>Skills & Expertise</h2>
                <?php if ($skills_array): ?>
                    <?php foreach ($skills_array as $skill): ?>
                        <span class="skill-tag"><?= htmlspecialchars($skill); ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><em>No skills listed</em></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="section-card">
                <h2>Work Experience</h2>
                <p><?= nl2br(htmlspecialchars($user['experience'] ?: 'Not specified')); ?></p>
            </div>

            <?php if ($is_viewing_worker): ?>
            <div class="section-card">
                <h2>Resume / CV</h2>
                <?php if (!empty($user['resume_url'])): ?>
                    <p style="margin-bottom: 15px;">Click the button below to view the official resume for this candidate.</p>
                    <a href="<?= '../' . htmlspecialchars($user['resume_url']); ?>" target="_blank" class="resume-btn">
                        📄 View Full Resume
                    </a>
                <?php else: ?>
                    <p><em>No resume has been uploaded yet.</em></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- REVIEWS & RATINGS SECTION -->
            <div class="section-card">
                <h2>⭐ Reviews & Ratings</h2>
                
                <?php if ($total_ratings > 0): ?>
                    <div class="rating-overview">
                        <div class="rating-summary">
                            <div class="rating-main">
                                <div class="rating-main-number"><?= number_format($user['avg_rating'], 1); ?></div>
                                <div class="rating-main-stars">
                                    <?php
                                    $full_stars = floor($user['avg_rating']);
                                    $half_star = ($user['avg_rating'] - $full_stars) >= 0.5;
                                    for ($i = 0; $i < $full_stars; $i++) echo '⭐';
                                    if ($half_star) echo '⭐';
                                    ?>
                                </div>
                                <div class="rating-main-count"><?= $total_ratings; ?> review<?= $total_ratings != 1 ? 's' : ''; ?></div>
                            </div>
                            
                            <div class="rating-bars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <?php 
                                    $count = $rating_breakdown[$i];
                                    $percentage = $total_ratings > 0 ? ($count / $total_ratings) * 100 : 0;
                                    ?>
                                    <div class="rating-bar-item">
                                        <div class="rating-bar-label"><?= $i; ?> Star<?= $i != 1 ? 's' : ''; ?></div>
                                        <div class="rating-bar-container">
                                            <div class="rating-bar-fill" style="width: <?= $percentage; ?>%"></div>
                                        </div>
                                        <div class="rating-bar-count"><?= $count; ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Individual Feedback -->
                    <?php while ($feedback = $feedbacks->fetch_assoc()): ?>
                        <div class="feedback-item">
                            <div class="feedback-header">
                                <?php if (!empty($feedback['reviewer_photo'])): ?>
                                    <img src="../<?= htmlspecialchars($feedback['reviewer_photo']); ?>" class="reviewer-photo" alt="Reviewer">
                                <?php else: ?>
                                    <div class="reviewer-placeholder">
                                        <?= strtoupper(substr($feedback['reviewer_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="feedback-info">
                                    <div class="reviewer-name"><?= htmlspecialchars($feedback['reviewer_name']); ?></div>
                                    <div class="feedback-job">
                                        <?= ucfirst($feedback['reviewer_role']); ?> • <?= htmlspecialchars($feedback['job_title']); ?>
                                    </div>
                                    <div class="feedback-date">
                                        <?= date('F j, Y', strtotime($feedback['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="feedback-rating">
                                <span class="feedback-stars">
                                    <?php
                                    $rating = $feedback['rating'];
                                    $full_stars = floor($rating);
                                    $half_star = ($rating - $full_stars) >= 0.5;
                                    for ($i = 0; $i < $full_stars; $i++) echo '⭐';
                                    if ($half_star) echo '⭐';
                                    ?>
                                </span>
                                <span class="feedback-rating-number"><?= number_format($feedback['rating'], 1); ?> / 5.0</span>
                            </div>
                            
                            <div class="feedback-comment">
                                <?= nl2br(htmlspecialchars($feedback['comment'])); ?>
                            </div>
                            
                            <div class="detailed-ratings">
                                <div class="detailed-rating-item">
                                    <div class="detailed-rating-label">Professionalism</div>
                                    <div class="detailed-rating-value"><?= $feedback['professionalism_rating']; ?>/5</div>
                                </div>
                                <div class="detailed-rating-item">
                                    <div class="detailed-rating-label">Communication</div>
                                    <div class="detailed-rating-value"><?= $feedback['communication_rating']; ?>/5</div>
                                </div>
                                <div class="detailed-rating-item">
                                    <div class="detailed-rating-label">Quality</div>
                                    <div class="detailed-rating-value"><?= $feedback['quality_rating']; ?>/5</div>
                                </div>
                                <div class="detailed-rating-item">
                                    <div class="detailed-rating-label">Punctuality</div>
                                    <div class="detailed-rating-value"><?= $feedback['punctuality_rating']; ?>/5</div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    
                <?php else: ?>
                    <div class="no-reviews">
                        <div class="no-reviews-icon">💬</div>
                        <h3>No Reviews Yet</h3>
                        <p>This user hasn't received any reviews yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <h2>Contact Information</h2>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']); ?></p>
                <?php if(!empty($user['phone'])): ?>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone']); ?></p>
                <?php endif; ?>
                <?php if(!empty($user['address'])): ?>
                    <p><strong>Address:</strong> <?= htmlspecialchars($user['address']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar">
            <?php if ($is_own_profile): ?>
                <div class="sidebar-card">
                    <h3>Quick Actions</h3>
                    <ul style="list-style:none; padding-top:10px;">
                        <li style="margin-bottom:10px;"><a href="edit_profile.php" style="text-decoration:none; color:#8a1538;">✏️ Update Profile</a></li>
                        <li><a href="settings.php" style="text-decoration:none; color:#8a1538;">⚙️ Account Settings</a></li>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="sidebar-card">
                <h3>Account Info</h3>
                <p style="font-size: 14px; color: #666;">Member since: <br><strong><?= date('F Y', strtotime($user['created_at'])); ?></strong></p>
                <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
                <p style="font-size: 14px; color: #666;">Status: <br><strong style="color: green;">✓ Verified User</strong></p>
            </div>
        </div>
    </div>
</div>

<footer><p>© 2025 POP!Work | Designed by NUR RASHIDAH BINTI ALIAS</p></footer>

</body>
</html>