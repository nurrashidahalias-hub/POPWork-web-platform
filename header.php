<?php
if (!isset($page_title)) {
    $page_title = "POP!Work";
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? null;

// Get unread message count
$unread_count = 0;
if ($user_logged_in && isset($_SESSION['user_id'])) {
    include_once("backend/db_connect.php");
    $unread_sql = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_sql);
    $unread_stmt->bind_param("i", $_SESSION['user_id']);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    $unread_data = $unread_result->fetch_assoc();
    $unread_count = $unread_data['unread'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ================================
           HEADER STYLES (From about.html)
        ================================ */
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0; padding: 0;
        }

        .pw-header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s;
            width: 100%;
        }

        .pw-nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        /* --- LOGO --- */
        .pw-logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; transition: all 0.3s;
        }
        .pw-logo:hover { transform: translateY(-2px); }
        
        .logo-icon {
            font-size: 28px;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.1));
        }
        
        .pw-logo span {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* --- NAVIGATION --- */
        .pw-nav {
            display: flex; gap: 30px; align-items: center;
        }

        .pw-link {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            position: relative;
            padding: 8px 0;
        }

        .pw-link::after {
            content: ''; position: absolute; bottom: 0; left: 0; width: 0; height: 3px;
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            transition: width 0.3s; border-radius: 3px;
        }

        .pw-link:hover::after, 
        .pw-link.active::after { width: 100%; }
        
        .pw-link:hover { color: #8a1538; }

        /* Messages Badge */
        .pw-message-badge {
            background: #e74c3c; color: white;
            font-size: 10px; padding: 2px 6px; border-radius: 10px;
            vertical-align: top; margin-left: 2px;
        }

        /* --- AUTH BUTTONS --- */
        .pw-auth { display: flex; gap: 15px; align-items: center; }

        .pw-btn {
            padding: 10px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s;
            border: 2px solid transparent;
            display: inline-block;
        }

        .pw-login {
            color: #8a1538;
            border-color: #8a1538;
        }
        .pw-login:hover {
            background: #8a1538; color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(138, 21, 56, 0.2);
        }

        .pw-register {
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(138, 21, 56, 0.2);
        }
        .pw-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(138, 21, 56, 0.3);
        }

        /* Logged In User Styles */
        .user-controls { display: flex; gap: 15px; align-items: center; }
        
        .profile-btn {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: #f1f2f6;
            color: #8a1538;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none;
            transition: 0.3s;
            border: 1px solid #eee;
        }
        .profile-btn:hover { background: #8a1538; color: white; }

        .logout-btn {
            font-size: 20px; color: #95a5a6;
            transition: 0.3s;
        }
        .logout-btn:hover { color: #e74c3c; transform: translateX(2px); }

        /* Mobile Menu Button */
        .mobile-menu-btn { display: none; font-size: 24px; cursor: pointer; color: #333; }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .pw-nav { display: none; position: absolute; top: 100%; left: 0; width: 100%; background: white; flex-direction: column; padding: 20px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
            .pw-nav.active { display: flex; }
            .mobile-menu-btn { display: block; }
            .pw-auth { display: none; } /* Hide auth buttons on small mobile to simplify */
            .pw-nav .pw-auth { display: flex; flex-direction: column; width: 100%; margin-top: 10px; } /* Show inside menu if needed */
        }
    </style>
</head>
<body>

<header class="pw-header">
    <div class="pw-nav-container">

        <a href="index.php" class="pw-logo">
            <div class="logo-icon">💼</div>
            <span>POP!Work</span>
        </a>

        <nav class="pw-nav" id="pwNav">
            <a href="index.php" class="pw-link">Home</a>
            <a href="jobs.php" class="pw-link">Find Jobs</a>
            <a href="about.php" class="pw-link">About Us</a>

            <?php if ($user_logged_in): ?>
                <?php if ($user_role === 'employer'): ?>
                    <a href="employer_dashboard.php" class="pw-link">Dashboard</a>
                    <a href="job_post.php" class="pw-link">Post Job</a>
                <?php elseif ($user_role === 'worker'): ?>
                    <a href="worker_dashboard.php" class="pw-link">Dashboard</a>
                    <a href="my_applications.php" class="pw-link">My Apps</a>
                <?php elseif ($user_role === 'admin'): ?>
                    <a href="admin_dashboard.php" class="pw-link">Admin</a>
                <?php endif; ?>

                <a href="messages.php" class="pw-link">
                    Messages 
                    <?php if ($unread_count > 0): ?>
                        <span class="pw-message-badge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>

        <div class="pw-auth">
            <?php if ($user_logged_in): ?>
                <div class="user-controls">
                    <a href="profile_page.php" class="profile-btn" title="My Profile">
                        <i class="fa-solid fa-user"></i>
                    </a>
                    <a href="backend/logout.php" class="logout-btn" title="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            <?php else: ?>
                <a href="" class="pw-btn pw-login">Log In</a>
                <a href="register.html" class="pw-btn pw-register">Sign Up</a>
            <?php endif; ?>
        </div>

        <div class="mobile-menu-btn" onclick="document.getElementById('pwNav').classList.toggle('active')">
            <i class="fa-solid fa-bars"></i>
        </div>

    </div>
</header>

</body>
</html>