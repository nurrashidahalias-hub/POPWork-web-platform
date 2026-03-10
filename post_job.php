<?php
// backend/post_job.php
// UPDATED VERSION - Now includes geofence location tracking features
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db_connect.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $employer_id = $_SESSION['user_id'];
        
        // Get and validate basic fields
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $pay_rate = floatval($_POST['pay_rate'] ?? 0);
        $pay_period = trim($_POST['pay_period'] ?? '');
        
        // Validate required fields
        if (empty($title)) throw new Exception('Job title is required');
        if (empty($category)) throw new Exception('Job category is required');
        if (empty($description)) throw new Exception('Job description is required');
        if (empty($location)) throw new Exception('Location is required');
        if ($pay_rate <= 0) throw new Exception('Valid pay rate is required');
        if (empty($pay_period)) throw new Exception('Pay period is required');
        
        // Optional fields with defaults
        $job_duration = $_POST['job_duration'] ?? 'Permanent';
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $experience_level = $_POST['experience_level'] ?? 'Any';
        $required_documents = trim($_POST['required_documents'] ?? '');
        $languages_required = trim($_POST['languages_required'] ?? '');
        $positions_available = intval($_POST['positions_available'] ?? 1);
        $is_urgent = intval($_POST['is_urgent'] ?? 0);
        $auto_expire_days = intval($_POST['auto_expire_days'] ?? 30);
        
        // MAP COORDINATES - Check BOTH possible field names (for backward compatibility)
        $latitude = null;
        $longitude = null;
        
        // Old job_post.php sends as 'latitude' and 'longitude'
        if (!empty($_POST['latitude']) && is_numeric($_POST['latitude'])) {
            $latitude = floatval($_POST['latitude']);
        } elseif (!empty($_POST['lat']) && is_numeric($_POST['lat'])) {
            $latitude = floatval($_POST['lat']);
        }
        
        if (!empty($_POST['longitude']) && is_numeric($_POST['longitude'])) {
            $longitude = floatval($_POST['longitude']);
        } elseif (!empty($_POST['lng']) && is_numeric($_POST['lng'])) {
            $longitude = floatval($_POST['lng']);
        }
        
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
                throw new Exception('Job location coordinates are required when on-site attendance is enabled');
            }
            
            // Validate geofence radius is within acceptable range (50m - 500m)
            if ($geofence_radius < 50 || $geofence_radius > 500) {
                $geofence_radius = 100; // Reset to default if invalid
            }
        }
        
        // ========================================================================
        
        // Add missing fields that job_post.php sends
        $responsibilities = trim($_POST['responsibilities'] ?? '[]');
        $skills = trim($_POST['skills'] ?? '[]');
        $schedule = trim($_POST['schedule'] ?? '');
        $benefits = trim($_POST['benefits'] ?? '');
        
        // Validate JSON fields
        if (!empty($responsibilities)) {
            $resp_array = json_decode($responsibilities, true);
            if ($resp_array === null && $responsibilities !== '[]') {
                throw new Exception('Invalid responsibilities format');
            }
        }
        
        if (!empty($skills)) {
            $skills_array = json_decode($skills, true);
            if ($skills_array === null && $skills !== '[]') {
                throw new Exception('Invalid skills format');
            }
        }
        
        // Calculate expiry date
        $expires_at = date('Y-m-d H:i:s', strtotime("+$auto_expire_days days"));
        
        // Check database connection
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        // UPDATED INSERT statement with GEOFENCE fields
        $sql = "INSERT INTO jobs (
                employer_id, title, category, description, location, 
                latitude, longitude, pay_rate, pay_period, job_duration,
                start_date, end_date, experience_level, required_documents,
                languages_required, responsibilities, skills, schedule, benefits,
                positions_available, is_urgent, expires_at, auto_expire_days,
                require_onsite_attendance, job_latitude, job_longitude, geofence_radius,
                status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                'available', NOW()
            )";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        
        // Bind parameters - 27 parameters total (added 4 new geofence fields)
        // Type string breakdown:
        // i s s s s (employer_id, title, category, description, location)
        // d d d s s (latitude, longitude, pay_rate, pay_period, job_duration)
        // s s s s (start_date, end_date, experience_level, required_documents)
        // s s s s s (languages_required, responsibilities, skills, schedule, benefits)
        // i i s i (positions_available, is_urgent, expires_at, auto_expire_days)
        // i d d i (require_onsite_attendance, job_latitude, job_longitude, geofence_radius) <- NEW
        
        $stmt->bind_param(
            "issssddssssssssssssiisiiddi",
            $employer_id,               // 1. i - Employer ID
            $title,                     // 2. s - Job title
            $category,                  // 3. s - Category
            $description,               // 4. s - Description
            $location,                  // 5. s - Location
            $latitude,                  // 6. d - Latitude (can be NULL)
            $longitude,                 // 7. d - Longitude (can be NULL)
            $pay_rate,                  // 8. d - Pay rate
            $pay_period,                // 9. s - Pay period
            $job_duration,              // 10. s - Duration
            $start_date,                // 11. s - Start date (can be NULL)
            $end_date,                  // 12. s - End date (can be NULL)
            $experience_level,          // 13. s - Experience level
            $required_documents,        // 14. s - Required documents
            $languages_required,        // 15. s - Languages
            $responsibilities,          // 16. s - Responsibilities JSON
            $skills,                    // 17. s - Skills JSON
            $schedule,                  // 18. s - Schedule
            $benefits,                  // 19. s - Benefits
            $positions_available,       // 20. i - Positions
            $is_urgent,                 // 21. i - Urgent flag
            $expires_at,                // 22. s - Expiry datetime
            $auto_expire_days,          // 23. i - Auto expire days
            $require_onsite_attendance, // 24. i - Geofence required flag (NEW)
            $job_latitude,              // 25. d - Job site latitude (NEW)
            $job_longitude,             // 26. d - Job site longitude (NEW)
            $geofence_radius            // 27. i - Geofence radius in meters (NEW)
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Database execute error: ' . $stmt->error);
        }
        
        $job_id = $stmt->insert_id;
        
        // Debug logging (remove in production)
        error_log("Job posted successfully: ID=$job_id, Coordinates: ($latitude, $longitude), Geofence: " . 
                  ($require_onsite_attendance ? "Enabled ({$geofence_radius}m)" : "Disabled"));
        
        $stmt->close();
        
        // Handle "Save as Template" feature if checkbox was checked
        if (isset($_POST['save_as_template']) && $_POST['save_as_template'] == '1') {
            $template_name = trim($_POST['template_name'] ?? '');
            
            if (!empty($template_name)) {
                $tpl_sql = "INSERT INTO job_templates (
                    employer_id, template_name, title, category, location,
                    pay_rate, pay_period, description, job_duration,
                    responsibilities, skills, is_default
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
                
                $tpl_stmt = $conn->prepare($tpl_sql);
                
                if ($tpl_stmt) {
                    $tpl_stmt->bind_param(
                        "issssdsssss",
                        $employer_id,
                        $template_name,
                        $title,
                        $category,
                        $location,
                        $pay_rate,
                        $pay_period,
                        $description,
                        $job_duration,
                        $responsibilities,
                        $skills
                    );
                    
                    $tpl_stmt->execute();
                    $tpl_stmt->close();
                }
            }
        }
        
        // Success - redirect with message
        header('Location: ../job_post.php?success=' . urlencode('Job posted successfully!'));
        exit();
        
    } catch (Exception $e) {
        // Log error
        error_log('Job posting error: ' . $e->getMessage());
        
        // For debugging - show actual error
        die('ERROR: ' . $e->getMessage() . '<br><br><a href="../job_post.php">Go Back</a>');
        
        // For production, use:
        // header('Location: ../job_post.php?error=' . urlencode($e->getMessage()));
        // exit();
    }
    
} else {
    header('Location: ../job_post.php');
    exit();
}
?>