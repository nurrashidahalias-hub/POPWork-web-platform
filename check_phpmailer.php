<?php
// Test if PHPMailer is properly loaded

// Update this path to match your setup
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h2>PHPMailer Installation Check</h2>";

// Try to create a PHPMailer object
try {
    $mail = new PHPMailer(true);
    echo "✅ <strong>SUCCESS!</strong> PHPMailer is installed correctly.<br>";
    echo "PHPMailer Version: " . PHPMailer::VERSION . "<br>";
    echo "<br>You can now use the email verification system!";
} catch (Exception $e) {
    echo "❌ <strong>ERROR:</strong> " . $e->getMessage();
}
?>
