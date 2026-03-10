<?php
// ============================================
// EMAIL VERIFICATION PAGE
// File: verify_email.php
// ============================================

require_once 'config.php';

$message = '';
$message_type = '';
$verified = false;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $message = 'Invalid verification link. Please check your email and try again.';
    $message_type = 'error';
} else {
    $token = $_GET['token'];
    
    try {
        $conn = getDBConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        // Find user with this token
        $stmt = $conn->prepare("SELECT user_id, email, is_verified, verification_token_expires FROM users WHERE verification_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $message = 'Invalid verification link. This link may have already been used or is incorrect.';
            $message_type = 'error';
        } else {
            $user = $result->fetch_assoc();
            
            // Check if already verified
            if ($user['is_verified'] == 1) {
                $message = 'Your email has already been verified. You can now login to your account.';
                $message_type = 'info';
                $verified = true;
            }
            // Check if token has expired
            else if (strtotime($user['verification_token_expires']) < time()) {
                $message = 'This verification link has expired. Please register again or contact support for assistance.';
                $message_type = 'error';
            }
            // Verify the email
            else {
                $stmt_update = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE user_id = ?");
                $stmt_update->bind_param("i", $user['user_id']);
                
                if ($stmt_update->execute()) {
                    $message = 'Email verified successfully! Your account is now active. You can login to start using ' . SITE_NAME . '.';
                    $message_type = 'success';
                    $verified = true;
                } else {
                    $message = 'Failed to verify email. Please try again or contact support.';
                    $message_type = 'error';
                }
                
                $stmt_update->close();
            }
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $message = 'An error occurred: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verification-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            padding: 50px 40px;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .icon.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .icon.error {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }

        .icon.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .message {
            font-size: 18px;
            color: #333;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .button {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(138, 21, 56, 0.3);
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(138, 21, 56, 0.4);
        }

        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 13px;
            color: #666;
        }

        .footer a {
            color: #8a1538;
            text-decoration: none;
            font-weight: 600;
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>Email Verification</p>
        </div>

        <div class="content">
            <div class="icon <?php echo $message_type; ?>">
                <?php if ($message_type === 'success'): ?>
                    ✓
                <?php elseif ($message_type === 'error'): ?>
                    ✗
                <?php else: ?>
                    ℹ
                <?php endif; ?>
            </div>

            <div class="message">
                <?php echo $message; ?>
            </div>

            <?php if ($verified): ?>
                <a href="login.html" class="button">Go to Login</a>
            <?php else: ?>
                <a href="register.html" class="button">Back to Register</a>
            <?php endif; ?>
        </div>

        <div class="footer">
            Need help? <a href="mailto:<?php echo SMTP_FROM_EMAIL; ?>">Contact Support</a>
        </div>
    </div>
</body>
</html>