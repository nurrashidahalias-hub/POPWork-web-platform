<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
  header("Location: ../login.html");
  exit();
}

$employer_id = $_SESSION['user_id'];

if (!isset($_GET['job_id'])) {
  echo "<script>alert('Invalid job selected.'); window.location='../employer_dashboard.php';</script>";
  exit();
}

$job_id = intval($_GET['job_id']);

// Get job details including location, category, pay_rate
$check_job = $conn->prepare("SELECT title, category, location, pay_rate FROM jobs WHERE job_id = ? AND employer_id = ?");
$check_job->bind_param("ii", $job_id, $employer_id);
$check_job->execute();
$result_job = $check_job->get_result();

if ($result_job->num_rows == 0) {
  echo "<script>alert('Unauthorized access.'); window.location='../employer_dashboard.php';</script>";
  exit();
}

$job = $result_job->fetch_assoc();

// Fetch applicants with applied_at date
$sql = "SELECT a.application_id, a.user_id, a.status, a.applied_at, u.email AS worker_email,
               p.name, p.photo_url, p.phone, p.skills, p.experience
        FROM applications a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN profiles p ON a.user_id = p.user_id
        WHERE a.job_id = ?
        ORDER BY 
          CASE 
            WHEN a.status = 'Pending' THEN 1
            WHEN a.status = 'Shortlisted' THEN 2
            WHEN a.status = 'Accepted' THEN 3
            WHEN a.status = 'Rejected' THEN 4
          END,
          a.applied_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$applicants = $stmt->get_result();

