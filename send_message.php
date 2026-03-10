<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

// Log incoming request
file_put_contents('debug_send.log', date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);
file_put_contents('debug_send.log', "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents('debug_send.log', "Session: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

include("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    $error = ['success' => false, 'error' => 'Not authenticated', 'session' => $_SESSION];
    file_put_contents('debug_send.log', "Auth failed: " . json_encode($error) . "\n", FILE_APPEND);
    echo json_encode($error);
    exit();
}

$user_id = $_SESSION['user_id'];
file_put_contents('debug_send.log', "User ID: $user_id\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id = intval($_POST['sender_id']);
    $receiver_id = intval($_POST['receiver_id']);
    $message = trim($_POST['message']);
    $job_id = isset($_POST['job_id']) && $_POST['job_id'] !== 'null' ? intval($_POST['job_id']) : null;
    
    file_put_contents('debug_send.log', "Parsed - Sender: $sender_id, Receiver: $receiver_id, Job: $job_id, Message: $message\n", FILE_APPEND);
    
    // Verify sender is the logged-in user
    if ($sender_id !== $user_id) {
        $error = ['success' => false, 'error' => 'Invalid sender', 'sender_id' => $sender_id, 'user_id' => $user_id];
        file_put_contents('debug_send.log', "Sender mismatch: " . json_encode($error) . "\n", FILE_APPEND);
        echo json_encode($error);
        exit();
    }
    
    // Validate message
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit();
    }
    
    if (strlen($message) > 2000) {
        echo json_encode(['success' => false, 'error' => 'Message too long']);
        exit();
    }
    
    // Insert message
    try {
        if ($job_id) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, job_id, message, timestamp, is_read) VALUES (?, ?, ?, ?, NOW(), 0)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("iiis", $sender_id, $receiver_id, $job_id, $message);
        } else {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, timestamp, is_read) VALUES (?, ?, ?, NOW(), 0)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
        }
        
        if ($stmt->execute()) {
            $result = ['success' => true, 'message_id' => $conn->insert_id];
            file_put_contents('debug_send.log', "Success: " . json_encode($result) . "\n\n", FILE_APPEND);
            echo json_encode($result);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error = ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        file_put_contents('debug_send.log', "Exception: " . json_encode($error) . "\n\n", FILE_APPEND);
        echo json_encode($error);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

$conn->close();
?>