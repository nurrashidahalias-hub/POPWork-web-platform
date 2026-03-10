<?php
// ============================================
// REGISTRATION HANDLER WITH EMAIL VERIFICATION
// File: register_handler.php
// ============================================

session_start();
require_once 'config.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// Set JSON header for API responses
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get database connection
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Sanitize and validate inputs
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    if ($password !== $confirm_password) {
        throw new Exception('Passwords do not match');
    }
    
    if (!in_array($role, ['worker', 'employer'])) {
        throw new Exception('Invalid role selected');
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id, is_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $existing_user = $result->fetch_assoc();
        if ($existing_user['is_verified'] == 1) {
            throw new Exception('This email is already registered. Please login instead.');
        } else {
            throw new Exception('This email is already registered but not verified. Please check your email for the verification link.');
        }
    }
    $stmt->close();
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $token_expires = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_HOURS . ' hours'));
    
    // Get name from form - combine first_name and last_name if they exist
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $full_name = trim($first_name . ' ' . $last_name);
    
    // If no name provided, use company name for employers
    if (empty($full_name) && isset($_POST['company_name'])) {
        $full_name = trim($_POST['company_name']);
    }
    
    // Insert user into database - NOW INCLUDING NAME!
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, is_verified, verification_token, verification_token_expires) VALUES (?, ?, ?, ?, 0, ?, ?)");
    $stmt->bind_param("ssssss", $full_name, $email, $hashed_password, $role, $verification_token, $token_expires);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create account. Please try again.');
    }
    
    $user_id = $conn->insert_id;
    $stmt->close();
    
    // Create profile based on role
    $name = $full_name; // Use the same full name
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
    $address = isset($_POST['address']) ? trim($_POST['address']) : null;
    
    // Common fields for both roles
    $date_of_birth = isset($_POST['date_of_birth']) && !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : null;
    $district = isset($_POST['district']) ? trim($_POST['district']) : null;
    
    if ($role === 'worker') {
        $skills = isset($_POST['skills']) ? trim($_POST['skills']) : null;
        $bio = isset($_POST['bio']) ? trim($_POST['bio']) : null;
        $university = isset($_POST['university']) ? trim($_POST['university']) : null;
        $is_student = isset($_POST['is_student']) ? 1 : 0;
        $experience = isset($_POST['experience']) ? trim($_POST['experience']) : null;
        
        // Insert worker profile with ALL fields
        $stmt = $conn->prepare("INSERT INTO profiles (user_id, name, phone, address, skills, bio, university, is_student, experience, date_of_birth, gender, district) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssssissss", $user_id, $name, $phone, $address, $skills, $bio, $university, $is_student, $experience, $date_of_birth, $gender, $district);
        $stmt->execute();
        $stmt->close();
        
    } else if ($role === 'employer') {
        $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : null;
        $company_industry = isset($_POST['company_industry']) ? trim($_POST['company_industry']) : null;
        $company_size = isset($_POST['company_size']) ? trim($_POST['company_size']) : null;
        $company_description = isset($_POST['company_description']) ? trim($_POST['company_description']) : null;
        
        // For employer, use company_name as name in profile
        $profile_name = $company_name ?: $name;
        $bio = $company_description;
        
        // Insert employer profile with ALL fields
        $stmt = $conn->prepare("INSERT INTO profiles (user_id, name, phone, address, bio, date_of_birth, gender, district) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $profile_name, $phone, $address, $bio, $date_of_birth, $gender, $district);
        $stmt->execute();
        $stmt->close();
    }
    
    // Handle file uploads if present
    handleFileUploads($user_id, $role);
    
    // Send verification email
    $email_sent = sendVerificationEmail($email, $verification_token, $name);
    
    if (!$email_sent) {
        // Even if email fails, account is created
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully, but we could not send the verification email. Please contact support.',
            'show_resend' => true
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully! Please check your email to verify your account. The verification link will expire in ' . TOKEN_EXPIRY_HOURS . ' hours.'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

// ============================================
// SEND VERIFICATION EMAIL FUNCTION
// ============================================
function sendVerificationEmail($email, $token, $name = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $name);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your ' . SITE_NAME . ' Account';
        
        $verification_link = SITE_URL . '/verify_email.php?token=' . $token;
        
        $mail->Body = getEmailTemplate($verification_link, $name);
        $mail->AltBody = "Please verify your email by clicking this link: " . $verification_link . "\n\nThis link will expire in " . TOKEN_EXPIRY_HOURS . " hours.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// ============================================
// EMAIL HTML TEMPLATE
// ============================================
function getEmailTemplate($verification_link, $name) {
    $greeting = $name ? "Hello " . htmlspecialchars($name) : "Hello";
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #ffffff; padding: 40px 30px; border: 1px solid #e0e0e0; }
            .button { display: inline-block; padding: 15px 40px; background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%); color: white !important; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 10px 10px; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin:0;'>Welcome to " . SITE_NAME . "!</h1>
            </div>
            <div class='content'>
                <h2>{$greeting},</h2>
                <p>Thank you for registering with " . SITE_NAME . "! We're excited to have you on board.</p>
                <p>To complete your registration and start using your account, please verify your email address by clicking the button below:</p>
                
                <div style='text-align: center;'>
                    <a href='{$verification_link}' class='button'>Verify My Email</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style='background: #f5f5f5; padding: 10px; word-break: break-all; font-size: 12px;'>{$verification_link}</p>
                
                <div class='warning'>
                    <strong>⚠️ Important:</strong> This verification link will expire in " . TOKEN_EXPIRY_HOURS . " hours for security reasons.
                </div>
                
                <p>If you did not create an account with " . SITE_NAME . ", please ignore this email.</p>
                
                <p>Best regards,<br>The " . SITE_NAME . " Team</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                <p>This is an automated email, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

// ============================================
// HANDLE FILE UPLOADS
// ============================================
function handleFileUploads($user_id, $role) {
    // Handle resume upload for workers
    if ($role === 'worker' && isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/resumes/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $new_filename = 'resume_' . $user_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['resume']['tmp_name'], $upload_path)) {
            // Update profile with resume URL
            $conn = getDBConnection();
            $stmt = $conn->prepare("UPDATE profiles SET resume_url = ? WHERE user_id = ?");
            $stmt->bind_param("si", $upload_path, $user_id);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        }
    }
    
    // Handle profile photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/photos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
            // Update profile with photo URL
            $conn = getDBConnection();
            $stmt = $conn->prepare("UPDATE profiles SET photo_url = ? WHERE user_id = ?");
            $stmt->bind_param("si", $upload_path, $user_id);
            $stmt->execute();
            $stmt->close();
            $conn->close();
        }
    }
}
?>