// Calculate statistics
$stats = ['Pending' => 0, 'Shortlisted' => 0, 'Accepted' => 0, 'Rejected' => 0];
$temp_applicants = [];
while ($row = $applicants->fetch_assoc()) {
  $status = ucfirst(strtolower($row['status']));
  if (isset($stats[$status])) {
    $stats[$status]++;
  }
  $temp_applicants[] = $row;
}
$total_applicants = count($temp_applicants);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Applicants - <?= htmlspecialchars($job['title']); ?> | POP!Work</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Poppins', sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
    }

    /* Photo Background */
    body::before {
      content: '';
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background-image: url('uploads/photos/background.jpg');
      background-size: cover;
      background-position: center;
      z-index: -1;
    }

    .page-wrapper {
      min-height: 100vh;
      background: rgba(240, 242, 245, 0.85);
    }

    /* Header */
    .page-header {
      background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
      padding: 40px;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.2);
    }

    .header-content {
      max-width: 1300px;
      margin: 0 auto;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: white;
      text-decoration: none;
      font-weight: 600;
      padding: 12px 24px;
      background: rgba(255, 255, 255, 0.15);
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 12px;
      margin-bottom: 24px;
      transition: all 0.3s;
    }

    .back-link:hover {
      background: rgba(255, 255, 255, 0.25);
      transform: translateX(-4px);
    }

    .header-title {
      font-size: 36px;
      font-weight: 800;
      color: white;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .job-name {
      font-size: 20px;
      font-weight: 700;
      color: white;
      margin-bottom: 16px;
    }

    .job-info {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
    }

    .job-info-item {
      display: flex;
      align-items: center;
      gap: 8px;
      color: white;
      font-size: 15px;
      padding: 8px 16px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* Content */
    .content-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 40px;
    }

    /* Stats */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: white;
      padding: 24px;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      transition: transform 0.3s;
      position: relative;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
    }

    .stat-card.pending::before { background: #f59e0b; }
    .stat-card.shortlisted::before { background: #3b82f6; }
    .stat-card.accepted::before { background: #10b981; }
    .stat-card.rejected::before { background: #ef4444; }

    .stat-card:hover {
      transform: translateY(-4px);
    }

    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      margin-bottom: 12px;
    }

    .stat-card.pending .stat-icon {
      background: #fef3c7;
      color: #f59e0b;
    }

    .stat-card.shortlisted .stat-icon {
      background: #dbeafe;
      color: #3b82f6;
    }

    .stat-card.accepted .stat-icon {
      background: #d1fae5;
      color: #10b981;
    }

    .stat-card.rejected .stat-icon {
      background: #fee2e2;
      color: #ef4444;
    }

    .stat-number {
      font-size: 32px;
      font-weight: 800;
      color: #1f2937;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 13px;
      color: #6b7280;
      font-weight: 600;
      text-transform: uppercase;
    }

    /* Table Section */
    .table-section {
      background: white;
      border-radius: 20px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      overflow: hidden;
    }

    .table-header {
      padding: 24px 32px;
      border-bottom: 2px solid #e5e7eb;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }

    .table-title {
      font-size: 20px;
      font-weight: 700;
      color: #1f2937;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .filter-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .filter-btn {
      padding: 8px 16px;
      border: 2px solid #e5e7eb;
      background: white;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      color: #6b7280;
      cursor: pointer;
      transition: all 0.3s;
      font-family: 'Poppins', sans-serif;
    }

    .filter-btn:hover {
      border-color: #8a1538;
      color: #8a1538;
    }

    .filter-btn.active {
      background: linear-gradient(135deg, #8a1538, #c91f4d);
      color: white;
      border-color: #8a1538;
    }

    /* Table */
    .table-container {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }

    thead {
      background: #f9fafb;
    }

    thead th {
      padding: 16px 16px;
      text-align: left;
      font-weight: 700;
      color: #1f2937;
      font-size: 13px;
      text-transform: uppercase;
      border-bottom: 2px solid #e5e7eb;
      white-space: nowrap;
    }

    /* Specific column widths */
    thead th:nth-child(1) { width: 20%; min-width: 220px; } /* Applicant */
    thead th:nth-child(2) { width: 12%; min-width: 100px; } /* Phone */
    thead th:nth-child(3) { width: 12%; min-width: 120px; } /* Skills */
    thead th:nth-child(4) { width: 10%; min-width: 100px; } /* Experience */
    thead th:nth-child(5) { width: 10%; min-width: 100px; } /* Applied */
    thead th:nth-child(6) { width: 12%; min-width: 120px; } /* Status */
    thead th:nth-child(7) { width: 16%; min-width: 340px; } /* Actions */

    tbody tr {
      border-bottom: 1px solid #e5e7eb;
      transition: background 0.3s;
    }

    tbody tr:hover {
      background: #fff5f7;
    }

    tbody td {
      padding: 16px 16px;
      color: #6b7280;
      font-size: 14px;
      vertical-align: middle;
    }

    /* Match thead widths */
    tbody td:nth-child(1) { width: 20%; } /* Applicant */
    tbody td:nth-child(2) { width: 10%; } /* Phone */
    tbody td:nth-child(3) { width: 10%; } /* Skills */
    tbody td:nth-child(4) { width: 10%; } /* Experience */
    tbody td:nth-child(5) { width: 10%; } /* Applied */
    tbody td:nth-child(6) { width: 12%; } /* Status */
    tbody td:nth-child(7) { width: 16%; } /* Actions */

    /* Applicant Cell */
    .applicant-cell {
      display: flex;
      align-items: center;
      gap: 16px;
      min-width: 200px;
      max-width: 100%;
    }

    .applicant-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #8a1538;
      flex-shrink: 0;
    }

    .applicant-avatar-placeholder {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: linear-gradient(135deg, #8a1538, #c91f4d);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 18px;
      flex-shrink: 0;
    }

    .applicant-info {
      flex: 1;
      min-width: 0;
      overflow: hidden;
    }

    .applicant-name {
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .applicant-email {
      color: #6b7280;
      font-size: 13px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Skills */
    .skills-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      max-width: 100%;
    }

    .skill-tag {
      padding: 4px 10px;
      background: #f3e8ff;
      color: #6b21a8;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 600;
      white-space: nowrap;
    }

    .skill-tag.more {
      background: #f3f4f6;
      color: #6b7280;
    }

    /* Status */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .status-badge.pending {
      background: #fef3c7;
      color: #92400e;
    }

    .status-badge.shortlisted {
      background: #dbeafe;
      color: #1e40af;
    }

    .status-badge.accepted {
      background: #d1fae5;
      color: #065f46;
    }

    .status-badge.rejected {
      background: #fee2e2;
      color: #991b1b;
    }

    /* Actions */
    .action-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: nowrap;
      align-items: center;
      justify-content: flex-start;
    }

    .action-btn {
      padding: 8px 14px;
      border: none;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-family: 'Poppins', sans-serif;
      white-space: nowrap;
    }

    .action-btn.view {
      background: linear-gradient(135deg, #8a1538, #c91f4d);
      color: white;
    }

    .action-btn.shortlist {
      background: #3b82f6;
      color: white;
    }

    .action-btn.accept {
      background: #10b981;
      color: white;
    }

    .action-btn.reject {
      background: #ef4444;
      color: white;
    }

    .action-btn:not(:disabled):hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .action-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Status Dropdown Selector */
    .status-select {
      padding: 8px 1px;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      font-family: 'Poppins', sans-serif;
      color: #6b7280;
      background: white;
      cursor: pointer;
      transition: all 0.3s;
      min-width: 10px;
      max-width: 100px;
    }

    .status-select:hover {
      border-color: #8a1538;
      color: #8a1538;
      background: #fff5f7;
    }

    .status-select:focus {
      outline: none;
      border-color: #8a1538;
      box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
    }

    .status-select option {
      padding: 8px;
      font-weight: 600;
    }


    /* Responsive */
    @media (max-width: 1200px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
      .page-header { padding: 24px 20px; }
      .header-title { font-size: 28px; }
      .content-container { padding: 24px 20px; }
      .stats-grid { grid-template-columns: 1fr; }
      .table-container { overflow-x: auto; }
      table { 
        min-width: 1200px;
        table-layout: auto;
      }
      .action-buttons { flex-wrap: wrap; }
    }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <div class="page-header">
      <div class="header-content">
        <a href="../employer_jobs.php" class="back-link">
          <i class="fas fa-arrow-left"></i> Back to Jobs
        </a>
        
        <h1 class="header-title">
          <i class="fas fa-users"></i>
          Job Applicants
        </h1>

        <div class="job-name"><?= htmlspecialchars($job['title']); ?></div>
        
        <div class="job-info">
          <div class="job-info-item">
            <i class="fas fa-map-marker-alt"></i>
            <?= htmlspecialchars($job['location']); ?>
          </div>
          <div class="job-info-item">
            <i class="fas fa-tag"></i>
            <?= htmlspecialchars($job['category']); ?>
          </div>
          <div class="job-info-item">
            <i class="fas fa-money-bill-wave"></i>
            RM <?= number_format($job['pay_rate'], 2); ?>/hr
          </div>
          <div class="job-info-item">
            <i class="fas fa-users"></i>
            <?= $total_applicants; ?> Total
          </div>
        </div>
      </div>
    </div>

    <div class="content-container">
      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card pending">
          <div class="stat-icon"><i class="fas fa-clock"></i></div>
          <div class="stat-number"><?= $stats['Pending']; ?></div>
          <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card shortlisted">
          <div class="stat-icon"><i class="fas fa-star"></i></div>
          <div class="stat-number"><?= $stats['Shortlisted']; ?></div>
          <div class="stat-label">Shortlisted</div>
        </div>
        <div class="stat-card accepted">
          <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
          <div class="stat-number"><?= $stats['Accepted']; ?></div>
          <div class="stat-label">Accepted</div>
        </div>
        <div class="stat-card rejected">
          <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
          <div class="stat-number"><?= $stats['Rejected']; ?></div>
          <div class="stat-label">Rejected</div>
        </div>
      </div>

      <!-- Table -->
      <div class="table-section">
        <div class="table-header">
          <div class="table-title">
            <i class="fas fa-table"></i>
            All Applicants
          </div>
          <div class="filter-buttons">
            <button class="filter-btn active" onclick="filterTable('all')">
              All (<?= $total_applicants; ?>)
            </button>
            <button class="filter-btn" onclick="filterTable('pending')">
              Pending (<?= $stats['Pending']; ?>)
            </button>
            <button class="filter-btn" onclick="filterTable('shortlisted')">
              Shortlisted (<?= $stats['Shortlisted']; ?>)
            </button>
            <button class="filter-btn" onclick="filterTable('accepted')">
              Accepted (<?= $stats['Accepted']; ?>)
            </button>
            <button class="filter-btn" onclick="filterTable('rejected')">
              Rejected (<?= $stats['Rejected']; ?>)
            </button>
          </div>
        </div>

        <div class="table-container">
          <?php if (count($temp_applicants) > 0): ?>
            <table>
              <thead>
                <tr>
                  <th>Applicant</th>
                  <th>Phone</th>
                  <th>Skills</th>
                  <th>Experience</th>
                  <th>Applied</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($temp_applicants as $app): ?>
                  <?php
                    $status = ucfirst(strtolower($app['status']));
                    $statusClass = strtolower($status);
                    $applicant_name = !empty($app['name']) ? $app['name'] : explode('@', $app['worker_email'])[0];
                    
                    $skills_array = !empty($app['skills']) ? array_map('trim', explode(',', $app['skills'])) : [];
                    $display_skills = array_slice($skills_array, 0, 3);
                    $remaining_skills = count($skills_array) - 3;

                    $applied_date = !empty($app['applied_at']) ? date('M d, Y', strtotime($app['applied_at'])) : 'N/A';
                  ?>
                  <tr data-status="<?= $statusClass; ?>">
                    <td>
                      <div class="applicant-cell">
                        <?php if (!empty($app['photo_url']) && file_exists('../' . $app['photo_url'])): ?>
                          <img src="../<?= htmlspecialchars($app['photo_url']); ?>" alt="Profile" class="applicant-avatar">
                        <?php else: ?>
                          <div class="applicant-avatar-placeholder">
                            <?= strtoupper(substr($applicant_name, 0, 1)); ?>
                          </div>
                        <?php endif; ?>
                        <div>
                          <div class="applicant-name"><?= htmlspecialchars($applicant_name); ?></div>
                          <div class="applicant-email"><?= htmlspecialchars($app['worker_email']); ?></div>
                        </div>
                      </div>
                    </td>

                    <td>
                      <?= !empty($app['phone']) ? htmlspecialchars($app['phone']) : '<span style="opacity:0.5">N/A</span>'; ?>
                    </td>

                    <td>
                      <?php if (!empty($skills_array)): ?>
                        <div class="skills-tags">
                          <?php foreach ($display_skills as $skill): ?>
                            <span class="skill-tag"><?= htmlspecialchars($skill); ?></span>
                          <?php endforeach; ?>
                          <?php if ($remaining_skills > 0): ?>
                            <span class="skill-tag more">+<?= $remaining_skills; ?></span>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <span style="opacity:0.5">None</span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <?= !empty($app['experience']) ? htmlspecialchars($app['experience']) . ' level' : '<span style="opacity:0.5">N/A</span>'; ?>
                    </td>

                    <td><?= $applied_date; ?></td>

                    <td>
                      <span class="status-badge <?= $statusClass; ?>">
                        <?php if ($status == 'Pending'): ?>
                          <i class="fas fa-clock"></i>
                        <?php elseif ($status == 'Shortlisted'): ?>
                          <i class="fas fa-star"></i>
                        <?php elseif ($status == 'Accepted'): ?>
                          <i class="fas fa-check-circle"></i>
                        <?php else: ?>
                          <i class="fas fa-times-circle"></i>
                        <?php endif; ?>
                        <?= $status; ?>
                      </span>
                    </td>

                    <td>
                      <div class="action-buttons">
                        <a href="view_profile.php?user_id=<?= $app['user_id']; ?>&job_id=<?= $job_id; ?>" class="action-btn view">
                          <i class="fas fa-user"></i> View
                        </a>
                        
                        <form method="POST" action="update_status.php" style="display: inline-block;">
                          <input type="hidden" name="application_id" value="<?= $app['application_id']; ?>">
                          <input type="hidden" name="job_id" value="<?= $job_id; ?>">
                          <select name="status" class="status-select" onchange="if(confirm('Change status to ' + this.value + '?')) this.form.submit();">
                            <option value="">update</option>
                            <option value="Pending" <?= $status == 'Pending' ? 'disabled' : ''; ?>>⏰ Mark as Pending</option>
                            <option value="Shortlisted" <?= $status == 'Shortlisted' ? 'disabled' : ''; ?>>⭐ Shortlist</option>
                            <option value="Accepted" <?= $status == 'Accepted' ? 'disabled' : ''; ?>>✅ Accept</option>
                            <option value="Rejected" <?= $status == 'Rejected' ? 'disabled' : ''; ?>>❌ Reject</option>
                          </select>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div style="text-align:center; padding:80px 20px;">
              <div style="font-size:64px; margin-bottom:16px; opacity:0.5;">📋</div>
              <div style="font-size:24px; font-weight:700; margin-bottom:8px;">No Applicants Yet</div>
              <div style="color:#6b7280;">This job hasn't received any applications.</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    function filterTable(status) {
      const rows = document.querySelectorAll('tbody tr');
      const buttons = document.querySelectorAll('.filter-btn');
      
      buttons.forEach(btn => btn.classList.remove('active'));
      event.target.classList.add('active');
      
      rows.forEach(row => {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
      });
    }
  </script>
</body>
</html>