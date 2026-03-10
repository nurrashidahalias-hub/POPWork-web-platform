<?php
session_start();
include("backend/db_connect.php");

// Get user ID from URL
$view_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($view_user_id <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch user info
$user_sql = "SELECT u.*, p.* FROM users u 
             JOIN profiles p ON u.user_id = p.user_id 
             WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $view_user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows == 0) {
    header("Location: index.php");
    exit();
}

$user = $user_result->fetch_assoc();

// Fetch all feedback
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
                 ORDER BY f.created_at DESC";

$feedback_stmt = $conn->prepare($feedback_sql);
$feedback_stmt->bind_param("i", $view_user_id);
$feedback_stmt->execute();
$feedbacks = $feedback_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews for <?= htmlspecialchars($user['name']); ?> | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #8a1538;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            padding: 10px 16px;
            background: white;
            border-radius: 8px;
            transition: 0.3s;
        }
        
        .back-link:hover {
            background: #8a1538;
            color: white;
        }
        
        .user-header {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .user-photo-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #8a1538;
            margin-bottom: 20px;
        }
        
        .user-placeholder-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8a1538, #c91f4d);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            border: 5px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .user-header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 15px;
        }
        
        .rating-display-large {
            display: inline-flex;
            align-items: center;
            gap: 15px;
            padding: 15px 30px;
            background: linear-gradient(135deg, #fff9e6, #fffbf0);
            border-radius: 16px;
            border: 3px solid #ffd700;
        }
        
        .rating-number-huge {
            font-size: 48px;
            font-weight: 700;
            color: #333;
        }
        
        .rating-stars-large {
            color: #ffd700;
            font-size: 32px;
        }
        
        .review-count {
            margin-top: 10px;
            font-size: 16px;
            color: #666;
        }
        
        .feedback-grid {
            display: grid;
            gap: 24px;
        }
        
        .feedback-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: 0.3s;
        }
        
        .feedback-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .feedback-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .reviewer-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
        }
        
        .reviewer-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8a1538, #c91f4d);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            border: 3px solid #e9ecef;
        }
        
        .reviewer-info h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 4px;
        }
        
        .reviewer-info p {
            font-size: 14px;
            color: #666;
        }
        
        .job-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #e7f3ff;
            color: #1876f2;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .rating-display {
            margin-bottom: 15px;
        }
        
        .feedback-stars {
            color: #ffd700;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .detailed-ratings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .rating-item {
            padding: 10px 14px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .rating-item-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .rating-item-stars {
            color: #ffd700;
            font-size: 14px;
        }
        
        .feedback-comment {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 10px;
            border-left: 4px solid #8a1538;
            margin-bottom: 12px;
        }
        
        .comment-text {
            color: #333;
            line-height: 1.6;
            font-size: 15px;
        }
        
        .feedback-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 13px;
            color: #999;
            flex-wrap: wrap;
        }
        
        .would-work-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .would-work-yes {
            background: #d4edda;
            color: #155724;
        }
        
        .would-work-no {
            background: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            background: white;
            border-radius: 20px;
            padding: 80px 40px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #666;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="view_profile.php?user_id=<?= $view_user_id; ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
        
        <!-- User Header -->
        <div class="user-header">
            <?php if (!empty($user['photo_url'])): ?>
                <img src="<?= htmlspecialchars($user['photo_url']); ?>" class="user-photo-large" alt="Profile">
            <?php else: ?>
                <div class="user-placeholder-large">
                    <?= strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            
            <h1><?= htmlspecialchars($user['name']); ?>'s Reviews</h1>
            
            <?php if ($user['avg_rating']): ?>
            <div class="rating-display-large">
                <span class="rating-number-huge"><?= number_format($user['avg_rating'], 1); ?></span>
                <div>
                    <div class="rating-stars-large">
                        <?php for($i = 0; $i < floor($user['avg_rating']); $i++) echo '⭐'; ?>
                    </div>
                </div>
            </div>
            <div class="review-count">
                Based on <?= $user['total_ratings']; ?> review<?= $user['total_ratings'] > 1 ? 's' : ''; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- All Feedback -->
        <div class="feedback-grid">
            <?php if ($feedbacks->num_rows > 0): ?>
                <?php while ($fb = $feedbacks->fetch_assoc()): ?>
                <div class="feedback-card">
                    <div class="feedback-header">
                        <?php if (!empty($fb['reviewer_photo'])): ?>
                            <img src="<?= htmlspecialchars($fb['reviewer_photo']); ?>" class="reviewer-photo" alt="Reviewer">
                        <?php else: ?>
                            <div class="reviewer-placeholder">
                                <?= strtoupper(substr($fb['reviewer_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="reviewer-info">
                            <h3><?= htmlspecialchars($fb['reviewer_name']); ?></h3>
                            <p>📋 <?= htmlspecialchars($fb['job_title']); ?> • <span class="job-badge"><?= htmlspecialchars($fb['job_category']); ?></span></p>
                        </div>
                    </div>
                    
                    <div class="rating-display">
                        <div class="feedback-stars">
                            <?php for($i = 0; $i < $fb['rating']; $i++) echo '⭐'; ?>
                        </div>
                        <strong style="color: #333; font-size: 18px;"><?= $fb['rating']; ?>/5</strong>
                    </div>
                    
                    <!-- Detailed Ratings -->
                    <?php if ($fb['work_quality'] || $fb['reliability'] || $fb['professionalism'] || 
                              $fb['job_accuracy'] || $fb['payment_timeliness'] || $fb['communication']): ?>
                    <div class="detailed-ratings">
                        <?php if ($fb['work_quality']): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Work Quality</div>
                            <span class="rating-item-stars"><?php for($i=0; $i<$fb['work_quality']; $i++) echo '⭐'; ?></span>
                            <strong> <?= $fb['work_quality']; ?>/5</strong>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($fb['reliability']): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Reliability</div>
                            <span class="rating-item-stars"><?php for($i=0; $i<$fb['reliability']; $i++) echo '⭐'; ?></span>
                            <strong> <?= $fb['reliability']; ?>/5</strong>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($fb['professionalism']): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Professionalism</div>
                            <span class="rating-item-stars"><?php for($i=0; $i<$fb['professionalism']; $i++) echo '⭐'; ?></span>
                            <strong> <?= $fb['professionalism']; ?>/5</strong>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($fb['job_accuracy']): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Job Accuracy</div>
                            <span class="rating-item-stars"><?php for($i=0; $i<$fb['job_accuracy']; $i++) echo '⭐'; ?></span>
                            <strong> <?= $fb['job_accuracy']; ?>/5</strong>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($fb['payment_timeliness']): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Payment On Time</div>
                            <span class="rating-item-stars"><?php for($i=0; $i<$fb['payment_timeliness']; $i++) echo '⭐'; ?></span>
                            <strong> <?= $fb['payment_timeliness']; ?>/5</strong>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($fb['communication']): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Communication</div>
                            <span class="rating-item-stars"><?php for($i=0; $i<$fb['communication']; $i++) echo '⭐'; ?></span>
                            <strong> <?= $fb['communication']; ?>/5</strong>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($fb['comment'])): ?>
                    <div class="feedback-comment">
                        <div class="comment-text">"<?= nl2br(htmlspecialchars($fb['comment'])); ?>"</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="feedback-meta">
                        <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($fb['created_at'])); ?></span>
                        
                        <span class="would-work-badge <?= $fb['would_work_again'] ? 'would-work-yes' : 'would-work-no'; ?>">
                            <?php if ($fb['would_work_again']): ?>
                                <i class="fas fa-check-circle"></i> Would work again
                            <?php else: ?>
                                <i class="fas fa-times-circle"></i> Would not work again
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">⭐</div>
                    <h3>No Reviews Yet</h3>
                    <p>This user hasn't received any reviews yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>