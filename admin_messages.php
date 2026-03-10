<?php
session_start();
include("backend/db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$admin_id = $_SESSION['user_id'];

// Get admin info
$admin_sql = "SELECT u.email, p.name FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();
$admin_name = $admin_data['name'] ?? explode('@', $admin_data['email'])[0];

// Handle delete message
if (isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id']);
    $delete_reason = trim($_POST['delete_reason']);
    
    // Check if message_deletions table exists, create if not
    $conn->query("CREATE TABLE IF NOT EXISTS message_deletions (
        deletion_id INT PRIMARY KEY AUTO_INCREMENT,
        message_id INT,
        deleted_by INT,
        deleted_by_name VARCHAR(255),
        reason TEXT,
        deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Log deletion
    $log_stmt = $conn->prepare("INSERT INTO message_deletions (message_id, deleted_by, deleted_by_name, reason) VALUES (?, ?, ?, ?)");
    $log_stmt->bind_param("iiss", $message_id, $admin_id, $admin_name, $delete_reason);
    $log_stmt->execute();
    
    // Delete message
    $delete_stmt = $conn->prepare("DELETE FROM messages WHERE message_id = ?");
    $delete_stmt->bind_param("i", $message_id);
    $delete_stmt->execute();
    
    $message = "Message deleted and logged successfully.";
    $message_type = 'success';
}

// Handle flag conversation
if (isset($_POST['flag_conversation'])) {
    $sender_id = intval($_POST['sender_id']);
    $receiver_id = intval($_POST['receiver_id']);
    $job_id = intval($_POST['job_id']);
    $flag_reason = trim($_POST['flag_reason']);
    
    // Check if table exists, create if not
    $conn->query("CREATE TABLE IF NOT EXISTS conversation_flags (
        flag_id INT PRIMARY KEY AUTO_INCREMENT,
        sender_id INT,
        receiver_id INT,
        job_id INT,
        flagged_by INT,
        flagged_by_name VARCHAR(255),
        reason TEXT,
        flagged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $flag_stmt = $conn->prepare("INSERT INTO conversation_flags (sender_id, receiver_id, job_id, flagged_by, flagged_by_name, reason) VALUES (?, ?, ?, ?, ?, ?)");
    $flag_stmt->bind_param("iiiiss", $sender_id, $receiver_id, $job_id, $admin_id, $admin_name, $flag_reason);
    $flag_stmt->execute();
    
    $message = "Conversation flagged successfully.";
    $message_type = 'success';
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="conversations_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Sender', 'Receiver', 'Job', 'Messages', 'Last Message', 'Last Activity']);
    
    $export_sql = "SELECT 
                   COALESCE(sp.name, su.email) as sender,
                   COALESCE(rp.name, ru.email) as receiver,
                   COALESCE(j.title, 'General') as job,
                   COUNT(*) as msg_count,
                   MAX(m.message) as last_msg,
                   MAX(m.timestamp) as last_time
            FROM messages m
            JOIN users su ON m.sender_id = su.user_id
            LEFT JOIN profiles sp ON su.user_id = sp.user_id
            JOIN users ru ON m.receiver_id = ru.user_id
            LEFT JOIN profiles rp ON ru.user_id = rp.user_id
            LEFT JOIN jobs j ON m.job_id = j.job_id
            GROUP BY m.sender_id, m.receiver_id, COALESCE(m.job_id, 0)
            ORDER BY last_time DESC";
    
    $export_result = $conn->query($export_sql);
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['sender'],
            $row['receiver'],
            $row['job'],
            $row['msg_count'],
            substr($row['last_msg'] ?? '', 0, 100),
            $row['last_time'] ?? ''
        ]);
    }
    fclose($output);
    exit();
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$user_filter = isset($_GET['user']) ? trim($_GET['user']) : '';
$job_filter = isset($_GET['job']) ? trim($_GET['job']) : '';

// Query conversations - FIXED to work with messages table
$sql = "SELECT 
        m.sender_id,
        m.receiver_id,
        COALESCE(m.job_id, 0) as job_id,
        COALESCE(j.title, 'General Inquiry') as job_title,
        sp.name as sender_name, su.email as sender_email,
        rp.name as receiver_name, ru.email as receiver_email,
        COUNT(*) as message_count,
        MAX(m.message) as last_message,
        MAX(m.type) as last_message_type,
        MAX(m.timestamp) as last_message_time
        FROM messages m
        JOIN users su ON m.sender_id = su.user_id
        LEFT JOIN profiles sp ON su.user_id = sp.user_id
        JOIN users ru ON m.receiver_id = ru.user_id
        LEFT JOIN profiles rp ON ru.user_id = rp.user_id
        LEFT JOIN jobs j ON m.job_id = j.job_id
        WHERE 1=1";

if ($user_filter != '') {
    $user_term = $conn->real_escape_string($user_filter);
    $sql .= " AND (sp.name LIKE '%$user_term%' OR su.email LIKE '%$user_term%' OR rp.name LIKE '%$user_term%' OR ru.email LIKE '%$user_term%')";
}

if ($job_filter != '') {
    $sql .= " AND j.title LIKE '%" . $conn->real_escape_string($job_filter) . "%'";
}

if ($search != '') {
    $search_term = $conn->real_escape_string($search);
    $sql .= " AND m.message LIKE '%$search_term%'";
}

$sql .= " GROUP BY m.sender_id, m.receiver_id, COALESCE(m.job_id, 0)
          ORDER BY last_message_time DESC LIMIT 100";

$conversations = $conn->query($sql);

// Stats
$total_conversations = $conn->query("SELECT COUNT(DISTINCT CONCAT(sender_id, '-', receiver_id, '-', COALESCE(job_id, 0))) as count FROM messages")->fetch_assoc()['count'];
$total_messages = $conn->query("SELECT COUNT(*) as count FROM messages")->fetch_assoc()['count'];
$text_messages = $conn->query("SELECT COUNT(*) as count FROM messages WHERE type = 'text'")->fetch_assoc()['count'];
$image_messages = $conn->query("SELECT COUNT(*) as count FROM messages WHERE type = 'image'")->fetch_assoc()['count'];
$audio_messages = $conn->query("SELECT COUNT(*) as count FROM messages WHERE type = 'audio'")->fetch_assoc()['count'];

// Check if tables exist for stats
$check_flags = $conn->query("SHOW TABLES LIKE 'conversation_flags'");
$flagged_count = 0;
if ($check_flags && $check_flags->num_rows > 0) {
    $flagged_count = $conn->query("SELECT COUNT(*) as count FROM conversation_flags")->fetch_assoc()['count'];
}

$check_deletions = $conn->query("SHOW TABLES LIKE 'message_deletions'");
$deleted_today = 0;
if ($check_deletions && $check_deletions->num_rows > 0) {
    $deleted_today = $conn->query("SELECT COUNT(*) as count FROM message_deletions WHERE DATE(deleted_at) = CURDATE()")->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Monitoring | POP!Work Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pop-maroon: #8a1538; --pop-light: #fdf2f4; --pop-gray: #f8f9fa; --pop-maroon-dark: #6d1029; }
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
        
        .main-content { margin-left: 260px; padding: 24px; }
        .top-bar { background: white; padding: 16px 24px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .top-bar h1 { font-size: 28px; color: var(--pop-maroon); font-weight: 700; }
        
        .alert-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; }
        .alert-box strong { color: #92400e; display: block; margin-bottom: 4px; }
        .alert-box p { color: #92400e; font-size: 14px; margin: 0; }
        
        .message { padding: 16px 24px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .message.success { background: #f0fdf4; color: #166534; border-left: 4px solid #10b981; }
        
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-box-label { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
        .stat-box-value { font-size: 28px; font-weight: 700; color: var(--pop-maroon); }
        .stat-box-sublabel { font-size: 12px; color: #9ca3af; margin-top: 4px; }
        
        .filters-bar { background: white; padding: 20px 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .filters-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 200px; position: relative; }
        .search-box input { width: 100%; padding: 12px 16px 12px 44px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-family: 'Poppins'; }
        .search-box i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
        .filter-input { padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-family: 'Poppins'; }
        
        .btn { padding: 12px 20px; border: none; border-radius: 10px; font-size: 14px; font-weight: 500; cursor: pointer; font-family: 'Poppins'; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: linear-gradient(135deg, var(--pop-maroon), var(--pop-maroon-dark)); color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .btn-danger { background: #ef4444; color: white; }
        
        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--pop-light); }
        th { padding: 16px; text-align: left; font-size: 13px; font-weight: 600; color: var(--pop-maroon); text-transform: uppercase; }
        td { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        tr:hover { background: var(--pop-light); }
        
        .table-container { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; }
        .table-header { padding: 20px 24px; border-bottom: 2px solid var(--pop-gray); display: flex; justify-content: space-between; }
        
        .action-btn { padding: 6px 12px; border: none; border-radius: 8px; font-size: 12px; cursor: pointer; font-weight: 500; }
        .action-btn.view { background: #dbeafe; color: #1e40af; }
        .action-btn.flag { background: #fef3c7; color: #92400e; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 16px; width: 100%; max-width: 900px; max-height: 90vh; display: flex; flex-direction: column; }
        .modal-header { padding: 24px; border-bottom: 2px solid var(--pop-gray); display: flex; justify-content: space-between; }
        .modal-body { padding: 24px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 20px 24px; border-top: 2px solid var(--pop-gray); display: flex; gap: 12px; justify-content: flex-end; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 500; }
        .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 10px; min-height: 100px; font-family: 'Poppins'; }
        
        .messages-container { display: flex; flex-direction: column; gap: 12px; max-height: 500px; overflow-y: auto; padding: 16px; background: #f9fafb; border-radius: 12px; }
        .chat-message { display: flex; gap: 12px; align-items: flex-start; }
        .chat-message.sender { flex-direction: row; }
        .chat-message.receiver { flex-direction: row-reverse; }
        .message-bubble { max-width: 60%; padding: 12px 16px; border-radius: 12px; }
        .chat-message.sender .message-bubble { background: white; border: 1px solid #e5e7eb; }
        .chat-message.receiver .message-bubble { background: linear-gradient(135deg, var(--pop-maroon), var(--pop-maroon-dark)); color: white; }
        .message-sender { font-size: 12px; font-weight: 600; margin-bottom: 4px; }
        .message-text { font-size: 14px; line-height: 1.5; }
        .message-meta { font-size: 11px; margin-top: 6px; }
        .message-action-btn { padding: 4px 10px; font-size: 11px; border-radius: 6px; border: none; cursor: pointer; margin-top: 8px; }
        .message-action-btn.delete { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>POP!Work</h2><p>Admin Panel</p></div>
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
            <a href="admin_messages.php" class="menu-item active">
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
            <h1><i class="fas fa-comments"></i> Message Monitoring</h1>
            <a href="?export=csv" class="btn btn-success"><i class="fas fa-download"></i> Export CSV</a>
        </div>

        <div class="alert-box">
            <strong><i class="fas fa-shield-alt"></i> Privacy & Safety Notice</strong>
            <p>Message monitoring is for safety and moderation only. Respect user privacy. All admin actions are logged.</p>
        </div>

        <?php if (!empty($message)): ?>
        <div class="message success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-label">Total Conversations</div>
                <div class="stat-box-value"><?= number_format($total_conversations) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Total Messages</div>
                <div class="stat-box-value"><?= number_format($total_messages) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Flagged</div>
                <div class="stat-box-value" style="color: #f59e0b;"><?= $flagged_count ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">Deleted Today</div>
                <div class="stat-box-value" style="color: #ef4444;"><?= $deleted_today ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">By Type</div>
                <div class="stat-box-sublabel">
                    <i class="fas fa-comment"></i> <?= $text_messages ?> • 
                    <i class="fas fa-image"></i> <?= $image_messages ?> • 
                    <i class="fas fa-microphone"></i> <?= $audio_messages ?>
                </div>
            </div>
        </div>

        <div class="filters-bar">
            <form method="GET">
                <div class="filters-row">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search messages..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <input type="text" name="user" class="filter-input" placeholder="Filter by user..." value="<?= htmlspecialchars($user_filter) ?>">
                    <input type="text" name="job" class="filter-input" placeholder="Filter by job..." value="<?= htmlspecialchars($job_filter) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                </div>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Conversations</h3>
                <span style="color: #6b7280; font-size: 14px;">Last 100</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Participants</th>
                        <th>Job</th>
                        <th>Last Message</th>
                        <th>Count</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($conversations->num_rows > 0): ?>
                        <?php while ($conv = $conversations->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div><strong><?= htmlspecialchars($conv['sender_name'] ?? $conv['sender_email']) ?></strong> → <?= htmlspecialchars($conv['receiver_name'] ?? $conv['receiver_email']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($conv['job_title']) ?></td>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; color: #6b7280;">
                                <?= $conv['last_message_type'] == 'text' ? htmlspecialchars(substr($conv['last_message'] ?? '', 0, 50)) : '[' . ucfirst($conv['last_message_type'] ?? 'No messages') . ']' ?>
                            </td>
                            <td><strong><?= $conv['message_count'] ?></strong></td>
                            <td><?= $conv['last_message_time'] ? date('M d, h:i A', strtotime($conv['last_message_time'])) : 'N/A' ?></td>
                            <td>
                                <button class="action-btn view" onclick="viewConversation(<?= $conv['sender_id'] ?>, <?= $conv['receiver_id'] ?>, <?= $conv['job_id'] ?>)"><i class="fas fa-eye"></i> View</button>
                                <button class="action-btn flag" onclick="flagConversation(<?= $conv['sender_id'] ?>, <?= $conv['receiver_id'] ?>, <?= $conv['job_id'] ?>)"><i class="fas fa-flag"></i> Flag</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 40px; color: #9ca3af;">No conversations found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modals -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-comments"></i> Conversation</h3>
                <button class="btn btn-secondary" onclick="closeModal('viewModal')" style="width: 36px; height: 36px; padding: 0; border-radius: 50%;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="viewModalBody">Loading...</div>
        </div>
    </div>

    <div id="flagModal" class="modal">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-flag"></i> Flag Conversation</h3>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('flagModal')" style="width: 36px; height: 36px; padding: 0; border-radius: 50%;"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="sender_id" id="flag_sender_id">
                    <input type="hidden" name="receiver_id" id="flag_receiver_id">
                    <input type="hidden" name="job_id" id="flag_job_id">
                    <div class="form-group">
                        <label>Reason *</label>
                        <textarea name="flag_reason" required placeholder="Why are you flagging this conversation?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('flagModal')">Cancel</button>
                    <button type="submit" name="flag_conversation" class="btn btn-danger"><i class="fas fa-flag"></i> Flag</button>
                </div>
            </div>
        </form>
    </div>

    <div id="deleteModal" class="modal">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-trash"></i> Delete Message</h3>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')" style="width: 36px; height: 36px; padding: 0; border-radius: 50%;"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="message_id" id="delete_message_id">
                    <div style="background: #fee2e2; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ef4444;">
                        <strong style="color: #991b1b;">Warning: This cannot be undone</strong>
                    </div>
                    <div class="form-group">
                        <label>Reason for Deletion *</label>
                        <textarea name="delete_reason" required placeholder="Why are you deleting this message?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" name="delete_message" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function viewConversation(senderId, receiverId, jobId) {
            document.getElementById('viewModal').classList.add('active');
            document.getElementById('viewModalBody').innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:32px;color:var(--pop-maroon);"></i><p style="margin-top:16px;color:#6b7280;">Loading conversation...</p></div>';
            
            fetch(`backend/get_conversation_detail.php?sender_id=${senderId}&receiver_id=${receiverId}&job_id=${jobId}`)
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        document.getElementById('viewModalBody').innerHTML = '<p style="text-align:center;color:#ef4444;">Error loading conversation</p>';
                        return;
                    }
                    
                    let html = '<div style="background:#f9fafb;padding:16px;border-radius:12px;margin-bottom:20px;">';
                    html += '<h4 style="color:#1f2937;margin-bottom:8px;"><i class="fas fa-info-circle"></i> Conversation Details</h4>';
                    html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">';
                    html += `<div><strong>Sender:</strong> ${d.conversation.sender_name} (${d.conversation.sender_email})</div>`;
                    html += `<div><strong>Receiver:</strong> ${d.conversation.receiver_name} (${d.conversation.receiver_email})</div>`;
                    html += `<div><strong>Job:</strong> ${d.conversation.job_title}</div>`;
                    html += `<div><strong>Total Messages:</strong> ${d.messages.length}</div>`;
                    html += '</div></div>';
                    
                    html += '<div class="messages-container">';
                    if (d.messages.length === 0) {
                        html += '<p style="text-align:center;color:#9ca3af;">No messages</p>';
                    } else {
                        d.messages.forEach(m => {
                            const isSender = m.sender_id == d.conversation.sender_id;
                            const cls = isSender ? 'sender' : 'receiver';
                            const name = isSender ? d.conversation.sender_name : d.conversation.receiver_name;
                            
                            let content = '';
                            if (m.file_path) {
                                const ext = m.file_path.split('.').pop().toLowerCase();
                                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                                    content = `<img src="${m.file_path}" style="max-width:250px;border-radius:8px;cursor:pointer;" onclick="window.open('${m.file_path}','_blank')">`;
                                } else if (['mp3', 'wav', 'webm', 'ogg'].includes(ext)) {
                                    content = `<audio controls src="${m.file_path}" style="max-width:250px;"></audio>`;
                                } else {
                                    content = `<a href="${m.file_path}" target="_blank" style="color:inherit;">📎 ${m.file_path.split('/').pop()}</a>`;
                                }
                            } else {
                                content = m.message.replace(/\n/g, '<br>');
                            }
                            
                            html += `
                                <div class="chat-message ${cls}">
                                    <div class="message-bubble">
                                        <div class="message-sender">${name}</div>
                                        <div class="message-text">${content}</div>
                                        <div class="message-meta" style="opacity:0.7;">
                                            ${new Date(m.timestamp).toLocaleString()}
                                            ${m.is_edited == 1 ? '<span style="font-style:italic;"> • edited</span>' : ''}
                                        </div>
                                        <button class="message-action-btn delete" onclick="deleteMessage(${m.message_id})">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    html += '</div>';
                    document.getElementById('viewModalBody').innerHTML = html;
                    
                    // Scroll to bottom
                    setTimeout(() => {
                        const c = document.querySelector('.messages-container');
                        if (c) c.scrollTop = c.scrollHeight;
                    }, 100);
                })
                .catch(err => {
                    console.error('Error:', err);
                    document.getElementById('viewModalBody').innerHTML = '<p style="text-align:center;color:#ef4444;">Failed to load conversation. Please try again.</p>';
                });
        }

        function flagConversation(senderId, receiverId, jobId) {
            document.getElementById('flag_sender_id').value = senderId;
            document.getElementById('flag_receiver_id').value = receiverId;
            document.getElementById('flag_job_id').value = jobId;
            document.getElementById('flagModal').classList.add('active');
        }

        function deleteMessage(id) {
            closeModal('viewModal');
            document.getElementById('delete_message_id').value = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(m => {
            m.addEventListener('click', e => {
                if (e.target === m) m.classList.remove('active');
            });
        });
    </script>
</body>
</html>