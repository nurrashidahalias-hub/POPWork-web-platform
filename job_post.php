<?php
// Start session for user authentication
session_start();

// Check if user is logged in as employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header('Location: login.php');
    exit();
}

require_once 'backend/db_connect.php';

$employer_id = $_SESSION['user_id'];

// Check if editing existing job
$editing = false;
$job_data = null;

if (isset($_GET['job_id'])) {
    $job_id = intval($_GET['job_id']);
    
    // Fetch job details
    $job_sql = "SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?";
    $job_stmt = $conn->prepare($job_sql);
    $job_stmt->bind_param("ii", $job_id, $employer_id);
    $job_stmt->execute();
    $result = $job_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $job_data = $result->fetch_assoc();
        $editing = true;
    }
}

// Load user's saved templates
$saved_templates = [];
$stmt = $conn->prepare("SELECT * FROM job_templates WHERE employer_id = ? OR is_default = 1 ORDER BY is_default DESC, template_name ASC");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $saved_templates[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $editing ? 'Edit Job' : 'Post a Job' ?> | POP!Work</title>
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
      background: var(--bg-light);
      min-height: 100vh;
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
    

    .main-content {
      margin-left: 280px;
      width: calc(100% - 280px);
      min-height: 100vh;
      position: relative;

    }

    .container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 32px 24px;
      position: relative;

    }

    /* Header */
    .page-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
      padding: 40px;
      border-radius: 20px;
      margin-bottom: 32px;
      box-shadow: var(--shadow-md);
      position: relative;
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
    }

    .message.success {
      background: #d4edda;
      color: #155724;
      border-left: 4px solid var(--success);
    }

    .message.error {
      background: #f8d7da;
      color: #721c24;
      border-left: 4px solid var(--danger);
    }

    /* Mode Toggle */
    .mode-toggle {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      padding: 8px;
      border-radius: 14px;
      box-shadow: var(--shadow-sm);
    }

    .mode-btn {
      flex: 1;
      padding: 14px 24px;
      background: transparent;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      color: var(--text-medium);
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .mode-btn:hover {
      background: var(--bg-light);
    }

    .mode-btn.active {
      background: var(--primary);
      color: white;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.2);
    }

    /* Info Box */
    .info-box {
      background: linear-gradient(135deg, #e3f2fd, #bbdefb);
      border-left: 4px solid #2196f3;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 24px;
    }

    .info-box.orange {
      background: linear-gradient(135deg, #fff3e0, #ffe0b2);
      border-left-color: #ff9800;
    }

    .info-box-title {
      font-weight: 700;
      font-size: 16px;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .info-box-text {
      color: var(--text-medium);
      line-height: 1.6;
    }

    /* Card */
    .card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 20px;
      padding: 40px;
      box-shadow: var(--shadow-sm);
    }

    /* Form Sections */
    .form-section {
      margin-bottom: 40px;
      padding-bottom: 32px;
      border-bottom: 2px solid var(--bg-light);
    }

    .form-section:last-child {
      border-bottom: none;
    }

    .section-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
    }

    .section-icon {
      width: 48px;
      height: 48px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
    }

    .section-title {
      font-size: 22px;
      font-weight: 700;
      color: var(--text-dark);
    }

    /* Form Elements */
    .form-group {
      margin-bottom: 24px;
    }

    .form-label {
      display: block;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 10px;
      font-size: 14px;
    }

    .required {
      color: var(--danger);
      font-weight: 700;
    }

    .form-input,
    .form-select,
    .form-textarea {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid var(--border);
      border-radius: 10px;
      font-size: 15px;
      font-family: inherit;
      transition: all 0.3s;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(138, 21, 56, 0.1);
    }

    .form-textarea {
      min-height: 150px;
      resize: vertical;
      line-height: 1.6;
    }

    .form-hint {
      display: block;
      font-size: 13px;
      color: var(--text-light);
      margin-top: 6px;
      font-style: italic;
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }

    .form-row-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
    }

    .form-row-4 {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
    }

    /* Checkbox Group */
    .checkbox-group {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }

    .checkbox-group label {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 12px;
      background: var(--bg-light);
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 14px;
    }

    .checkbox-group label:hover {
      background: #e8eaf0;
    }

    .checkbox-group input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
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
      font-weight: 700;
      color: darkred;
      font-size: 15px;
    }

    .pay-input {
      padding-left: 48px !important;
    }

    /* Tags Container */
    .tags-container {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      padding: 16px;
      background: var(--bg-light);
      border-radius: 10px;
      min-height: 60px;
      margin-bottom: 12px;
    }

    .tag {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border: 2px solid var(--primary);
      color: var(--primary);
      padding: 8px 14px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      transition: all 0.3s;
    }

    .tag:hover {
      background: var(--primary);
      color: white;
    }

    .tag.selected {
      background: var(--primary);
      color: white;
    }

    .tag .remove {
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
    }

    /* Buttons */
    .btn {
      padding: 12px 24px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-sm {
      padding: 8px 16px;
      font-size: 13px;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.2);
    }

    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(138, 21, 56, 0.3);
    }

    .btn-secondary {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn-secondary:hover {
      background: var(--primary);
      color: white;
    }

    .submit-section {
      margin-top: 32px;
      padding-top: 32px;
      border-top: 2px solid var(--bg-light);
      text-align: center;
    }

    .submit-btn {
      padding: 18px 60px;
      background: linear-gradient(135deg, var(--success), #2ecc71);
      color: white;
      border: none;
      border-radius: 14px;
      font-size: 18px;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 6px 16px rgba(39, 174, 96, 0.3);
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 12px;
    }

    .submit-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(39, 174, 96, 0.4);
    }

    .char-counter {
      display: block;
      text-align: right;
      font-size: 13px;
      color: var(--text-light);
      margin-top: 6px;
    }

    .char-counter.warning { color: var(--warning); }
    .char-counter.error { color: var(--danger); font-weight: 600; }

    /* ====== GEOFENCE STYLES ====== */
    .geofence-section {
      background: var(--bg-light);
      border: 2px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      margin-top: 24px;
    }

    .geofence-toggle {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 16px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s;
      border: 2px solid var(--border);
    }

    .geofence-toggle:hover {
      border-color: var(--primary);
      background: #fdf2f4;
    }

    .toggle-switch {
      position: relative;
      width: 54px;
      height: 28px;
      background: #cbd5e1;
      border-radius: 14px;
      transition: background 0.3s;
      cursor: pointer;
    }

    .toggle-switch.active {
      background: var(--primary);
    }

    .toggle-slider {
      position: absolute;
      top: 3px;
      left: 3px;
      width: 22px;
      height: 22px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 50%;
      transition: transform 0.3s;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .toggle-switch.active .toggle-slider {
      transform: translateX(26px);
    }

    .toggle-label {
      flex: 1;
    }

    .toggle-title {
      font-weight: 700;
      font-size: 16px;
      color: var(--text-dark);
      margin-bottom: 4px;
    }

    .toggle-description {
      font-size: 13px;
      color: var(--text-medium);
    }

    .geofence-details {
      margin-top: 20px;
      display: none;
    }

    .geofence-details.active {
      display: block;
      animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .geofence-info-box {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 16px;
      display: flex;
      gap: 12px;
    }

    .geofence-info-box i {
      color: #3b82f6;
      font-size: 18px;
      margin-top: 2px;
    }

    #location-map {
      height: 400px;
      border-radius: 10px;
      margin-top: 16px;
      border: 2px solid var(--border);
    }

    .coordinates-display {
      display: flex;
      gap: 12px;
      margin-top: 12px;
    }

    .coord-box {
      flex: 1;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      padding: 12px;
      border-radius: 8px;
      border: 1px solid var(--border);
    }

    .coord-label {
      font-size: 11px;
      color: var(--text-light);
      text-transform: uppercase;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .coord-value {
      font-weight: 700;
      color: var(--text-dark);
      font-family: 'Courier New', monospace;
      font-size: 14px;
    }

    .radius-slider-container {
      margin-top: 16px;
    }

    .radius-value {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .radius-display {
      font-size: 20px;
      font-weight: 700;
      color: var(--primary);
    }

    .range-slider {
      width: 100%;
      height: 8px;
      border-radius: 4px;
      background: linear-gradient(to right, #10b981 0%, #10b981 20%, #f59e0b 20%, #f59e0b 60%, #ef4444 60%, #ef4444 100%);
      outline: none;
      -webkit-appearance: none;
    }

    .range-slider::-webkit-slider-thumb {
      -webkit-appearance: none;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: var(--primary);
      cursor: pointer;
      border: 3px solid white;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    }

    .range-slider::-moz-range-thumb {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: var(--primary);
      cursor: pointer;
      border: 3px solid white;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    }

    .range-labels {
      display: flex;
      justify-content: space-between;
      margin-top: 8px;
      font-size: 12px;
      color: var(--text-light);
    }

    /* Basic Map */
    #map {
      height: 300px;
      border-radius: 10px;
      margin-top: 12px;
      border: 2px solid var(--border);
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

      .description-actions {
        flex-direction: column;
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

      .mode-toggle {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <?php 
    $page_title = $editing ? "Edit Job" : "Post a Job";
    include 'includes/sidebar.php';
  ?>

  <div class="main-content">
    <div class="container">
      <!-- Page Header -->
      <div class="page-header">
        <h1><i class="fas fa-briefcase"></i> <?= $editing ? 'Edit Job Posting' : 'Post a New Job' ?></h1>
        <p><?= $editing ? 'Update your job details and geofence settings' : 'Find the perfect candidate for your position' ?></p>
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

      <?php if (!$editing): ?>
      <!-- Mode Toggle (only for new jobs) -->
      <div class="mode-toggle">
        <button type="button" class="mode-btn active" id="templateModeBtn">
          <i class="fas fa-list"></i> Use Template
        </button>
        <button type="button" class="mode-btn" id="manualModeBtn">
          <i class="fas fa-pen"></i> Manual Entry
        </button>
      </div>

      <!-- Info Boxes -->
      <div class="info-box" id="templateInfo">
        <div class="info-box-title"><i class="fas fa-lightbulb"></i> Quick Tip</div>
        <p class="info-box-text">Select a template to auto-fill job details, or switch to Manual Entry to create from scratch. You can save your own templates for future use!</p>
      </div>

      <div class="info-box orange" id="manualInfo" style="display:none;">
        <div class="info-box-title"><i class="fas fa-pen"></i> Manual Mode</div>
        <p class="info-box-text">Fill in all fields manually. You can save this as a custom template after posting!</p>
      </div>
      <?php endif; ?>

      <div class="card">
        <form action="backend/<?= $editing ? 'update_job.php' : 'post_job.php' ?>" method="POST" id="jobPostForm">
          <?php if ($editing): ?>
            <input type="hidden" name="job_id" value="<?= $job_data['job_id'] ?>">
          <?php endif; ?>
          
          <?php if (!$editing): ?>
          <!-- Template Selection -->
          <div class="form-section" id="templateSection">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-list"></i></span>
              <h3 class="section-title">Select Template</h3>
            </div>
            
            <div class="form-group">
              <label class="form-label" for="templateSelect">Job Template</label>
              <select id="templateSelect" class="form-select">
                <option value="">-- Select a Template or Start Fresh --</option>
                <optgroup label="System Templates">
                  <option value="Event Crew">Event Crew</option>
                  <option value="Cashier">Cashier</option>
                  <option value="Photographer">Photographer</option>
                  <option value="Barista">Barista / Cafe Crew</option>
                  <option value="Delivery Rider">Delivery Rider</option>
                  <option value="Promoter">Promoter / Brand Ambassador</option>
                  <option value="Admin Assistant">Admin Assistant / Clerk</option>
                  <option value="Waiter">Waiter / Waitress</option>
                  <option value="Kitchen Helper">Kitchen Helper</option>
                  <option value="Sales Assistant">Sales Assistant</option>
                  <option value="Security Guard">Security Guard</option>
                  <option value="Cleaner">Cleaner / Janitor</option>
                </optgroup>
                <?php if (!empty($saved_templates)): ?>
                <optgroup label="My Saved Templates">
                  <?php foreach ($saved_templates as $tpl): if (!$tpl['is_default']): ?>
                  <option value="custom_<?php echo $tpl['template_id']; ?>">
                    <?php echo htmlspecialchars($tpl['template_name']); ?>
                  </option>
                  <?php endif; endforeach; ?>
                </optgroup>
                <?php endif; ?>
              </select>
            </div>
          </div>
          <?php endif; ?>

          <!-- Basic Information -->
          <div class="form-section">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-info-circle"></i></span>
              <h3 class="section-title">Basic Information</h3>
            </div>

            <div class="form-group">
              <label class="form-label" for="title">Job Title <span class="required">*</span></label>
              <input type="text" id="title" name="title" class="form-input" 
                     placeholder="e.g., Event Crew, Cashier, Photographer" 
                     value="<?= $editing ? htmlspecialchars($job_data['title']) : '' ?>" 
                     required maxlength="100">
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="category">Job Type <span class="required">*</span></label>
                <select name="category" id="category" class="form-select" required>
                  <option value="">-- Select Job Type --</option>
                  <option value="Gig" <?= ($editing && $job_data['category']=='Gig')?'selected':'' ?>>🎯 Gig (One-time task)</option>
                  <option value="Part-Time" <?= ($editing && $job_data['category']=='Part-Time')?'selected':'' ?>>⏰ Part-Time</option>
                  <option value="Full-Time" <?= ($editing && $job_data['category']=='Full-Time')?'selected':'' ?>>💼 Full-Time</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="location">Location <span class="required">*</span></label>
                <select name="location" id="location" class="form-select" required>
                  <option value="">-- Select District --</option>
                  <?php
                  $locations = ['Beaufort','Beluran','Keningau','Kinabatangan','Kota Belud','Kota Kinabalu','Kota Marudu','Kuala Penyu','Kudat','Kunak','Lahad Datu','Nabawan','Papar','Penampang','Pitas','Ranau','Sandakan','Semporna','Sipitang','Tambunan','Tamparuli','Tawau','Telupid','Tenom','Tongod','Tuaran','Putatan','Kalabakan'];
                  foreach ($locations as $loc) {
                    $selected = ($editing && $job_data['location']==$loc) ? 'selected' : '';
                    echo "<option value=\"$loc\" $selected>$loc</option>";
                  }
                  ?>
                </select>
              </div>
            </div>

            <div class="form-row-4">
              <div class="form-group">
                <label class="form-label" for="experience_level">Experience Level</label>
                <select name="experience_level" id="experience_level" class="form-select">
                  <option value="Any" <?= ($editing && $job_data['experience_level']=='Any')?'selected':'' ?>>Any Level</option>
                  <option value="Entry" <?= ($editing && $job_data['experience_level']=='Entry')?'selected':'' ?>>Entry Level</option>
                  <option value="Mid" <?= ($editing && $job_data['experience_level']=='Mid')?'selected':'' ?>>Mid Level</option>
                  <option value="Senior" <?= ($editing && $job_data['experience_level']=='Senior')?'selected':'' ?>>Senior Level</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="positions_available">Positions <span class="required">*</span></label>
                <input type="number" id="positions_available" name="positions_available" class="form-input" 
                       value="<?= $editing ? $job_data['positions_available'] : '1' ?>" min="1" max="100" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="is_urgent">Urgency</label>
                <select name="is_urgent" id="is_urgent" class="form-select">
                  <option value="0" <?= ($editing && !$job_data['is_urgent'])?'selected':'' ?>>Normal</option>
                  <option value="1" <?= ($editing && $job_data['is_urgent'])?'selected':'' ?>>🔥 Urgent</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="auto_expire_days">Expires In</label>
                <select name="auto_expire_days" id="auto_expire_days" class="form-select">
                  <option value="7" <?= ($editing && $job_data['auto_expire_days']==7)?'selected':'' ?>>7 days</option>
                  <option value="14" <?= ($editing && $job_data['auto_expire_days']==14)?'selected':'' ?>>14 days</option>
                  <option value="30" <?= ($editing && $job_data['auto_expire_days']==30)?'selected':'' ?>>30 days</option>
                  <option value="60" <?= ($editing && $job_data['auto_expire_days']==60)?'selected':'' ?>>60 days</option>
                  <option value="90" <?= ($editing && $job_data['auto_expire_days']==90)?'selected':'' ?>>90 days</option>
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
                  <option value="Permanent" <?= ($editing && $job_data['job_duration']=='Permanent')?'selected':'' ?>>Permanent</option>
                  <option value="1 day" <?= ($editing && $job_data['job_duration']=='1 day')?'selected':'' ?>>1 day</option>
                  <option value="1 week" <?= ($editing && $job_data['job_duration']=='1 week')?'selected':'' ?>>1 week</option>
                  <option value="2 weeks" <?= ($editing && $job_data['job_duration']=='2 weeks')?'selected':'' ?>>2 weeks</option>
                  <option value="1 month" <?= ($editing && $job_data['job_duration']=='1 month')?'selected':'' ?>>1 month</option>
                  <option value="3 months" <?= ($editing && $job_data['job_duration']=='3 months')?'selected':'' ?>>3 months</option>
                  <option value="6 months" <?= ($editing && $job_data['job_duration']=='6 months')?'selected':'' ?>>6 months</option>
                  <option value="1 year" <?= ($editing && $job_data['job_duration']=='1 year')?'selected':'' ?>>1 year</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-input" 
                       value="<?= $editing ? $job_data['start_date'] : '' ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="end_date">End Date (for temporary positions)</label>
              <input type="date" id="end_date" name="end_date" class="form-input" 
                     value="<?= $editing ? $job_data['end_date'] : '' ?>">
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
                         placeholder="0.00" step="0.01" min="0" 
                         value="<?= $editing ? $job_data['pay_rate'] : '' ?>" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="pay_period">Pay Period <span class="required">*</span></label>
                <select name="pay_period" id="pay_period" class="form-select" required>
                  <option value="">-- Select --</option>
                  <option value="Per Hour" <?= ($editing && $job_data['pay_period']=='Per Hour')?'selected':'' ?>>Per Hour</option>
                  <option value="Per Day" <?= ($editing && $job_data['pay_period']=='Per Day')?'selected':'' ?>>Per Day</option>
                  <option value="Per Week" <?= ($editing && $job_data['pay_period']=='Per Week')?'selected':'' ?>>Per Week</option>
                  <option value="Per Month" <?= ($editing && $job_data['pay_period']=='Per Month')?'selected':'' ?>>Per Month</option>
                  <option value="Per Project" <?= ($editing && $job_data['pay_period']=='Per Project')?'selected':'' ?>>Per Project</option>
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
                $docs = ['Resume', 'Cover Letter', 'Portfolio', 'Certificates', 'ID Card'];
                $selected_docs = $editing ? explode(',', $job_data['required_documents']) : [];
                foreach($docs as $doc) {
                  $checked = in_array($doc, $selected_docs) ? 'checked' : '';
                  echo "<label><input type=\"checkbox\" class=\"doc-item\" value=\"$doc\" $checked> $doc</label>";
                }
                ?>
              </div>
              <input type="hidden" name="required_documents" id="requiredDocuments">
            </div>

            <div class="form-group">
              <label class="form-label">Languages Required</label>
              <div class="checkbox-group">
                <?php
                $langs = ['Bahasa Malaysia', 'English', 'Mandarin', 'Tamil', 'Other dialects'];
                $selected_langs = $editing ? explode(',', $job_data['languages_required']) : [];
                foreach($langs as $lang) {
                  $checked = in_array($lang, $selected_langs) ? 'checked' : '';
                  echo "<label><input type=\"checkbox\" class=\"lang-item\" value=\"$lang\" $checked> $lang</label>";
                }
                ?>
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
              <div class="tags-container" id="responsibilities"></div>
              <input type="hidden" name="responsibilities" id="responsibilitiesInput" 
                     value='<?= $editing ? htmlspecialchars($job_data['responsibilities']) : '[]' ?>'>
              <button type="button" class="btn btn-secondary btn-sm" id="addCustomResp">
                <i class="fas fa-plus"></i> Add Custom Responsibility
              </button>
            </div>

            <div class="form-group">
              <label class="form-label">Required Skills</label>
              <div class="tags-container" id="skills"></div>
              <input type="hidden" name="skills" id="skillsInput" 
                     value='<?= $editing ? htmlspecialchars($job_data['skills']) : '[]' ?>'>
              <button type="button" class="btn btn-secondary btn-sm" id="addCustomSkill">
                <i class="fas fa-plus"></i> Add Custom Skill
              </button>
            </div>

            <div class="form-group">
              <label class="form-label">Work Schedule</label>
              <div class="checkbox-group">
                <?php
                $schedules = ['Flexible hours', 'Weekdays only', 'Weekends only', 'Night shifts', 'Rotating shifts'];
                $selected_schedule = $editing ? explode(',', $job_data['schedule']) : [];
                foreach($schedules as $sched) {
                  $checked = in_array($sched, $selected_schedule) ? 'checked' : '';
                  echo "<label><input type=\"checkbox\" class=\"schedule-item\" value=\"$sched\" $checked> $sched</label>";
                }
                ?>
              </div>
              <input type="hidden" name="schedule" id="scheduleInput">
            </div>

            <div class="form-group">
              <label class="form-label">Additional Benefits</label>
              <div class="checkbox-group">
                <?php
                $benefits = ['Free meals', 'Transportation allowance', 'Training provided', 'Performance bonus', 'EPF/SOCSO', 'Medical coverage'];
                $selected_benefits = $editing ? explode(',', $job_data['benefits']) : [];
                foreach($benefits as $benefit) {
                  $checked = in_array($benefit, $selected_benefits) ? 'checked' : '';
                  echo "<label><input type=\"checkbox\" class=\"benefit-item\" value=\"$benefit\" $checked> $benefit</label>";
                }
                ?>
              </div>
              <input type="hidden" name="benefits" id="benefitsInput">
            </div>

            <div class="form-group">
              <label class="form-label" for="description">Job Description <span class="required">*</span></label>
              <textarea id="description" name="description" class="form-textarea" 
                        placeholder="Provide a detailed description of the job..." 
                        maxlength="2000" required><?= $editing ? htmlspecialchars($job_data['description']) : '' ?></textarea>
              <span class="char-counter" id="charCounter">0 / 2000</span>
              <span class="form-hint">💡 Tip: Select a template above to auto-generate a professional description</span>
            </div>
          </div>

          <!-- Location & Attendance Tracking -->
          <div class="form-section">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-map-marker-alt"></i></span>
              <h3 class="section-title">Location & Attendance Tracking</h3>
            </div>

            <!-- Basic Location (Optional) -->
            <div class="form-group">
              <label class="form-label">General Location Marker (Optional)</label>
              <p class="form-hint">Click on the map to mark the general area for this job</p>
              <div id="map"></div>
              <input type="hidden" name="latitude" id="latitude" value="<?= $editing ? $job_data['latitude'] : '' ?>">
              <input type="hidden" name="longitude" id="longitude" value="<?= $editing ? $job_data['longitude'] : '' ?>">
              <button type="button" class="btn btn-secondary btn-sm" onclick="clearMap()" style="margin-top: 10px;">
                <i class="fas fa-times"></i> Clear Marker
              </button>
            </div>

            <!-- Geofence Section -->
            <div class="geofence-section">
              <div class="geofence-toggle" onclick="toggleGeofence()">
                <div class="toggle-switch <?= ($editing && $job_data['require_onsite_attendance']) ? 'active' : '' ?>" id="geofence-switch">
                  <div class="toggle-slider"></div>
                </div>
                <div class="toggle-label">
                  <div class="toggle-title">🎯 Require On-Site Attendance</div>
                  <div class="toggle-description">
                    Workers must be at the job location to clock in/out
                  </div>
                </div>
              </div>

              <input type="hidden" name="require_onsite_attendance" id="geofence-input" 
                     value="<?= ($editing && $job_data['require_onsite_attendance']) ? '1' : '0' ?>">

              <div class="geofence-details <?= ($editing && $job_data['require_onsite_attendance']) ? 'active' : '' ?>" id="geofence-details">
                
                <div class="geofence-info-box">
                  <i class="fas fa-info-circle"></i>
                  <div class="info-content">
                    <strong>How it works:</strong> Click on the map below to set the exact job location. 
                    Workers will need to be within the specified radius to clock in/out. This ensures 
                    they're actually at the work site.
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">
                    Job Location <span class="required">*</span>
                  </label>
                  <div id="location-map"></div>
                  <div class="coordinates-display">
                    <div class="coord-box">
                      <div class="coord-label">Latitude</div>
                      <div class="coord-value" id="lat-display">
                        <?= ($editing && $job_data['job_latitude']) ? $job_data['job_latitude'] : 'Not set' ?>
                      </div>
                    </div>
                    <div class="coord-box">
                      <div class="coord-label">Longitude</div>
                      <div class="coord-value" id="lng-display">
                        <?= ($editing && $job_data['job_longitude']) ? $job_data['job_longitude'] : 'Not set' ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="radius-slider-container">
                  <div class="radius-value">
                    <label class="form-label">Geofence Radius</label>
                    <span class="radius-display" id="radius-display">
                      <?= ($editing && $job_data['geofence_radius']) ? $job_data['geofence_radius'] : 100 ?>m
                    </span>
                  </div>
                  <input type="range" id="radius-slider" name="geofence_radius" 
                         class="range-slider" min="50" max="500" step="10"
                         value="<?= ($editing && $job_data['geofence_radius']) ? $job_data['geofence_radius'] : 100 ?>"
                         oninput="updateRadius(this.value)">
                  <div class="range-labels">
                    <span>50m (Strict)</span>
                    <span>500m (Flexible)</span>
                  </div>
                </div>

                <input type="hidden" name="job_latitude" id="job-lat" 
                       value="<?= ($editing && $job_data['job_latitude']) ? $job_data['job_latitude'] : '' ?>">
                <input type="hidden" name="job_longitude" id="job-lng" 
                       value="<?= ($editing && $job_data['job_longitude']) ? $job_data['job_longitude'] : '' ?>">
              </div>
            </div>
          </div>

          <?php if (!$editing): ?>
          <!-- Save as Template -->
          <div class="form-section">
            <div class="section-header">
              <span class="section-icon"><i class="fas fa-save"></i></span>
              <h3 class="section-title">Save as Template (Optional)</h3>
            </div>
            
            <div class="form-group">
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="save_as_template" name="save_as_template" value="1" style="width:auto;">
                <span class="form-label" style="margin: 0;">Save this job posting as a template for future use</span>
              </label>
            </div>
            
            <div class="form-group" id="templateNameGroup" style="display:none;">
              <label class="form-label" for="template_name">Template Name</label>
              <input type="text" id="template_name" name="template_name" class="form-input" 
                     placeholder="e.g., My Event Crew Template" maxlength="100">
            </div>
          </div>
          <?php endif; ?>

          <!-- Submit Button -->
          <div class="submit-section">
            <button type="submit" class="submit-btn" id="submitBtn">
              <i class="fas fa-paper-plane"></i> <?= $editing ? 'Update Job' : 'Post Job' ?>
            </button>
          </div>

        </form>
      </div>
    </div>
  </div>

  <script>
    // ===== JOB TEMPLATES DATA =====
    const jobTemplates = {
      'Event Crew': {
        responsibilities: ['Set up and dismantle event equipment','Assist with crowd management','Coordinate with event organizers','Ensure safety protocols are followed','Handle registration and guest assistance','Manage event logistics and materials'],
        skills: ['Physical stamina','Teamwork','Communication skills','Time management','Problem solving','Customer service'],
        suggestedPayPeriod: 'Per Day'
      },
      'Cashier': {
        responsibilities: ['Handle cash and card transactions','Process customer purchases accurately','Provide excellent customer service','Maintain cleanliness of checkout area','Balance cash register at end of shift','Answer customer inquiries'],
        skills: ['Cash handling','Customer service','Attention to detail','Basic math skills','POS system operation','Communication skills'],
        suggestedPayPeriod: 'Per Hour'
      },
      'Photographer': {
        responsibilities: ['Capture high-quality photos at events','Edit and retouch images','Set up lighting and equipment','Coordinate with clients on photo requirements','Deliver final images on time','Maintain photography equipment'],
        skills: ['Photography skills','Photo editing (Photoshop/Lightroom)','Creativity','Attention to detail','Customer service','Equipment handling'],
        suggestedPayPeriod: 'Per Project'
      },
      'Barista': {
        responsibilities: ['Prepare coffee and beverages','Maintain cleanliness of work area','Handle customer orders','Operate coffee machines','Stock inventory','Provide excellent customer service'],
        skills: ['Coffee making','Customer service','Multitasking','Attention to detail','Cash handling','Food safety knowledge'],
        suggestedPayPeriod: 'Per Hour'
      },
      'Delivery Rider': {
        responsibilities: ['Deliver orders to customers on time','Handle deliveries safely','Maintain delivery vehicle','Collect payments (if required)','Navigate using GPS','Provide courteous customer service'],
        skills: ['Driving/Riding license','Navigation skills','Time management','Physical fitness','Customer service','Safety awareness'],
        suggestedPayPeriod: 'Per Day'
      },
      'Promoter': {
        responsibilities: ['Promote products/services to potential customers','Engage with customers and answer questions','Distribute promotional materials','Maintain brand presentation standards','Achieve sales or engagement targets','Report daily activities'],
        skills: ['Communication skills','Persuasion','Product knowledge','Customer engagement','Presentation skills','Target-oriented'],
        suggestedPayPeriod: 'Per Day'
      },
      'Admin Assistant': {
        responsibilities: ['Manage office correspondence and files','Schedule appointments and meetings','Prepare documents and reports','Handle phone calls and emails','Assist with administrative tasks','Maintain office supplies'],
        skills: ['Microsoft Office','Organization','Time management','Communication','Attention to detail','Multitasking'],
        suggestedPayPeriod: 'Per Month'
      },
      'Waiter': {
        responsibilities: ['Take customer orders accurately','Serve food and beverages','Provide excellent customer service','Handle payments','Maintain cleanliness of dining area','Assist with table setup'],
        skills: ['Customer service','Communication','Multitasking','Attention to detail','Teamwork','Cash handling'],
        suggestedPayPeriod: 'Per Hour'
      },
      'Kitchen Helper': {
        responsibilities: ['Assist chefs with food preparation','Maintain kitchen cleanliness','Wash dishes and utensils','Stock ingredients and supplies','Follow food safety standards','Support cooking operations'],
        skills: ['Food safety knowledge','Physical stamina','Teamwork','Attention to detail','Time management','Reliability'],
        suggestedPayPeriod: 'Per Hour'
      },
      'Sales Assistant': {
        responsibilities: ['Assist customers with purchases','Provide product information','Process transactions','Maintain store appearance','Stock shelves','Achieve sales targets'],
        skills: ['Customer service','Product knowledge','Communication','Sales techniques','Cash handling','Teamwork'],
        suggestedPayPeriod: 'Per Hour'
      },
      'Security Guard': {
        responsibilities: ['Monitor premises for security','Control access to buildings','Patrol assigned areas','Report incidents','Respond to emergencies','Maintain security logs'],
        skills: ['Vigilance','Physical fitness','Communication','Problem solving','First aid knowledge','Reliability'],
        suggestedPayPeriod: 'Per Day'
      },
      'Cleaner': {
        responsibilities: ['Clean and sanitize assigned areas','Empty trash bins','Restock supplies','Report maintenance issues','Follow cleaning schedules','Maintain equipment'],
        skills: ['Attention to detail','Physical stamina','Time management','Reliability','Safety awareness','Teamwork'],
        suggestedPayPeriod: 'Per Hour'
      }
    };

    // ===== MODE TOGGLE =====
    <?php if (!$editing): ?>
    const templateModeBtn = document.getElementById('templateModeBtn');
    const manualModeBtn = document.getElementById('manualModeBtn');
    const templateSection = document.getElementById('templateSection');
    const templateInfo = document.getElementById('templateInfo');
    const manualInfo = document.getElementById('manualInfo');

    templateModeBtn.addEventListener('click', function() {
      templateModeBtn.classList.add('active');
      manualModeBtn.classList.remove('active');
      templateSection.style.display = 'block';
      templateInfo.style.display = 'block';
      manualInfo.style.display = 'none';
    });

    manualModeBtn.addEventListener('click', function() {
      manualModeBtn.classList.add('active');
      templateModeBtn.classList.remove('active');
      templateSection.style.display = 'none';
      templateInfo.style.display = 'none';
      manualInfo.style.display = 'block';
    });
    <?php endif; ?>

    // ===== AUTO-GENERATE DESCRIPTION FROM TEMPLATE =====
    function generateJobDescription(title, category, template) {
      const jobTypeEmoji = {
        'Gig': '🎯',
        'Part-Time': '⏰',
        'Full-Time': '💼'
      };
      
      const emoji = jobTypeEmoji[category] || '📋';
      
      let description = `📌 Position: ${title}\n`;

      
      // Add responsibilities
      if (template.responsibilities && template.responsibilities.length > 0) {
        description += `🎯 KEY RESPONSIBILITIES:\n`;
        template.responsibilities.forEach(resp => {
          description += `• ${resp}\n`;
        });
        description += `\n`;
      }
      
      // Add skills
      if (template.skills && template.skills.length > 0) {
        description += `✅ REQUIRED SKILLS:\n`;
        template.skills.forEach(skill => {
          description += `• ${skill}\n`;
        });
        description += `\n`;
      }
      
      description += `📧 Interested candidates, please apply now!`;
      
      return description;
    }

    // ===== TEMPLATE SELECTION =====
    <?php if (!$editing): ?>
    document.getElementById('templateSelect').addEventListener('change', function() {
      const selectedTemplate = this.value;
      
      if (selectedTemplate.startsWith('custom_')) {
        // Load custom template
        const templateId = selectedTemplate.replace('custom_', '');
        const customTemplates = <?php echo json_encode($saved_templates); ?>;
        const template = customTemplates.find(t => t.template_id == templateId);
        
        if (template) {
          document.getElementById('title').value = template.title || '';
          document.getElementById('category').value = template.category || '';
          document.getElementById('location').value = template.location || '';
          document.getElementById('pay_rate').value = template.pay_rate || '';
          document.getElementById('pay_period').value = template.pay_period || '';
          document.getElementById('description').value = template.description || '';
          document.getElementById('job_duration').value = template.job_duration || 'Permanent';
          
          if (template.responsibilities) {
            try {
              const respArray = JSON.parse(template.responsibilities);
              responsibilitiesList = respArray;
              renderResponsibilities();
            } catch(e) {}
          }
          
          if (template.skills) {
            try {
              const skillsArray = JSON.parse(template.skills);
              skillsList = skillsArray;
              renderSkills();
            } catch(e) {}
          }
          
          updateCharCount();
        }
      } else if (selectedTemplate && jobTemplates[selectedTemplate]) {
        // Load system template
        const template = jobTemplates[selectedTemplate];
        const title = selectedTemplate;
        const category = document.getElementById('category').value || 'Gig';
        
        document.getElementById('title').value = title;
        
        if (template.suggestedPayPeriod) {
          document.getElementById('pay_period').value = template.suggestedPayPeriod;
        }
        
        responsibilitiesList = template.responsibilities || [];
        skillsList = template.skills || [];
        
        renderResponsibilities();
        renderSkills();
        
        // AUTO-GENERATE DESCRIPTION
        const description = generateJobDescription(title, category, template);
        document.getElementById('description').value = description;
        updateCharCount();
      }
    });
    <?php endif; ?>

    // ===== RESPONSIBILITIES & SKILLS =====
    let responsibilitiesList = <?= $editing ? $job_data['responsibilities'] : '[]' ?>;
    let skillsList = <?= $editing ? $job_data['skills'] : '[]' ?>;

    function renderResponsibilities() {
      const container = document.getElementById('responsibilities');
      container.innerHTML = '';
      responsibilitiesList.forEach((resp, index) => {
        const tag = document.createElement('div');
        tag.className = 'tag selected';
        tag.innerHTML = `${resp} <span class="remove" onclick="removeResponsibility(${index})">×</span>`;
        container.appendChild(tag);
      });
      document.getElementById('responsibilitiesInput').value = JSON.stringify(responsibilitiesList);
    }

    function renderSkills() {
      const container = document.getElementById('skills');
      container.innerHTML = '';
      skillsList.forEach((skill, index) => {
        const tag = document.createElement('div');
        tag.className = 'tag selected';
        tag.innerHTML = `${skill} <span class="remove" onclick="removeSkill(${index})">×</span>`;
        container.appendChild(tag);
      });
      document.getElementById('skillsInput').value = JSON.stringify(skillsList);
    }

    function removeResponsibility(index) {
      responsibilitiesList.splice(index, 1);
      renderResponsibilities();
    }

    function removeSkill(index) {
      skillsList.splice(index, 1);
      renderSkills();
    }

    document.getElementById('addCustomResp').addEventListener('click', function() {
      const resp = prompt('Enter a responsibility:');
      if (resp && resp.trim()) {
        responsibilitiesList.push(resp.trim());
        renderResponsibilities();
      }
    });

    document.getElementById('addCustomSkill').addEventListener('click', function() {
      const skill = prompt('Enter a skill:');
      if (skill && skill.trim()) {
        skillsList.push(skill.trim());
        renderSkills();
      }
    });

    // Initialize if editing
    <?php if ($editing): ?>
    renderResponsibilities();
    renderSkills();
    <?php endif; ?>

    // ===== CHECKBOXES TO HIDDEN INPUTS =====
    function updateHiddenInputs() {
      // Documents
      const docs = Array.from(document.querySelectorAll('.doc-item:checked')).map(cb => cb.value);
      document.getElementById('requiredDocuments').value = docs.join(',');
      
      // Languages
      const langs = Array.from(document.querySelectorAll('.lang-item:checked')).map(cb => cb.value);
      document.getElementById('languages_required').value = langs.join(',');
      
      // Schedule
      const schedule = Array.from(document.querySelectorAll('.schedule-item:checked')).map(cb => cb.value);
      document.getElementById('scheduleInput').value = schedule.join(',');
      
      // Benefits
      const benefits = Array.from(document.querySelectorAll('.benefit-item:checked')).map(cb => cb.value);
      document.getElementById('benefitsInput').value = benefits.join(',');
    }

    document.querySelectorAll('.doc-item, .lang-item, .schedule-item, .benefit-item').forEach(checkbox => {
      checkbox.addEventListener('change', updateHiddenInputs);
    });

    // Initialize on load
    updateHiddenInputs();

    // ===== CHARACTER COUNTER =====
    const descTextarea = document.getElementById('description');
    const charCounter = document.getElementById('charCounter');

    function updateCharCount() {
      const length = descTextarea.value.length;
      charCounter.textContent = `${length} / 2000`;
      
      if (length > 1800) {
        charCounter.classList.add('error');
        charCounter.classList.remove('warning');
      } else if (length > 1500) {
        charCounter.classList.add('warning');
        charCounter.classList.remove('error');
      } else {
        charCounter.classList.remove('warning', 'error');
      }
    }

    descTextarea.addEventListener('input', updateCharCount);
    updateCharCount();

    // ===== SAVE AS TEMPLATE TOGGLE =====
    <?php if (!$editing): ?>
    document.getElementById('save_as_template').addEventListener('change', function() {
      document.getElementById('templateNameGroup').style.display = this.checked ? 'block' : 'none';
    });
    <?php endif; ?>

    // ===== BASIC MAP (GENERAL LOCATION) =====
    let basicMap = null;
    let basicMarker = null;

    function initBasicMap() {
      const defaultCenter = [5.9804, 116.0735]; // Sabah center
      basicMap = L.map('map').setView(defaultCenter, 10);
      
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
      }).addTo(basicMap);
      
      <?php if ($editing && $job_data['latitude'] && $job_data['longitude']): ?>
      basicMarker = L.marker([<?= $job_data['latitude'] ?>, <?= $job_data['longitude'] ?>]).addTo(basicMap);
      basicMap.setView([<?= $job_data['latitude'] ?>, <?= $job_data['longitude'] ?>], 13);
      <?php endif; ?>
      
      basicMap.on('click', function(e) {
        if (basicMarker) {
          basicMap.removeLayer(basicMarker);
        }
        basicMarker = L.marker(e.latlng).addTo(basicMap);
        document.getElementById('latitude').value = e.latlng.lat.toFixed(8);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(8);
      });
    }

    function clearMap() {
      if (basicMarker) {
        basicMap.removeLayer(basicMarker);
        basicMarker = null;
      }
      document.getElementById('latitude').value = '';
      document.getElementById('longitude').value = '';
    }

    // Initialize basic map on load
    window.addEventListener('load', function() {
      initBasicMap();
    });

    // ===== GEOFENCE FUNCTIONALITY =====
    let geofenceMap = null;
    let geofenceMarker = null;
    let geofenceCircle = null;
    let jobLat = <?= ($editing && $job_data['job_latitude']) ? $job_data['job_latitude'] : 'null' ?>;
    let jobLng = <?= ($editing && $job_data['job_longitude']) ? $job_data['job_longitude'] : 'null' ?>;

    function initGeofenceMap() {
      const defaultCenter = jobLat && jobLng ? [jobLat, jobLng] : [5.9804, 116.0735];
      
      geofenceMap = L.map('location-map').setView(defaultCenter, jobLat && jobLng ? 15 : 10);
      
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
      }).addTo(geofenceMap);
      
      if (jobLat && jobLng) {
        addGeofenceMarker(jobLat, jobLng);
      }
      
      geofenceMap.on('click', function(e) {
        addGeofenceMarker(e.latlng.lat, e.latlng.lng);
      });
    }

    function addGeofenceMarker(lat, lng) {
      if (geofenceMarker) {
        geofenceMap.removeLayer(geofenceMarker);
      }
      if (geofenceCircle) {
        geofenceMap.removeLayer(geofenceCircle);
      }
      
      const customIcon = L.divIcon({
        className: 'custom-marker',
        html: `<div style="background: #8a1538; width: 40px; height: 40px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.4);"><i class="fas fa-briefcase" style="color: white; transform: rotate(45deg); font-size: 18px;"></i></div>`,
        iconSize: [40, 40],
        iconAnchor: [20, 40]
      });
      
      geofenceMarker = L.marker([lat, lng], { icon: customIcon }).addTo(geofenceMap);
      geofenceMarker.bindPopup('<strong>Job Location</strong>').openPopup();
      
      const radius = parseInt(document.getElementById('radius-slider').value);
      geofenceCircle = L.circle([lat, lng], {
        radius: radius,
        color: '#3b82f6',
        fillColor: '#3b82f6',
        fillOpacity: 0.2,
        weight: 2
      }).addTo(geofenceMap);
      
      document.getElementById('job-lat').value = lat.toFixed(8);
      document.getElementById('job-lng').value = lng.toFixed(8);
      document.getElementById('lat-display').textContent = lat.toFixed(8);
      document.getElementById('lng-display').textContent = lng.toFixed(8);
      
      jobLat = lat;
      jobLng = lng;
      
      geofenceMap.fitBounds(geofenceCircle.getBounds());
    }

    function updateRadius(value) {
      document.getElementById('radius-display').textContent = value + 'm';
      
      if (geofenceCircle && jobLat && jobLng) {
        geofenceMap.removeLayer(geofenceCircle);
        geofenceCircle = L.circle([jobLat, jobLng], {
          radius: parseInt(value),
          color: '#3b82f6',
          fillColor: '#3b82f6',
          fillOpacity: 0.2,
          weight: 2
        }).addTo(geofenceMap);
      }
    }

    function toggleGeofence() {
      const switchElem = document.getElementById('geofence-switch');
      const detailsElem = document.getElementById('geofence-details');
      const inputElem = document.getElementById('geofence-input');
      
      switchElem.classList.toggle('active');
      detailsElem.classList.toggle('active');
      
      if (switchElem.classList.contains('active')) {
        inputElem.value = '1';
        setTimeout(() => {
          if (!geofenceMap) {
            initGeofenceMap();
          } else {
            geofenceMap.invalidateSize();
          }
        }, 100);
      } else {
        inputElem.value = '0';
      }
    }

    // Initialize geofence map if editing with geofence enabled
    <?php if ($editing && $job_data['require_onsite_attendance']): ?>
      window.addEventListener('load', function() {
        initGeofenceMap();
      });
    <?php endif; ?>

    // ===== FORM VALIDATION =====
    document.getElementById('jobPostForm').addEventListener('submit', function(e) {
      const geofenceEnabled = document.getElementById('geofence-input').value === '1';
      
      if (geofenceEnabled) {
        const lat = document.getElementById('job-lat').value;
        const lng = document.getElementById('job-lng').value;
        
        if (!lat || !lng) {
          e.preventDefault();
          alert('Please click on the map to set the job location for geofence tracking.');
          return false;
        }
      }
      
      // Update all hidden inputs before submit
      updateHiddenInputs();
    });
  </script>
</body>
</html>