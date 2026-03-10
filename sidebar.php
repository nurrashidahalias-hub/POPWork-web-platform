<!-- ================================
     POP!Work Professional Sidebar
     Modern, Clean & Responsive
     Fetches user data from database
     UPDATED: Now includes Location Tracking Features
================================ -->

<?php
// Fetch user profile data from database if not already loaded
if (isset($_SESSION['user_id']) && !isset($sidebar_user_loaded)) {
    $sidebar_user_id = $_SESSION['user_id'];
    
    // Fetch user info including profile picture and name
    $sidebar_sql = "SELECT u.email, u.role, p.name, p.photo_url, p.profile_picture 
                    FROM users u 
                    LEFT JOIN profiles p ON u.user_id = p.user_id 
                    WHERE u.user_id = ?";
    
    $sidebar_stmt = $conn->prepare($sidebar_sql);
    $sidebar_stmt->bind_param("i", $sidebar_user_id);
    $sidebar_stmt->execute();
    $sidebar_user = $sidebar_stmt->get_result()->fetch_assoc();
    
    // Get user name (prioritize name from profiles, fallback to email)
    if (!empty($sidebar_user['name'])) {
        $sidebar_display_name = $sidebar_user['name'];
    } else {
        $email_parts = explode('@', $sidebar_user['email']);
        $sidebar_display_name = ucfirst($email_parts[0]);
    }
    
    // Get profile picture (check both photo_url and profile_picture columns)
    $sidebar_profile_pic = null;
    if (!empty($sidebar_user['photo_url']) && file_exists($sidebar_user['photo_url'])) {
        $sidebar_profile_pic = $sidebar_user['photo_url'];
    } elseif (!empty($sidebar_user['profile_picture']) && file_exists($sidebar_user['profile_picture'])) {
        $sidebar_profile_pic = $sidebar_user['profile_picture'];
    }
    
    // Get user role
    $sidebar_user_role = ucfirst($sidebar_user['role'] ?? 'Member');
    
    // Get unread messages count
    $unread_sql = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_sql);
    $unread_stmt->bind_param("i", $sidebar_user_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result()->fetch_assoc();
    $unread_count = $unread_result['unread'] ?? 0;
    
    // Mark as loaded to prevent duplicate queries
    $sidebar_user_loaded = true;
    
    $sidebar_stmt->close();
    $unread_stmt->close();
}

// Determine current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Define sidebar items based on user role
$sidebar_items = [];

if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'employer':
            $sidebar_items = [
                [
                    'icon' => 'fa-solid fa-chart-line',
                    'label' => 'Dashboard',
                    'url' => 'employer_dashboard.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-briefcase',
                    'label' => 'Manage Jobs',
                    'url' => 'employer_jobs.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-plus-circle',
                    'label' => 'Post Job',
                    'url' => 'job_post.php', 
                    'badge' => null
                ],
                // ===== NEW: LOCATION TRACKING SECTION =====
                [
                    'icon' => 'fa-solid fa-map-marked-alt',
                    'label' => 'Live Tracking',
                    'url' => 'employer_tracking_dashboard.php',
                    'badge' => null,
                   
                ],
                // ==========================================
                
                [
                    'icon' => 'fa-solid fa-history',
                    'label' => 'Attendance History',
                    'url' => 'attendance_history.php',
                    'badge' => null,
                  
                ],
                [
                    'icon' => 'fa-solid fa-star',
                    'label' => 'Review Workers',
                    'url' => 'my_completed_jobs.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-wallet',
                    'label' => 'Payment',
                    'url' => 'job_completion_payment.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-message',
                    'label' => 'Messages',
                    'url' => 'messages.php',
                    'badge' => $unread_count ?? 0
                ],
                [
                    'icon' => 'fa-solid fa-user',
                    'label' => 'Profile',
                    'url' => 'profile_page.php',
                    'badge' => null
                ]
            ];
            break;

        case 'worker':
            $sidebar_items = [
                [
                    'icon' => 'fa-solid fa-chart-line',
                    'label' => 'Dashboard',
                    'url' => 'worker_dashboard.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-search',
                    'label' => 'Job Search',
                    'url' => 'jobs.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-file-alt',
                    'label' => 'My Applications',
                    'url' => 'my_applications.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-clock',
                    'label' => 'Attendance',
                    'url' => 'attendance.php',
                    'badge' => null
                ],
                // ===== NEW: ATTENDANCE HISTORY =====
                [
                    'icon' => 'fa-solid fa-history',
                    'label' => 'Attendance History',
                    'url' => 'worker_attendance_history.php',
                    'badge' => null,
                    
                ],
                // ====================================
                [
                    'icon' => 'fa-solid fa-star',
                    'label' => 'Give Feedback',
                    'url' => 'my_completed_jobs.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-wallet',
                    'label' => 'Payment',
                    'url' => 'worker_payment.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-message',
                    'label' => 'Messages',
                    'url' => 'messages.php',
                    'badge' => $unread_count ?? 0
                ],
                [
                    'icon' => 'fa-solid fa-user',
                    'label' => 'Profile',
                    'url' => 'profile_page.php',
                    'badge' => null
                ]
            ];
            break;

        case 'admin':
            $sidebar_items = [
                [
                    'icon' => 'fa-solid fa-chart-pie',
                    'label' => 'Dashboard',
                    'url' => 'admin_dashboard.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-users',
                    'label' => 'Manage Users',
                    'url' => 'admin_manage_users.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-briefcase',
                    'label' => 'Manage Jobs',
                    'url' => 'admin_manage_jobs.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-file-alt',
                    'label' => 'All Applications',
                    'url' => 'admin_applications.php',
                    'badge' => null
                ],
                // ===== NEW: TRACKING OVERVIEW FOR ADMIN =====
                [
                    'icon' => 'fa-solid fa-map-marked-alt',
                    'label' => 'Location Tracking',
                    'url' => 'employer_tracking_dashboard.php',
                    'badge' => null,
                    'highlight' => true
                ],
                // =============================================
                [
                    'icon' => 'fa-solid fa-chart-bar',
                    'label' => 'Reports',
                    'url' => 'admin_reports.php',
                    'badge' => null
                ],
                [
                    'icon' => 'fa-solid fa-cog',
                    'label' => 'System Settings',
                    'url' => 'admin_settings.php',
                    'badge' => null
                ]
            ];
            break;
    }
}
?>

