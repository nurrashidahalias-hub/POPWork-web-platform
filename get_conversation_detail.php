<?php
session_start();
include("db_connect.php");

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['sender_id']) || !isset($_GET['receiver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sender and Receiver IDs required']);
    exit();
}

$sender_id = intval($_GET['sender_id']);
$receiver_id = intval($_GET['receiver_id']);
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

// Get conversation participants info
$sender_sql = "SELECT u.email, p.name, p.profile_picture 
               FROM users u 
               LEFT JOIN profiles p ON u.user_id = p.user_id 
               WHERE u.user_id = ?";
$stmt = $conn->prepare($sender_sql);
$stmt->bind_param("i", $sender_id);
$stmt->execute();
$sender_info = $stmt->get_result()->fetch_assoc();

$receiver_sql = "SELECT u.email, p.name, p.profile_picture 
                 FROM users u 
                 LEFT JOIN profiles p ON u.user_id = p.user_id 
                 WHERE u.user_id = ?";
$stmt = $conn->prepare($receiver_sql);
$stmt->bind_param("i", $receiver_id);
$stmt->execute();
$receiver_info = $stmt->get_result()->fetch_assoc();

// Get job info if exists
$job_title = 'General Inquiry';
if ($job_id > 0) {
    $job_sql = "SELECT title FROM jobs WHERE job_id = ?";
    $stmt = $conn->prepare($job_sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $job_result = $stmt->get_result()->fetch_assoc();
    if ($job_result) {
        $job_title = $job_result['title'];
    }
}

$conversation = [
    'sender_id' => $sender_id,
    'receiver_id' => $receiver_id,
    'job_id' => $job_id,
    'job_title' => $job_title,
    'sender_name' => $sender_info['name'] ?? explode('@', $sender_info['email'])[0],
    'sender_email' => $sender_info['email'],
    'sender_profile_picture' => $sender_info['profile_picture'] ?? null,
    'receiver_name' => $receiver_info['name'] ?? explode('@', $receiver_info['email'])[0],
    'receiver_email' => $receiver_info['email'],
    'receiver_profile_picture' => $receiver_info['profile_picture'] ?? null
];

// Get all messages between these users for this job
$msg_sql = "SELECT m.*, u.email as sender_email, p.name as sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            LEFT JOIN profiles p ON u.user_id = p.user_id
            WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
                   OR (m.sender_id = ? AND m.receiver_id = ?))
            AND (m.job_id = ? OR (m.job_id IS NULL AND ? = 0))
            ORDER BY m.timestamp ASC";

$stmt = $conn->prepare($msg_sql);
$stmt->bind_param("iiiiii", $sender_id, $receiver_id, $receiver_id, $sender_id, $job_id, $job_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode([
    'success' => true,
    'conversation' => $conversation,
    'messages' => $messages
]);
?>