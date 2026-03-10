<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../my_completed_jobs.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get form data
$application_id = intval($_POST['application_id']);
$reviewee_id = intval($_POST['reviewee_id']);
$rating = intval($_POST['rating']);
$comment = trim($_POST['comment'] ?? '');
$would_work_again = isset($_POST['would_work_again']) ? 1 : 0;

// Role-specific ratings
$work_quality = 0;
$reliability = 0;
$professionalism = 0;
$job_accuracy = 0;
$payment_timeliness = 0;
$communication = 0;

if ($user_role === 'employer') {
    $work_quality = !empty($_POST['work_quality']) ? intval($_POST['work_quality']) : 0;
    $reliability = !empty($_POST['reliability']) ? intval($_POST['reliability']) : 0;
    $professionalism = !empty($_POST['professionalism']) ? intval($_POST['professionalism']) : 0;
} else {
    $job_accuracy = !empty($_POST['job_accuracy']) ? intval($_POST['job_accuracy']) : 0;
    $payment_timeliness = !empty($_POST['payment_timeliness']) ? intval($_POST['payment_timeliness']) : 0;
    $communication = !empty($_POST['communication']) ? intval($_POST['communication']) : 0;
}

// Validate required fields
if ($application_id <= 0 || $reviewee_id <= 0 || $rating < 1 || $rating > 5) {
    header("Location: ../my_completed_jobs.php?error=" . urlencode("Invalid feedback data"));
    exit();
}

// Verify application belongs to user
if ($user_role === 'worker') {
    $verify_sql = "SELECT a.application_id, j.employer_id 
                   FROM applications a 
                   JOIN jobs j ON a.job_id = j.job_id 
                   WHERE a.application_id = ? AND a.user_id = ? AND a.status IN ('Accepted', 'completed')";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $application_id, $user_id);
} else {
    $verify_sql = "SELECT a.application_id, a.user_id as worker_id 
                   FROM applications a 
                   JOIN jobs j ON a.job_id = j.job_id 
                   WHERE a.application_id = ? AND j.employer_id = ? AND a.status IN ('Accepted', 'completed')";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $application_id, $user_id);
}

$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows == 0) {
    header("Location: ../my_completed_jobs.php?error=" . urlencode("Unauthorized access"));
    exit();
}

$app_data = $verify_result->fetch_assoc();

// Double-check reviewee_id matches
$expected_reviewee = $user_role === 'worker' ? $app_data['employer_id'] : $app_data['worker_id'];
if ($reviewee_id != $expected_reviewee) {
    header("Location: ../my_completed_jobs.php?error=" . urlencode("Invalid reviewee"));
    exit();
}

// Check if feedback already exists
$check_sql = "SELECT feedback_id FROM feedback WHERE application_id = ? AND reviewer_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $application_id, $user_id);
$check_stmt->execute();
$existing = $check_stmt->get_result();

if ($existing->num_rows > 0) {
    header("Location: ../my_completed_jobs.php?error=" . urlencode("You have already submitted feedback for this job"));
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    $insert_sql = "INSERT INTO feedback (
                    application_id, 
                    reviewer_id, 
                    reviewee_id, 
                    rating, 
                    comment,
                    work_quality, 
                    reliability, 
                    professionalism,
                    job_accuracy, 
                    payment_timeliness, 
                    communication,
                    would_work_again
                   ) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?)";
    
    $insert_stmt = $conn->prepare($insert_sql);
    
    if (!$insert_stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    // FIXED: Type string with EXACTLY 12 characters (not 13!)
    // i i i i s i i i i i i i
    // 1 2 3 4 5 6 7 8 9 10 11 12
    
    $insert_stmt->bind_param(
        "iiiisiiiiiii",        // 12 characters: 4 integers, 1 string, 7 integers
        $application_id,       // 1: i
        $user_id,              // 2: i
        $reviewee_id,          // 3: i
        $rating,               // 4: i
        $comment,              // 5: s
        $work_quality,         // 6: i
        $reliability,          // 7: i
        $professionalism,      // 8: i
        $job_accuracy,         // 9: i
        $payment_timeliness,   // 10: i
        $communication,        // 11: i
        $would_work_again      // 12: i
    );
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to insert feedback: " . $insert_stmt->error);
    }
    
    // Update reviewee's average rating
    $update_rating_sql = "UPDATE profiles p
                         SET avg_rating = (
                             SELECT AVG(rating) 
                             FROM feedback 
                             WHERE reviewee_id = ?
                         ),
                         total_ratings = (
                             SELECT COUNT(*) 
                             FROM feedback 
                             WHERE reviewee_id = ?
                         )
                         WHERE user_id = ?";
    
    $update_stmt = $conn->prepare($update_rating_sql);
    
    if (!$update_stmt) {
        throw new Exception("Failed to prepare rating update: " . $conn->error);
    }
    
    $update_stmt->bind_param("iii", $reviewee_id, $reviewee_id, $reviewee_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update average rating: " . $update_stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Success
    header("Location: ../my_completed_jobs.php?success=" . urlencode("Thank you for your feedback! ⭐"));
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    error_log("Feedback submission error: " . $e->getMessage());
    header("Location: ../my_completed_jobs.php?error=" . urlencode("Failed to submit feedback. Please try again."));
    exit();
}
?>