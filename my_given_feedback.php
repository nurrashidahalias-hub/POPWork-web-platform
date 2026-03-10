<?php
session_start();
include("backend/db_connect.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch feedback YOU gave to others
$sql = "SELECT f.*, 
               a.application_id,
               j.title as job_title,
               j.category as job_category,
               reviewee.name as reviewee_name,
               reviewee.photo_url as reviewee_photo,
               reviewee.avg_rating as reviewee_avg_rating,
               f.created_at as feedback_date
        FROM feedback f
        JOIN applications a ON f.application_id = a.application_id
        JOIN jobs j ON a.job_id = j.job_id
        JOIN profiles reviewee ON f.reviewee_id = reviewee.user_id
        WHERE f.reviewer_id = ?
        ORDER BY f.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$feedbacks = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback I Gave | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 40px;
            min-height: 100vh;
        }
        
        .page-header {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .page-header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #666;
            font-size: 16px;
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
            border-left: 5px solid #8a1538;
        }
        
        .feedback-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(138, 21, 56, 0.15);
        }
        
        .feedback-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .person-photo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
        }
        
        .person-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8a1538, #c91f4d);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            border: 3px solid #e9ecef;
        }
        
        .feedback-person-info h3 {
            font-size: 20px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .feedback-person-info p {
            color: #666;
            font-size: 14px;
        }
        
        .job-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #e7f3ff;
            color: #1876f2;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .overall-rating {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: linear-gradient(135deg, #fff9e6, #fffbf0);
            border-radius: 12px;
            border: 2px solid #ffd700;
        }
        
        .rating-stars {
            color: #ffd700;
            font-size: 24px;
            display: flex;
            gap: 2px;
        }
        
        .rating-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        
        .rating-label {
            font-size: 13px;
            color: #666;
            font-weight: 600;
        }
        
        .detailed-ratings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .rating-item {
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .rating-item-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .rating-item-stars {
            color: #ffd700;
            font-size: 16px;
        }
        
        .rating-item-value {
            font-weight: 700;
            color: #333;
            margin-left: 5px;
        }
        
        .feedback-comment {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #8a1538;
            margin-bottom: 15px;
        }
        
        .comment-label {
            font-size: 13px;
            color: #666;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .comment-text {
            color: #333;
            line-height: 1.6;
            font-size: 15px;
        }
        
        .feedback-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
            color: #666;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .would-work-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
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
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .feedback-header {
                flex-direction: column;
                text-align: center;
            }
            
            .detailed-ratings {
                grid-template-columns: 1fr;
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
        <div class="page-header">
            <h1>💬 Feedback I Gave</h1>
            <p>Review all the feedback you've given to others</p>
        </div>
        
        <div class="feedback-grid">
            <?php if ($feedbacks->num_rows > 0): ?>
                <?php while ($fb = $feedbacks->fetch_assoc()): ?>
                <div class="feedback-card">
                    <!-- Person Info -->
                    <div class="feedback-header">
                        <?php if (!empty($fb['reviewee_photo'])): ?>
                            <img src="<?= htmlspecialchars($fb['reviewee_photo']); ?>" class="person-photo" alt="Profile">
                        <?php else: ?>
                            <div class="person-placeholder">
                                <?= strtoupper(substr($fb['reviewee_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="feedback-person-info">
                            <h3><?= htmlspecialchars($fb['reviewee_name']); ?></h3>
                            <p>📋 <?= htmlspecialchars($fb['job_title']); ?></p>
                            <span class="job-badge"><?= htmlspecialchars($fb['job_category']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Overall Rating -->
                    <div class="rating-display">
                        <div class="overall-rating">
                            <div>
                                <div class="rating-label">Your Rating</div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="rating-stars">
                                        <?php for($i = 0; $i < $fb['rating']; $i++) echo '⭐'; ?>
                                    </span>
                                    <span class="rating-number"><?= $fb['rating']; ?>/5</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detailed Ratings -->
                    <div class="detailed-ratings">
                        <?php if (!empty($fb['work_quality'])): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Work Quality</div>
                            <span class="rating-item-stars">
                                <?php for($i = 0; $i < $fb['work_quality']; $i++) echo '⭐'; ?>
                            </span>
                            <span class="rating-item-value"><?= $fb['work_quality']; ?>/5</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($fb['reliability'])): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Reliability</div>
                            <span class="rating-item-stars">
                                <?php for($i = 0; $i < $fb['reliability']; $i++) echo '⭐'; ?>
                            </span>
                            <span class="rating-item-value"><?= $fb['reliability']; ?>/5</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($fb['professionalism'])): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Professionalism</div>
                            <span class="rating-item-stars">
                                <?php for($i = 0; $i < $fb['professionalism']; $i++) echo '⭐'; ?>
                            </span>
                            <span class="rating-item-value"><?= $fb['professionalism']; ?>/5</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($fb['job_accuracy'])): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Job Description Accuracy</div>
                            <span class="rating-item-stars">
                                <?php for($i = 0; $i < $fb['job_accuracy']; $i++) echo '⭐'; ?>
                            </span>
                            <span class="rating-item-value"><?= $fb['job_accuracy']; ?>/5</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($fb['payment_timeliness'])): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Payment On Time</div>
                            <span class="rating-item-stars">
                                <?php for($i = 0; $i < $fb['payment_timeliness']; $i++) echo '⭐'; ?>
                            </span>
                            <span class="rating-item-value"><?= $fb['payment_timeliness']; ?>/5</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($fb['communication'])): ?>
                        <div class="rating-item">
                            <div class="rating-item-label">Communication</div>
                            <span class="rating-item-stars">
                                <?php for($i = 0; $i < $fb['communication']; $i++) echo '⭐'; ?>
                            </span>
                            <span class="rating-item-value"><?= $fb['communication']; ?>/5</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Comment -->
                    <?php if (!empty($fb['comment'])): ?>
                    <div class="feedback-comment">
                        <div class="comment-label">💬 Your Comment</div>
                        <div class="comment-text"><?= nl2br(htmlspecialchars($fb['comment'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Meta Info -->
                    <div class="feedback-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <?= date('M j, Y', strtotime($fb['feedback_date'])); ?>
                        </div>
                        
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
                    <div class="empty-state-icon">📝</div>
                    <h3>No Feedback Given Yet</h3>
                    <p>When you give feedback to others, it will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>