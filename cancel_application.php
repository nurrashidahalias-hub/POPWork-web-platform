<?php
session_start();
include("db_connect.php");

// 1. Safety Check: Only logged-in workers
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'worker') {
    die("Unauthorized access.");
}

if (isset($_GET['id'])) {
    $application_id = intval($_GET['id']);
    $worker_id = $_SESSION['user_id'];

    // 2. Delete the application ONLY if it belongs to this worker
    $sql = "DELETE FROM applications WHERE application_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $application_id, $worker_id);

    if ($stmt->execute()) {
        // Success: Redirect back with a message
        header("Location: ../my_applications.php?msg=cancelled");
    } else {
        echo "Error cancelling application: " . $conn->error;
    }
}
?>