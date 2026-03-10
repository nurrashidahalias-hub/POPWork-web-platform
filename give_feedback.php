<?php
session_start();
include("backend/db_connect.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;

if ($application_id <= 0) {
    header("Location: my_completed_jobs.php");
    exit();
}

// Fetch application details
if ($user_role === 'worker') {
    $sql = "SELECT a.*, j.title as job_title, j.employer_id,
                   emp.name as employer_name, emp.photo_url as employer_photo
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            LEFT JOIN profiles emp ON j.employer_id = emp.user_id
            WHERE a.application_id = ? AND a.user_id = ? AND a.status IN ('Accepted', 'completed')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $application_id, $user_id);
} else {
    $sql = "SELECT a.*, j.title as job_title, a.user_id as worker_id,
                   worker.name as worker_name, worker.photo_url as worker_photo
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            LEFT JOIN profiles worker ON a.user_id = worker.user_id
            WHERE a.application_id = ? AND j.employer_id = ? AND a.status IN ('Accepted', 'completed')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $application_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: my_completed_jobs.php?error=" . urlencode("Application not found or unauthorized"));
    exit();
}

$app = $result->fetch_assoc();

// Check if feedback already exists
$check_sql = "SELECT feedback_id FROM feedback WHERE application_id = ? AND reviewer_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $application_id, $user_id);
$check_stmt->execute();
$existing = $check_stmt->get_result();

if ($existing->num_rows > 0) {
    header("Location: my_completed_jobs.php?info=" . urlencode("You have already given feedback for this job"));
    exit();
}

