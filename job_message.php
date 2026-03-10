<?php
session_start();
include("backend/db_connect.php");

// Ensure only employers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
  header("Location: login.html");
  exit();
}

$employer_id = $_SESSION['user_id'];
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

// Verify job belongs to this employer
$job_check_sql = "SELECT title FROM jobs WHERE job_id = ? AND employer_id = ?";
$job_check_stmt = $conn->prepare($job_check_sql);
$job_check_stmt->bind_param("ii", $job_id, $employer_id);
$job_check_stmt->execute();
$job_result = $job_check_stmt->get_result();

if ($job_result->num_rows === 0) {
  header("Location: employer_dashboard.php");
  exit();
}

$job_data = $job_result->fetch_assoc();
$job_title = $job_data['title'];

// Get all applicants for this job with their latest message
$sql = "SELECT 
    a.application_id,
    a.user_id,
    a.status,
    a.created_at as applied_date,
    p.name,
    p.profile_picture,
    u.email,
    (SELECT message FROM messages 
     WHERE (sender_id = a.user_id AND receiver_id = ? AND job_id = ?) 
        OR (sender_id = ? AND receiver_id = a.user_id AND job_id = ?)
     ORDER BY timestamp DESC LIMIT 1) as last_message,
    (SELECT timestamp FROM messages 
     WHERE (sender_id = a.user_id AND receiver_id = ? AND job_id = ?) 
        OR (sender_id = ? AND receiver_id = a.user_id AND job_id = ?)
     ORDER BY timestamp DESC LIMIT 1) as last_message_time,
    (SELECT COUNT(*) FROM messages 
     WHERE sender_id = a.user_id AND receiver_id = ? AND job_id = ? AND is_read = 0) as unread_count
    FROM applications a
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN profiles p ON a.user_id = p.user_id
    WHERE a.job_id = ?
    ORDER BY last_message_time DESC, a.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiiiiiiii", $employer_id, $job_id, $employer_id, $job_id, 
                  $employer_id, $job_id, $employer_id, $job_id, $employer_id, $job_id, $job_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages - <?= htmlspecialchars($job_title); ?> | POP!Work</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      background: #f5f7fa;
      min-height: 100vh;
      color: #2c3e50;
    }

    .page-header {
      background: white;
      border-bottom: 2px solid #e1e8ed;
      padding: 24px 0;
      margin-bottom: 24px;
    }

    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 24px;
    }

    .back-button {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #8a1538;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 16px;
      transition: all 0.2s;
    }

    .back-button:hover {
      gap: 12px;
    }

    .page-title {
      font-size: 28px;
      color: #1c1e21;
      margin-bottom: 4px;
      font-weight: 700;
    }

    .page-subtitle {
      color: #65676b;
      font-size: 14px;
    }

    .main-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 24px 40px;
    }

    .applicants-list {
      background: white;
      border: 1px solid #e1e8ed;
      border-radius: 12px;
      overflow: hidden;
    }

    .applicant-item {
      display: flex;
      align-items: center;
      padding: 20px 24px;
      border-bottom: 1px solid #f0f2f5;
      transition: all 0.2s;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
    }

    .applicant-item:hover {
      background: #f8f9fa;
    }

    .applicant-item:last-child {
      border-bottom: none;
    }

    .applicant-avatar {
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
    }

    .applicant-avatar img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
    }

    .unread-badge {
      position: absolute;
      top: -2px;
      right: -2px;
      background: #e74c3c;
      color: white;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 6px;
      border-radius: 10px;
      min-width: 20px;
      text-align: center;
    }

    .applicant-info {
      flex: 1;
      min-width: 0;
    }

    .applicant-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 4px;
    }

    .applicant-name {
      font-size: 16px;
      font-weight: 600;
      color: #1c1e21;
    }

    .message-time {
      font-size: 12px;
      color: #95a5a6;
      white-space: nowrap;
    }

    .last-message {
      font-size: 14px;
      color: #65676b;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      margin-bottom: 8px;
    }

    .last-message.unread {
      color: #1c1e21;
      font-weight: 600;
    }

    .applicant-meta {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .status-badge.pending {
      background: #fff3cd;
      color: #856404;
    }

    .status-badge.accepted {
      background: #d4edda;
      color: #155724;
    }

    .status-badge.rejected {
      background: #f8d7da;
      color: #721c24;
    }

    .message-icon {
      font-size: 20px;
      color: #8a1538;
      margin-left: 12px;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
    }

    .empty-state-icon {
      font-size: 64px;
      margin-bottom: 16px;
      opacity: 0.3;
    }

    .empty-state h3 {
      font-size: 20px;
      color: #1c1e21;
      margin-bottom: 8px;
    }

    .empty-state p {
      color: #65676b;
      margin-bottom: 20px;
    }

    @media (max-width: 768px) {
      .applicant-item {
        padding: 16px;
      }

      .applicant-avatar {
        width: 48px;
        height: 48px;
        font-size: 18px;
      }

      .applicant-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
    }
  </style>
</head>
<body>
  <?php 
    $page_title = "Job Messages";
    include 'includes/header.php';
  ?>

  <div class="page-header">
    <div class="header-content">
      <a href="employer_dashboard.php" class="back-button">
        ← Back to Dashboard
      </a>
      <h1 class="page-title">Messages</h1>
      <p class="page-subtitle">Job: <?= htmlspecialchars($job_title); ?></p>
    </div>
  </div>

  <div class="main-container">
    <div class="applicants-list">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <a href="chat.php?job_id=<?= $job_id; ?>&applicant_id=<?= $row['user_id']; ?>" class="applicant-item">
            <div class="applicant-avatar">
              <?php if ($row['profile_picture']): ?>
                <img src="<?= htmlspecialchars($row['profile_picture']); ?>" alt="<?= htmlspecialchars($row['name']); ?>">
              <?php else: ?>
                <?= strtoupper(substr($row['name'] ?: $row['email'], 0, 1)); ?>
              <?php endif; ?>
              
              <?php if ($row['unread_count'] > 0): ?>
                <span class="unread-badge"><?= $row['unread_count']; ?></span>
              <?php endif; ?>
            </div>

            <div class="applicant-info">
              <div class="applicant-header">
                <span class="applicant-name"><?= htmlspecialchars($row['name'] ?: explode('@', $row['email'])[0]); ?></span>
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
                <?php endif; ?>
              </div>

              <div class="last-message <?= $row['unread_count'] > 0 ? 'unread' : ''; ?>">
                <?php if ($row['last_message']): ?>
                  <?= htmlspecialchars(substr($row['last_message'], 0, 80)); ?><?= strlen($row['last_message']) > 80 ? '...' : ''; ?>
                <?php else: ?>
                  No messages yet - Start a conversation
                <?php endif; ?>
              </div>

              <div class="applicant-meta">
                <span class="status-badge <?= $row['status']; ?>">
                  <?= ucfirst($row['status']); ?>
                </span>
                <span style="font-size: 12px; color: #95a5a6;">
                  Applied: <?= date('M d, Y', strtotime($row['applied_date'])); ?>
                </span>
              </div>
            </div>

            <div class="message-icon">💬</div>
          </a>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-state">
          <div class="empty-state-icon">💬</div>
          <h3>No Applicants Yet</h3>
          <p>When workers apply to this job, you can message them here</p>
          <a href="employer_dashboard.php" class="back-button">
            ← Back to Dashboard
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
</body>
</html>