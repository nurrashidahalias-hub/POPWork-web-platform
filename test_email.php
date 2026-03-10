<?php
// ============================================
// EMAIL CONFIGURATION TEST SCRIPT
// File: test_email.php
// ============================================
// Use this to test if your email configuration is working
// Access: http://localhost/popwork/test_email.php
// ============================================

require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// Set your test email address here
$test_email = 'your-test-email@gmail.com'; // CHANGE THIS!

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
        .config-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        .config-info strong {
            color: #8a1538;
        }
        .result {
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .btn {
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email Configuration Test</h1>
        
        <div class="config-info">
            <strong>Current SMTP Configuration:</strong><br>
            Host: <?php echo SMTP_HOST; ?><br>
            Port: <?php echo SMTP_PORT; ?><br>
            Username: <?php echo SMTP_USERNAME; ?><br>
            From: <?php echo SMTP_FROM_EMAIL; ?><br>
            Test Email: <code><?php echo $test_email; ?></code>
        </div>

        <?php if ($test_email === 'your-test-email@gmail.com'): ?>
            <div class="warning">
                ⚠️ <strong>Warning:</strong> Please update the <code>$test_email</code> variable at the top of this file with your actual email address!
            </div>
        <?php endif; ?>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                
                // Enable verbose debug output (for testing)
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = function($str, $level) {
                    echo "<div style='background: #f4f4f4; padding: 5px; margin: 2px 0; font-size: 12px; font-family: monospace;'>$str</div>";
                };
                
                // Recipients
                $mail->setFrom(SMTP_FROM_EMAIL, SITE_NAME);
                $mail->addAddress($test_email);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Test Email from ' . SITE_NAME;
                $mail->Body    = '
                    <html>
                    <body style="font-family: Arial, sans-serif; padding: 20px;">
                        <h2 style="color: #8a1538;">Email Test Successful! ✓</h2>
                        <p>If you are reading this, your email configuration is working correctly.</p>
                        <p><strong>Configuration Details:</strong></p>
                        <ul>
                            <li>SMTP Host: ' . SMTP_HOST . '</li>
                            <li>SMTP Port: ' . SMTP_PORT . '</li>
                            <li>From: ' . SMTP_FROM_EMAIL . '</li>
                        </ul>
                        <p>You can now proceed with implementing the email verification system.</p>
                        <hr>
                        <p style="font-size: 12px; color: #666;">This is an automated test email from ' . SITE_NAME . '</p>
                    </body>
                    </html>
                ';
                $mail->AltBody = 'Email Test Successful! If you are reading this, your email configuration is working correctly.';
                
                $mail->send();
                echo '<div class="result success">';
                echo '<strong>✓ Success!</strong><br>';
                echo 'Test email has been sent to: ' . htmlspecialchars($test_email) . '<br>';
                echo 'Please check your inbox (and spam folder) to confirm delivery.';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="result error">';
                echo '<strong>✗ Error:</strong><br>';
                echo 'Email could not be sent.<br>';
                echo 'Mailer Error: ' . $mail->ErrorInfo;
                echo '</div>';
            }
        }
        ?>

        <form method="POST" style="margin-top: 20px;">
            <button type="submit" class="btn">Send Test Email</button>
        </form>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 13px; color: #666;">
            <strong>Troubleshooting Tips:</strong>
            <ul style="margin-top: 10px; line-height: 1.8;">
                <li>Make sure you're using a Gmail <strong>App Password</strong>, not your regular password</li>
                <li>Check that 2-Step Verification is enabled on your Google Account</li>
                <li>Verify all credentials in <code>config.php</code> are correct</li>
                <li>Check if port 587 is open on your server/firewall</li>
                <li>For localhost testing, ensure your local server has internet access</li>
            </ul>
        </div>

        <div style="margin-top: 20px; text-align: center;">
            <a href="register.html" style="color: #8a1538; text-decoration: none; font-weight: 600;">
                ← Back to Registration
            </a>
        </div>
    </div>
</body>
</html>