<?php
session_start();
include("backend/db_connect.php");

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Get admin info (for Top Bar)
$admin_sql = "SELECT u.email, p.name, p.profile_picture, p.photo_url 
              FROM users u 
              LEFT JOIN profiles p ON u.user_id = p.user_id 
              WHERE u.user_id = ?";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();
$admin_name = $admin_data['name'] ?? explode('@', $admin_data['email'])[0];

// Admin Top Bar Picture Logic
$admin_pic = null;
if (!empty($admin_data['profile_picture']) && file_exists($admin_data['profile_picture'])) {
    $admin_pic = $admin_data['profile_picture'];
} elseif (!empty($admin_data['photo_url'])) {
    $admin_pic = $admin_data['photo_url'];
}

// Handle user actions
$message = '';
$message_type = '';

if (isset($_GET['msg'])) { $message = htmlspecialchars($_GET['msg']); $message_type = 'success'; }
if (isset($_GET['error'])) { $message = htmlspecialchars($_GET['error']); $message_type = 'error'; }

// Block/Unblock User
if (isset($_POST['toggle_block'])) {
    $user_id = intval($_POST['user_id']);
    $current_status = $_POST['current_status'];
    $new_status = ($current_status == 'active') ? 'blocked' : 'active';
    
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active', 'blocked') DEFAULT 'active'");
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    
    if ($stmt->execute()) {
        $action = ($new_status == 'blocked') ? 'blocked' : 'unblocked';
        $message = "User successfully $action!";
        $message_type = 'success';
    } else {
        $message = "Error updating user status.";
        $message_type = 'error';
    }
}

