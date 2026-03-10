<?php
session_start();
include("db_connect.php");

header('Content-Type: application/json');
ini_set('display_errors', 0);

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }

    $user_id = $_SESSION['user_id'];

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['photo'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        throw new Exception('Only image files allowed');
    }

    if ($file['size'] > 2097152) {
        throw new Exception('File exceeds 2MB limit');
    }

    if (@getimagesize($file['tmp_name']) === false) {
        throw new Exception('Invalid image file');
    }

    // Paths
    $root = dirname(__DIR__);
    $dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'photos';
    
    if (!is_dir($dir)) {
        throw new Exception('Folder does not exist: uploads/photos/');
    }

    $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $filename;
    $db_path = 'uploads/photos/' . $filename;

    // Get old photo
    $check = $conn->prepare("SELECT photo_url FROM profiles WHERE user_id = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $old = $result->fetch_assoc()['photo_url'];
        
        // Update existing profile
        $stmt = $conn->prepare("UPDATE profiles SET photo_url = ? WHERE user_id = ?");
        $stmt->bind_param("si", $db_path, $user_id);
    } else {
        // Insert new profile
        $stmt = $conn->prepare("INSERT INTO profiles (user_id, photo_url) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $db_path);
        $old = null;
    }

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('Failed to save file');
    }

    // Update DB
    if (!$stmt->execute()) {
        @unlink($dest);
        throw new Exception('Database error: ' . $stmt->error);
    }

    // Delete old file
    if ($old && file_exists($root . DIRECTORY_SEPARATOR . $old)) {
        @unlink($root . DIRECTORY_SEPARATOR . $old);
    }

    echo json_encode(['success' => true, 'message' => 'Photo uploaded!', 'photo_url' => $db_path]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>