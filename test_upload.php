<?php
// Save this as: backend/test_upload.php
// Then visit it in your browser: http://yoursite.com/backend/test_upload.php

echo "<h2>Image Upload Diagnostics</h2>";

// Check if backend directory exists
$backend_dir = __DIR__;
echo "<p><strong>Backend directory:</strong> $backend_dir</p>";

// Check uploads directory
$upload_dir = '../uploads/chat/';
$full_path = realpath($backend_dir . '/' . $upload_dir);

echo "<p><strong>Expected upload directory:</strong> $upload_dir</p>";
echo "<p><strong>Full path:</strong> " . ($full_path ?: "NOT FOUND") . "</p>";

if (file_exists($backend_dir . '/' . $upload_dir)) {
    echo "<p style='color: green;'>✓ Upload directory EXISTS</p>";
    
    if (is_writable($backend_dir . '/' . $upload_dir)) {
        echo "<p style='color: green;'>✓ Upload directory is WRITABLE</p>";
    } else {
        echo "<p style='color: red;'>✗ Upload directory is NOT writable</p>";
        echo "<p>Run: <code>chmod 777 " . $full_path . "</code></p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ Upload directory does NOT exist (will be created automatically)</p>";
    
    // Try to create it
    if (mkdir($backend_dir . '/' . $upload_dir, 0777, true)) {
        echo "<p style='color: green;'>✓ Successfully created upload directory</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create upload directory</p>";
    }
}

// Check PHP upload settings
echo "<h3>PHP Upload Configuration:</h3>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";

// Test file creation
$test_file = $backend_dir . '/' . $upload_dir . 'test_' . time() . '.txt';
if (@file_put_contents($test_file, 'test')) {
    echo "<p style='color: green;'>✓ Can write test file: $test_file</p>";
    unlink($test_file);
} else {
    echo "<p style='color: red;'>✗ Cannot write test file to upload directory</p>";
}
?>