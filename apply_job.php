<?php
session_start();
include("db_connect.php");

// ✅ Ensure worker is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'worker') {
  header("Location: ../login.html");
  exit();
}

$worker_id = $_SESSION['user_id'];

// ✅ Check if job_id exists in POST request
if (!isset($_POST['job_id']) || empty($_POST['job_id'])) {
  echo "<script>alert('Invalid job selection.'); window.location='../worker_dashboard.php';</script>";
  exit();
}

$job_id = intval($_POST['job_id']);

// ✅ Check if the job actually exists
$check_job = $conn->prepare("SELECT job_id FROM jobs WHERE job_id = ?");
$check_job->bind_param("i", $job_id);
$check_job->execute();
$job_exists = $check_job->get_result();
if ($job_exists->num_rows === 0) {
  echo "<script>alert('Job not found or removed.'); window.location='../worker_dashboard.php';</script>";
  exit();
}

// ✅ Check if the worker has already applied
$check = $conn->prepare("SELECT * FROM applications WHERE job_id = ? AND user_id = ?");
$check->bind_param("ii", $job_id, $worker_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
  echo "<script>alert('You have already applied for this job.'); window.location='../my_applications.php';</script>";
  exit();
}

// ✅ Insert new application with default status 'Pending'
$sql = "INSERT INTO applications (job_id, user_id, status) VALUES (?, ?, 'Pending')"; 
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $job_id, $worker_id);

if ($stmt->execute()) {
  echo "<script>alert('Application submitted successfully!'); window.location='../my_applications.php';</script>";
} else {
  echo "<script>alert('Error submitting your application. Please try again.'); window.location='../worker_dashboard.php';</script>";
}

// ✅ Close connections
$stmt->close();
$conn->close();
?>