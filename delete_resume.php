<?php
session_start();
header('Content-Type: application/json');
include("db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized - Please log in'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get current resume path
    $stmt = $conn->prepare("SELECT resume_url FROM profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    
    if (!$profile || empty($profile['resume_url'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No resume found'
        ]);
        exit();
    }
    
    $resume_path = $profile['resume_url'];
    
    // Delete the file if it exists
    if (file_exists($resume_path)) {
        if (!unlink($resume_path)) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete resume file'
            ]);
            exit();
        }
    }
    
    // Update database to remove resume URL
    $update_stmt = $conn->prepare("UPDATE profiles SET resume_url = NULL WHERE user_id = ?");
    $update_stmt->bind_param("i", $user_id);
    
    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Resume deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update database'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>