// Determine who is being reviewed
if ($user_role === 'worker') {
    $reviewee_id = $app['employer_id'];
    $reviewee_name = $app['employer_name'] ?? 'Employer';
    $reviewee_photo = $app['employer_photo'];
    $reviewee_type = 'Employer';
} else {
    $reviewee_id = $app['worker_id'];
    $reviewee_name = $app['worker_name'] ?? 'Worker';
    $reviewee_photo = $app['worker_photo'];
    $reviewee_type = 'Worker';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Give Feedback | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background: #f0f2f5;
            min-height: 100vh;
            position: relative;
        }

        /* Photo Background Like Other Pages */
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

        /* Main Content with Sidebar */
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            min-height: 100vh;
            position: relative;
            z-index: 1;
            padding: 40px 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.25);
            border: 2px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .back-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .back-link:hover::before {
            left: 100%;
        }
        
        .back-link:hover {
            background: linear-gradient(135deg, #6d1028 0%, #a71d47 100%);
            box-shadow: 0 6px 20px rgba(138, 21, 56, 0.4);
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .back-link:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(138, 21, 56, 0.3);
        }

        .back-link i {
            font-size: 16px;
            transition: transform 0.3s;
        }

        .back-link:hover i {
            transform: translateX(-4px);
        }
        
        .feedback-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .card-header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .card-header p {
            color: #666;
            font-size: 15px;
        }
        
        .person-info {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            border-radius: 16px;
            margin-bottom: 32px;
            color: white;
        }
        
        .person-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
        }
        
        .person-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            border: 4px solid white;
        }
        
        .person-details h3 {
            font-size: 20px;
            margin-bottom: 4px;
        }
        
        .person-details p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 28px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            font-size: 15px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        /* Star Rating */
        .star-rating {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .star {
            font-size: 40px;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .star:hover,
        .star.active {
            color: #ffd700;
            transform: scale(1.1);
        }
        
        .rating-value {
            font-size: 14px;
            color: #666;
            margin-top: 8px;
        }
        
        /* Small Star Rating */
        .small-star-rating {
            display: flex;
            gap: 4px;
        }
        
        .small-star {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .small-star:hover,
        .small-star.active {
            color: #ffd700;
        }
        
        .form-textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
            min-height: 120px;
            transition: 0.3s;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .char-counter {
            text-align: right;
            font-size: 13px;
            color: #999;
            margin-top: 4px;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        
        .checkbox-wrapper label {
            font-size: 15px;
            color: #333;
            cursor: pointer;
            flex: 1;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 32px;
        }
        
        .btn {
            flex: 1;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            border: none;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e0e0e0;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .rating-grid {
            display: grid;
            gap: 20px;
        }
        
        .rating-item {
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .rating-item-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .feedback-card {
                padding: 24px;
            }
            
            .person-info {
                flex-direction: column;
                text-align: center;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include("includes/sidebar.php"); ?>
    <div class="main-content">
    <div class="container">
        <a href="my_completed_jobs.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to My Jobs
        </a>
        
        <div class="feedback-card">
            <div class="card-header">
                <h1>⭐ Give Feedback</h1>
                <p>Share your experience working with this <?= strtolower($reviewee_type); ?></p>
            </div>
            
            <!-- Person Being Reviewed -->
            <div class="person-info">
                <?php if (!empty($reviewee_photo)): ?>
                    <img src="<?= htmlspecialchars($reviewee_photo); ?>" class="person-photo" alt="Profile">
                <?php else: ?>
                    <div class="person-placeholder">
                        <?= strtoupper(substr($reviewee_name, 0, 1)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="person-details">
                    <h3><?= htmlspecialchars($reviewee_name); ?></h3>
                    <p>📋 Job: <?= htmlspecialchars($app['job_title']); ?></p>
                    <p>👤 Role: <?= $reviewee_type; ?></p>
                </div>
            </div>
            
            <!-- Feedback Form -->
            <form action="backend/submit_feedback.php" method="POST" id="feedbackForm">
                <input type="hidden" name="application_id" value="<?= $application_id; ?>">
                <input type="hidden" name="reviewee_id" value="<?= $reviewee_id; ?>">
                
                <!-- Overall Rating -->
                <div class="form-group">
                    <label class="form-label">Overall Rating <span class="required">*</span></label>
                    <div class="star-rating" id="overallRating">
                        <span class="star" data-value="1">★</span>
                        <span class="star" data-value="2">★</span>
                        <span class="star" data-value="3">★</span>
                        <span class="star" data-value="4">★</span>
                        <span class="star" data-value="5">★</span>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" required>
                    <div class="rating-value" id="ratingText">Please select a rating</div>
                </div>
                
                <!-- Role-Specific Detailed Ratings -->
                <div class="rating-grid">
                    <?php if ($user_role === 'employer'): ?>
                        <!-- Employer rates Worker -->
                        <div class="rating-item">
                            <div class="rating-item-label">Work Quality</div>
                            <div class="small-star-rating" data-rating-type="work_quality">
                                <span class="small-star" data-value="1">★</span>
                                <span class="small-star" data-value="2">★</span>
                                <span class="small-star" data-value="3">★</span>
                                <span class="small-star" data-value="4">★</span>
                                <span class="small-star" data-value="5">★</span>
                            </div>
                            <input type="hidden" name="work_quality" class="rating-hidden">
                        </div>
                        
                        <div class="rating-item">
                            <div class="rating-item-label">Reliability</div>
                            <div class="small-star-rating" data-rating-type="reliability">
                                <span class="small-star" data-value="1">★</span>
                                <span class="small-star" data-value="2">★</span>
                                <span class="small-star" data-value="3">★</span>
                                <span class="small-star" data-value="4">★</span>
                                <span class="small-star" data-value="5">★</span>
                            </div>
                            <input type="hidden" name="reliability" class="rating-hidden">
                        </div>
                        
                        <div class="rating-item">
                            <div class="rating-item-label">Professionalism</div>
                            <div class="small-star-rating" data-rating-type="professionalism">
                                <span class="small-star" data-value="1">★</span>
                                <span class="small-star" data-value="2">★</span>
                                <span class="small-star" data-value="3">★</span>
                                <span class="small-star" data-value="4">★</span>
                                <span class="small-star" data-value="5">★</span>
                            </div>
                            <input type="hidden" name="professionalism" class="rating-hidden">
                        </div>
                    <?php else: ?>
                        <!-- Worker rates Employer -->
                        <div class="rating-item">
                            <div class="rating-item-label">Job Description Accuracy</div>
                            <div class="small-star-rating" data-rating-type="job_accuracy">
                                <span class="small-star" data-value="1">★</span>
                                <span class="small-star" data-value="2">★</span>
                                <span class="small-star" data-value="3">★</span>
                                <span class="small-star" data-value="4">★</span>
                                <span class="small-star" data-value="5">★</span>
                            </div>
                            <input type="hidden" name="job_accuracy" class="rating-hidden">
                        </div>
                        
                        <div class="rating-item">
                            <div class="rating-item-label">Payment On Time</div>
                            <div class="small-star-rating" data-rating-type="payment_timeliness">
                                <span class="small-star" data-value="1">★</span>
                                <span class="small-star" data-value="2">★</span>
                                <span class="small-star" data-value="3">★</span>
                                <span class="small-star" data-value="4">★</span>
                                <span class="small-star" data-value="5">★</span>
                            </div>
                            <input type="hidden" name="payment_timeliness" class="rating-hidden">
                        </div>
                        
                        <div class="rating-item">
                            <div class="rating-item-label">Communication</div>
                            <div class="small-star-rating" data-rating-type="communication">
                                <span class="small-star" data-value="1">★</span>
                                <span class="small-star" data-value="2">★</span>
                                <span class="small-star" data-value="3">★</span>
                                <span class="small-star" data-value="4">★</span>
                                <span class="small-star" data-value="5">★</span>
                            </div>
                            <input type="hidden" name="communication" class="rating-hidden">
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Written Feedback -->
                <div class="form-group" style="margin-top: 28px;">
                    <label class="form-label" for="comment">Your Feedback (Optional)</label>
                    <textarea 
                        name="comment" 
                        id="comment" 
                        class="form-textarea" 
                        placeholder="Share your experience working with this <?= strtolower($reviewee_type); ?>..."
                        maxlength="500"></textarea>
                    <div class="char-counter">
                        <span id="charCount">0</span> / 500 characters
                    </div>
                </div>
                
                <!-- Would Work Again -->
                <div class="form-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" name="would_work_again" id="wouldWorkAgain" value="1" checked>
                        <label for="wouldWorkAgain">
                            I would work with this <?= strtolower($reviewee_type); ?> again
                        </label>
                    </div>
                </div>
                
                <!-- Submit Buttons -->
                <div class="form-actions">
                    <a href="my_completed_jobs.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Overall Rating
        const overallStars = document.querySelectorAll('#overallRating .star');
        const ratingInput = document.getElementById('ratingInput');
        const ratingText = document.getElementById('ratingText');
        const submitBtn = document.getElementById('submitBtn');
        
        const ratingLabels = {
            1: '⭐ Poor',
            2: '⭐⭐ Fair',
            3: '⭐⭐⭐ Good',
            4: '⭐⭐⭐⭐ Very Good',
            5: '⭐⭐⭐⭐⭐ Excellent'
        };
        
        overallStars.forEach(star => {
            star.addEventListener('click', function() {
                const value = this.dataset.value;
                ratingInput.value = value;
                ratingText.textContent = ratingLabels[value];
                
                overallStars.forEach(s => {
                    if (s.dataset.value <= value) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const value = this.dataset.value;
                overallStars.forEach(s => {
                    if (s.dataset.value <= value) {
                        s.style.color = '#ffd700';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        });
        
        document.getElementById('overallRating').addEventListener('mouseleave', function() {
            const selectedValue = ratingInput.value;
            overallStars.forEach(s => {
                if (selectedValue && s.dataset.value <= selectedValue) {
                    s.style.color = '#ffd700';
                } else {
                    s.style.color = '#ddd';
                }
            });
        });
        
        // Small Star Ratings
        const ratingGroups = document.querySelectorAll('.small-star-rating');
        
        ratingGroups.forEach(group => {
            const stars = group.querySelectorAll('.small-star');
            const hiddenInput = group.parentElement.querySelector('.rating-hidden');
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const value = this.dataset.value;
                    hiddenInput.value = value;
                    
                    stars.forEach(s => {
                        if (s.dataset.value <= value) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });
        });
        
        // Character Counter
        const commentTextarea = document.getElementById('comment');
        const charCount = document.getElementById('charCount');
        
        commentTextarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
        
        // Form Validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            if (!ratingInput.value) {
                e.preventDefault();
                alert('Please select an overall rating before submitting.');
                return false;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        });
    </script>
    </div> <!-- End main-content -->
</body>
</html>