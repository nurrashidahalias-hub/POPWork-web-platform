<?php
session_start();
include("db_connect.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../register.html");
    exit();
}

// Get form data
$email = trim($_POST['email']);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$role = $_POST['role'];
$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$full_name = $first_name . ' ' . $last_name; // Combine names
$phone = trim($_POST['phone']);
$date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
$gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
$district = $_POST['district'];
$address = !empty($_POST['address']) ? trim($_POST['address']) : null;

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email format");
}

// Check if email already exists
$check_sql = "SELECT user_id FROM users WHERE email = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    die("Email already registered. <a href='../login.html'>Login here</a>");
}

// Start transaction
$conn->begin_transaction();

try {
    // Insert into users table
    $user_sql = "INSERT INTO users (email, password, role, created_at) VALUES (?, ?, ?, NOW())";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("sss", $email, $password, $role);
    
    if (!$user_stmt->execute()) {
        throw new Exception("Failed to create user account");
    }
    
    $user_id = $conn->insert_id;
    
    // Handle file uploads
    $photo_url = null;
    $resume_url = null;
    
    // Define base upload directory (absolute path with DIRECTORY_SEPARATOR)
    $base_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    
    // Create base uploads directory if it doesn't exist
    if (!is_dir($base_dir)) {
        if (!mkdir($base_dir, 0777, true)) {
            error_log("Failed to create base directory: " . $base_dir);
        }
    }
    
    // Upload profile photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo_tmp = $_FILES['photo']['tmp_name'];
        $photo_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($photo_ext, $allowed_images) && $_FILES['photo']['size'] <= 2097152) { // 2MB
            // Create photos directory
            $photos_dir = $base_dir . DIRECTORY_SEPARATOR . 'photos';
            if (!is_dir($photos_dir)) {
                mkdir($photos_dir, 0777, true);
            }
            
            $photo_name = 'profile_' . $user_id . '_' . time() . '.' . $photo_ext;
            $photo_path = $photos_dir . DIRECTORY_SEPARATOR . $photo_name;
            
            if (move_uploaded_file($photo_tmp, $photo_path)) {
                $photo_url = 'uploads/photos/' . $photo_name;
            } else {
                error_log("Failed to move photo file to: " . $photo_path);
            }
        }
    }
    
    // Upload resume (for workers only)
    if ($role === 'worker' && isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $resume_tmp = $_FILES['resume']['tmp_name'];
        $resume_ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        $allowed_docs = ['pdf', 'doc', 'docx'];
        
        if (in_array($resume_ext, $allowed_docs) && $_FILES['resume']['size'] <= 5242880) { // 5MB
            // Create resumes directory
            $resumes_dir = $base_dir . DIRECTORY_SEPARATOR . 'resumes';
            if (!is_dir($resumes_dir)) {
                mkdir($resumes_dir, 0777, true);
            }
            
            $resume_name = 'resume_' . $user_id . '_' . time() . '.' . $resume_ext;
            $resume_path = $resumes_dir . DIRECTORY_SEPARATOR . $resume_name;
            
            if (move_uploaded_file($resume_tmp, $resume_path)) {
                $resume_url = 'uploads/resumes/' . $resume_name;
            } else {
                error_log("Failed to move resume file to: " . $resume_path);
            }
        }
    }
    
    // Generate auto bio based on role and data
    $bio = generateAutoBio($role, [
        'name' => $first_name,
        'district' => $district,
        'is_student' => isset($_POST['is_student']),
        'university' => $_POST['university'] ?? null,
        'experience' => $_POST['experience'] ?? null,
        'skills' => $_POST['skills'] ?? null,
        'company_name' => $_POST['company_name'] ?? null,
        'company_industry' => $_POST['company_industry'] ?? null
    ]);
    
    // Insert into profiles table
    if ($role === 'worker') {
        $is_student = isset($_POST['is_student']) ? 1 : 0;
        $university = !empty($_POST['university']) ? trim($_POST['university']) : null;
        $skills = !empty($_POST['skills']) ? trim($_POST['skills']) : null;
        $experience = !empty($_POST['experience']) ? $_POST['experience'] : null;
        
        $profile_sql = "INSERT INTO profiles (user_id, name, phone, address, district, date_of_birth, gender, 
                        bio, skills, experience, resume_url, photo_url, is_student, university) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $profile_stmt = $conn->prepare($profile_sql);
        
        // Type string: i s s s s s s s s s s s i s (14 parameters)
        $profile_stmt->bind_param("isssssssssssis", 
            $user_id,       // i - integer
            $full_name,     // s - string
            $phone,         // s
            $address,       // s
            $district,      // s
            $date_of_birth, // s
            $gender,        // s
            $bio,           // s
            $skills,        // s
            $experience,    // s
            $resume_url,    // s
            $photo_url,     // s
            $is_student,    // i - integer
            $university     // s
        );
        
    } elseif ($role === 'employer') {
        $company_name = trim($_POST['company_name']);
        $company_industry = !empty($_POST['company_industry']) ? $_POST['company_industry'] : null;
        $company_size = !empty($_POST['company_size']) ? $_POST['company_size'] : null;
        $company_description = !empty($_POST['company_description']) ? trim($_POST['company_description']) : null;
        
        // For employers, bio is the company description or auto-generated
        $employer_bio = !empty($company_description) ? $company_description : $bio;
        
        $profile_sql = "INSERT INTO profiles (user_id, name, phone, address, district, date_of_birth, gender, 
                        bio, photo_url) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $profile_stmt = $conn->prepare($profile_sql);
        
        // Type string: i s s s s s s s s (9 parameters)
        $profile_stmt->bind_param("issssssss", 
            $user_id,
            $company_name,
            $phone,
            $address,
            $district,
            $date_of_birth,
            $gender,
            $employer_bio,
            $photo_url
        );
        
    } else { // admin
        $profile_sql = "INSERT INTO profiles (user_id, name, phone, address, district, date_of_birth, gender, 
                        bio, photo_url) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $profile_stmt = $conn->prepare($profile_sql);
        
        // Type string: i s s s s s s s s (9 parameters)
        $profile_stmt->bind_param("issssssss", 
            $user_id,
            $full_name,
            $phone,
            $address,
            $district,
            $date_of_birth,
            $gender,
            $bio,
            $photo_url
        );
    }
    
    if (!$profile_stmt->execute()) {
        throw new Exception("Failed to create profile: " . $profile_stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Set session and redirect
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $role;
    
    // Redirect based on role with welcome message
    if ($role === 'worker') {
        header("Location: ../worker_dashboard.php?welcome=1");
    } elseif ($role === 'employer') {
        header("Location: ../employer_dashboard.php?welcome=1");
    } else {
        header("Location: ../admin_dashboard.php?welcome=1");
    }
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Clean up uploaded files
    if (isset($photo_path) && file_exists($photo_path)) {
        unlink($photo_path);
    }
    if (isset($resume_path) && file_exists($resume_path)) {
        unlink($resume_path);
    }
    
    die("Registration failed: " . $e->getMessage() . " <a href='../register.html'>Try again</a>");
}

$conn->close();

// Helper function to generate auto bio
function generateAutoBio($role, $data) {
    if ($role === 'worker') {
        $bio = "Hi! I'm " . $data['name'];
        
        if ($data['is_student'] && !empty($data['university'])) {
            $bio .= ", currently studying at " . $data['university'];
        }
        
        if (!empty($data['district'])) {
            $bio .= ", based in " . $data['district'];
        }
        
        $bio .= ". ";
        
        if ($data['is_student']) {
            $bio .= "I'm a motivated student seeking flexible part-time opportunities to gain practical work experience while managing my academic commitments. ";
        }
        
        if (!empty($data['experience'])) {
            $exp_text = [
                'entry' => "I'm eager to start my career and learn from experienced professionals. ",
                'junior' => "I have some work experience and I'm looking to grow my skills further. ",
                'intermediate' => "I bring solid experience and proven skills to any role. ",
                'senior' => "I'm an experienced professional with extensive expertise in my field. "
            ];
            $bio .= $exp_text[$data['experience']] ?? "";
        }
        
        if (!empty($data['skills'])) {
            $skills_array = array_slice(array_map('trim', explode(',', $data['skills'])), 0, 3);
            if (count($skills_array) > 0) {
                $bio .= "My key skills include " . implode(', ', $skills_array) . ". ";
            }
        }
        
        $bio .= "I'm a reliable team player with strong communication skills and a positive attitude, ready to contribute to your team's success.";
        
    } elseif ($role === 'employer') {
        $bio = !empty($data['company_name']) ? $data['company_name'] : "Our Company";
        
        if (!empty($data['company_industry'])) {
            $industry_text = [
                'hospitality' => 'hospitality and tourism',
                'retail' => 'retail and sales',
                'food' => 'food and beverage',
                'construction' => 'construction',
                'events' => 'events and entertainment',
                'logistics' => 'logistics and transportation',
                'agriculture' => 'agriculture',
                'technology' => 'technology and IT',
                'healthcare' => 'healthcare',
                'education' => 'education'
            ];
            $bio .= " is a dynamic company in the " . ($industry_text[$data['company_industry']] ?? $data['company_industry']) . " sector";
        }
        
        if (!empty($data['district'])) {
            $bio .= ", based in " . $data['district'];
        }
        
        $bio .= ". We are committed to providing quality services and creating a positive work environment. ";
        $bio .= "We value our employees and offer opportunities for growth and professional development. ";
        $bio .= "Join our team and be part of our success story!";
        
    } else { // admin
        $bio = "System Administrator at POP!Work platform.";
    }
    
    return $bio;
}
?>