<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'worker') {
    header("Location: ../login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Helper function to calculate distance between two coordinates (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Earth radius in meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;
    
    return round($distance, 2); // Return distance in meters
}

if ($action === 'clock_in') {
    $application_id = intval($_POST['application_id']);
    $job_id = intval($_POST['job_id']);
    $employer_id = intval($_POST['employer_id']);
    
    // Get worker's current location
    $worker_lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $worker_lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    
    // Get job details including geofence settings
    $job_sql = "SELECT require_onsite_attendance, job_latitude, job_longitude, geofence_radius, title 
                FROM jobs WHERE job_id = ?";
    $job_stmt = $conn->prepare($job_sql);
    $job_stmt->bind_param("i", $job_id);
    $job_stmt->execute();
    $job = $job_stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        $_SESSION['error'] = "Job not found.";
        header("Location: ../attendance.php");
        exit();
    }
    
    $location_verified = 0;
    $distance = null;
    
    // Check if job requires onsite attendance
    if ($job['require_onsite_attendance']) {
        // Validate that we have location data
        if (!$worker_lat || !$worker_lng) {
            $_SESSION['error'] = "Location access is required for this job. Please enable location services and try again.";
            header("Location: ../attendance.php");
            exit();
        }
        
        // Validate job has geofence coordinates
        if (!$job['job_latitude'] || !$job['job_longitude']) {
            $_SESSION['error'] = "Job location not properly configured. Please contact the employer.";
            header("Location: ../attendance.php");
            exit();
        }
        
        // Calculate distance from job site
        $distance = calculateDistance(
            $worker_lat, 
            $worker_lng, 
            $job['job_latitude'], 
            $job['job_longitude']
        );
        
        // Check if worker is within allowed radius
        $allowed_radius = $job['geofence_radius'] ?? 100; // Default 100m
        
        if ($distance > $allowed_radius) {
            $_SESSION['error'] = "You are too far from the job location. You are " . round($distance) . "m away, but must be within " . $allowed_radius . "m to clock in.";
            header("Location: ../attendance.php");
            exit();
        }
        
        $location_verified = 1; // Location is verified
    }
    
    // Check if already clocked in
    $check_sql = "SELECT attendance_id FROM attendance 
                  WHERE application_id = ? AND clock_out_time IS NULL";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $application_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "You are already clocked in for this job.";
        header("Location: ../attendance.php");
        exit();
    }
    
    // Insert attendance record with location data
    $clock_in_time = date('Y-m-d H:i:s');
    $insert_sql = "INSERT INTO attendance 
                   (application_id, user_id, job_id, employer_id, clock_in_time, 
                    clock_in_latitude, clock_in_longitude, distance_from_job_location, 
                    location_verified, status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'clocked_in')";
    
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iiiisdddi", 
        $application_id, 
        $user_id, 
        $job_id, 
        $employer_id, 
        $clock_in_time,
        $worker_lat,
        $worker_lng,
        $distance,
        $location_verified
    );
    
    if ($stmt->execute()) {
        // Update last_clock_in in applications
        $update_app = "UPDATE applications SET last_clock_in = ? WHERE application_id = ?";
        $app_stmt = $conn->prepare($update_app);
        $app_stmt->bind_param("si", $clock_in_time, $application_id);
        $app_stmt->execute();
        
        $success_msg = "Successfully clocked in!";
        if ($location_verified) {
            $success_msg .= " Location verified: " . round($distance) . "m from job site.";
        }
        $_SESSION['success'] = $success_msg;
    } else {
        $_SESSION['error'] = "Failed to clock in. Please try again.";
    }
    
} elseif ($action === 'clock_out') {
    $attendance_id = intval($_POST['attendance_id']);
    $application_id = intval($_POST['application_id']);
    
    // Get worker's current location for clock out
    $worker_lat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $worker_lng = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    
    // Get attendance record with job details
    $att_sql = "SELECT a.clock_in_time, a.user_id, a.job_id, 
                       j.require_onsite_attendance, j.job_latitude, j.job_longitude, j.geofence_radius
                FROM attendance a
                JOIN jobs j ON a.job_id = j.job_id
                WHERE a.attendance_id = ?";
    $att_stmt = $conn->prepare($att_sql);
    $att_stmt->bind_param("i", $attendance_id);
    $att_stmt->execute();
    $attendance = $att_stmt->get_result()->fetch_assoc();
    
    if (!$attendance) {
        $_SESSION['error'] = "Attendance record not found.";
        header("Location: ../attendance.php");
        exit();
    }
    
    // Verify this attendance belongs to the current user
    if ($attendance['user_id'] != $user_id) {
        $_SESSION['error'] = "Unauthorized action.";
        header("Location: ../attendance.php");
        exit();
    }
    
    $clock_out_distance = null;
    $clock_out_verified = 0;
    
    // Check if job requires onsite attendance for clock out
    if ($attendance['require_onsite_attendance']) {
        // Validate that we have location data
        if (!$worker_lat || !$worker_lng) {
            $_SESSION['error'] = "Location access is required to clock out of this job. Please enable location services and try again.";
            header("Location: ../attendance.php");
            exit();
        }
        
        // Validate job has geofence coordinates
        if (!$attendance['job_latitude'] || !$attendance['job_longitude']) {
            $_SESSION['error'] = "Job location not properly configured. Please contact the employer.";
            header("Location: ../attendance.php");
            exit();
        }
        
        // Calculate distance from job site
        $clock_out_distance = calculateDistance(
            $worker_lat, 
            $worker_lng, 
            $attendance['job_latitude'], 
            $attendance['job_longitude']
        );
        
        // Check if worker is within allowed radius
        $allowed_radius = $attendance['geofence_radius'] ?? 100;
        
        if ($clock_out_distance > $allowed_radius) {
            $_SESSION['error'] = "You are too far from the job location. You are " . round($clock_out_distance) . "m away, but must be within " . $allowed_radius . "m to clock out.";
            header("Location: ../attendance.php");
            exit();
        }
        
        $clock_out_verified = 1; // Location is verified
    }
    
    // Calculate total hours
    $clock_in = new DateTime($attendance['clock_in_time']);
    $clock_out = new DateTime();
    $diff = $clock_in->diff($clock_out);
    $total_hours = $diff->h + ($diff->i / 60) + ($diff->days * 24);
    $total_hours = round($total_hours, 2);
    
    // Update attendance record with clock out
    $update_sql = "UPDATE attendance 
                   SET clock_out_time = NOW(), 
                       clock_out_latitude = ?,
                       clock_out_longitude = ?,
                       clock_out_distance = ?,
                       clock_out_verified = ?,
                       total_hours = ?, 
                       status = 'clocked_out' 
                   WHERE attendance_id = ?";
    
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("dddidi", 
        $worker_lat, 
        $worker_lng, 
        $clock_out_distance,
        $clock_out_verified,
        $total_hours, 
        $attendance_id
    );
    
    if ($stmt->execute()) {
        // Update total work hours in applications
        $update_app = "UPDATE applications 
                       SET total_work_hours = total_work_hours + ? 
                       WHERE application_id = ?";
        $app_stmt = $conn->prepare($update_app);
        $app_stmt->bind_param("di", $total_hours, $application_id);
        $app_stmt->execute();
        
        $success_msg = "Successfully clocked out! Total hours: " . $total_hours . "h";
        if ($clock_out_verified) {
            $success_msg .= " Location verified: " . round($clock_out_distance) . "m from job site.";
        }
        $_SESSION['success'] = $success_msg;
    } else {
        $_SESSION['error'] = "Failed to clock out. Please try again.";
    }
}

header("Location: ../attendance.php");
exit();
?>