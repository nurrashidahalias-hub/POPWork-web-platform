<?php
session_start();
include("db_connect.php");

// Use session user_id (more secure)
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Session expired. Please log in again.'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$section = $_POST['section'] ?? null;

if (!$section) {
    echo json_encode(['success' => false, 'message' => 'No section specified']);
    exit();
}

// Check if profile exists
$check_stmt = $conn->prepare("SELECT profile_id FROM profiles WHERE user_id = ?");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$profile_exists = $check_stmt->get_result()->num_rows > 0;

$success = false;

try {
    switch ($section) {
        case 'about':
            $bio = $_POST['bio'] ?? '';
            
            if ($profile_exists) {
                $stmt = $conn->prepare("UPDATE profiles SET bio = ? WHERE user_id = ?");
                $stmt->bind_param("si", $bio, $user_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO profiles (user_id, bio) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $bio);
            }
            
            $success = $stmt->execute();
            break;
            
        case 'skills':
            $skills = $_POST['skills'] ?? '';
            
            if ($profile_exists) {
                $stmt = $conn->prepare("UPDATE profiles SET skills = ? WHERE user_id = ?");
                $stmt->bind_param("si", $skills, $user_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO profiles (user_id, skills) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $skills);
            }
            
            $success = $stmt->execute();
            break;
            
        case 'experience':
            $experience = $_POST['experience'] ?? '';
            
            if ($profile_exists) {
                $stmt = $conn->prepare("UPDATE profiles SET experience = ? WHERE user_id = ?");
                $stmt->bind_param("si", $experience, $user_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO profiles (user_id, experience) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $experience);
            }
            
            $success = $stmt->execute();
            break;
            
        case 'education':
            // Get education field values
            $university = $_POST['university'] ?? '';
            $major = $_POST['major'] ?? '';
            $edu_duration = $_POST['edu_duration'] ?? '';
            $is_student = isset($_POST['is_student']) ? (int)$_POST['is_student'] : 0;

            if ($profile_exists) {
                // Update existing profile
                $stmt = $conn->prepare("UPDATE profiles SET university = ?, major = ?, edu_duration = ?, is_student = ? WHERE user_id = ?");
                $stmt->bind_param("sssii", $university, $major, $edu_duration, $is_student, $user_id);
            } else {
                // Create new profile
                $stmt = $conn->prepare("INSERT INTO profiles (user_id, university, major, edu_duration, is_student) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $user_id, $university, $major, $edu_duration, $is_student);
            }
            
            $success = $stmt->execute();
            break;
            
        case 'contact':
            $name = $_POST['name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $district = $_POST['district'] ?? '';
            $address = $_POST['address'] ?? '';
            
            if ($profile_exists) {
                $stmt = $conn->prepare("UPDATE profiles SET name = ?, phone = ?, district = ?, address = ? WHERE user_id = ?");
                $stmt->bind_param("ssssi", $name, $phone, $district, $address, $user_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO profiles (user_id, name, phone, district, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $name, $phone, $district, $address);
            }
            
            $success = $stmt->execute();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid section: ' . $section]);
            exit();
    }

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . ($stmt->error ?? $conn->error)]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>