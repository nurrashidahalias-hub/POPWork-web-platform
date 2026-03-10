<?php
session_start();
include("db_connect.php");

// 🔒 Security Check: Only Admins can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize Input
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role']; // worker, employer, admin
    $phone = trim($_POST['phone']);

    // 2. Validate Email Uniqueness
    $check = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header("Location: ../admin_users.php?error=Email already exists");
        exit();
    }

    // 3. Hash Password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 4. Insert into USERS table
    $stmt = $conn->prepare("INSERT INTO users (email, password, role, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $email, $hashed_password, $role);

    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;

        // 5. Insert into PROFILES table
        // We set a default district or leave it empty for them to fill later
        $stmt_profile = $conn->prepare("INSERT INTO profiles (user_id, name, phone, district) VALUES (?, ?, ?, 'Not Set')");
        $stmt_profile->bind_param("iss", $new_user_id, $name, $phone);
        
        if ($stmt_profile->execute()) {
            header("Location: ../admin_users.php?msg=User created successfully");
            exit();
        } else {
            echo "Error creating profile: " . $conn->error;
        }
    } else {
        echo "Error creating user: " . $conn->error;
    }
}
?>