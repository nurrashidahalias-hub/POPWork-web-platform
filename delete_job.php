<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
  header("Location: ../login.html");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $job_id = intval($_POST['job_id']);
  $employer_id = $_SESSION['user_id'];

  $sql = "DELETE FROM jobs WHERE job_id = ? AND employer_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $job_id, $employer_id);

  if ($stmt->execute()) {
    echo "<script>alert('Job deleted successfully!'); window.location='../employer_dashboard.php';</script>";
  } else {
    echo "<script>alert('Error deleting job.'); window.location='../employer_dashboard.php';</script>";
  }
}
?>
