<?php
session_start();
include("db_connect.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['receiver_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'];
$status = $_POST['status']; // "1" for typing, "0" for stopped

if ($status == "1") {
    // Record that current user is typing to the partner
    $sql = "UPDATE users SET is_typing_with = ?, last_typing_at = NOW() WHERE user_id = ?";
} else {
    // Clear typing status
    $sql = "UPDATE users SET is_typing_with = NULL, last_typing_at = NULL WHERE user_id = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $receiver_id, $user_id);
$stmt->execute();

echo json_encode(['success' => true]);