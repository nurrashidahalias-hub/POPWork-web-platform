<?php
session_start();
include("db_connect.php");

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['job_id'])) {
    echo json_encode(['success' => false, 'message' => 'Job ID required']);
    exit();
}

$job_id = intval($_GET['job_id']);

// Get job details
$job_sql = "SELECT j.*, 
                   p.name as employer_name,
                   u.email as employer_email
            FROM jobs j
            LEFT JOIN users u ON j.employer_id = u.user_id
            LEFT JOIN profiles p ON u.user_id = p.user_id
            WHERE j.job_id = ?";

$stmt = $conn->prepare($job_sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job_result = $stmt->get_result();

if ($job_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Job not found']);
    exit();
}

$job = $job_result->fetch_assoc();

// Get applications for this job
$app_sql = "SELECT a.*, 
                   p.name as worker_name,
                   u.email as worker_email
            FROM applications a
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN profiles p ON u.user_id = p.user_id
            WHERE a.job_id = ?
            ORDER BY a.applied_at DESC";

$app_stmt = $conn->prepare($app_sql);
$app_stmt->bind_param("i", $job_id);
$app_stmt->execute();
$app_result = $app_stmt->get_result();

$applications = [];
while ($app = $app_result->fetch_assoc()) {
    $applications[] = $app;
}

echo json_encode([
    'success' => true,
    'job' => $job,
    'applications' => $applications
]);
?>