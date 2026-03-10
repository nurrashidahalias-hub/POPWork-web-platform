<?php
session_start();
include("db_connect.php");

// Ensure only employers can access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
    header("Location: ../login.html");
    exit();
}

$employer_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['application_id'])) {
        $_SESSION['error_message'] = "Application ID is required";
        header("Location: ../job_completion_payment.php");
        exit();
    }

    $application_id = intval($_POST['application_id']);

    // Verify the application belongs to this employer's job
    $verify_sql = "SELECT a.application_id, a.user_id, a.job_id, j.title, j.employer_id
                   FROM applications a
                   JOIN jobs j ON a.job_id = j.job_id
                   WHERE a.application_id = ? AND j.employer_id = ?";
    
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $application_id, $employer_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Unauthorized access or application not found";
        header("Location: ../job_completion_payment.php");
        exit();
    }

    $application = $result->fetch_assoc();

    // Check if there are any active clock-in sessions
    $active_check_sql = "SELECT COUNT(*) as active_count 
                        FROM attendance 
                        WHERE application_id = ? AND clock_out_time IS NULL";
    $active_stmt = $conn->prepare($active_check_sql);
    $active_stmt->bind_param("i", $application_id);
    $active_stmt->execute();
    $active_result = $active_stmt->get_result()->fetch_assoc();

    if ($active_result['active_count'] > 0) {
        $_SESSION['error_message'] = "Cannot mark as complete. Worker is still clocked in. Please wait for them to clock out.";
        header("Location: ../job_completion_payment.php");
        exit();
    }

    // Calculate total work hours from attendance records
    $hours_sql = "SELECT 
                    SUM(total_hours) as total_hours,
                    COUNT(*) as total_sessions
                  FROM attendance 
                  WHERE application_id = ? AND clock_out_time IS NOT NULL";
    
    $hours_stmt = $conn->prepare($hours_sql);
    $hours_stmt->bind_param("i", $application_id);
    $hours_stmt->execute();
    $hours_result = $hours_stmt->get_result()->fetch_assoc();

    $total_hours = $hours_result['total_hours'] ?? 0;
    $total_sessions = $hours_result['total_sessions'] ?? 0;

    if ($total_hours == 0) {
        $_SESSION['error_message'] = "Cannot mark as complete. No attendance records found for this job.";
        header("Location: ../job_completion_payment.php");
        exit();
    }

    // Get pay rate to calculate payment amount
    $pay_rate_sql = "SELECT pay_rate FROM jobs WHERE job_id = ?";
    $pay_stmt = $conn->prepare($pay_rate_sql);
    $pay_stmt->bind_param("i", $application['job_id']);
    $pay_stmt->execute();
    $pay_result = $pay_stmt->get_result()->fetch_assoc();
    $pay_rate = $pay_result['pay_rate'] ?? 0;

    $payment_amount = $total_hours * $pay_rate;

    // Mark job as completed
    $update_sql = "UPDATE applications 
                   SET job_completed = 1,
                       completion_date = NOW(),
                       total_work_hours = ?,
                       payment_amount = ?,
                       payment_status = 'pending'
                   WHERE application_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ddi", $total_hours, $payment_amount, $application_id);

    if ($update_stmt->execute()) {
        // Log the completion
        $log_sql = "INSERT INTO job_completion_log (application_id, job_id, worker_id, employer_id, total_hours, payment_amount, completed_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("iiiidd", 
            $application_id, 
            $application['job_id'], 
            $application['user_id'], 
            $employer_id, 
            $total_hours, 
            $payment_amount
        );
        $log_stmt->execute();

        $_SESSION['success_message'] = "Job marked as complete! Total hours: " . number_format($total_hours, 2) . "h. Payment amount: RM " . number_format($payment_amount, 2);
    } else {
        $_SESSION['error_message'] = "Failed to mark job as complete: " . $conn->error;
    }

    $verify_stmt->close();
    $active_stmt->close();
    $hours_stmt->close();
    $pay_stmt->close();
    $update_stmt->close();
    if (isset($log_stmt)) $log_stmt->close();

} else {
    $_SESSION['error_message'] = "Invalid request method";
}

$conn->close();
header("Location: ../job_completion_payment.php");
exit();
?>