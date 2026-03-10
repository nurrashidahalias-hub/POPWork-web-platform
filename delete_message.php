<?php
session_start();
header('Content-Type: application/json');
include("db_connect.php");

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get message ID and action type
$message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : 'delete_for_me'; // 'delete_for_me' or 'unsend'

if (!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Message ID required']);
    exit();
}

// Verify the message exists and get details
$check_sql = "SELECT sender_id, receiver_id, message, type, timestamp FROM messages WHERE message_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $message_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$message = $result->fetch_assoc();
$check_stmt->close();

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit();
}

// Check if user is part of this conversation
$is_sender = ($message['sender_id'] == $user_id);
$is_receiver = ($message['receiver_id'] == $user_id);

if (!$is_sender && !$is_receiver) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Handle different delete actions
if ($action === 'unsend') {
    // Only sender can unsend
    if (!$is_sender) {
        echo json_encode(['success' => false, 'error' => 'Only sender can unsend messages']);
        exit();
    }
    
    // Check if message is within unsend time limit (e.g., 1 hour)
    $message_time = strtotime($message['timestamp']);
    $current_time = time();
    $time_diff = $current_time - $message_time;
    $unsend_time_limit = 3600; // 1 hour in seconds
    
    // Allow unsend if within time limit OR if receiver hasn't read it yet
    $can_unsend_sql = "SELECT is_read FROM messages WHERE message_id = ?";
    $can_unsend_stmt = $conn->prepare($can_unsend_sql);
    $can_unsend_stmt->bind_param("i", $message_id);
    $can_unsend_stmt->execute();
    $can_unsend_result = $can_unsend_stmt->get_result();
    $read_status = $can_unsend_result->fetch_assoc();
    $can_unsend_stmt->close();
    
    $is_read = $read_status['is_read'] ?? 0;
    
    // If message is older than 1 hour and has been read, don't allow unsend
    if ($time_diff > $unsend_time_limit && $is_read == 1) {
        echo json_encode([
            'success' => false, 
            'error' => 'Cannot unsend messages older than 1 hour that have been read'
        ]);
        exit();
    }
    
    // Delete file if it's an image or audio
    if (($message['type'] === 'image' || $message['type'] === 'audio') && !empty($message['message'])) {
        $file_path = '../' . $message['message'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Unsend: Permanently delete the message for everyone
    $delete_sql = "DELETE FROM messages WHERE message_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $message_id);
    
    if ($delete_stmt->execute()) {
        $delete_stmt->close();
        echo json_encode([
            'success' => true, 
            'action' => 'unsend',
            'message' => 'Message unsent for everyone'
        ]);
    } else {
        $delete_stmt->close();
        echo json_encode(['success' => false, 'error' => 'Failed to unsend message']);
    }
    
} elseif ($action === 'delete_for_me') {
    // Delete for me: Add a soft delete flag or use a deleted_by table
    
    // First, check if we have a message_deletions table, if not, we'll just delete
    // For better implementation, create a message_deletions table to track who deleted what
    
    // Check if message_deletions table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'message_deletions'");
    
    if ($table_check->num_rows > 0) {
        // Use message_deletions table for soft delete
        $soft_delete_sql = "INSERT INTO message_deletions (message_id, user_id, deleted_at) 
                           VALUES (?, ?, NOW())
                           ON DUPLICATE KEY UPDATE deleted_at = NOW()";
        $soft_delete_stmt = $conn->prepare($soft_delete_sql);
        $soft_delete_stmt->bind_param("ii", $message_id, $user_id);
        
        if ($soft_delete_stmt->execute()) {
            $soft_delete_stmt->close();
            echo json_encode([
                'success' => true, 
                'action' => 'delete_for_me',
                'message' => 'Message deleted for you'
            ]);
        } else {
            $soft_delete_stmt->close();
            echo json_encode(['success' => false, 'error' => 'Failed to delete message']);
        }
    } else {
        // Fallback: If no deletions table, just do hard delete
        // This is simpler but doesn't differentiate between users
        $delete_sql = "DELETE FROM messages WHERE message_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $message_id);
        
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            echo json_encode([
                'success' => true, 
                'action' => 'delete_for_me',
                'message' => 'Message deleted'
            ]);
        } else {
            $delete_stmt->close();
            echo json_encode(['success' => false, 'error' => 'Failed to delete message']);
        }
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();
?>