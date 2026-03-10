<?php
// ============================================
// DATABASE & EMAIL CONFIGURATION
// ============================================

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Change to your database username
define('DB_PASS', '');              // Change to your database password
define('DB_NAME', 'popwork');

// Email Configuration (using PHPMailer)
define('SMTP_HOST', 'smtp.gmail.com');      // SMTP server
define('SMTP_PORT', 587);                    // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_USERNAME', 'popworkco@gmail.com');  // Your email address
define('SMTP_PASSWORD', 'qjqe kyth cmbc wckh');     // Your email app password (NOT regular password!)
define('SMTP_FROM_EMAIL', 'popworkco@gmail.com');
define('SMTP_FROM_NAME', 'POP!Work');

// Site Configuration
define('SITE_URL', 'http://localhost/popwork');  // Change to your site URL (no trailing slash)
define('SITE_NAME', 'POP!Work');

// Verification Token Settings
define('TOKEN_EXPIRY_HOURS', 24);  // Verification link expires after 24 hours

// Database Connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
        
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}
?>