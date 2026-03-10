<?php
session_start();
include("backend/db_connect.php");

// Ensure only employers can access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
  header("Location: login.html");
  exit();
}

// Validate job_id
if (!isset($_GET['job_id'])) {
  die("Invalid Job ID.");
}

$job_id = intval($_GET['job_id']);
$employer_id = $_SESSION['user_id'];

// Fetch job details
$sql = "SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $job_id, $employer_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if (!$job) {
  die("Job not found or unauthorized.");
}

// Parse JSON fields
$responsibilities = !empty($job['responsibilities']) ? json_decode($job['responsibilities'], true) : [];
$skills = !empty($job['skills']) ? json_decode($job['skills'], true) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Job - <?= htmlspecialchars($job['title']) ?> | POP!Work</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/sidebar.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  
  <style>
    :root {
      --primary: #8a1538;
      --primary-dark: #6b1129;
      --primary-light: #c91f4d;
      --success: #27ae60;
      --warning: #f39c12;
      --danger: #e74c3c;
      --info: #3498db;
      --bg-light: #f5f7fa;
      --text-dark: #1c1e21;
      --text-medium: #4a5568;
      --text-light: #65676b;
      --border: #e1e8ed;
      --shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
      --shadow-md: 0 4px 12px rgba(138, 21, 56, 0.15);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
      position: relative;
    }

    /* Photo Background Like Other Pages */
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

    .main-content {
      margin-left: 280px;
      width: calc(100% - 280px);
      min-height: 100vh;
      position: relative;
      z-index: 1;
    }

    .container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 32px 24px;
    }

    /* Header */
    .page-header {
      background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
      color: white;
      padding: 40px;
      border-radius: 20px;
      margin-bottom: 32px;
      box-shadow: var(--shadow-md);
    }

    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: white;
      text-decoration: none;
      font-weight: 600;
      padding: 8px 16px;
      background: rgba(255, 255, 255, 0.15);
      border-radius: 8px;
      transition: all 0.2s;
    }

    .back-link:hover {
      background: rgba(255, 255, 255, 0.25);
    }

    .page-header h1 {
      font-size: 36px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .page-header p {
      font-size: 16px;
      opacity: 0.95;
    }

    /* Messages */
    .message {
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 12px;
      animation: slideIn 0.3s ease;
    }

    .message.success {
      background: rgba(39, 174, 96, 0.1);
      border: 2px solid rgba(39, 174, 96, 0.3);
      color: #27ae60;
    }

    .message.error {
      background: rgba(231, 76, 60, 0.1);
      border: 2px solid rgba(231, 76, 60, 0.3);
      color: #e74c3c;
    }

    /* Info Box */
    .info-box {
      padding: 16px 20px;
      background: rgba(243, 157, 18, 0.6);
      border: 2px solid rgba(243, 156, 18, 0.3);
      border-radius: 12px;
      margin-bottom: 24px;
    }

    .info-box-title {
      font-size: 15px;
      font-weight: 700;
      color: var(--bg-light);
      margin-bottom: 6px;
    }

    .info-box-text {
      font-size: 14px;
      color: var(--border);
      line-height: 1.6;
    }

    /* Card */
    .card {
      background: white;
      border-radius: 16px;
      box-shadow: var(--shadow-sm);
      padding: 32px;
      margin-bottom: 24px;
    }

    /* Section */
    .form-section {
      margin-bottom: 32px;
      padding-bottom: 32px;
      border-bottom: 2px solid var(--bg-light);
    }

    .form-section:last-of-type {
      border-bottom: none;
    }

    .section-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
    }

    .section-icon {
      font-size: 24px;
    }

    .section-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-dark);
    }

    /* Form Elements */
    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 8px;
    }

    .required {
      color: var(--danger);
    }

    .form-input,
    .form-select,
    .form-textarea {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid var(--border);
      border-radius: 10px;
      font-size: 14px;
      font-family: inherit;
      transition: all 0.2s;
      background: white;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
    }

    .form-textarea {
      min-height: 120px;
      resize: vertical;
    }

    .form-hint {
      display: block;
      font-size: 13px;
      color: var(--text-light);
      margin-top: 6px;
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 16px;
    }

    .form-row-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }

    .form-row-4 {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
    }

    /* Pay Input */
    .pay-input-wrapper {
      position: relative;
    }

    .currency-prefix {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      font-weight: 600;
      color: var(--text-medium);
    }

    .pay-input {
      padding-left: 48px;
    }

    /* Checkbox Group */
    .checkbox-group {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 12px;
    }

    .checkbox-group label {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      background: var(--bg-light);
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s;
      font-size: 14px;
    }

    .checkbox-group label:hover {
      background: rgba(138, 21, 56, 0.08);
    }

    .checkbox-group input[type="checkbox"] {
      width: auto;
      cursor: pointer;
    }

    /* Tags */
    .tags-container {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      min-height: 50px;
      padding: 12px;
      border: 2px dashed var(--border);
      border-radius: 10px;
      margin-bottom: 12px;
    }

    .tag-item {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      background: linear-gradient(135deg, rgba(138, 21, 56, 0.1), rgba(201, 31, 77, 0.1));
      border: 2px solid rgba(138, 21, 56, 0.2);
      border-radius: 20px;
      font-size: 14px;
      font-weight: 500;
      color: var(--primary);
    }

    .remove-tag {
      cursor: pointer;
      font-weight: 700;
      font-size: 18px;
      opacity: 0.7;
      transition: opacity 0.2s;
    }

    .remove-tag:hover {
      opacity: 1;
    }

    /* Map */
    #map {
      height: 400px;
      border-radius: 12px;
      overflow: hidden;
      margin-bottom: 12px;
      border: 2px solid var(--border);
    }

    /* Buttons */
    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      box-shadow: var(--shadow-md);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(138, 21, 56, 0.3);
    }

    .btn-secondary {
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn-secondary:hover {
      background: var(--primary);
      color: white;
    }

    .btn-sm {
      padding: 8px 16px;
      font-size: 13px;
    }

    .submit-section {
      margin-top: 32px;
      padding-top: 32px;
      border-top: 2px solid var(--bg-light);
      display: flex;
      gap: 12px;
      justify-content: center;
    }

    .submit-btn {
      padding: 16px 48px;
      background: linear-gradient(135deg, var(--success), #2ecc71);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
      transition: all 0.3s;
    }

    .submit-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
    }

    .cancel-btn {
      padding: 16px 48px;
      background: white;
      color: var(--text-medium);
      border: 2px solid var(--border);
      border-radius: 12px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
    }

    .cancel-btn:hover {
      background: var(--bg-light);
      border-color: var(--text-medium);
    }

    .char-counter {
      display: block;
      text-align: right;
      font-size: 13px;
      color: var(--text-light);
      margin-top: 6px;
    }

    .char-counter.warning {
      color: var(--warning);
    }

    .char-counter.error {
      color: var(--danger);
      font-weight: 600;
    }

    /* Animations */
    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Responsive */
    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
        width: 100%;
      }

      .form-row,
      .form-row-3,
      .form-row-4 {
        grid-template-columns: 1fr;
      }

      .checkbox-group {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 576px) {
      .container {
        padding: 20px 16px;
      }

      .page-header {
        padding: 24px;
      }

      .page-header h1 {
        font-size: 28px;
      }

      .card {
        padding: 20px;
      }

      .submit-section {
        flex-direction: column;
      }

      .submit-btn,
      .cancel-btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>
  <?php 
    $page_title = "Edit Job";
    include 'includes/sidebar.php';
  ?>

  <div class="main-content">
    <div class="container">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-top">
          <a href="view_job.php?job_id=<?= $job_id ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Job
          </a>
        </div>
        <h1><i class="fas fa-edit"></i> Edit Job Posting</h1>
        <p>Update details for: <strong><?= htmlspecialchars($job['title']) ?></strong></p>
      </div>

      <!-- Messages -->
      <?php if (isset($_GET['success'])): ?>
      <div class="message success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($_GET['success']) ?>
      </div>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
      <div class="message error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($_GET['error']) ?>
      </div>
      <?php endif; ?>

      <!-- Info Box -->
      <div class="info-box">
        <div class="info-box-title"><i class="fas fa-info-circle"></i> Editing Job</div>
        <p class="info-box-text">You are editing an existing job posting. All changes will be saved and visible to applicants immediately.</p>
      </div>

      <div class="card">
        <form action="backend/update_job.php" method="POST" id="jobEditForm">
          <input type="hidden" name="job_id" value="<?= $job['job_id']; ?>">

          <!-- Basic Information -->
          <div class="form-section">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-info-circle"></i></span>
              <h3 class="section-title">Basic Information</h3>
            </div>

            <div class="form-group">
              <label class="form-label" for="title">Job Title <span class="required">*</span></label>
              <input type="text" id="title" name="title" class="form-input" 
                     placeholder="e.g., Event Crew, Cashier, Photographer" required maxlength="100"
                     value="<?= htmlspecialchars($job['title']); ?>">
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="category">Job Type <span class="required">*</span></label>
                <select name="category" id="category" class="form-select" required>
                  <option value="">-- Select Job Type --</option>
                  <option value="Gig" <?= $job['category']=='Gig'?'selected':''; ?>>🎯 Gig (One-time task)</option>
                  <option value="Part-Time" <?= $job['category']=='Part-Time'?'selected':''; ?>>⏰ Part-Time</option>
                  <option value="Full-Time" <?= $job['category']=='Full-Time'?'selected':''; ?>>💼 Full-Time</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="location">Location <span class="required">*</span></label>
                <select name="location" id="location" class="form-select" required>
                  <option value="">-- Select District --</option>
                  <?php
                  $locations = ['Beaufort','Beluran','Keningau','Kinabatangan','Kota Belud','Kota Kinabalu','Kota Marudu','Kuala Penyu','Kudat','Kunak','Lahad Datu','Nabawan','Papar','Penampang','Pitas','Ranau','Sandakan','Semporna','Sipitang','Tambunan','Tamparuli','Tawau','Telupid','Tenom','Tongod','Tuaran','Putatan','Kalabakan'];
                  foreach ($locations as $loc) {
                    $sel = ($job['location']==$loc)?'selected':'';
                    echo "<option value=\"$loc\" $sel>$loc</option>";
                  }
                  ?>
                </select>
              </div>
            </div>

            <div class="form-row-4">
              <div class="form-group">
                <label class="form-label" for="experience_level">Experience Level</label>
                <select name="experience_level" id="experience_level" class="form-select">
                  <option value="Any" <?= ($job['experience_level']??'Any')=='Any'?'selected':''; ?>>Any Level</option>
                  <option value="Entry" <?= ($job['experience_level']??'')=='Entry'?'selected':''; ?>>Entry Level</option>
                  <option value="Mid" <?= ($job['experience_level']??'')=='Mid'?'selected':''; ?>>Mid Level</option>
                  <option value="Senior" <?= ($job['experience_level']??'')=='Senior'?'selected':''; ?>>Senior Level</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="positions_available">Positions <span class="required">*</span></label>
                <input type="number" id="positions_available" name="positions_available" 
                       class="form-input" value="<?= $job['positions_available'] ?? 1; ?>" min="1" max="100" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="is_urgent">Urgency</label>
                <select name="is_urgent" id="is_urgent" class="form-select">
                  <option value="0" <?= ($job['is_urgent']??0)==0?'selected':''; ?>>Normal</option>
                  <option value="1" <?= ($job['is_urgent']??0)==1?'selected':''; ?>>🔥 Urgent</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="auto_expire_days">Expires In</label>
                <select name="auto_expire_days" id="auto_expire_days" class="form-select">
                  <option value="7" <?= ($job['auto_expire_days']??30)==7?'selected':''; ?>>7 days</option>
                  <option value="14" <?= ($job['auto_expire_days']??30)==14?'selected':''; ?>>14 days</option>
                  <option value="30" <?= ($job['auto_expire_days']??30)==30?'selected':''; ?>>30 days</option>
                  <option value="60" <?= ($job['auto_expire_days']??30)==60?'selected':''; ?>>60 days</option>
                  <option value="90" <?= ($job['auto_expire_days']??30)==90?'selected':''; ?>>90 days</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Job Duration -->
          <div class="form-section">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-calendar-alt"></i></span>
              <h3 class="section-title">Job Duration</h3>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="job_duration">Duration Type</label>
                <select name="job_duration" id="job_duration" class="form-select">
                  <?php
                  $durations = ['Permanent','1 day','1 week','2 weeks','1 month','3 months','6 months','1 year'];
                  foreach($durations as $dur) {
                    $sel = (($job['job_duration']??'Permanent')==$dur)?'selected':'';
                    echo "<option value=\"$dur\" $sel>$dur</option>";
                  }
                  ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-input" 
                       value="<?= $job['start_date'] ?? ''; ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="end_date">End Date (for temporary positions)</label>
              <input type="date" id="end_date" name="end_date" class="form-input" 
                     value="<?= $job['end_date'] ?? ''; ?>">
              <span class="form-hint">Leave empty for permanent positions or if duration is flexible</span>
            </div>
          </div>

          <!-- Compensation -->
          <div class="form-section">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-money-bill-wave"></i></span>
              <h3 class="section-title">Compensation</h3>
            </div>
            
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="pay_rate">Pay Rate <span class="required">*</span></label>
                <div class="pay-input-wrapper">
                  <span class="currency-prefix">RM</span>
                  <input type="number" id="pay_rate" name="pay_rate" class="form-input pay-input" 
                         placeholder="0.00" step="0.01" min="0" required 
                         value="<?= $job['pay_rate']; ?>">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="pay_period">Pay Period <span class="required">*</span></label>
                <select name="pay_period" id="pay_period" class="form-select" required>
                  <option value="">-- Select --</option>
                  <option value="Per Hour" <?= $job['pay_period']=='Per Hour'?'selected':''; ?>>Per Hour</option>
                  <option value="Per Day" <?= $job['pay_period']=='Per Day'?'selected':''; ?>>Per Day</option>
                  <option value="Per Week" <?= $job['pay_period']=='Per Week'?'selected':''; ?>>Per Week</option>
                  <option value="Per Month" <?= $job['pay_period']=='Per Month'?'selected':''; ?>>Per Month</option>
                  <option value="Per Project" <?= $job['pay_period']=='Per Project'?'selected':''; ?>>Per Project</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Requirements -->
          <div class="form-section">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-clipboard-check"></i></span>
              <h3 class="section-title">Requirements</h3>
            </div>

            <div class="form-group">
              <label class="form-label">Required Documents</label>
              <div class="checkbox-group">
                <?php 
                $docs = !empty($job['required_documents']) ? explode(',', $job['required_documents']) : [];
                $allDocs = ['Resume', 'Cover Letter', 'Portfolio', 'Certificates', 'ID Card', 'References'];
                foreach ($allDocs as $doc): 
                  $checked = in_array($doc, $docs) ? 'checked' : '';
                ?>
                <label>
                  <input type="checkbox" class="doc-item" value="<?= $doc ?>" <?= $checked ?>> <?= $doc ?>
                </label>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="required_documents" id="required_documents">
            </div>

            <div class="form-group">
              <label class="form-label">Languages Required</label>
              <div class="checkbox-group">
                <?php 
                $langs = !empty($job['languages_required']) ? explode(',', $job['languages_required']) : [];
                $allLangs = ['English', 'Malay', 'Mandarin', 'Tamil', 'Kadazan', 'Dusun'];
                foreach ($allLangs as $lang): 
                  $checked = in_array($lang, $langs) ? 'checked' : '';
                ?>
                <label>
                  <input type="checkbox" class="lang-item" value="<?= $lang ?>" <?= $checked ?>> <?= $lang ?>
                </label>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="languages_required" id="languages_required">
            </div>
          </div>

          <!-- Job Details -->
          <div class="form-section">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-file-alt"></i></span>
              <h3 class="section-title">Job Details</h3>
            </div>

            <div class="form-group">
              <label class="form-label">Main Responsibilities</label>
              <div class="tags-container" id="responsibilities">
                <?php if (!empty($responsibilities) && is_array($responsibilities)): ?>
                  <?php foreach ($responsibilities as $resp): ?>
                    <span class="tag-item">
                      <?= htmlspecialchars($resp) ?>
                      <span class="remove-tag" onclick="this.parentElement.remove()">×</span>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <input type="hidden" name="responsibilities" id="responsibilitiesInput">
              <button type="button" class="btn btn-secondary btn-sm" id="addCustomResp">
                <i class="fas fa-plus"></i> Add Custom Responsibility
              </button>
            </div>

            <div class="form-group">
              <label class="form-label">Required Skills</label>
              <div class="tags-container" id="skills">
                <?php if (!empty($skills) && is_array($skills)): ?>
                  <?php foreach ($skills as $skill): ?>
                    <span class="tag-item">
                      <?= htmlspecialchars($skill) ?>
                      <span class="remove-tag" onclick="this.parentElement.remove()">×</span>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <input type="hidden" name="skills" id="skillsInput">
              <button type="button" class="btn btn-secondary btn-sm" id="addCustomSkill">
                <i class="fas fa-plus"></i> Add Custom Skill
              </button>
            </div>

            <div class="form-group">
              <label class="form-label">Work Schedule</label>
              <div class="checkbox-group">
                <?php 
                $schedule = !empty($job['schedule']) ? explode(',', $job['schedule']) : [];
                $allSchedule = ['Flexible hours', 'Weekdays only', 'Weekends only', 'Night shifts', 'Rotating shifts'];
                foreach ($allSchedule as $sched): 
                  $checked = in_array($sched, $schedule) ? 'checked' : '';
                ?>
                <label>
                  <input type="checkbox" class="schedule-item" value="<?= $sched ?>" <?= $checked ?>> <?= $sched ?>
                </label>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="schedule" id="scheduleInput">
            </div>

            <div class="form-group">
              <label class="form-label">Additional Benefits</label>
              <div class="checkbox-group">
                <?php 
                $benefits = !empty($job['benefits']) ? explode(',', $job['benefits']) : [];
                $allBenefits = ['Free meals', 'Transportation allowance', 'Training provided', 'Performance bonus', 'EPF/SOCSO', 'Medical coverage'];
                foreach ($allBenefits as $benefit): 
                  $checked = in_array($benefit, $benefits) ? 'checked' : '';
                ?>
                <label>
                  <input type="checkbox" class="benefit-item" value="<?= $benefit ?>" <?= $checked ?>> <?= $benefit ?>
                </label>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="benefits" id="benefitsInput">
            </div>

            <div class="form-group">
              <label class="form-label" for="description">Job Description <span class="required">*</span></label>
              <textarea id="description" name="description" class="form-textarea" 
                        placeholder="Provide a detailed description of the job..." 
                        maxlength="2000" required><?= htmlspecialchars($job['description']); ?></textarea>
              <span class="char-counter" id="charCounter">0 / 2000</span>
            </div>
          </div>

          <!-- Map Location -->
          <div class="form-section">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-map-marked-alt"></i></span>
              <h3 class="section-title">Physical Location (Optional)</h3>
            </div>
            
            <div class="form-group">
              <label class="form-label">Click on the map to mark the exact job location</label>
              <div id="map"></div>
              <input type="hidden" name="latitude" id="latitude" value="<?= $job['latitude'] ?? ''; ?>">
              <input type="hidden" name="longitude" id="longitude" value="<?= $job['longitude'] ?? ''; ?>">
              <button type="button" class="btn btn-secondary" onclick="clearMap()">
                <i class="fas fa-times"></i> Clear Location
              </button>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="submit-section">
            <a href="view_job.php?job_id=<?= $job_id ?>" class="cancel-btn">
              <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="submit-btn" id="submitBtn">
              <i class="fas fa-save"></i> Save Changes
            </button>
          </div>

        </form>
      </div>
    </div>
  </div>

  

  <script>
    // ===== ADD CUSTOM RESPONSIBILITY =====
    document.getElementById('addCustomResp').addEventListener('click', function() {
      const text = prompt('Enter a responsibility:');
      if (text && text.trim()) {
        const container = document.getElementById('responsibilities');
        addTag(container, text.trim());
      }
    });

    // ===== ADD CUSTOM SKILL =====
    document.getElementById('addCustomSkill').addEventListener('click', function() {
      const text = prompt('Enter a skill:');
      if (text && text.trim()) {
        const container = document.getElementById('skills');
        addTag(container, text.trim());
      }
    });

    // ===== ADD TAG HELPER =====
    function addTag(container, text) {
      const tag = document.createElement('span');
      tag.className = 'tag-item';
      tag.innerHTML = `
        ${text}
        <span class="remove-tag" onclick="this.parentElement.remove()">×</span>
      `;
      container.appendChild(tag);
    }

    // ===== CHARACTER COUNTER =====
    function updateCharCounter() {
      const len = document.getElementById('description').value.length;
      const counter = document.getElementById('charCounter');
      counter.textContent = len + ' / 2000';
      counter.className = 'char-counter';
      if (len > 1800) counter.classList.add('warning');
      if (len > 2000) counter.classList.add('error');
    }
    document.getElementById('description').addEventListener('input', updateCharCounter);
    updateCharCounter(); // Init counter

    // ===== FORM SUBMISSION =====
    document.getElementById('jobEditForm').addEventListener('submit', function(e) {
      // Collect responsibilities
      const respTags = document.querySelectorAll('#responsibilities .tag-item');
      const respArray = Array.from(respTags).map(tag => tag.textContent.replace('×', '').trim());
      document.getElementById('responsibilitiesInput').value = JSON.stringify(respArray);
      
      // Collect skills
      const skillTags = document.querySelectorAll('#skills .tag-item');
      const skillsArray = Array.from(skillTags).map(tag => tag.textContent.replace('×', '').trim());
      document.getElementById('skillsInput').value = JSON.stringify(skillsArray);
      
      // Collect documents
      const docs = Array.from(document.querySelectorAll('.doc-item:checked')).map(cb => cb.value).join(',');
      document.getElementById('required_documents').value = docs;
      
      // Collect languages
      const langs = Array.from(document.querySelectorAll('.lang-item:checked')).map(cb => cb.value).join(',');
      document.getElementById('languages_required').value = langs;
      
      // Collect schedule
      const schedule = Array.from(document.querySelectorAll('.schedule-item:checked')).map(cb => cb.value).join(',');
      document.getElementById('scheduleInput').value = schedule;
      
      // Collect benefits
      const benefits = Array.from(document.querySelectorAll('.benefit-item:checked')).map(cb => cb.value).join(',');
      document.getElementById('benefitsInput').value = benefits;
      
      document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      document.getElementById('submitBtn').disabled = true;
    });

    // ===== MAP FUNCTIONALITY =====
    let map, marker;

    function initMap() {
      const existingLat = <?= !empty($job['latitude']) ? $job['latitude'] : 'null' ?>;
      const existingLng = <?= !empty($job['longitude']) ? $job['longitude'] : 'null' ?>;
      const defaultPos = [5.9788, 116.0753]; // Sabah center
      
      const initialPos = (existingLat && existingLng) ? [existingLat, existingLng] : defaultPos;
      const initialZoom = (existingLat && existingLng) ? 15 : 8;

      map = L.map('map').setView(initialPos, initialZoom);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(map);

      map.on('click', function(e) {
        const { lat, lng } = e.latlng;
        placeMarker(lat, lng);
        document.getElementById("latitude").value = lat.toFixed(6);
        document.getElementById("longitude").value = lng.toFixed(6);
      });

      // Place existing marker if coordinates exist
      if (existingLat && existingLng) {
        placeMarker(existingLat, existingLng);
      }
    }

    function placeMarker(latitude, longitude) {
      if (marker) { 
        marker.setLatLng([latitude, longitude]);
      } else {
        marker = L.marker([latitude, longitude]).addTo(map);
      }
    }

    function clearMap() {
      if (marker) {
        map.removeLayer(marker);
        marker = null;
      }
      document.getElementById("latitude").value = "";
      document.getElementById("longitude").value = "";
    }

    // ===== INIT =====
    document.addEventListener('DOMContentLoaded', function() {
      initMap();
      
      // Set min dates
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('start_date').min = today;
      document.getElementById('end_date').min = today;
    });
  </script>
</body>
</html>