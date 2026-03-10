<?php
session_start();
include("db_connect.php");

// ✅ Ensure only employers can update status
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
  header("Location: ../login.html");
  exit();
}

// ✅ Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $application_id = intval($_POST['application_id']);
  $status = $_POST['status'];

  // ✅ Validate input
  $allowed = ['Pending', 'Shortlisted', 'Accepted', 'Rejected'];
  if (!in_array($status, $allowed)) {
    echo "<script>alert('Invalid status!'); window.history.back();</script>";
    exit();
  }

  // ✅ Get job_id to redirect back to correct page
  $job_query = $conn->prepare("SELECT job_id FROM applications WHERE application_id = ?");
  $job_query->bind_param("i", $application_id);
  $job_query->execute();
  $result = $job_query->get_result();
  $job = $result->fetch_assoc();
  $job_id = $job ? $job['job_id'] : 0;
  $job_query->close();

  if ($job_id == 0) {
    echo "<script>alert('Job not found.'); window.history.back();</script>";
    exit();
  }

  // ✅ Update status in DB
  $sql = "UPDATE applications SET status = ? WHERE application_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("si", $status, $application_id);

  if ($stmt->execute()) {
    echo "<script>
      alert('Application status updated successfully!');
      window.location='../backend/view_applicants.php?job_id=$job_id';
    </script>";
  } else {
    echo "<script>alert('Failed to update status.'); window.history.back();</script>";
  }

  $stmt->close();
  $conn->close();
}
?>
