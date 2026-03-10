<?php
session_start();
include("db_connect.php");

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['payment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Payment ID required']);
    exit();
}

$payment_id = intval($_GET['payment_id']);

// Get payment details
$sql = "SELECT pr.*, 
               j.title as job_title,
               j.category as job_category,
               wp.name as worker_name,
               wu.email as worker_email,
               ep.name as employer_name,
               eu.email as employer_email
        FROM payment_records pr
        JOIN jobs j ON pr.job_id = j.job_id
        JOIN users wu ON pr.user_id = wu.user_id
        LEFT JOIN profiles wp ON wu.user_id = wp.user_id
        JOIN users eu ON pr.employer_id = eu.user_id
        LEFT JOIN profiles ep ON eu.user_id = ep.user_id
        WHERE pr.payment_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Payment not found']);
    exit();
}

$payment = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'payment' => $payment
]);
?>