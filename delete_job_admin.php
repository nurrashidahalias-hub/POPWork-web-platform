<?php
session_start();
include("db_connect.php");

// ✅ Admin-only access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
  header("Location: ../login.html");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['job_id'])) {
  $job_id = intval($_POST['job_id']);

  // Admin can delete ANY job based on job_id only
  $sql = "DELETE FROM jobs WHERE job_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $job_id);

  if ($stmt->execute()) {
    echo "<script>alert('Job deleted successfully by Admin.'); window.location='../admin_dashboard.php';</script>";
  } else {
    echo "<script>alert('Error deleting job.'); window.location='../admin_dashboard.php';</script>";
  }
  
  $stmt->close();
} else {
    echo "<script>alert('Invalid request.'); window.location='../admin_dashboard.php';</script>";
}
$conn->close();
?>