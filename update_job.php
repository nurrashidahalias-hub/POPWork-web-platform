<?php
session_start();
include("db_connect.php");

// Ensure only employers can update jobs
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
  header("Location: ../login.html");
  exit();
}

// Check form submission
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  echo "<script>alert('Invalid access method.'); window.location='../employer_dashboard.php';</script>";
  exit();
}

// Get and sanitize all inputs
$job_id = intval($_POST['job_id']);
$employer_id = $_SESSION['user_id'];

// Basic Information
$title = trim($_POST['title']);
$category = trim($_POST['category']);
$location = trim($_POST['location']);
$experience_level = trim($_POST['experience_level'] ?? 'Any');
$positions_available = intval($_POST['positions_available'] ?? 1);
$is_urgent = intval($_POST['is_urgent'] ?? 0);
$auto_expire_days = intval($_POST['auto_expire_days'] ?? 30);

// Job Duration
$job_duration = trim($_POST['job_duration'] ?? 'Permanent');
$start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

// Compensation
$pay_rate = floatval($_POST['pay_rate']);
$pay_period = trim($_POST['pay_period']);

// Requirements
$required_documents = trim($_POST['required_documents'] ?? '');
$languages_required = trim($_POST['languages_required'] ?? '');

// Additional fields
$responsibilities = trim($_POST['responsibilities'] ?? '[]');
$skills = trim($_POST['skills'] ?? '[]');
$schedule = trim($_POST['schedule'] ?? '');
$benefits = trim($_POST['benefits'] ?? '');

// Description
$description = trim($_POST['description']);

// Location coordinates (backward compatibility)
$latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

// ========================================================================
// NEW: GEOFENCE LOCATION TRACKING FIELDS
// ========================================================================

// Geofence attendance requirement (0 = not required, 1 = required)
$require_onsite_attendance = intval($_POST['require_onsite_attendance'] ?? 0);

// Job site coordinates (different from general location coordinates)
$job_latitude = null;
$job_longitude = null;
$geofence_radius = 100; // Default 100 meters

// If geofence is enabled, get the job site coordinates
if ($require_onsite_attendance == 1) {
    if (!empty($_POST['job_latitude']) && is_numeric($_POST['job_latitude'])) {
        $job_latitude = floatval($_POST['job_latitude']);
    }
    
    if (!empty($_POST['job_longitude']) && is_numeric($_POST['job_longitude'])) {
        $job_longitude = floatval($_POST['job_longitude']);
    }
    
    if (!empty($_POST['geofence_radius']) && is_numeric($_POST['geofence_radius'])) {
        $geofence_radius = intval($_POST['geofence_radius']);
    }
    
    // Validate that coordinates are set if geofence is required
    if ($job_latitude === null || $job_longitude === null) {
        header("Location: ../edit_job.php?job_id=$job_id&error=" . urlencode("Job location coordinates are required when on-site attendance is enabled."));
        exit();
    }
    
    // Validate geofence radius is within acceptable range (50m - 500m)
    if ($geofence_radius < 50 || $geofence_radius > 500) {
        $geofence_radius = 100; // Reset to default if invalid
    }
} else {
    // If geofence is disabled, clear the coordinates
    $job_latitude = null;
    $job_longitude = null;
    $geofence_radius = 100;
}

// ========================================================================

// Define allowed values
$allowed_categories = ["Gig", "Part-Time", "Full-Time"];
$sabah_districts = [
  "Beaufort", "Beluran", "Keningau", "Kinabatangan", "Kota Belud", "Kota Kinabalu", "Kota Marudu", 
  "Kuala Penyu", "Kudat", "Kunak", "Lahad Datu", "Nabawan", "Papar", "Penampang", "Pitas", "Ranau", 
  "Sandakan", "Semporna", "Sipitang", "Tambunan", "Tamparuli", "Tawau", "Telupid", "Tenom", "Tongod", 
  "Tuaran", "Putatan", "Kalabakan"
];

// Validate selections
if (!in_array($category, $allowed_categories)) {
  header("Location: ../edit_job.php?job_id=$job_id&error=" . urlencode("Invalid category selected."));
  exit();
}

if (!in_array($location, $sabah_districts)) {
  header("Location: ../edit_job.php?job_id=$job_id&error=" . urlencode("Invalid location selected."));
  exit();
}

// Validate required fields
if (empty($title) || empty($description) || $pay_rate <= 0 || $positions_available <= 0) {
  header("Location: ../edit_job.php?job_id=$job_id&error=" . urlencode("Please fill in all required fields correctly."));
  exit();
}

// Validate JSON format for responsibilities and skills
$responsibilities_array = json_decode($responsibilities, true);
$skills_array = json_decode($skills, true);

if ($responsibilities_array === null && $responsibilities !== '[]') {
  header("Location: ../edit_job.php?job_id=$job_id&error=" . urlencode("Invalid responsibilities format."));
  exit();
}

if ($skills_array === null && $skills !== '[]') {
  header("Location: ../edit_job.php?job_id=$job_id&error=" . urlencode("Invalid skills format."));
  exit();
}