<!-- Sidebar Container -->
<aside class="pw-sidebar" id="pwSidebar">
    
    <!-- Sidebar Header -->
    <div class="pw-sidebar-header">
        <div class="pw-sidebar-logo">
            <i class="fa-solid fa-briefcase"></i>
            <span>POP!Work</span>
        </div>
        <button class="pw-sidebar-close" id="sidebarClose">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>

    <!-- User Info Card -->
    <?php if (isset($_SESSION['user_id']) && isset($sidebar_display_name)): ?>
    <div class="pw-sidebar-user">
        <div class="pw-user-avatar">
            <?php if ($sidebar_profile_pic): ?>
                <img src="<?= htmlspecialchars($sidebar_profile_pic) ?>" alt="<?= htmlspecialchars($sidebar_display_name) ?>">
            <?php else: ?>
                <div class="pw-user-avatar-placeholder">
                    <?= strtoupper(substr($sidebar_display_name, 0, 1)) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="pw-user-info">
            <div class="pw-user-name" title="<?= htmlspecialchars($sidebar_display_name) ?>">
                <?= htmlspecialchars($sidebar_display_name) ?>
            </div>
            <div class="pw-user-role">
                <?= htmlspecialchars($sidebar_user_role) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Menu -->
    <nav class="pw-sidebar-nav">
        <ul class="pw-sidebar-menu">
            <?php foreach ($sidebar_items as $item): ?>
                <?php 
                    $is_active = ($current_page === basename($item['url'])) ? 'active' : '';
                    $has_badge = $item['badge'] && $item['badge'] > 0;
                    $is_highlighted = isset($item['highlight']) && $item['highlight'];
                ?>
                <li class="pw-sidebar-item <?= $is_active ?> <?= $is_highlighted ? 'highlighted' : '' ?>">
                    <a href="<?= htmlspecialchars($item['url']) ?>" class="pw-sidebar-link">
                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                        <?php if ($has_badge): ?>
                            <span class="pw-sidebar-badge"><?= $item['badge'] ?></span>
                        <?php endif; ?>
                        <?php if ($is_highlighted): ?>
                            <span class="pw-new-feature-badge">NEW</span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <div class="pw-sidebar-footer">
        <a href="backend/logout.php" class="pw-logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>

<!-- Sidebar Toggle Button (Mobile) -->
<button class="pw-sidebar-toggle" id="sidebarToggle">
    <i class="fa-solid fa-bars"></i>
</button>

<!-- Sidebar Overlay (Mobile) -->
<div class="pw-sidebar-overlay" id="sidebarOverlay"></div>

<!-- Include Sidebar CSS -->
<link rel="stylesheet" href="assets/css/sidebar.css">

<!-- Sidebar JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('pwSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');

    // Open sidebar
    toggleBtn?.addEventListener('click', function() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    });

    // Close sidebar
    function closeSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992) {
                closeSidebar();
            }
        }, 250);
    });
});
</script>

<style>
/* Additional CSS for avatar placeholder */
.pw-user-avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #8a1538, #6d1028);
    color: white;
    font-size: 24px;
    font-weight: 600;
    border-radius: 50%;
}

.pw-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}

.pw-user-name {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ===== NEW FEATURE BADGE STYLES ===== */
.pw-new-feature-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    font-size: 9px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: auto;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
    animation: pulse-glow 2s ease-in-out infinite;
}

@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
    }
    50% {
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.6);
    }
}

/* Highlighted menu items (new features) */
.pw-sidebar-item.highlighted .pw-sidebar-link {
    position: relative;
}

.pw-sidebar-item.highlighted .pw-sidebar-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 70%;
    background: linear-gradient(to bottom, #10b981, #059669);
    border-radius: 0 3px 3px 0;
    opacity: 0.8;
}

.pw-sidebar-item.highlighted:hover .pw-sidebar-link::before {
    opacity: 1;
}

/* Additional spacing for badge and new feature badge */
.pw-sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
}

.pw-sidebar-link span:first-of-type {
    flex: 1;
}

/* Adjust badge positioning */
.pw-sidebar-badge {
    margin-left: auto;
}

/* When both badge and new feature badge exist */
.pw-sidebar-link .pw-sidebar-badge + .pw-new-feature-badge {
    margin-left: 4px;
}

/* Mobile responsiveness for badges */
@media (max-width: 768px) {
    .pw-new-feature-badge {
        font-size: 8px;
        padding: 2px 5px;
    }
}
</style>