<?php
session_start();
include("backend/db_connect.php");

// 🔒 Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    
    // 1. Update Basic Info
    $stmt = $conn->prepare("UPDATE profiles SET name = ?, phone = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $name, $phone, $user_id);
    $stmt->execute();
    
    // 2. Update Email
    $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    
    // 3. Handle Profile Picture Upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_photo']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($file_ext, $allowed)) {
            // Create directory if not exists
            $upload_dir = "uploads/photos/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate unique filename: admin_ID_timestamp.jpg
            $new_filename = "admin_" . $user_id . "_" . time() . "." . $file_ext;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                // Update DB
                $stmt = $conn->prepare("UPDATE profiles SET profile_picture = ? WHERE user_id = ?");
                $stmt->bind_param("si", $target_file, $user_id);
                $stmt->execute();
            }
        }
    }

    // 4. Update Password (if provided)
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        $stmt->execute();
        $message = "Profile updated successfully!";
    } else {
        $message = "Profile details updated successfully!";
    }
    $message_type = 'success';
}

// Fetch Admin Data
$sql = "SELECT u.email, u.role, u.created_at, p.name, p.phone, p.photo_url, p.profile_picture 
        FROM users u 
        LEFT JOIN profiles p ON u.user_id = p.user_id 
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
    
// Fallback for name
$display_name = $admin['name'] ?? 'Administrator';
$initials = strtoupper(substr($display_name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pop-maroon: #8a1538;
            --pop-maroon-dark: #6d1029;
            --pop-light: #fdf2f4;
            --pop-gray: #f8f9fa;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: var(--pop-gray); min-height: 100vh; }

                /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .menu-item:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: white;
        }

        .menu-item.active {
            background: rgba(255,255,255,0.15);
            border-left-color: white;
        }

        .menu-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 18px;
        }

        .menu-item span {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Main Content */
        .main-content { margin-left: 260px; padding: 30px; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; color: var(--pop-maroon); font-weight: 700; }
        .breadcrumb { font-size: 14px; color: #6b7280; margin-top: 5px; }

        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease; }
        .alert.success { background: #dcfce7; color: #166534; border-left: 5px solid #166534; }
        .alert.error { background: #fee2e2; color: #991b1b; border-left: 5px solid #991b1b; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .profile-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        @media (max-width: 1024px) { .profile-grid { grid-template-columns: 1fr; } }

        .card { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid #eee; }
        .card-header h3 { font-size: 18px; color: #333; font-weight: 600; }
        .card-body { padding: 25px; }

        /* Updated Profile Hero */
        .profile-hero { background: var(--pop-light); padding: 40px 20px; text-align: center; }
        
        .avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
        }

        .avatar-circle {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: 700;
            box-shadow: 0 5px 15px rgba(138, 21, 56, 0.3);
            overflow: hidden;
            position: relative;
        }
        
        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Camera Upload Icon */
        .upload-icon {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: white;
            color: var(--pop-maroon);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: all 0.3s;
            border: 2px solid white;
        }
        .upload-icon:hover {
            background: var(--pop-maroon);
            color: white;
            transform: scale(1.1);
        }

        .admin-badge { background: #fee2e2; color: #991b1b; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
        .info-list { margin-top: 20px; }
        .info-item { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .info-item:last-child { border-bottom: none; }
        .info-label { color: #6b7280; font-weight: 500; }
        .info-value { color: #1f2937; font-weight: 600; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-size: 14px; }
        .form-group input { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-family: 'Poppins', sans-serif; transition: all 0.3s; }
        .form-group input:focus { border-color: var(--pop-maroon); outline: none; }
        .section-title { font-size: 16px; font-weight: 600; color: var(--pop-maroon); margin: 30px 0 15px; padding-bottom: 10px; border-bottom: 2px solid #f3f4f6; }
        .btn-save { background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%); color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 500; cursor: pointer; font-size: 15px; font-family: 'Poppins', sans-serif; transition: transform 0.2s; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(138, 21, 56, 0.3); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>POP!Work</h2>
            <p>Admin Panel</p>
            </div>
            <nav class="sidebar-menu">
            <a href="admin_dashboard.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin_users.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="admin_jobs.php" class="menu-item">
                <i class="fas fa-briefcase"></i>
                <span>Job Management</span>
            </a>
            <a href="admin_payments.php" class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <span>Payments</span>
            </a>
            <a href="admin_attendance.php" class="menu-item">
                <i class="fas fa-clock"></i>
                <span>Attendance</span>
            </a>
            <a href="admin_messages.php" class="menu-item">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>
            <a href="admin_feedback.php" class="menu-item">
                <i class="fas fa-star"></i>
                <span>Feedback</span>
            </a>
                        <a href="admin_reports.php" class="menu-item">
                <i class="fas fa-chart-bar"></i><span>Reports & Analytics</span>
            </a>
            <a href="admin_profile.php" class="menu-item active">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="backend/logout.php" class="menu-item" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1>Admin Profile</h1>
            <div class="breadcrumb">Manage your account settings and preferences</div>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= $message_type ?>">
                <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="profile-grid">
                
                <div class="card">
                    <div class="profile-hero">
                        <div class="avatar-container">
                            <div class="avatar-circle">
                                <?php if (!empty($admin['profile_picture']) && file_exists($admin['profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($admin['profile_picture']) ?>" alt="Profile">
                                <?php else: ?>
                                    <?= $initials ?>
                                <?php endif; ?>
                            </div>
                            <label for="profile_photo" class="upload-icon" title="Change Photo">
                                <i class="fas fa-camera"></i>
                            </label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/*" style="display: none;" onchange="previewImage(this)">
                        </div>
                        
                        <h2 style="font-size: 22px; color: #333; margin-bottom: 5px;"><?= htmlspecialchars($display_name) ?></h2>
                        <span class="admin-badge">Super Administrator</span>
                    </div>
                    <div class="card-body">
                        <div class="info-list">
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-envelope" style="width: 20px;"></i> Email</span>
                                <span class="info-value"><?= htmlspecialchars($admin['email']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-phone" style="width: 20px;"></i> Phone</span>
                                <span class="info-value"><?= htmlspecialchars($admin['phone'] ?? 'Not Set') ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-calendar" style="width: 20px;"></i> Joined</span>
                                <span class="info-value"><?= date('M d, Y', strtotime($admin['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cog"></i> Account Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="section-title" style="margin-top: 0;">Personal Information</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($display_name) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" placeholder="012-3456789">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                        </div>

                        <div class="section-title">Security</div>
                        <div class="form-group">
                            <label>Change Password (Optional)</label>
                            <input type="password" name="new_password" placeholder="Leave blank to keep current password">
                            <small style="color: #6b7280; display: block; margin-top: 5px;">Minimum 8 characters recommended.</small>
                        </div>

                        <div style="margin-top: 30px; text-align: right;">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save" style="margin-right: 8px;"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function toggleMenu(element) {
            element.parentElement.classList.toggle('open');
        }

        // Preview Image before upload
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var avatarCircle = document.querySelector('.avatar-circle');
                    // Check if img already exists, if not create it
                    var img = avatarCircle.querySelector('img');
                    if (!img) {
                        avatarCircle.innerHTML = ''; // Remove initials
                        img = document.createElement('img');
                        avatarCircle.appendChild(img);
                    }
                    img.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>