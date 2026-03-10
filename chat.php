<?php
// Start output buffering to prevent any accidental output before DOCTYPE
ob_start();

session_start();
include("backend/db_connect.php");

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info for sidebar
$user_sql = "SELECT p.name, u.email FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

// Get unread messages count for sidebar
$unread_query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'];

// 2. Safely Get IDs from URL
$job_id = isset($_GET['job_id']) && !empty($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Determine who we are talking to
if (isset($_GET['applicant_id'])) $other_user_id = (int)$_GET['applicant_id'];
elseif (isset($_GET['employer_id'])) $other_user_id = (int)$_GET['employer_id'];
else $other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if (!$other_user_id) {
    die("Error: No recipient specified.");
}

// 3. Fetch Partner Info & Job Title
$partner_name = "Messages";
$job_display_title = "General Inquiry";
$last_seen = "Offline";

// Update current user's last activity
$conn->query("UPDATE users SET last_activity = NOW() WHERE user_id = $user_id");

// Get Partner details
$stmt = $conn->prepare("SELECT u.email, p.name, p.profile_picture, u.last_activity 
                        FROM users u 
                        LEFT JOIN profiles p ON u.user_id = p.user_id 
                        WHERE u.user_id = ?");
$stmt->bind_param("i", $other_user_id);
$stmt->execute();
$partner = $stmt->get_result()->fetch_assoc();

if ($partner) {
    $partner_name = $partner['name'] ?? explode('@', $partner['email'])[0];
    $partner_profile_pic = $partner['profile_picture'];
    $diff = time() - strtotime($partner['last_activity'] ?? 'now');
    if ($diff < 60) $last_seen = "Online";
    elseif ($diff < 3600) $last_seen = floor($diff / 60) . " min ago";
    elseif ($diff < 86400) $last_seen = floor($diff / 3600) . " hours ago";
    else $last_seen = date("M d, Y", strtotime($partner['last_activity']));
}

// Get Job Title if job_id exists
if ($job_id > 0) {
    $stmt_job = $conn->prepare("SELECT title FROM jobs WHERE job_id = ?");
    $stmt_job->bind_param("i", $job_id);
    $stmt_job->execute();
    $job_res = $stmt_job->get_result()->fetch_assoc();
    if ($job_res) $job_display_title = $job_res['title'];
}

// Clean any output that might have been buffered and prepare for clean HTML output
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= htmlspecialchars($partner_name) ?> | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <style>
        :root { 
            --pop-maroon: #8a1538; 
            --pop-light: #fdf2f4; 
            --pop-gray: #f8f9fa;
            --pop-maroon-dark: #6d1029;
            --pop-maroon-light: #a71d47;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background: #f0f2f5; 
            height: 100vh; 
            display: flex; 
            overflow: hidden; 
        }
        
        .dashboard-wrapper {
            display: flex;
            width: 100%;
            height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            transition: margin-left 0.3s ease;
            padding: 20px;
            background: #e8eaf0;
        }
        
        .chat-main-wrapper { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            max-width: 900px; 
            max-height: 700px;
            width: 100%; 
            margin: 0 auto; 
            background: white; 
            position: relative; 
            overflow: hidden; 
            box-shadow: 0 6px 30px rgba(138, 21, 56, 0.15);
            border-radius: 20px;
        }

        /* Header - POP!Work Style */
        .chat-header { 
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            padding: 16px 24px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 20px 20px 0 0;
        }
        
        .header-left { 
            display: flex; 
            align-items: center; 
            gap: 16px;
            flex: 1;
        }
        
        .back-btn { 
            color: white; 
            font-size: 20px; 
            text-decoration: none;
            transition: transform 0.2s;
            opacity: 0.9;
        }
        
        .back-btn:hover {
            opacity: 1;
            transform: translateX(-3px);
        }
        
        .avatar { 
            width: 44px; 
            height: 44px; 
            border-radius: 50%; 
            background: white; 
            color: var(--pop-maroon); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            overflow: hidden;
            font-size: 18px;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .avatar img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }
        
        .partner-meta { 
            flex: 1;
        }
        
        .partner-meta h4 { 
            font-size: 17px; 
            font-weight: 600; 
            color: white; 
            line-height: 1.3;
            margin-bottom: 2px;
        }
        
        .partner-meta span { 
            font-size: 12px; 
            color: rgba(255,255,255,0.8);
        }
        
        .partner-meta span.online {
            color: #4ade80;
        }
        
        .job-tag { 
            font-size: 11px; 
            background: rgba(255,255,255,0.2); 
            color: white; 
            padding: 3px 12px; 
            border-radius: 12px; 
            display: inline-block; 
            margin-top: 4px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-actions i {
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s;
            opacity: 0.9;
        }

        .header-actions i:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        /* Messages Container - POP!Work Style */
        .messages-container { 
            flex: 1; 
            overflow-y: auto; 
            padding: 24px; 
            display: flex; 
            flex-direction: column; 
            gap: 12px;
            background: linear-gradient(to bottom, #fafbfc 0%, #f5f7fa 100%);
            position: relative;
        }

        /* Custom Scrollbar */
        .messages-container::-webkit-scrollbar {
            width: 8px;
        }

        .messages-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .messages-container::-webkit-scrollbar-thumb {
            background: rgba(138, 21, 56, 0.2);
            border-radius: 10px;
        }

        .messages-container::-webkit-scrollbar-thumb:hover {
            background: rgba(138, 21, 56, 0.3);
        }

        /* Date Divider */
        .date-divider {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .date-divider span {
            background: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            color: #666;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            font-weight: 500;
        }

        /* Message Bubbles - POP!Work Style */
        .message-wrapper { 
            display: flex; 
            align-items: flex-end; 
            gap: 10px; 
            max-width: 70%; 
            position: relative;
            margin-bottom: 4px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-wrapper.sent { 
            align-self: flex-end; 
            flex-direction: row-reverse; 
        }
        
        .bubble { 
            background: white; 
            padding: 10px 16px 12px;
            border-radius: 18px; 
            position: relative; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            min-width: 60px; 
            font-size: 14.5px; 
            word-wrap: break-word;
            line-height: 1.4;
            border: 1px solid #f0f0f0;
        }

        .message-wrapper.sent .bubble { 
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white;
            border: none;
            box-shadow: 0 2px 12px rgba(138, 21, 56, 0.25);
        }

        .msg-body {
            margin-bottom: 4px;
            color: #1f2937;
        }

        .message-wrapper.sent .msg-body {
            color: white;
        }

        .msg-chev { 
            position: absolute; 
            top: 50%; 
            transform: translateY(-50%);
            cursor: pointer; 
            opacity: 0; 
            transition: opacity 0.2s; 
            color: #999; 
            font-size: 16px;
            padding: 6px;
            border-radius: 50%;
        }
        
        .msg-chev:hover {
            background: rgba(0,0,0,0.05);
        }

        .message-wrapper.sent .msg-chev { 
            left: -28px !important;
            right: auto !important;
        }

        .message-wrapper:not(.sent) .msg-chev { 
            right: 490px !important;
            left: auto !important;
        }
        
        .message-wrapper:hover .msg-chev { 
            opacity: 0.6; 
        }

        .msg-chev:hover {
            opacity: 4 !important;
        }

        .meta { 
            font-size: 10px; 
            color: #9ca3af;
            display: flex; 
            align-items: center; 
            justify-content: flex-end; 
            gap: 4px; 
            margin-top: 2px;
        }

        .message-wrapper.sent .meta {
            color: rgba(255,255,255,0.7);
        }
        
        .meta i {
            font-size: 12px;
        }

        .meta .check-mark {
            color: #4ade80;
            font-size: 14px;
        }

        .meta .edited-label {
            font-style: italic;
            opacity: 0.8;
            margin-right: 4px;
        }
        
        /* Input area - POP!Work Style */
        .input-area { 
            padding: 16px 24px; 
            background: white;
            border-top: 1px solid #e5e7eb;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        
        .input-flex { 
            display: flex; 
            gap: 12px; 
            align-items: center; 
        }

        .input-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .input-actions i {
            color: #6b7280;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .input-actions i:hover {
            color: var(--pop-maroon);
            transform: scale(1.1);
        }
        
        .msg-input { 
            flex: 1; 
            padding: 12px 18px; 
            border: 2px solid #e5e7eb;
            border-radius: 24px; 
            outline: none; 
            background: #f9fafb;
            font-size: 14px;
            font-family: inherit;
            resize: none;
            max-height: 120px;
            transition: all 0.2s;
        }

        .msg-input:focus {
            border-color: var(--pop-maroon);
            background: white;
            box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
        }
        
        .send-btn { 
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white; 
            border: none; 
            width: 50px; 
            height: 50px; 
            border-radius: 50%; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3);
        }

        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(138, 21, 56, 0.4);
        }

        .send-btn:active {
            transform: scale(0.95);
        }

        .send-btn i {
            font-size: 18px;
        }
        
        /* Delete Options Modal - POP!Work Style */
        .delete-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 3000;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .delete-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 20px;
            padding: 0;
            min-width: 400px;
            max-width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .delete-modal-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .delete-modal-header h3 {
            color: #1f2937;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .delete-modal-header p {
            color: #6b7280;
            font-size: 13px;
        }

        .delete-modal-body {
            padding: 8px 0;
        }

        .delete-option {
            padding: 16px 24px;
            cursor: pointer;
            transition: background 0.2s;
            border: none;
            width: 100%;
            text-align: left;
            background: none;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .delete-option:hover {
            background: #f9fafb;
        }

        .delete-option-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .delete-option-icon.delete-me {
            background: #fef3c7;
            color: #f59e0b;
        }

        .delete-option-icon.unsend {
            background: #fee2e2;
            color: #ef4444;
        }

        .delete-option-text h4 {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .delete-option-text p {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.4;
        }

        .delete-option-text .time-limit {
            color: #ef4444;
            font-weight: 500;
            margin-top: 4px;
            font-size: 11px;
        }

        .delete-modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
        }

        .cancel-btn {
            padding: 10px 24px;
            border: none;
            background: #f3f4f6;
            color: #4b5563;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }

        .cancel-btn:hover {
            background: #e5e7eb;
        }
        
        /* Context Menu - POP!Work Style */
        .pop-dropdown { 
            display: none; 
            position: fixed; 
            background: white; 
            border-radius: 12px; 
            z-index: 2000; 
            min-width: 180px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .pop-dropdown button { 
            width: 100%; 
            padding: 12px 20px; 
            border: none; 
            background: none; 
            text-align: left; 
            cursor: pointer; 
            font-size: 14px; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            color: #374151;
            transition: background 0.1s;
        }
        
        .pop-dropdown button:hover { 
            background: #f9fafb;
        }

        .pop-dropdown button.danger:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .pop-dropdown button i {
            width: 20px;
            color: #6b7280;
        }

        .pop-dropdown button.danger i {
            color: #dc2626;
        }
        
        /* Modals */
        .fullview-modal { 
            display: none; 
            position: fixed; 
            inset: 0; 
            background: rgba(0,0,0,0.95); 
            z-index: 3000;
            backdrop-filter: blur(10px);
        }
        
        .fullview-content { 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            max-width: 90%;
            max-height: 90vh;
        }
        
        .fullview-content img { 
            width: 100%;
            height: auto;
            border-radius: 12px; 
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }

        .close-fullview {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 32px;
            cursor: pointer;
            z-index: 3001;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            transition: all 0.2s;
        }

        .close-fullview:hover {
            background: rgba(0,0,0,0.7);
            transform: rotate(90deg);
        }

        /* Recording UI */
        #recordingUI { 
            display: none; 
            flex: 1; 
            align-items: center; 
            gap: 12px; 
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            padding: 12px 18px;
            border-radius: 24px;
            border: 2px solid #fca5a5;
            position: relative;
            overflow: hidden;
        }

        #recordingUI::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(to bottom, #ef4444, #dc2626);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { height: 0; }
            to { height: 100%; }
        }

        .recording-indicator {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .recording-wave {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .wave-bar {
            width: 3px;
            height: 12px;
            background: #ef4444;
            border-radius: 2px;
            animation: wave 1s ease-in-out infinite;
        }

        .wave-bar:nth-child(1) { animation-delay: 0s; }
        .wave-bar:nth-child(2) { animation-delay: 0.1s; }
        .wave-bar:nth-child(3) { animation-delay: 0.2s; }
        .wave-bar:nth-child(4) { animation-delay: 0.3s; }
        .wave-bar:nth-child(5) { animation-delay: 0.4s; }

        @keyframes wave {
            0%, 100% { height: 8px; }
            50% { height: 20px; }
        }
        
        .dot { 
            width: 10px; 
            height: 10px; 
            background: #ef4444; 
            border-radius: 50%; 
            animation: pulse 1.5s infinite;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }

        @keyframes pulse {
            0%, 100% { 
                opacity: 1; 
                transform: scale(1);
                box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
            }
            50% { 
                opacity: 0.6; 
                transform: scale(0.85);
                box-shadow: 0 0 20px rgba(239, 68, 68, 0.8);
            }
        }

        #timer {
            font-size: 15px;
            color: #dc2626;
            font-weight: 700;
            min-width: 50px;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.5px;
        }

        .recording-text {
            color: #991b1b;
            font-size: 13px;
            font-weight: 600;
            margin-left: 4px;
        }

        .delete-recording {
            background: white;
            border: 2px solid #fee2e2;
            color: #dc2626;
            cursor: pointer;
            font-size: 18px;
            padding: 10px;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.15);
        }

        .delete-recording:hover {
            transform: scale(1.1) rotate(10deg);
            background: #fef2f2;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }

        .delete-recording:active {
            transform: scale(0.95);
        }

        /* Hide voice button and show recording UI when recording */
        body.recording #voiceBtn {
            display: none;
        }

        body.recording #recordingUI {
            display: flex;
        }

        body.recording #msgInput {
            display: none;
        }

        body.recording .input-actions label {
            pointer-events: none;
            opacity: 0.5;
        }
        
        /* Message being edited */
        .message-wrapper.editing {
            opacity: 0.7;
            position: relative;
        }

        .message-wrapper.editing::after {
            content: "Editing...";
            position: absolute;
            top: -28px;
            right: 0;
            font-size: 12px;
            color: var(--pop-maroon);
            background: var(--pop-light);
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .msg-body img { 
            max-width: 300px;
            width: 100%;
            border-radius: 12px; 
            cursor: pointer;
            margin: 4px 0;
            transition: transform 0.2s;
        }

        .msg-body img:hover {
            transform: scale(1.02);
        }
        
        .msg-body audio { 
            max-width: 280px;
            margin: 4px 0;
        }
        
        /* Edit mode indicator */
        #editModeIndicator { 
            display: none; 
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px; 
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white;
            font-size: 14px; 
            font-weight: 500;
        }
        
        #editModeIndicator i { 
            margin-right: 8px; 
        }

        #editModeIndicator button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            cursor: pointer;
            font-size: 13px;
            padding: 6px 16px;
            border-radius: 8px;
            transition: background 0.2s;
            font-weight: 500;
        }

        #editModeIndicator button:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Loading indicator */
        .loading-indicator {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
            font-size: 14px;
        }

        .loading-indicator i {
            font-size: 32px;
            margin-bottom: 16px;
            color: var(--pop-maroon);
        }

        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 60px 40px;
            text-align: center;
        }

        .empty-state i {
            font-size: 80px;
            color: var(--pop-maroon);
            margin-bottom: 24px;
            opacity: 0.3;
        }

        .empty-state h3 {
            color: #374151;
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #9ca3af;
            font-size: 14px;
        }

        /* Go to Bottom Button */
        .go-to-bottom {
            position: absolute;
            bottom: 100px;
            right: 24px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: none;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: all 0.3s;
            color: var(--pop-maroon);
        }

        .go-to-bottom:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(138, 21, 56, 0.3);
        }

        .go-to-bottom.show {
            display: flex;
            animation: bounceIn 0.4s ease;
        }

        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .go-to-bottom i {
            font-size: 18px;
        }

        .unread-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--pop-maroon);
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        /* File Preview Modal */
        .file-preview-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            z-index: 3000;
            backdrop-filter: blur(6px);
            animation: fadeIn 0.2s;
        }

        .file-preview-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 20px;
            padding: 0;
            min-width: 400px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
            overflow: hidden;
        }

        .file-preview-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .file-preview-header h3 {
            color: #1f2937;
            font-size: 18px;
            font-weight: 600;
        }

        .close-preview {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 24px;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .close-preview:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .file-preview-body {
            padding: 24px;
            max-height: 400px;
            overflow-y: auto;
        }

        .file-preview-image {
            width: 100%;
            border-radius: 12px;
            max-height: 350px;
            object-fit: contain;
        }

        .file-preview-info {
            background: #f9fafb;
            padding: 16px;
            border-radius: 12px;
            margin-top: 16px;
        }

        .file-preview-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .file-preview-info-row:last-child {
            margin-bottom: 0;
        }

        .file-preview-info-label {
            color: #6b7280;
            font-weight: 500;
        }

        .file-preview-info-value {
            color: #1f2937;
            font-weight: 600;
        }

        .file-preview-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .preview-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }

        .preview-btn-cancel {
            background: #f3f4f6;
            color: #4b5563;
        }

        .preview-btn-cancel:hover {
            background: #e5e7eb;
        }

        .preview-btn-send {
            background: linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(138, 21, 56, 0.3);
        }

        .preview-btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(138, 21, 56, 0.4);
        }

        .preview-btn-send:active {
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 0;
            }

            .chat-main-wrapper {
                border-radius: 0;
                max-height: 100vh;
            }

            .messages-container {
                padding: 16px;
            }

            .message-wrapper {
                max-width: 85%;
            }

            .delete-modal-content {
                min-width: 90%;
            }

            .file-preview-content {
                min-width: 90%;
            }

            .go-to-bottom {
                bottom: 90px;
                right: 16px;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-wrapper">
    <?php include("includes/sidebar.php"); ?>

    <div class="main-content">
        <div class="chat-main-wrapper">
            <!-- Header -->
            <div class="chat-header">
                <div class="header-left">
                    <a href="messages.php" class="back-btn" title="Back to messages">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div class="avatar">
                        <?php if (!empty($partner_profile_pic)): ?>
                            <img src="<?= htmlspecialchars($partner_profile_pic) ?>" alt="<?= htmlspecialchars($partner_name) ?>">
                        <?php else: ?>
                            <?= strtoupper(substr($partner_name, 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="partner-meta">
                        <h4><?= htmlspecialchars($partner_name) ?></h4>
                        <span class="<?= $last_seen === 'Online' ? 'online' : '' ?>">
                            <?= htmlspecialchars($last_seen) ?>
                        </span>
                        <?php if ($job_id > 0): ?>
                            <div class="job-tag"><?= htmlspecialchars($job_display_title) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Edit Mode Indicator -->
            <div id="editModeIndicator">
                <span>
                    <i class="fa-solid fa-pen"></i>
                    Edit message
                </span>
                <button onclick="cancelEdit()">Cancel</button>
            </div>

            <!-- Messages Container -->
            <div class="messages-container" id="messagesContainer">
                <div class="loading-indicator">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <div>Loading messages...</div>
                </div>
            </div>

            <!-- Go to Bottom Button -->
            <button class="go-to-bottom" id="goToBottomBtn" onclick="scrollToBottom(true)">
                <i class="fa-solid fa-chevron-down"></i>
            </button>

            <!-- Input Area -->
            <div class="input-area">
                <div class="input-flex">
                    <div id="recordingUI">
                        <div class="recording-indicator">
                            <div class="dot"></div>
                            <div class="recording-wave">
                                <div class="wave-bar"></div>
                                <div class="wave-bar"></div>
                                <div class="wave-bar"></div>
                                <div class="wave-bar"></div>
                                <div class="wave-bar"></div>
                            </div>
                            <span id="timer">00:00</span>
                            <span class="recording-text">Recording...</span>
                        </div>
                        <button class="delete-recording" onclick="cancelRecording()" title="Cancel recording">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                    
                    <div class="input-actions">
                        <button class="send-btn" id="voiceBtn" onclick="startRecording()" title="Voice message" style="width: 44px; height: 44px; background: linear-gradient(135deg, #b9105cff 0%, #b12f61ff 100%); box-shadow: 0 4px 12px rgba(255, 255, 255, 1);">
                            <i class="fa-solid fa-microphone"></i>
                        </button>
                        <label for="fileInput" style="cursor: pointer; display: flex;">
                            <i class="fa-solid fa-paperclip" title="Attach file"></i>
                        </label>
                        <input type="file" id="fileInput" accept="image/*,audio/*,video/*" style="display:none;" onchange="showFilePreview(this)">
                    </div>
                    
                    <textarea 
                        id="msgInput" 
                        class="msg-input" 
                        placeholder="Type a message..." 
                        rows="1"
                        style="display: flex;"
                    ></textarea>
                    
                    <button class="send-btn" id="sendBtn" onclick="handleSendAction()" title="Send">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Context Menu -->
<div id="popMenu" class="pop-dropdown">
    <button onclick="copyText()">
        <i class="fa-solid fa-copy"></i>
        Copy
    </button>
    <button onclick="editMsg()">
        <i class="fa-solid fa-pen"></i>
        Edit
    </button>
    <button onclick="showDeleteModal()" class="danger">
        <i class="fa-solid fa-trash"></i>
        Delete
    </button>
</div>

<!-- Delete Options Modal -->
<div id="deleteModal" class="delete-modal" onclick="closeDeleteModal()">
    <div class="delete-modal-content" onclick="event.stopPropagation()">
        <div class="delete-modal-header">
            <h3>Delete Message</h3>
            <p>Choose how you want to delete this message</p>
        </div>
        <div class="delete-modal-body">
            <button class="delete-option" onclick="deleteMessage('delete_for_me')">
                <div class="delete-option-icon delete-me">
                    <i class="fa-solid fa-user-minus"></i>
                </div>
                <div class="delete-option-text">
                    <h4>Delete for Me</h4>
                    <p>This message will be removed from your chat only</p>
                </div>
            </button>
            <button class="delete-option" onclick="deleteMessage('unsend')" id="unsendOption">
                <div class="delete-option-icon unsend">
                    <i class="fa-solid fa-ban"></i>
                </div>
                <div class="delete-option-text">
                    <h4>Unsend for Everyone</h4>
                    <p>This message will be removed for everyone in the chat</p>
                    <div class="time-limit" id="unsendTimeLimit"></div>
                </div>
            </button>
        </div>
        <div class="delete-modal-footer">
            <button class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Full-View Modal for Images -->
<div id="fullviewModal" class="fullview-modal" onclick="closeFullView()">
    <span class="close-fullview" onclick="closeFullView()">×</span>
    <div class="fullview-content">
        <img id="fullviewImage" src="" alt="Full View">
    </div>
</div>

<!-- File Preview Modal -->
<div id="filePreviewModal" class="file-preview-modal">
    <div class="file-preview-content" onclick="event.stopPropagation()">
        <div class="file-preview-header">
            <h3>Send File</h3>
            <button class="close-preview" onclick="closeFilePreview()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="file-preview-body">
            <div id="previewContent"></div>
            <div class="file-preview-info">
                <div class="file-preview-info-row">
                    <span class="file-preview-info-label">File name:</span>
                    <span class="file-preview-info-value" id="previewFileName"></span>
                </div>
                <div class="file-preview-info-row">
                    <span class="file-preview-info-label">File size:</span>
                    <span class="file-preview-info-value" id="previewFileSize"></span>
                </div>
                <div class="file-preview-info-row">
                    <span class="file-preview-info-label">File type:</span>
                    <span class="file-preview-info-value" id="previewFileType"></span>
                </div>
            </div>
        </div>
        <div class="file-preview-footer">
            <button class="preview-btn preview-btn-cancel" onclick="closeFilePreview()">Cancel</button>
            <button class="preview-btn preview-btn-send" onclick="confirmFileSend()">
                <i class="fa-solid fa-paper-plane"></i> Send File
            </button>
        </div>
    </div>
</div>

<script>
    const msgInput = document.getElementById('msgInput');
    const popMenu = document.getElementById('popMenu');
    const userId = <?= $user_id ?>;
    const otherUserId = <?= $other_user_id ?>;
    const jobId = <?= $job_id ?>;
    let activeText = '', activeId = null, activeMessageData = null;
    let lastMessageDate = null;
    let currentFileToSend = null;
    let isAtBottom = true;

    // Go to Bottom Button Logic
    const messagesContainer = document.getElementById('messagesContainer');
    const goToBottomBtn = document.getElementById('goToBottomBtn');

    messagesContainer.addEventListener('scroll', function() {
        const scrollTop = this.scrollTop;
        const scrollHeight = this.scrollHeight;
        const clientHeight = this.clientHeight;
        
        isAtBottom = (scrollHeight - scrollTop - clientHeight) < 50;
        
        if (isAtBottom) {
            goToBottomBtn.classList.remove('show');
        } else {
            goToBottomBtn.classList.add('show');
        }
    });

    function scrollToBottom(smooth = false) {
        if (smooth) {
            messagesContainer.scrollTo({
                top: messagesContainer.scrollHeight,
                behavior: 'smooth'
            });
        } else {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    // File Preview Functions
    function showFilePreview(input) {
        const file = input.files[0];
        if (!file) {
            console.log('No file selected');
            return;
        }

        console.log('File selected:', file.name, file.type, file.size);

        currentFileToSend = file;
        const modal = document.getElementById('filePreviewModal');
        const previewContent = document.getElementById('previewContent');
        const fileName = document.getElementById('previewFileName');
        const fileSize = document.getElementById('previewFileSize');
        const fileType = document.getElementById('previewFileType');

        // Set file info
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileType.textContent = file.type || 'Unknown';

        // Show preview based on file type
        previewContent.innerHTML = '';
        
        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.className = 'file-preview-image';
            img.src = URL.createObjectURL(file);
            img.onerror = function() {
                console.error('Failed to load image preview');
                previewContent.innerHTML = `
                    <div style="text-align: center; padding: 40px;">
                        <i class="fa-solid fa-image" style="font-size: 64px; color: var(--pop-maroon); opacity: 0.5;"></i>
                        <p style="margin-top: 16px; color: #6b7280; font-size: 14px;">Image preview failed</p>
                    </div>
                `;
            };
            previewContent.appendChild(img);
        } else if (file.type.startsWith('audio/')) {
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.src = URL.createObjectURL(file);
            audio.style.width = '100%';
            previewContent.appendChild(audio);
        } else if (file.type.startsWith('video/')) {
            const video = document.createElement('video');
            video.controls = true;
            video.src = URL.createObjectURL(file);
            video.style.width = '100%';
            video.style.maxHeight = '350px';
            video.style.borderRadius = '12px';
            previewContent.appendChild(video);
        } else {
            // For other file types, show file icon
            const fileIcon = document.createElement('div');
            fileIcon.style.textAlign = 'center';
            fileIcon.style.padding = '40px';
            fileIcon.innerHTML = `
                <i class="fa-solid fa-file" style="font-size: 64px; color: var(--pop-maroon); opacity: 0.5;"></i>
                <p style="margin-top: 16px; color: #6b7280; font-size: 14px;">Preview not available</p>
            `;
            previewContent.appendChild(fileIcon);
        }

        modal.style.display = 'flex';
        console.log('File preview modal opened');
    }

        function closeFilePreview() {
            document.getElementById('filePreviewModal').style.display = 'none';
            document.getElementById('fileInput').value = '';
            // DON'T clear currentFileToSend here - let confirmFileSend() do it after upload
            // currentFileToSend = null;  ← COMMENT THIS OUT
            console.log('File preview closed');
        }

function confirmFileSend() {
    console.log('=== SEND FILE CLICKED ===');
    console.log('currentFileToSend:', currentFileToSend);
    
    if (!currentFileToSend) {
        console.error('❌ No file to send');
        alert('No file selected');
        return;
    }

    console.log('✅ File exists:', currentFileToSend.name);
    console.log('✅ Closing preview modal');
    closeFilePreview();
    
    // Show loading state
    const container = document.getElementById('messagesContainer');
    const loadingMsg = document.createElement('div');
    loadingMsg.className = 'message-wrapper sent';
    loadingMsg.innerHTML = `
        <div class="bubble">
            <div class="msg-body">
                <i class="fa-solid fa-spinner fa-spin"></i> Uploading ${currentFileToSend.name}...
            </div>
        </div>
    `;
    container.appendChild(loadingMsg);
    scrollToBottom(true);
    
    const fd = new FormData();
    fd.append('file', currentFileToSend);
    fd.append('receiver_id', otherUserId);
    fd.append('job_id', jobId);
    
    console.log('📤 Sending to server:', {
        fileName: currentFileToSend.name,
        fileSize: currentFileToSend.size,
        fileType: currentFileToSend.type,
        receiver_id: otherUserId,
        job_id: jobId
    });
    
    fetch('backend/fetch_messages.php', { 
        method: 'POST', 
        body: fd 
    })
    .then(response => {
        console.log('📥 Response status:', response.status);
        console.log('📥 Response ok?:', response.ok);
        
        if (!response.ok) {
            // Try to get error details
            return response.text().then(text => {
                console.error('❌ Server error response:', text);
                throw new Error(`HTTP error! status: ${response.status}`);
            });
        }
        return response.json();
    })
        .then(data => {
            console.log('✅ Server response data:', data);
        
        if (data.success) {
            console.log('✅ Upload successful!');
            currentFileToSend = null;
            fetchMessages();
        } else {
            console.error('❌ Upload failed:', data.error);
            alert('Upload failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('❌ Upload error:', error);
        alert('Failed to upload file: ' + error.message);
        fetchMessages();
    });
}

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Auto-resize textarea
    msgInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        
        // Toggle send button icon
        const sendBtn = document.getElementById('sendBtn');
        if (this.value.trim()) {
            sendBtn.querySelector('i').className = 'fa-solid fa-paper-plane';
        } else {
            sendBtn.querySelector('i').className = 'fa-solid fa-paper-plane';
        }
    });

    msgInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function sendMessage() {
        const text = msgInput.value.trim();
        const editingId = msgInput.dataset.editingId;
        
        if (!text) return;

        if (editingId) {
            // Update existing message
            fetch('backend/fetch_messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `edit_message=1&message_id=${editingId}&new_text=${encodeURIComponent(text)}`
            })
            .then(() => {
                cancelEdit();
                fetchMessages();
            });
        } else {
            // Send new message
            fetch('backend/fetch_messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `send_message=1&receiver_id=${otherUserId}&job_id=${jobId}&message=${encodeURIComponent(text)}`
            })
            .then(() => {
                msgInput.value = '';
                msgInput.style.height = 'auto';
                fetchMessages();
            });
        }
    }

    function formatMessageDate(dateStr) {
        const msgDate = new Date(dateStr);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        const isToday = msgDate.toDateString() === today.toDateString();
        const isYesterday = msgDate.toDateString() === yesterday.toDateString();
        
        if (isToday) return 'Today';
        if (isYesterday) return 'Yesterday';
        
        const options = { month: 'long', day: 'numeric', year: 'numeric' };
        return msgDate.toLocaleDateString('en-US', options);
    }

    function fetchMessages() {
        fetch(`backend/fetch_messages.php?user_id=${userId}&other_user_id=${otherUserId}&job_id=${jobId}`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('messagesContainer');
            const wasAtBottom = isAtBottom;
            
            container.innerHTML = '';
            lastMessageDate = null;
            
            if (!data || data.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-regular fa-comments"></i>
                        <h3>No messages yet</h3>
                        <p>Start the conversation with ${htmlspecialchars('<?= $partner_name ?>')}</p>
                    </div>
                `;
                return;
            }

            data.forEach(msg => {
                // Add date divider if date changes
                const msgDate = formatMessageDate(msg.created_at);
                if (msgDate !== lastMessageDate) {
                    const divider = document.createElement('div');
                    divider.className = 'date-divider';
                    divider.innerHTML = `<span>${msgDate}</span>`;
                    container.appendChild(divider);
                    lastMessageDate = msgDate;
                }

                const isSent = msg.sender_id == userId;
                const wrapper = document.createElement('div');
                wrapper.className = `message-wrapper ${isSent ? 'sent' : ''}`;
                wrapper.dataset.messageId = msg.message_id;
                wrapper.dataset.timestamp = msg.created_at;
                wrapper.dataset.isRead = msg.is_read || 0;
                
                let content = '';
                
                // Handle different message types
                if (msg.file_path) {
                    const fileExt = msg.file_path.split('.').pop().toLowerCase();
                    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
                        content = `<img src="${msg.file_path}" alt="Image" onclick="openFullView('${msg.file_path}')">`;
                    } else if (['mp3', 'wav', 'webm', 'ogg'].includes(fileExt)) {
                        content = `<audio controls src="${msg.file_path}"></audio>`;
                    } else {
                        content = `<a href="${msg.file_path}" download style="color: inherit;">📎 ${msg.file_path.split('/').pop()}</a>`;
                    }
                } else {
                    content = htmlspecialchars(msg.message).replace(/\n/g, '<br>');
                }
                
                const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const edited = msg.is_edited == 1 ? '<span class="edited-label">edited</span>' : '';
                const checkMark = isSent ? (msg.is_read == 1 ? '✓✓' : '✓') : '';
                
                wrapper.innerHTML = `
                    <i class="fa-solid fa-chevron-down msg-chev" onclick="showMenu(event, ${msg.message_id}, '${escapeQuotes(msg.message)}', ${isSent}, '${msg.created_at}', ${msg.is_read || 0})"></i>
                    <div class="bubble">
                        <div class="msg-body">${content}</div>
                        <div class="meta">
                            ${edited}
                            <span>${time}</span>
                            ${checkMark ? `<span class="check-mark">${checkMark}</span>` : ''}
                        </div>
                    </div>
                `;
                
                container.appendChild(wrapper);
            });

            if (wasAtBottom) {
                scrollToBottom(false);
            }

            // Mark messages as read
            fetch('backend/fetch_messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `mark_read=1&sender_id=${otherUserId}&receiver_id=${userId}`
            });
        })
        .catch(err => {
            console.error('Error fetching messages:', err);
            document.getElementById('messagesContainer').innerHTML = 
                `<div class="empty-state">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i>
                    <h3>Error loading messages</h3>
                    <p>Please refresh the page</p>
                </div>`;
        });
    }

    function escapeQuotes(str) {
        if (!str) return '';
        return str.replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/\n/g, ' ');
    }

    function htmlspecialchars(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function showMenu(e, msgId, msgText, isSender, timestamp, isRead) {
        e.stopPropagation();
        activeId = msgId;
        activeText = msgText;
        activeMessageData = { isSender, timestamp, isRead };
        
        popMenu.style.display = 'block';
        
        // Position menu
        const menuWidth = 180;
        const menuHeight = 130;
        let left = e.pageX;
        let top = e.pageY;
        
        if (left + menuWidth > window.innerWidth) {
            left = e.pageX - menuWidth;
        }
        if (top + menuHeight > window.innerHeight) {
            top = e.pageY - menuHeight;
        }
        
        popMenu.style.left = left + 'px';
        popMenu.style.top = top + 'px';
    }

    function showDeleteModal() {
        popMenu.style.display = 'none';
        
        if (!activeMessageData) return;
        
        const modal = document.getElementById('deleteModal');
        const unsendOption = document.getElementById('unsendOption');
        const unsendTimeLimit = document.getElementById('unsendTimeLimit');
        
        // Calculate time since message was sent
        const messageTime = new Date(activeMessageData.timestamp).getTime();
        const currentTime = new Date().getTime();
        const timeDiff = (currentTime - messageTime) / 1000; // in seconds
        const hourLimit = 3600; // 1 hour
        
        // Show/hide unsend option based on sender and time
        if (activeMessageData.isSender) {
            unsendOption.style.display = 'flex';
            
            if (timeDiff > hourLimit && activeMessageData.isRead == 1) {
                unsendOption.style.opacity = '0.5';
                unsendOption.style.pointerEvents = 'none';
                unsendTimeLimit.textContent = '⚠️ Cannot unsend - Message is older than 1 hour and has been read';
            } else {
                unsendOption.style.opacity = '1';
                unsendOption.style.pointerEvents = 'auto';
                const remainingTime = Math.max(0, hourLimit - timeDiff);
                const minutes = Math.floor(remainingTime / 60);
                if (activeMessageData.isRead == 0) {
                    unsendTimeLimit.textContent = '✓ Message not yet read - Can unsend anytime';
                } else if (minutes > 0) {
                    unsendTimeLimit.textContent = `⏱️ ${minutes} minutes left to unsend`;
                } else {
                    unsendTimeLimit.textContent = '⏱️ Less than 1 minute left to unsend';
                }
            }
        } else {
            unsendOption.style.display = 'none';
        }
        
        modal.style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    function deleteMessage(action) {
        if (!activeId) return;
        
        closeDeleteModal();
        
        const formData = new FormData();
        formData.append('message_id', activeId);
        formData.append('action', action);
        
        fetch('backend/delete_message.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchMessages();
            } else {
                alert('Error: ' + (data.error || 'Failed to delete message'));
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            alert('Failed to delete message');
        });
    }

    function copyText() {
        if (activeText) {
            navigator.clipboard.writeText(activeText);
            popMenu.style.display = 'none';
        }
    }

    function openFullView(src) {
        const modal = document.getElementById('fullviewModal');
        const img = document.getElementById('fullviewImage');
        img.src = src;
        modal.style.display = 'flex';
    }

    function closeFullView() {
        document.getElementById('fullviewModal').style.display = 'none';
    }

    // Voice Recording
    let mediaRecorder, audioChunks = [], recordingTimer, recordingSeconds = 0;
    let isRecording = false;
    let shouldSendRecording = false; // Flag to control whether to send

    function handleSendAction() {
        if (isRecording) {
            // If recording, stop and send
            stopRecording(true);
        } else {
            // If not recording, send text message
            sendMessage();
        }
    }

    function startRecording() {
        navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            recordingSeconds = 0;
            isRecording = true;
            shouldSendRecording = false; // Reset flag

            mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
            mediaRecorder.onstop = () => {
                // Only send if shouldSendRecording flag is true
                if (shouldSendRecording) {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const fd = new FormData();
                fd.append('file', audioBlob, 'voice_' + Date.now() + '.webm');
                fd.append('receiver_id', otherUserId);
                fd.append('job_id', jobId);
                
                // Show uploading message
                const container = document.getElementById('messagesContainer');
                const loadingMsg = document.createElement('div');
                loadingMsg.className = 'message-wrapper sent';
                loadingMsg.innerHTML = `
                    <div class="bubble">
                        <div class="msg-body">
                            <i class="fa-solid fa-spinner fa-spin"></i> Sending voice message...
                        </div>
                    </div>
                `;
                container.appendChild(loadingMsg);
                scrollToBottom(true);
                
                fetch('backend/fetch_messages.php', { method: 'POST', body: fd })
                .then(() => fetchMessages())
                .catch(() => {
                    alert('Failed to send voice message');
                    fetchMessages();
                    });
                }
                // Clear audio chunks after processing
                audioChunks = [];
            };

            mediaRecorder.start();
            
            // Update UI
            document.body.classList.add('recording');
            
            // Update send button to show it will stop/send recording
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.title = 'Send voice message';
            sendBtn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
            sendBtn.querySelector('i').className = 'fa-solid fa-paper-plane';
            
            recordingTimer = setInterval(() => {
                recordingSeconds++;
                const mins = Math.floor(recordingSeconds / 60).toString().padStart(2, '0');
                const secs = (recordingSeconds % 60).toString().padStart(2, '0');
                document.getElementById('timer').textContent = `${mins}:${secs}`;
            }, 1000);
        })
        .catch(() => {
            alert('Microphone access denied. Please allow microphone access to send voice messages.');
        });
    }

        function stopRecording(send) {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            shouldSendRecording = send; // Set flag before stopping
            
            if (send) {
                mediaRecorder.stop(); // This will trigger onstop event
            } else {
                // Cancel recording - stop all tracks first, then stop recorder
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
                mediaRecorder.stop(); // This will trigger onstop but shouldSendRecording is false
            }
            
            // Reset UI
            document.body.classList.remove('recording');
            isRecording = false;
            
            clearInterval(recordingTimer);
            recordingSeconds = 0;
            document.getElementById('timer').textContent = '00:00';
            
            // Reset send button
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.title = 'Send';
            sendBtn.style.background = 'linear-gradient(135deg, var(--pop-maroon) 0%, var(--pop-maroon-dark) 100%)';
            sendBtn.querySelector('i').className = 'fa-solid fa-paper-plane';
        }
    }

    function cancelRecording() {
        if (confirm('Are you sure you want to cancel this voice recording?')) {
            stopRecording(false);
        }
    }

    function editMsg() {
        if (!activeText || !activeId) return;
        
        // Highlight the message being edited
        document.querySelectorAll('.message-wrapper').forEach(msg => {
            msg.classList.remove('editing');
        });
        
        const editingMessage = document.querySelector(`[data-message-id="${activeId}"]`);
        if (editingMessage) {
            editingMessage.classList.add('editing');
            editingMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Show current text in input for editing
        msgInput.value = activeText;
        msgInput.focus();
        msgInput.style.height = 'auto';
        msgInput.style.height = msgInput.scrollHeight + 'px';
        msgInput.setSelectionRange(msgInput.value.length, msgInput.value.length);
        msgInput.dataset.editingId = activeId;
        
        // Show edit mode indicator
        document.getElementById('editModeIndicator').style.display = 'flex';
        
        // Change send button
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.querySelector('i').className = 'fa-solid fa-check';
        sendBtn.title = 'Save Changes';
        
        popMenu.style.display = 'none';
    }

    function cancelEdit() {
        msgInput.value = '';
        msgInput.style.height = 'auto';
        delete msgInput.dataset.editingId;
        
        document.querySelectorAll('.message-wrapper').forEach(msg => {
            msg.classList.remove('editing');
        });
        
        document.getElementById('editModeIndicator').style.display = 'none';
        
        const sendBtn = document.getElementById('sendBtn');
        sendBtn.querySelector('i').className = 'fa-solid fa-paper-plane';
        sendBtn.title = 'Send';
    }

    // Close context menu when clicking outside
    document.addEventListener('click', () => popMenu.style.display = 'none');
    
    // Auto-refresh messages
    setInterval(fetchMessages, 5000);
     
    // Initial load
    fetchMessages();
</script>

</body>
</html>