<?php
session_start();
include("backend/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

if ($user_role == 'employer') {
    // Get all conversations for employer
    $sql = "
        SELECT DISTINCT
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id 
            ELSE m.sender_id 
        END as partner_id,
        m.job_id,
        a.status as application_status,
        COALESCE(a.created_at, MIN(m.timestamp)) as applied_date,
        j.title as job_title,
        j.location as job_location,
        p.name,
        p.profile_picture,
        u.email,
        (SELECT message FROM messages m2
         WHERE ((m2.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AND m2.receiver_id = ?) 
            OR (m2.sender_id = ? AND m2.receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END))
         AND (m2.job_id = m.job_id OR (m2.job_id IS NULL AND m.job_id IS NULL))
         ORDER BY m2.timestamp DESC LIMIT 1) as last_message,
        (SELECT timestamp FROM messages m3
         WHERE ((m3.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AND m3.receiver_id = ?) 
            OR (m3.sender_id = ? AND m3.receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END))
         AND (m3.job_id = m.job_id OR (m3.job_id IS NULL AND m.job_id IS NULL))
         ORDER BY m3.timestamp DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages m4
         WHERE m4.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END 
         AND m4.receiver_id = ? 
         AND (m4.job_id = m.job_id OR (m4.job_id IS NULL AND m.job_id IS NULL))
         AND m4.is_read = 0) as unread_count
        FROM messages m
        LEFT JOIN jobs j ON m.job_id = j.job_id
        LEFT JOIN applications a ON (m.job_id = a.job_id 
            AND a.user_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
        LEFT JOIN users u ON CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END = u.user_id
        LEFT JOIN profiles p ON u.user_id = p.user_id
        WHERE (m.sender_id = ? OR m.receiver_id = ?)
        AND (m.job_id IS NULL OR j.employer_id = ?)
        GROUP BY COALESCE(m.job_id, 0), CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
        
        UNION
        
        SELECT DISTINCT
        a.user_id as partner_id,
        a.job_id,
        a.status as application_status,
        a.created_at as applied_date,
        j.title as job_title,
        j.location as job_location,
        p.name,
        p.profile_picture,
        u.email,
        (SELECT message FROM messages 
         WHERE ((sender_id = a.user_id AND receiver_id = ?) 
            OR (sender_id = ? AND receiver_id = a.user_id))
         AND job_id = a.job_id
         ORDER BY timestamp DESC LIMIT 1) as last_message,
        (SELECT timestamp FROM messages 
         WHERE ((sender_id = a.user_id AND receiver_id = ?) 
            OR (sender_id = ? AND receiver_id = a.user_id))
         AND job_id = a.job_id
         ORDER BY timestamp DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages 
         WHERE sender_id = a.user_id AND receiver_id = ? 
         AND job_id = a.job_id AND is_read = 0) as unread_count
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.job_id
        INNER JOIN users u ON a.user_id = u.user_id
        LEFT JOIN profiles p ON a.user_id = p.user_id
        WHERE j.employer_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM messages m2 
            WHERE m2.job_id = a.job_id 
            AND (m2.sender_id = a.user_id OR m2.receiver_id = a.user_id)
            AND (m2.sender_id = ? OR m2.receiver_id = ?)
        )
        
        ORDER BY 
            job_title ASC,
            CASE WHEN last_message_time IS NOT NULL THEN last_message_time ELSE applied_date END DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiiiiiiiiiiiiiiiiiiiiii", 
        $user_id, $user_id, $user_id, $user_id, $user_id,
        $user_id, $user_id, $user_id, $user_id,
        $user_id, $user_id,
        $user_id, $user_id,
        $user_id, $user_id,
        $user_id, $user_id,
        $user_id, $user_id,
        $user_id, $user_id,
        $user_id,
        $user_id,
        $user_id, $user_id
    );
} else {
    // Worker query
    $sql = "SELECT DISTINCT
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as partner_id,
        m.job_id,
        a.status as application_status,
        MIN(m.timestamp) as applied_date,
        COALESCE(j.title, 'General Inquiry') as job_title,
        j.location as job_location,
        p.name,
        p.profile_picture,
        u.email,
        (SELECT message FROM messages m2
         WHERE ((m2.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AND m2.receiver_id = ?) 
            OR (m2.sender_id = ? AND m2.receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END))
         AND (m2.job_id = m.job_id OR (m2.job_id IS NULL AND m.job_id IS NULL))
         ORDER BY m2.timestamp DESC LIMIT 1) as last_message,
        (SELECT timestamp FROM messages m3
         WHERE ((m3.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AND m3.receiver_id = ?) 
            OR (m3.sender_id = ? AND m3.receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END))
         AND (m3.job_id = m.job_id OR (m3.job_id IS NULL AND m.job_id IS NULL))
         ORDER BY m3.timestamp DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages m4
         WHERE m4.sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END 
         AND m4.receiver_id = ? 
         AND (m4.job_id = m.job_id OR (m4.job_id IS NULL AND m.job_id IS NULL)) 
         AND m4.is_read = 0) as unread_count
        FROM messages m
        LEFT JOIN jobs j ON m.job_id = j.job_id
        LEFT JOIN applications a ON (m.job_id = a.job_id AND a.user_id = ?)
        LEFT JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.user_id
        LEFT JOIN profiles p ON u.user_id = p.user_id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY m.job_id, partner_id
        ORDER BY last_message_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiiiiiiiiiiii", 
        $user_id, $user_id, $user_id, $user_id, $user_id,
        $user_id, $user_id, $user_id, $user_id,
        $user_id, $user_id,
        $user_id, $user_id,
        $user_id, $user_id
    );
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $user_role == 'employer' ? 'Job Applicants' : 'Messages' ?> | POP!Work</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      background: #f5f7fa;
      color: #2c3e50;
      position: relative;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('uploads/photos/background.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
    }

    .dashboard-wrapper {
      display: flex;
      max-width: 100vw;
      overflow-x: hidden;
    }

    .main-content {
      flex: 1;
      margin-left: 280px;
      width: calc(100% - 280px);
      min-height: 100vh;
      position: relative;
      z-index: 1;
    }

    .page-header {
      background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
      border-bottom: none;
      padding: 32px 0;
      margin-bottom: 0;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.15);
      position: relative;
      z-index: 10;
    }

    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 40px;
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .page-title {
      font-size: 32px;
      color: white;
      font-weight: 700;
      margin-bottom: 2px;
    }

    .page-subtitle {
      color: rgba(255, 255, 255, 0.9);
      font-size: 15px;
    }

    .main-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 32px 40px 40px;
    }

    .filter-tabs {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 12px;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.5);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .filter-tab {
      padding: 10px 20px;
      border: none;
      background: transparent;
      color: #65676b;
      font-weight: 600;
      cursor: pointer;
      border-radius: 8px;
      transition: all 0.3s;
    }

    .filter-tab:hover {
      background: #f8f9fa;
    }

    .filter-tab.active {
      background: #8a1538;
      color: white;
      box-shadow: 0 2px 8px rgba(138, 21, 56, 0.2);
    }

    .job-section {
      margin-bottom: 32px;
    }

    .job-header {
      background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
      color: white;
      padding: 20px 24px;
      border-radius: 12px 12px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 8px rgba(138, 21, 56, 0.15);
    }

    .job-title-header {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .job-meta {
      font-size: 13px;
      opacity: 0.95;
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .applicant-count {
      background: rgba(255, 255, 255, 0.25);
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      backdrop-filter: blur(10px);
    }

    .conversations-list {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 0 0 12px 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }


    .conversation-item {
      display: flex;
      align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid rgba(240, 242, 245, 0.8);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      position: relative;
    }

    .conversation-item::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 4px;
      background: linear-gradient(135deg, #8a1538, #c91f4d);
      transform: scaleY(0);
      transition: transform 0.3s ease;
    }

    .conversation-item:hover {
      background: linear-gradient(to right, rgba(138, 21, 56, 0.03), transparent);
      transform: translateX(4px);
    }

    .conversation-item:hover::before {
      transform: scaleY(1);
    }

    .conversation-item:last-child {
      border-bottom: none;
    }

    .partner-avatar {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 20px;
      margin-right: 16px;
      flex-shrink: 0;
      position: relative;
      box-shadow: 0 2px 8px rgba(138, 21, 56, 0.2);
    }

    .partner-avatar img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
    }

    .unread-badge, .new-applicant-badge, .inquiry-badge {
      position: absolute;
      top: -2px;
      right: -2px;
      color: white;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 7px;
      border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.25);
    }

    .unread-badge {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
    }

    .new-applicant-badge {
      background: linear-gradient(135deg, #27ae60, #229954);
    }

    .inquiry-badge {
      background: linear-gradient(135deg, #3498db, #2980b9);
    }

    .conversation-info {
      flex: 1;
      min-width: 0;
    }

    .conversation-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 4px;
    }

    .partner-name {
      font-size: 16px;
      font-weight: 600;
      color: #1c1e21;
    }

    .message-time {
      font-size: 12px;
      color: #95a5a6;
      white-space: nowrap;
      font-weight: 500;
    }

    .last-message {
      font-size: 14px;
      color: #65676b;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      margin-bottom: 4px;
    }

    .last-message.unread {
      color: #1c1e21;
      font-weight: 600;
    }

    .no-message-text {
      color: #27ae60;
      font-style: italic;
      font-size: 13px;
      font-weight: 500;
    }

    .applicant-meta {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 6px;
    }

    .meta-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 6px;
      font-weight: 600;
    }

    .status-badge {
      background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
      color: #2e7d32;
      border: 1px solid #a5d6a7;
    }

    .status-badge.pending {
      background: linear-gradient(135deg, #fff3e0, #ffe0b2);
      color: #f57c00;
      border: 1px solid #ffcc80;
    }

    .status-badge.inquiry {
      background: linear-gradient(135deg, #e3f2fd, #bbdefb);
      color: #1976d2;
      border: 1px solid #90caf9;
    }

    .message-icon {
      font-size: 24px;
      margin-left: 12px;
      flex-shrink: 0;
      transition: all 0.3s;
    }

    .message-icon.has-messages {
      color: #8a1538;
    }

    .message-icon.no-messages {
      color: #bdc3c7;
    }

    .conversation-item:hover .message-icon.has-messages {
      transform: scale(1.1);
    }

    .empty-state {
      text-align: center;
      padding: 80px 20px;
      background: rgba(255, 255, 255, 0.5);
      border-radius: 12px;
    }

    .empty-state-icon {
      font-size: 80px;
      margin-bottom: 24px;
      opacity: 0.3;
      background: linear-gradient(135deg, #8a1538, #c91f4d);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .empty-state h3 {
      font-size: 24px;
      color: #1c1e21;
      margin-bottom: 12px;
      font-weight: 700;
    }

    .empty-state p {
      color: #65676b;
      margin-bottom: 24px;
      font-size: 16px;
    }

    @media (max-width: 1200px) {
      .main-content {
        margin-left: 0;
        width: 100%;
      }
    }

    @media (max-width: 768px) {
      .conversation-item {
        padding: 16px;
      }

      .partner-avatar {
        width: 48px;
        height: 48px;
        font-size: 18px;
      }

      .filter-tabs {
        overflow-x: auto;
      }
      
      .main-container {
        padding: 24px 20px;
      }
    }
  </style>
</head>
<body>
  <div class="dashboard-wrapper">
    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
      <div class="page-header">
        <div class="header-content">
    
          <div>
            <h1 class="page-title">
              Messages
            </h1>
            <p class="page-subtitle">
              <?= $user_role == 'employer' ? 'Your conversations' : 'Your conversations' ?>
            </p>
          </div>
        </div>
      </div>

      <div class="main-container">
        <?php if ($user_role == 'employer'): ?>
        <div class="filter-tabs">
          <button class="filter-tab active" onclick="filterApplicants('all')">
            <i class="fa-solid fa-users"></i> All Conversations
          </button>
          <button class="filter-tab" onclick="filterApplicants('unread')">
            <i class="fa-solid fa-envelope"></i> Unread Messages
          </button>
          <button class="filter-tab" onclick="filterApplicants('new')">
            <i class="fa-solid fa-user-plus"></i> New Applications
          </button>
        </div>
        <?php endif; ?>

        <?php if ($result->num_rows > 0): ?>
          <?php 
          $job_applicants = [];
          
          while ($row = $result->fetch_assoc()) {
            $group_name = !empty($row['job_title']) ? $row['job_title'] : 'General Inquiries';
            $job_applicants[$group_name][] = $row;
          }

          foreach ($job_applicants as $job_title => $applicants): 
            $first_applicant = $applicants[0];
            $display_job_id = $first_applicant['job_id'];
          ?>
            <div class="job-section">
              <div class="job-header">
                <div class="job-header-left">
                  <div class="job-title-header">
                    <i class="fa-solid fa-briefcase"></i>
                    <?= htmlspecialchars($job_title) ?>
                  </div>
                  <div class="job-meta">
                    <?php if (!empty($first_applicant['job_location'])): ?>
                      <span>
                        <i class="fa-solid fa-location-dot"></i>
                        <?= htmlspecialchars($first_applicant['job_location']) ?>
                      </span>
                    <?php endif; ?>
                    
                    <?php if ($display_job_id): ?>
                      <span>
                        <i class="fa-solid fa-hashtag"></i>
                        Job ID: #<?= $display_job_id ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="applicant-count">
                  <?= count($applicants) ?> Conversation<?= count($applicants) != 1 ? 's' : '' ?>
                </div>
              </div>

              <div class="conversations-list">
                <?php foreach ($applicants as $row): ?>
                  <?php
                    $partner_name = $row['name'] ?: explode('@', $row['email'])[0];
                    $chat_url = "chat.php?job_id=" . $row['job_id'] . "&applicant_id=" . $row['partner_id'];
                    
                    $has_messages = !empty($row['last_message']);
                    $has_application = !empty($row['application_status']);
                    
                    $is_new_applicant = !$has_messages && $has_application;
                    $is_inquiry = $has_messages && !$has_application;
                  ?>
                  <a href="<?= $chat_url ?>" class="conversation-item" 
                     data-has-messages="<?= $has_messages ? 'true' : 'false' ?>"
                     data-unread="<?= $row['unread_count'] > 0 ? 'true' : 'false' ?>"
                     data-is-new="<?= $is_new_applicant ? 'true' : 'false' ?>">
                    
                    <div class="partner-avatar">
                      <?php if ($row['profile_picture']): ?>
                        <img src="<?= htmlspecialchars($row['profile_picture']) ?>" 
                             alt="<?= htmlspecialchars($partner_name) ?>">
                      <?php else: ?>
                        <?= strtoupper(substr($partner_name, 0, 1)) ?>
                      <?php endif; ?>
                      
                      <?php if ($row['unread_count'] > 0): ?>
                        <span class="unread-badge"><?= $row['unread_count'] ?></span>
                      <?php elseif ($is_new_applicant): ?>
                        <span class="new-applicant-badge">NEW</span>
                      <?php elseif ($is_inquiry): ?>
                        <span class="inquiry-badge">INQUIRY</span>
                      <?php endif; ?>
                    </div>

                    <div class="conversation-info">
                      <div class="conversation-header">
                        <span class="partner-name"><?= htmlspecialchars($partner_name) ?></span>
                        <?php if ($row['last_message_time']): ?>
                          <span class="message-time">
                            <?php
                              $time_diff = time() - strtotime($row['last_message_time']);
                              if ($time_diff < 60) {
                                echo "Just now";
                              } elseif ($time_diff < 3600) {
                                echo floor($time_diff / 60) . "m ago";
                              } elseif ($time_diff < 86400) {
                                echo floor($time_diff / 3600) . "h ago";
                              } else {
                                echo date('M d', strtotime($row['last_message_time']));
                              }
                            ?>
                          </span>
                        <?php elseif (isset($row['applied_date'])): ?>
                          <span class="message-time">
                            Applied <?= date('M d', strtotime($row['applied_date'])) ?>
                          </span>
                        <?php endif; ?>
                      </div>

                      <div class="last-message <?= $row['unread_count'] > 0 ? 'unread' : '' ?>">
                        <?php if ($has_messages): ?>
                          <?= htmlspecialchars(substr($row['last_message'], 0, 80)) ?><?= strlen($row['last_message']) > 80 ? '...' : '' ?>
                        <?php else: ?>
                          <span class="no-message-text">
                            <i class="fa-solid fa-paper-plane"></i> 
                            Click to start a conversation
                          </span>
                        <?php endif; ?>
                      </div>

                      <?php if ($user_role == 'employer'): ?>
                      <div class="applicant-meta">
                        <?php if (isset($row['application_status']) && !empty($row['application_status'])): ?>
                          <span class="meta-badge status-badge <?= strtolower($row['application_status']) ?>">
                            <i class="fa-solid fa-circle-check"></i>
                            Applied: <?= ucfirst($row['application_status']) ?>
                          </span>
                        <?php else: ?>
                          <span class="meta-badge status-badge inquiry">
                            <i class="fa-solid fa-circle-question"></i>
                            Inquiry
                          </span>
                        <?php endif; ?>
                      </div>
                      <?php endif; ?>
                    </div>

                    <div class="message-icon <?= $has_messages ? 'has-messages' : 'no-messages' ?>">
                      <?php if ($has_messages): ?>
                        💬
                      <?php else: ?>
                        <i class="fa-regular fa-comment"></i>
                      <?php endif; ?>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
          
        <?php else: ?>
          <div class="conversations-list">
            <div class="empty-state">
              <div class="empty-state-icon">
                <?= $user_role == 'employer' ? '👥' : '💬' ?>
              </div>
              <h3><?= $user_role == 'employer' ? 'No Conversations Yet' : 'No Messages Yet' ?></h3>
              <p>
                <?= $user_role == 'employer' 
                    ? 'When someone applies to your jobs or sends you a message, they will appear here' 
                    : 'Your conversations will appear here' ?>
              </p>
              <a href="<?= $user_role ?>_dashboard.php" class="back-button">
                <i class="fa-solid fa-arrow-left"></i> Go to Dashboard
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    function filterApplicants(filter) {
      const tabs = document.querySelectorAll('.filter-tab');
      const items = document.querySelectorAll('.conversation-item');
      const jobSections = document.querySelectorAll('.job-section');
      
      tabs.forEach(tab => tab.classList.remove('active'));
      event.target.classList.add('active');
      
      items.forEach(item => {
        const hasMessages = item.dataset.hasMessages === 'true';
        const hasUnread = item.dataset.unread === 'true';
        const isNew = item.dataset.isNew === 'true';
        
        let show = false;
        
        switch(filter) {
          case 'all':
            show = true;
            break;
          case 'unread':
            show = hasUnread;
            break;
          case 'new':
            show = isNew;
            break;
        }
        
        item.style.display = show ? 'flex' : 'none';
      });
      
      jobSections.forEach(section => {
        const visibleApplicants = section.querySelectorAll('.conversation-item[style="display: flex;"], .conversation-item:not([style])');
        const hasVisible = Array.from(visibleApplicants).some(item => {
          return item.style.display !== 'none';
        });
        section.style.display = hasVisible ? 'block' : 'none';
      });
    }
  </script>
</body>
</html>