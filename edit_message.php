<?php
session_start();
header('Content-Type: application/json');
include("db_connect.php");

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log the incoming request
    error_log("Edit message request: " . print_r($_POST, true));
    
    if (!isset($_POST['message_id']) || !isset($_POST['new_message'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit();
    }
    
    $message_id = intval($_POST['message_id']);
    $new_message = trim($_POST['new_message']);
    
    // Allow editing to empty for deletion-like behavior (optional)
    if (empty($new_message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit();
    }
    
    // Verify user owns this message
    $check_stmt = $conn->prepare("SELECT sender_id, type, message FROM messages WHERE message_id = ?");
    $check_stmt->bind_param("i", $message_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit();
    }
    
    $message = $result->fetch_assoc();
    
    if ($message['sender_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'You can only edit your own messages']);
        exit();
    }
    
    if ($message['type'] !== 'text') {
        echo json_encode(['success' => false, 'error' => 'Can only edit text messages']);
        exit();
    }
    
    // Check if message actually changed
    if ($message['message'] === $new_message) {
        echo json_encode(['success' => true, 'message' => 'No changes made']);
        exit();
    }
    
    // Check if edited column exists, if not, just update message without it
    $columns_query = "SHOW COLUMNS FROM messages LIKE 'edited'";
    $columns_result = $conn->query($columns_query);
    $has_edited_column = $columns_result->num_rows > 0;
    
    if ($has_edited_column) {
        // Update with edited flag and timestamp
        $update_sql = "UPDATE messages SET message = ?, edited = 1, edited_at = NOW() WHERE message_id = ?";
    } else {
        // Just update the message
        $update_sql = "UPDATE messages SET message = ? WHERE message_id = ?";
    }
    
    $update_stmt = $conn->prepare($update_sql);
    
    if ($has_edited_column) {
        $update_stmt->bind_param("si", $new_message, $message_id);
    } else {
        $update_stmt->bind_param("si", $new_message, $message_id);
    }
    
    if ($update_stmt->execute()) {
        error_log("Message updated successfully: ID $message_id");
        echo json_encode([
            'success' => true, 
            'message' => 'Message updated successfully',
            'edited' => $has_edited_column ? 1 : 0,
            'new_text' => $new_message
        ]);
    } else {
        error_log("Failed to update message: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Failed to update message: ' . $conn->error]);
    }
    
    $update_stmt->close();
    $check_stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

$conn->close();
?>