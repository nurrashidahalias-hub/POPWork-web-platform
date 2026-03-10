<?php
session_start();
include("db_connect.php");

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['feedback_id'])) {
    echo json_encode(['success' => false, 'message' => 'Feedback ID required']);
    exit();
}

$feedback_id = intval($_GET['feedback_id']);

// Get feedback details
$sql = "SELECT f.*, 
               j.title as job_title,
               j.category as job_category,
               reviewer_profile.name as reviewer_name,
               reviewer_user.email as reviewer_email,
               reviewer_user.role as reviewer_role,
               reviewee_profile.name as reviewee_name,
               reviewee_user.email as reviewee_email,
               reviewee_user.role as reviewee_role
        FROM feedback f
        JOIN applications a ON f.application_id = a.application_id
        JOIN jobs j ON a.job_id = j.job_id
        JOIN users reviewer_user ON f.reviewer_id = reviewer_user.user_id
        LEFT JOIN profiles reviewer_profile ON reviewer_user.user_id = reviewer_profile.user_id
        JOIN users reviewee_user ON f.reviewee_id = reviewee_user.user_id
        LEFT JOIN profiles reviewee_profile ON reviewee_user.user_id = reviewee_profile.user_id
        WHERE f.feedback_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $feedback_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Feedback not found']);
    exit();
}

$feedback = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'feedback' => $feedback
]);
?>