// Update User
if (isset($_POST['update_user'])) {
    $user_id = intval($_POST['user_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    $stmt = $conn->prepare("UPDATE users SET email = ?, role = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $email, $role, $user_id);
    $stmt->execute();
    
    $profile_check = $conn->prepare("SELECT user_id FROM profiles WHERE user_id = ?");
    $profile_check->bind_param("i", $user_id);
    $profile_check->execute();
    
    if ($profile_check->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE profiles SET name = ? WHERE user_id = ?");
        $stmt->bind_param("si", $name, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO profiles (user_id, name) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $name);
    }
    
    if ($stmt->execute()) {
        $message = "User successfully updated!";
        $message_type = 'success';
    } else {
        $message = "Error updating user.";
        $message_type = 'error';
    }
}

// Get filters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ---------------------------------------------------------
// 🔍 MAIN QUERY: Fetching Users for the Table
// ---------------------------------------------------------
// Added 'p.photo_url' to the select list
$sql = "SELECT u.user_id, u.email, u.role, u.status, u.created_at, u.last_activity, 
               p.name, p.profile_picture, p.photo_url, 
               COUNT(DISTINCT CASE WHEN u.role = 'worker' THEN a.application_id END) as jobs_completed,
               COUNT(DISTINCT CASE WHEN u.role = 'employer' THEN j.job_id END) as jobs_posted,
               SUM(CASE WHEN u.role = 'worker' THEN a.total_work_hours ELSE 0 END) as total_hours,
               SUM(CASE WHEN u.role = 'worker' THEN a.payment_amount ELSE 0 END) as total_earned
        FROM users u
        LEFT JOIN profiles p ON u.user_id = p.user_id
        LEFT JOIN applications a ON u.user_id = a.user_id AND a.job_completed = 1
        LEFT JOIN jobs j ON u.user_id = j.employer_id
        WHERE u.role IN ('worker', 'employer', 'admin')";

if ($role_filter != 'all') {
    $sql .= " AND u.role = '" . $conn->real_escape_string($role_filter) . "'";
}

if ($status_filter != 'all') {
    $sql .= " AND u.status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($search != '') {
    $search_term = $conn->real_escape_string($search);
    $sql .= " AND (p.name LIKE '%$search_term%' OR u.email LIKE '%$search_term%')";
}

$sql .= " GROUP BY u.user_id ORDER BY u.created_at DESC";
$users = $conn->query($sql);

// Counts
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('worker', 'employer')")->fetch_assoc()['count'];
$total_workers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'worker'")->fetch_assoc()['count'];
$total_employers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employer'")->fetch_assoc()['count'];
$blocked_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'blocked'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | POP!Work Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pop-maroon: #8a1538; --pop-light: #fdf2f4; --pop-gray: #f8f9fa; --pop-maroon-dark: #6d1029; --pop-maroon-light: #a71d47; }
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
        .main-content { margin-left: 260px; padding: 24px; min-height: 100vh; }

        .top-bar { background: white; padding: 16px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .top-bar h1 { font-size: 28px; color: var(--pop-maroon); font-weight: 700; }
        .top-actions { display: flex; align-items: center; gap: 20px; }

        .add-btn { background: var(--pop-maroon); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; transition: all 0.3s; }
        .add-btn:hover { background: #6d1029; transform: translateY(-2px); }

        /* Admin Widget */
        .admin-profile-widget { display: flex; align-items: center; gap: 12px; padding-left: 20px; border-left: 1px solid #eee; }
        .admin-avatar-small { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-light) 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .admin-avatar-small img { width: 100%; height: 100%; object-fit: cover; }
        .admin-info-text { display: flex; flex-direction: column; line-height: 1.2; text-align: right; }
        .admin-name { font-weight: 600; font-size: 14px; color: #333; }
        .admin-role { font-size: 12px; color: #666; }

        .message { padding: 16px 24px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease; }
        .message.success { background: #f0fdf4; color: #166534; border-left: 4px solid #10b981; }
        .message.error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-box-label { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
        .stat-box-value { font-size: 32px; font-weight: 700; color: var(--pop-maroon); }

        .filters-bar { background: white; padding: 20px 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .filters-row { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; }
        .search-box { flex: 1; min-width: 250px; position: relative; }
        .search-box input { width: 100%; padding: 12px 16px 12px 44px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-family: 'Poppins', sans-serif; }
        .search-box input:focus { outline: none; border-color: var(--pop-maroon); }
        .search-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
        .filter-select { padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-family: 'Poppins', sans-serif; cursor: pointer; }
        .btn { padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s; font-family: 'Poppins', sans-serif; }
        .btn-primary { background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%); color: white; }

        .users-table-container { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--pop-light); }
        th { padding: 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--pop-maroon); text-transform: uppercase; }
        td { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; vertical-align: middle; }
        tr:hover { background: var(--pop-light); }

        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 45px; height: 45px; min-width: 45px; border-radius: 50%; background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-light) 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 18px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-details { display: flex; flex-direction: column; justify-content: center; }
        .user-name { font-weight: 600; color: #1f2937; font-size: 15px; margin-bottom: 3px; }
        .user-email { font-size: 13px; color: #6b7280; font-weight: 400; display: flex; align-items: center; gap: 5px; }

        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .badge.worker { background: #dbeafe; color: #1e40af; }
        .badge.employer { background: #fef3c7; color: #92400e; }
        .badge.admin { background: #fee2e2; color: #991b1b; }
        .badge.active { background: #d1fae5; color: #065f46; }
        .badge.blocked { background: #fee2e2; color: #991b1b; }
        
        .action-btns { display: flex; gap: 8px; }
        .action-btn { padding: 8px 12px; border: none; border-radius: 8px; font-size: 12px; cursor: pointer; transition: all 0.3s; font-weight: 500; }
        .action-btn.view { background: #dbeafe; color: #1e40af; }
        .action-btn.edit { background: #fef3c7; color: #92400e; }
        .action-btn.block { background: #fee2e2; color: #991b1b; }

        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); padding: 0; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { padding: 24px; border-bottom: 2px solid var(--pop-gray); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 20px; color: var(--pop-maroon); margin: 0; }
        .close-modal { width: 32px; height: 32px; border: none; background: var(--pop-gray); border-radius: 50%; cursor: pointer; font-size: 18px; color: #6b7280; transition: all 0.3s; }
        .close-modal:hover { background: #e5e7eb; color: var(--pop-maroon); }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 20px 24px; border-top: 2px solid var(--pop-gray); display: flex; gap: 12px; justify-content: flex-end; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; color: #374151; }
        .form-group input, .form-group select { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-family: 'Poppins', sans-serif; }
        .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
        .info-label { font-weight: 500; color: #6b7280; }
        .info-value { color: #1f2937; font-weight: 500; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-danger { background: #ef4444; color: white; }
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
            <a href="admin_users.php" class="menu-item active">
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
            <a href="admin_profile.php" class="menu-item">
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
        <div class="top-bar">
            <h1>User Management</h1>
            <div class="top-actions">
                <button class="add-btn" onclick="openModal('addUserModal')">
                    <i class="fas fa-plus"></i> Add New User
                </button>
                <a href="admin_profile.php" style="text-decoration: none;">
                    <div class="admin-profile-widget">
                        <div class="admin-info-text">
                            <span class="admin-name"><?= htmlspecialchars($admin_name) ?></span>
                            <span class="admin-role">Administrator</span>
                        </div>
                        <div class="admin-avatar-small">
                            <?php if ($admin_pic): ?>
                                <img src="<?= htmlspecialchars($admin_pic) ?>" alt="Admin">
                            <?php else: ?>
                                <?= strtoupper(substr($admin_name, 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="message <?= $message_type ?>">
            <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box"><div class="stat-box-label">Total Users</div><div class="stat-box-value"><?= $total_users ?></div></div>
            <div class="stat-box"><div class="stat-box-label">Workers</div><div class="stat-box-value"><?= $total_workers ?></div></div>
            <div class="stat-box"><div class="stat-box-label">Employers</div><div class="stat-box-value"><?= $total_employers ?></div></div>
            <div class="stat-box"><div class="stat-box-label">Blocked</div><div class="stat-box-value" style="color: #ef4444;"><?= $blocked_users ?></div></div>
        </div>

        <div class="filters-bar">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" id="userSearchInput" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="role" class="filter-select">
                        <option value="all" <?= $role_filter == 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="worker" <?= $role_filter == 'worker' ? 'selected' : '' ?>>Workers</option>
                        <option value="employer" <?= $role_filter == 'employer' ? 'selected' : '' ?>>Employers</option>
                        <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>Admins</option>
                    </select>
                    <select name="status" class="filter-select">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="blocked" <?= $status_filter == 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                </div>
            </form>
        </div>

        <div class="users-table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 300px;">User Details</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Stats</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php 
                                            // 1. Prioritize uploaded file
                                            if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                                                echo '<img src="' . htmlspecialchars($user['profile_picture']) . '" alt="User Photo">';
                                            } 
                                            // 2. Fallback to photo_url (e.g. Google Auth or manual link)
                                            elseif (!empty($user['photo_url'])) {
                                                echo '<img src="' . htmlspecialchars($user['photo_url']) . '" alt="User Photo">';
                                            } 
                                            // 3. Fallback to Initials
                                            else {
                                                echo strtoupper(substr($user['name'] ?? $user['email'], 0, 1));
                                            }
                                            ?>
                                        </div>
                                        
                                        <div class="user-details">
                                            <span class="user-name"><?= htmlspecialchars($user['name'] ?? 'No Name') ?></span>
                                            <span class="user-email">
                                                <i class="fas fa-envelope" style="font-size: 11px;"></i> 
                                                <?= htmlspecialchars($user['email']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge <?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span></td>
                                <td><span class="badge <?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span></td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <?php if ($user['role'] == 'worker'): ?>
                                        <small>Jobs: <strong><?= $user['jobs_completed'] ?></strong><br>Hrs: <strong><?= number_format($user['total_hours'], 1) ?></strong></small>
                                    <?php elseif ($user['role'] == 'employer'): ?>
                                        <small>Posted: <strong><?= $user['jobs_posted'] ?></strong></small>
                                    <?php else: ?>
                                        <small>Admin Access</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn view" onclick="viewUser(<?= htmlspecialchars(json_encode($user)) ?>)"><i class="fas fa-eye"></i></button>
                                        <button class="action-btn edit" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)"><i class="fas fa-edit"></i></button>
                                        <?php if($user['role'] !== 'admin'): ?>
                                            <button class="action-btn block" onclick="toggleBlock(<?= $user['user_id'] ?>, '<?= $user['status'] ?>', '<?= htmlspecialchars($user['name'] ?? $user['email']) ?>')"><i class="fas fa-<?= $user['status'] == 'active' ? 'ban' : 'check-circle' ?>"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; padding: 40px; color: #9ca3af;"><i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>No users found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-user-plus"></i> Add New User</h3><button class="close-modal" onclick="closeModal('addUserModal')"><i class="fas fa-times"></i></button></div>
            <form action="backend/admin_add_user.php" method="POST">
                <div class="modal-body">
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" required placeholder="e.g. John Doe"></div>
                    <div class="form-group"><label>Email Address</label><input type="email" name="email" required placeholder="john@example.com"></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" required placeholder="Set temporary password"></div>
                    <div class="form-group"><label>Role</label><select name="role" required><option value="worker">Worker</option><option value="employer">Employer</option><option value="admin" style="color: red; font-weight: bold;">⚠ Administrator</option></select></div>
                    <div class="form-group"><label>Phone (Optional)</label><input type="text" name="phone" placeholder="012-3456789"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button><button type="submit" class="btn btn-primary">Create Account</button></div>
            </form>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-user"></i> User Details</h3><button class="close-modal" onclick="closeModal('viewModal')"><i class="fas fa-times"></i></button></div>
            <div class="modal-body" id="viewModalBody"></div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h3><i class="fas fa-edit"></i> Edit User</h3><button type="button" class="close-modal" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button></div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="form-group"><label>Name</label><input type="text" name="name" id="edit_name" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" required></div>
                    <div class="form-group"><label>Role</label><select name="role" id="edit_role"><option value="worker">Worker</option><option value="employer">Employer</option><option value="admin">Admin</option></select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button><button type="submit" name="update_user" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>

    <div id="blockModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h3><i class="fas fa-exclamation-triangle"></i> Confirm Action</h3><button type="button" class="close-modal" onclick="closeModal('blockModal')"><i class="fas fa-times"></i></button></div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="block_user_id">
                    <input type="hidden" name="current_status" id="block_current_status">
                    <p id="block_message" style="font-size: 16px; color: #374151; line-height: 1.6;"></p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('blockModal')">Cancel</button><button type="submit" name="toggle_block" class="btn btn-danger" id="block_confirm_btn">Confirm</button></div>
            </form>
        </div>
    </div>

    <script>
        function toggleMenu(element) { element.parentElement.classList.toggle('open'); }
        function openModal(modalId) { document.getElementById(modalId).classList.add('active'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
        document.querySelectorAll('.modal').forEach(modal => { modal.addEventListener('click', function(e) { if (e.target === this) { this.classList.remove('active'); } }); });

        function viewUser(user) {
            const body = document.getElementById('viewModalBody');
            const stats = user.role === 'worker' 
                ? `<div class="info-row"><span class="info-label">Jobs Completed:</span><span class="info-value">${user.jobs_completed}</span></div><div class="info-row"><span class="info-label">Total Hours:</span><span class="info-value">${parseFloat(user.total_hours).toFixed(1)} hours</span></div>`
                : `<div class="info-row"><span class="info-label">Jobs Posted:</span><span class="info-value">${user.jobs_posted}</span></div>`;
            
            // Check image logic for View Modal as well
            let avatarHTML = '';
            // We reuse the same logic: check file, then check url
            let imgSrc = '';
            if (user.profile_picture) imgSrc = user.profile_picture;
            else if (user.photo_url) imgSrc = user.photo_url;

            if(imgSrc) {
                avatarHTML = `<div style="text-align:center; margin-bottom:20px;"><img src="${imgSrc}" style="width:100px; height:100px; border-radius:50%; object-fit:cover; box-shadow:0 4px 10px rgba(0,0,0,0.1);"></div>`;
            } else {
                avatarHTML = `<div style="text-align:center; margin-bottom:20px;"><div style="width:100px; height:100px; border-radius:50%; background:#8a1538; color:white; font-size:40px; line-height:100px; margin:0 auto;">${user.name ? user.name.charAt(0).toUpperCase() : user.email.charAt(0).toUpperCase()}</div></div>`;
            }

            body.innerHTML = `${avatarHTML}<div class="info-row"><span class="info-label">Name:</span><span class="info-value">${user.name || 'No Name'}</span></div><div class="info-row"><span class="info-label">Email:</span><span class="info-value">${user.email}</span></div><div class="info-row"><span class="info-label">Role:</span><span class="info-value">${user.role.toUpperCase()}</span></div><div class="info-row"><span class="info-label">Status:</span><span class="info-value">${user.status.toUpperCase()}</span></div>${stats}`;
            openModal('viewModal');
        }

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_name').value = user.name || '';
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            openModal('editModal');
        }

        function toggleBlock(userId, currentStatus, userName) {
            const action = currentStatus === 'active' ? 'block' : 'unblock';
            document.getElementById('block_user_id').value = userId;
            document.getElementById('block_current_status').value = currentStatus;
            document.getElementById('block_message').innerHTML = `Are you sure you want to ${action} <strong>${userName}</strong>?`;
            document.getElementById('block_confirm_btn').innerHTML = action.charAt(0).toUpperCase() + action.slice(1);
            openModal('blockModal');
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'modify') { document.getElementById('userSearchInput').focus(); }
    </script>
</body>
</html>