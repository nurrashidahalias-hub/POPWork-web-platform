<?php
session_start();
include("db_connect.php");

if ($_SESSION['role'] === 'admin' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        echo "<script>alert('User deleted successfully.'); window.location='../admin_dashboard.php';</script>";
    } else {
        echo "<script>alert('Error deleting user.'); window.location='../admin_dashboard.php';</script>";
    }
}
?>
