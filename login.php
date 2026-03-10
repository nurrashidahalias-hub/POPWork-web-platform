<?php
// ============================================
// LOGIN HANDLER - NO EMAIL VERIFICATION REQUIRED
// File: backend/login.php
// ============================================

session_start();
require_once '../config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.html');
    exit;
}

// Get and sanitize inputs
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$password = $_POST['password'];

// Validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Invalid email address';
    header('Location: ../login.html');
    exit;
}

if (empty($password)) {
    $_SESSION['error'] = 'Password is required';
    header('Location: ../login.html');
    exit;
}

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get user from database
    $stmt = $conn->prepare("SELECT user_id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = 'Invalid email or password';
        $stmt->close();
        $conn->close();
        header('Location: ../login.html');
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Verify password
    if (!password_verify($password, $user['password'])) {
        $_SESSION['error'] = 'Invalid email or password';
        $conn->close();
        header('Location: ../login.html');
        exit;
    }

    // ============================================
    // EMAIL VERIFICATION CHECK REMOVED!
    // Users can now login without verifying email
    // ============================================

    // ============================================
    // LOGIN SUCCESSFUL!
    // ============================================
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;

    // Update last activity
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    // Set success message
    $_SESSION['success'] = 'Login successful! Welcome back.';

    // Redirect based on role
    if ($user['role'] === 'worker') {
        header('Location: ../worker_dashboard.php');
    } elseif ($user['role'] === 'employer') {
        header('Location: ../employer_dashboard.php');
    } elseif ($user['role'] === 'admin') {
        header('Location: ../admin_dashboard.php');
    } else {
        // Default redirect
        header('Location: ../index.php');
    }
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = 'An error occurred. Please try again.';
    error_log("Login error: " . $e->getMessage());
    header('Location: ../login.html');
    exit;
}
?>