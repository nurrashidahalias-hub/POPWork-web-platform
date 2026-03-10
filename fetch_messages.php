<?php
session_start();
header('Content-Type: application/json');
include("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// ============================================
// GET REQUEST: FETCH MESSAGES
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    if (!isset($_GET['other_user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Other user ID required']);
        exit();
    }
    
    $other_user_id = intval($_GET['other_user_id']);
    $job_id = isset($_GET['job_id']) && $_GET['job_id'] !== '0' && $_GET['job_id'] !== '' && $_GET['job_id'] !== 'null' ? intval($_GET['job_id']) : null;
    
    // Build query to fetch messages between two users
    $sql = "SELECT 
                m.message_id,
                m.sender_id,
                m.receiver_id,
                m.message,
                m.type,
                m.timestamp as created_at,
                m.is_read,
                m.edited as is_edited,
                m.edited_at,
                m.job_id
            FROM messages m
            WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
                OR (m.sender_id = ? AND m.receiver_id = ?))";
    
    $params = [$user_id, $other_user_id, $other_user_id, $user_id];
    $types = "iiii";
    
    // Add job filter if specified
    if ($job_id) {
        $sql .= " AND m.job_id = ?";
        $params[] = $job_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY m.timestamp ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Determine if message contains a file path
        $file_path = null;
        $message_text = $row['message'];
        
        // If type is image or audio, the message field contains the file path
        if ($row['type'] === 'image' || $row['type'] === 'audio') {
            $file_path = $row['message'];
            $message_text = ''; // Clear message text for file types
        }
        
        $messages[] = [
            'message_id' => $row['message_id'],
            'sender_id' => $row['sender_id'],
            'receiver_id' => $row['receiver_id'],
            'message' => $message_text,
            'file_path' => $file_path,
            'type' => $row['type'] ?? 'text',
            'created_at' => $row['created_at'],
            'is_read' => $row['is_read'] ?? 0,
            'is_edited' => $row['is_edited'] ?? 0,
            'edited_at' => $row['edited_at'],
            'job_id' => $row['job_id']
        ];
    }
    
    // Mark messages as read (messages sent TO current user FROM other user)
    $mark_read_sql = "UPDATE messages 
                      SET is_read = 1 
                      WHERE receiver_id = ? 
                      AND sender_id = ? 
                      AND is_read = 0";
    
    $mark_stmt = $conn->prepare($mark_read_sql);
    $mark_stmt->bind_param("ii", $user_id, $other_user_id);
    $mark_stmt->execute();
    $mark_stmt->close();
    
    $stmt->close();
    
    // Return messages directly as array (chat.php expects this format)
    echo json_encode($messages);
    exit();
}

// ============================================
// POST REQUEST: HANDLE VARIOUS ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle message editing
    if (isset($_POST['edit_message'])) {
        $message_id = intval($_POST['message_id']);
        $new_text = trim($_POST['new_text']);
        
        // Verify the message belongs to the current user
        $check_sql = "SELECT sender_id FROM messages WHERE message_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $message_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $message_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if (!$message_data || $message_data['sender_id'] != $user_id) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit();
        }
        
        // Update the message
        $update_sql = "UPDATE messages SET message = ?, edited = 1, edited_at = NOW() WHERE message_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_text, $message_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update message']);
        }
        $update_stmt->close();
        exit();
    }
    
    // Handle message deletion
    if (isset($_POST['delete_message'])) {
        $message_id = intval($_POST['delete_message']) ?: intval($_POST['message_id']);
        
        // Verify the message belongs to the current user
        $check_sql = "SELECT sender_id FROM messages WHERE message_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $message_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $message_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if (!$message_data || $message_data['sender_id'] != $user_id) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit();
        }
        
        // Delete the message
        $delete_sql = "DELETE FROM messages WHERE message_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $message_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete message']);
        }
        $delete_stmt->close();
        exit();
    }
    
    // Handle marking messages as read
    if (isset($_POST['mark_read'])) {
        $sender_id = intval($_POST['sender_id']);
        $receiver_id = intval($_POST['receiver_id']);
        
        $mark_sql = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
        $mark_stmt = $conn->prepare($mark_sql);
        $mark_stmt->bind_param("ii", $sender_id, $receiver_id);
        
        if ($mark_stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark as read']);
        }
        $mark_stmt->close();
        exit();
    }
    
   // Handle file upload (image or audio)
if (isset($_FILES['file'])) {
    error_log("File upload initiated by user: $user_id");
    
    $file = $_FILES['file'];
    $receiver_id = intval($_POST['receiver_id']);
    $job_id = isset($_POST['job_id']) && $_POST['job_id'] !== 'null' && $_POST['job_id'] !== '0' && $_POST['job_id'] !== '' ? intval($_POST['job_id']) : null;
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Upload error']);
        exit();
    }
    
    // Determine file type
    $file_type = 'image';
    if (strpos($file['type'], 'audio') !== false) {
        $file_type = 'audio';
    } elseif (strpos($file['type'], 'video') !== false) {
        $file_type = 'video';
    }
    
    // FIXED: Use absolute path
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/PopWork/uploads/chat/';
    
    // Create directory if needed
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Save RELATIVE path to database (for web access)
        $relative_path = 'uploads/chat/' . $filename;
        
        if ($job_id) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, type, job_id, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iissi", $user_id, $receiver_id, $relative_path, $file_type, $job_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, type, timestamp) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiss", $user_id, $receiver_id, $relative_path, $file_type);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    }
    exit();
}
    
    // Handle text message (send_message)
    if (isset($_POST['send_message']) || isset($_POST['message'])) {
        $message = trim($_POST['message']);
        $receiver_id = intval($_POST['receiver_id']);
        $job_id = isset($_POST['job_id']) && $_POST['job_id'] !== 'null' && $_POST['job_id'] !== '0' && $_POST['job_id'] !== '' ? intval($_POST['job_id']) : null;
        
        // Log for debugging
        error_log("Message send attempt - User: $user_id, Receiver: $receiver_id, Job: $job_id, Message: $message");
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit();
        }
        
        if (strlen($message) > 2000) {
            echo json_encode(['success' => false, 'error' => 'Message too long (max 2000 characters)']);
            exit();
        }
        
        if ($receiver_id === 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid receiver ID']);
            exit();
        }

              
        // Insert message - using session user_id as sender for security
        if ($job_id) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, type, job_id, timestamp) VALUES (?, ?, ?, 'text', ?, NOW())");
            $stmt->bind_param("iisi", $user_id, $receiver_id, $message, $job_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, type, timestamp) VALUES (?, ?, ?, 'text', NOW())");
            $stmt->bind_param("iis", $user_id, $receiver_id, $message);
        }
        
        if ($stmt->execute()) {
            $message_id = $conn->insert_id;
            error_log("Message sent successfully - ID: $message_id");
            echo json_encode(['success' => true, 'message_id' => $message_id]);
        } else {
            error_log("Failed to send message: " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Failed to send message: ' . $conn->error]);
        }
        $stmt->close();
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'No valid action provided']);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request method']);
$conn->close();
?>