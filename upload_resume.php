<?php
ob_start();
session_start();
require_once("db_connect.php");
ob_end_clean();

header('Content-Type: application/json');

function respond($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if (!isset($_SESSION['user_id'])) respond(false, 'Not authenticated');
if (!isset($_FILES['resume'])) respond(false, 'No file uploaded');
if ($_FILES['resume']['error'] !== UPLOAD_ERR_OK) respond(false, 'Upload failed');

$user_id = $_SESSION['user_id'];
$file = $_FILES['resume'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['pdf', 'doc', 'docx'])) respond(false, 'Only PDF, DOC, DOCX allowed');
if ($file['size'] > 5242880) respond(false, 'File exceeds 5MB');

$root = dirname(__DIR__);
$dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumes';

if (!is_dir($dir)) respond(false, 'Create folder: uploads/resumes/');

$filename = 'resume_' . $user_id . '_' . time() . '.' . $ext;
$dest = $dir . DIRECTORY_SEPARATOR . $filename;
$db_path = 'uploads/resumes/' . $filename;

$check = $conn->prepare("SELECT resume_url FROM profiles WHERE user_id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $old = $result->fetch_assoc()['resume_url'];
    $stmt = $conn->prepare("UPDATE profiles SET resume_url = ? WHERE user_id = ?");
    $stmt->bind_param("si", $db_path, $user_id);
} else {
    $old = null;
    $stmt = $conn->prepare("INSERT INTO profiles (user_id, resume_url) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $db_path);
}

if (!move_uploaded_file($file['tmp_name'], $dest)) respond(false, 'Failed to save file');
if (!$stmt->execute()) {
    @unlink($dest);
    respond(false, 'Database error');
}

if ($old && file_exists($root . DIRECTORY_SEPARATOR . $old)) @unlink($root . DIRECTORY_SEPARATOR . $old);

respond(true, 'Resume uploaded!', ['resume_url' => $db_path]);
?>