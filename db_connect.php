<?php
/**
 * Database Connection Configuration
 * Uses environment variables for security
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
// IMPORTANT: Create a .env file in root with these values
// Or use these default values for local development
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';
$db_name = $_ENV['DB_NAME'] ?? 'popwork';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Sorry, we're experiencing technical difficulties. Please try again later.");
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Optional: Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');
?>