// Ensure the job belongs to the logged-in employer
$check = $conn->prepare("SELECT job_id FROM jobs WHERE job_id = ? AND employer_id = ?");
$check->bind_param("ii", $job_id, $employer_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows == 0) {
  header("Location: ../employer_dashboard.php?error=" . urlencode("Unauthorized or job not found."));
  exit();
}
$check->close();

// Calculate new expiry date based on auto_expire_days
$new_expiry = date('Y-m-d', strtotime("+{$auto_expire_days} days"));

/**
 * UPDATED SQL Query with GEOFENCE fields
 * Total: 26 SET fields + 2 WHERE fields = 28 parameters
 */
$sql = "UPDATE jobs SET 
        title = ?, 
        category = ?, 
        location = ?, 
        experience_level = ?, 
        positions_available = ?, 
        is_urgent = ?, 
        auto_expire_days = ?, 
        job_duration = ?, 
        start_date = ?, 
        end_date = ?, 
        pay_rate = ?, 
        pay_period = ?, 
        required_documents = ?, 
        languages_required = ?, 
        responsibilities = ?,
        skills = ?,
        schedule = ?,
        benefits = ?,
        description = ?, 
        latitude = ?, 
        longitude = ?, 
        expires_at = ?,
        require_onsite_attendance = ?,
        job_latitude = ?,
        job_longitude = ?,
        geofence_radius = ?,
        updated_at = NOW()
        WHERE job_id = ? AND employer_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
  header("Location: ../edit_job.php?job_id=$job_id&error=" . urlencode("Database error: " . $conn->error));
  exit();
}

/**
 * UPDATED Type String - 28 parameters
 * s = string (text/date/JSON)
 * i = integer (whole number)
 * d = double (decimal/coordinates)
 * 
 * Breaking down: s s s s i i i s s s d s s s s s s s s d d s i d d i i i
 * Fields 1-8: title, category, location, exp_level, positions, urgent, expire_days, duration
 * Fields 9-10: start_date, end_date
 * Field 11: pay_rate (decimal)
 * Fields 12-18: pay_period, docs, langs, responsibilities, skills, schedule, benefits
 * Field 19: description
 * Fields 20-21: latitude, longitude (decimals)
 * Field 22: expires_at
 * Fields 23: require_onsite_attendance (integer) <- NEW
 * Fields 24-25: job_latitude, job_longitude (decimals) <- NEW
 * Field 26: geofence_radius (integer) <- NEW
 * Fields 27-28: job_id, employer_id (WHERE clause)
 */
$types = "ssssiiisssdssssssssddsiddiii"; 

/**
 * Bind Parameters - All 28 variables in correct order
 */
$stmt->bind_param(
    $types,
    $title,                      // 1. s - Job title
    $category,                   // 2. s - Job type (Gig/Part-Time/Full-Time)
    $location,                   // 3. s - District location
    $experience_level,           // 4. s - Experience level required
    $positions_available,        // 5. i - Number of positions
    $is_urgent,                  // 6. i - Urgency flag (0 or 1)
    $auto_expire_days,           // 7. i - Days until expiry
    $job_duration,               // 8. s - Duration type
    $start_date,                 // 9. s - Start date (can be NULL)
    $end_date,                   // 10. s - End date (can be NULL)
    $pay_rate,                   // 11. d - Pay amount (decimal)
    $pay_period,                 // 12. s - Pay period (Per Hour/Day/etc)
    $required_documents,         // 13. s - Comma-separated documents
    $languages_required,         // 14. s - Comma-separated languages
    $responsibilities,           // 15. s - JSON array of responsibilities
    $skills,                     // 16. s - JSON array of skills
    $schedule,                   // 17. s - Comma-separated schedule options
    $benefits,                   // 18. s - Comma-separated benefits
    $description,                // 19. s - Job description
    $latitude,                   // 20. d - Location latitude (can be NULL)
    $longitude,                  // 21. d - Location longitude (can be NULL)
    $new_expiry,                 // 22. s - New expiry date
    $require_onsite_attendance,  // 23. i - Geofence required flag (NEW)
    $job_latitude,               // 24. d - Job site latitude (NEW)
    $job_longitude,              // 25. d - Job site longitude (NEW)
    $geofence_radius,            // 26. i - Geofence radius in meters (NEW)
    $job_id,                     // 27. i - Job ID (WHERE clause)
    $employer_id                 // 28. i - Employer ID (WHERE clause)
);

// Execute the update with error logging
if ($stmt->execute()) {
    // Log success for debugging
    error_log("Job $job_id updated successfully by employer $employer_id. Geofence: " . 
              ($require_onsite_attendance ? "Enabled ({$geofence_radius}m)" : "Disabled"));
    
    // Check if any rows were actually updated
    if ($stmt->affected_rows > 0 || $stmt->affected_rows === 0) {
        header("Location: ../view_job.php?job_id=$job_id&success=" . urlencode("Job updated successfully!"));
    } else {
        header("Location: ../edit_job.php?job_id=$job_id&error=" . urlencode("No changes were made."));
    }
} else {
    // Log error for debugging
    error_log("Job update failed for job_id $job_id: " . $stmt->error);
    header("Location: ../edit_job.php?job_id=$job_id&error=" . urlencode("Error updating job: " . $stmt->error));
}

$stmt->close();
$conn->close();
?>