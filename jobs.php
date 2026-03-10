<?php
session_start();
include("backend/db_connect.php");

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? null;

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    
    // Get user info
    $user_sql = "SELECT p.name, u.email FROM users u LEFT JOIN profiles p ON u.user_id = p.user_id WHERE u.user_id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_name = $user_data['name'] ?? explode('@', $user_data['email'])[0];
    
    // Get unread messages count
    $unread_query = "SELECT COUNT(*) as unread FROM messages WHERE receiver_id = ? AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param("i", $user_id);
    $unread_stmt->execute();
    $unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'];
}

// Get search, category, and location filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';

// Base query
$sql = "SELECT j.job_id, j.title, j.category, j.location, j.pay_rate, j.description, j.created_at, j.latitude, j.longitude, j.employer_id,
               u.email AS employer_email, p.name AS employer_name, p.photo_url AS employer_photo
        FROM jobs j
        JOIN users u ON j.employer_id = u.user_id
        LEFT JOIN profiles p ON j.employer_id = p.user_id
        WHERE j.status = 'available'";

$params = [];
$types = '';

if (!empty($search)) {
  $sql .= " AND (j.title LIKE ? OR j.location LIKE ? OR j.description LIKE ?)";
  $like = "%{$search}%";
  $params = array_merge($params, [$like, $like, $like]);
  $types .= 'sss';
}

if (!empty($category)) {
  $sql .= " AND j.category = ?";
  $params[] = $category;
  $types .= 's';
}

if (!empty($location)) {
  $sql .= " AND j.location = ?";
  $params[] = $location;
  $types .= 's';
}

$sql .= " ORDER BY j.created_at DESC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$jobs_array = [];
while ($row = $result->fetch_assoc()) {
  $jobs_array[] = $row;
}
$total_jobs = count($jobs_array);

$categories = ["Gig", "Part-Time", "Full-Time"];
$sabah_districts = ["Beaufort", "Beluran", "Keningau", "Kinabatangan", "Kota Belud", "Kota Kinabalu", "Kota Marudu", "Kuala Penyu", "Kudat", "Kunak", "Lahad Datu", "Nabawan", "Papar", "Penampang", "Pitas", "Ranau", "Sandakan", "Semporna", "Sipitang", "Tambunan", "Tamparuli", "Tawau", "Telupid", "Tenom", "Tongod", "Tuaran", "Putatan", "Kalabakan"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find Jobs | POP!Work</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <?php if ($is_logged_in): ?>
    <link rel="stylesheet" href="assets/css/sidebar.css">
  <?php else: ?>
    <link rel="stylesheet" href="assets/css/header.css">
  <?php endif; ?>

  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    /* Photo Background Like Other Pages */
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f0f2f5;
      color: #1f2937;
      position: relative;
      min-height: 100vh;
    }

    /* Only disable scroll for logged-in users (they have split-view layout) */
    body.logged-in {
      height: 100vh;
      overflow: hidden;
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

    /* Layout Wrapper */
    .content-wrapper {
        display: flex;
        flex-direction: column;
        transition: margin-left 0.3s;
        position: relative;
        z-index: 1;
        min-height: 100vh;
    }

    /* Only set fixed height for logged-in users with sidebar */
    .content-wrapper.with-sidebar {
        height: 100vh;
        margin-left: 270px;
        width: calc(100% - 270px);
    }
    
    @media (max-width: 768px) {
        .content-wrapper.with-sidebar {
            margin-left: 0;
            width: 100%;
        }
    }

    .job-page-container {
      display: flex;
      flex-direction: column;
      background: transparent;
    }

    /* For logged-in users: fixed height container */
    .content-wrapper.with-sidebar .job-page-container {
      height: 100%;
    }

    /* For unlogged users: allow natural height */
    .content-wrapper:not(.with-sidebar) .job-page-container {
      min-height: 100vh;
    }

    /* --- HERO HEADER --- */
    .hero-search {
      background: linear-gradient(135deg, #8a1538 0%, #a61e47 100%);
      color: white;
      padding: 15px 20px;
      flex-shrink: 0; /* Prevent header from shrinking */
      z-index: 10;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .hero-search-content {
      max-width: 1400px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 15px;
    }

    .hero-title { font-size: 20px; font-weight: 700; margin: 0; line-height: 1.2; }
    .hero-subtitle { font-size: 12px; opacity: 0.9; margin: 0; }

    /* Compact Search Bar */
    .search-form {
      flex: 1;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(5px);
      border-radius: 8px;
      padding: 6px;
      display: flex;
      gap: 8px;
      align-items: center;
      border: 1px solid rgba(255,255,255,0.2);
    }

    .search-input, .search-select {
      padding: 6px 10px;
      border: 1px solid rgba(255,255,255,0.8);
      border-radius: 6px;
      font-size: 13px;
      background: white;
      height: 34px;
    }
    .search-input { flex: 2; }
    .search-select { flex: 1; }
    .search-btn {
      background: white; color: #8a1538; border: none; padding: 0 15px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; height: 34px;
    }

    /* --- SPLIT VIEW CONTAINER --- */
    .split-view-container {
      flex: 1; /* Take up remaining height */
      padding: 20px;
      overflow: hidden; /* Prevent page scroll */
      max-width: 1400px;
      margin: 0 auto;
      width: 100%;
    }

    .split-view-wrapper {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
      display: grid;
      grid-template-columns: 350px 1fr;
      height: 100%; /* Fill the container */
      overflow: hidden; /* Contain inner scrolls */
      border: 1px solid #e5e7eb;
    }

    /* --- LEFT PANEL: JOB LIST --- */
    .jobs-list-panel {
      border-right: 1px solid #e5e7eb;
      display: flex;
      flex-direction: column;
      height: 100%;
      background: #fff;
      overflow: hidden; /* Important for child scrolling */
    }

    .list-header {
      padding: 12px 15px;
      background: #f9fafb;
      border-bottom: 1px solid #e5e7eb;
      font-size: 13px;
      font-weight: 600;
      color: #4b5563;
      flex-shrink: 0;
    }

    /* SCROLLABLE LIST AREA */
    .jobs-list { 
      flex: 1; 
      overflow-y: auto; /* Enable Scroll */
      min-height: 0;    /* Fix for flex item scrolling */
    }

    .job-card {
      padding: 15px;
      border-bottom: 1px solid #f3f4f6;
      cursor: pointer;
      transition: background 0.2s;
    }
    .job-card:hover { background: #f9fafb; }
    .job-card.active { background: #fff1f2; border-left: 3px solid #8a1538; }

    .job-card-title { font-size: 14px; font-weight: 600; color: #111; margin-bottom: 4px; }
    .job-card-meta { font-size: 11px; color: #666; margin-bottom: 6px; display: flex; justify-content: space-between; }
    .job-card-price { font-size: 13px; font-weight: 700; color: #8a1538; }

    .badge { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
    .badge-gig { background: #e0f2fe; color: #0369a1; }
    .badge-part { background: #fef3c7; color: #b45309; }
    .badge-full { background: #dcfce7; color: #15803d; }

    /* --- RIGHT PANEL: DETAILS --- */
    .job-details-panel {
      height: 100%;
      overflow-y: auto; /* Enable Scroll */
      padding: 30px;
      background: #fff;
    }

    /* Responsive */
    @media (max-width: 900px) {
        .hero-search-content { flex-direction: column; align-items: stretch; }
        .split-view-wrapper { grid-template-columns: 1fr; grid-template-rows: 40% 60%; }
        .jobs-list-panel { border-right: none; border-bottom: 1px solid #e5e7eb; }
        /* On mobile allow full page scroll instead of split panes */
        body { overflow: auto; height: auto; }
        .content-wrapper { height: auto; }
        .split-view-container { height: auto; padding: 10px; }
        .split-view-wrapper { height: auto; display: block; }
        .jobs-list-panel { height: 300px; }
        .job-details-panel { height: auto; min-height: 500px; }
    }
  </style>
</head>
<body<?php if ($is_logged_in) echo " class=\"logged-in\""; ?>>

  <?php if ($is_logged_in): ?>
      <?php include 'includes/sidebar.php'; ?>
      <div class="content-wrapper with-sidebar">
  <?php else: ?>
        <?php include 'includes/header.php'; ?>
        <div class="content-wrapper">
    <?php endif; ?>

      <div class="job-page-container">
        
        <div class="hero-search">
          <div class="hero-search-content">
            <div class="hero-text">
              <h1 class="hero-title">Find Jobs</h1>
              <p class="hero-subtitle"><?= $total_jobs ?> listings</p>
            </div>
            
            <form class="search-form" method="GET" action="jobs.php">
              <input type="text" name="search" class="search-input" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
              <select name="location" class="search-select">
                <option value="">Location</option>
                <?php foreach($sabah_districts as $dist): ?>
                  <option value="<?= $dist ?>" <?= $location === $dist ? 'selected' : '' ?>><?= $dist ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
            </form>
          </div>
        </div>

        <div class="split-view-container">
          <div class="split-view-wrapper">
            
            <div class="jobs-list-panel">
              <div class="list-header"><?= $total_jobs ?> Jobs Found</div>
              <div class="jobs-list">
                <?php if (count($jobs_array) > 0): ?>
                  <?php foreach ($jobs_array as $index => $job): ?>
                    <div class="job-card <?= $index === 0 ? 'active' : '' ?>" onclick="showJobDetails(<?= $index ?>)">
                      <div class="job-card-title"><?= htmlspecialchars($job['title']) ?></div>
                      <div class="job-card-meta">
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($job['location']) ?></span>
                        <span><?= date('M d', strtotime($job['created_at'])) ?></span>
                      </div>
                      <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span class="badge badge-<?= strtolower(explode('-', $job['category'])[0]) ?>">
                          <?= htmlspecialchars($job['category']) ?>
                        </span>
                        <div class="job-card-price">RM <?= number_format($job['pay_rate'], 2) ?>/hr</div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div style="padding:40px; text-align:center; color:#999; font-size:13px;">No jobs found.</div>
                <?php endif; ?>
              </div>
            </div>

            <div class="job-details-panel" id="job-details-view">
              <div style="text-align:center; padding-top:100px; color:#ccc;">
                <i class="fas fa-spinner fa-spin" style="font-size:30px;"></i>
              </div>
            </div>

          </div>
        </div>
      </div>

  </div> <script>
    const jobs = <?= json_encode($jobs_array) ?>;
    const isUserLoggedIn = <?= json_encode($is_logged_in) ?>;
    let currentJobMap = null;

    if (jobs.length > 0) showJobDetails(0);

function showJobDetails(index) {
  const job = jobs[index];
  const container = document.getElementById('job-details-view');
  
  document.querySelectorAll('.job-card').forEach((el, i) => {
    el.classList.toggle('active', i === index);
  });

  container.innerHTML = `
    <div style="margin-bottom:20px;">
      <h1 style="font-size:22px; font-weight:700; color:#1f2937; margin-bottom:5px;">${job.title}</h1>
      <div style="color:#6b7280; font-size:13px; margin-bottom:20px; display:flex; gap:15px;">
         <span><i class="fas fa-map-marker-alt"></i> ${job.location}</span>
         <span><i class="fas fa-clock"></i> Posted ${new Date(job.created_at).toLocaleDateString()}</span>
      </div>

      <div style="background:#f9fafb; padding:15px; border-radius:10px; display:flex; justify-content:space-between; align-items:center; border:1px solid #e5e7eb; margin-bottom:25px;">
        <div>
          <div style="font-size:11px; text-transform:uppercase; color:#6b7280; font-weight:600;">Hourly Rate</div>
          <div style="font-size:20px; font-weight:700; color:#8a1538;">RM ${parseFloat(job.pay_rate).toFixed(2)}<span style="font-size:12px; color:#666;">/hr</span></div>
        </div>
        ${isUserLoggedIn ? 
          `<form method="POST" action="backend/apply_job.php" style="display:inline; margin:0;">
             <input type="hidden" name="job_id" value="${job.job_id}">
             <button type="submit" style="background:linear-gradient(135deg, #8a1538 0%, #6d1029 100%); color:white; padding:10px 24px; border-radius:8px; border:none; font-size:14px; font-weight:600; cursor:pointer; font-family:'Poppins', sans-serif; transition: all 0.3s; box-shadow: 0 2px 4px rgba(138, 21, 56, 0.2);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(138, 21, 56, 0.3)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(138, 21, 56, 0.2)';">
               <i class="fas fa-paper-plane"></i> Apply Now
             </button>
           </form>` : 
          `<div style="text-align:center;">
             <a href="login.html" style="background:#374151; color:white; padding:10px 24px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; display:inline-block; transition: all 0.3s;">
               <i class="fas fa-sign-in-alt"></i> Login to Apply
             </a>
             <div style="font-size:11px; color:#6b7280; margin-top:6px;">Don't have an account? <a href="signup.html" style="color:#8a1538; font-weight:600;">Sign up</a></div>
           </div>`
        }
      </div>

      <h3 style="font-size:15px; font-weight:600; margin-bottom:8px; color:#1f2937;">Job Description</h3>
      <p style="font-size:14px; line-height:1.6; color:#4b5563; margin-bottom:30px; white-space: pre-line;">${job.description}</p>
      
<div style="border-top:1px solid #e5e7eb; padding-top:20px;">
  <h3 style="font-size:14px; font-weight:600; margin-bottom:12px; color:#1f2937;">About the Employer</h3>
  
  <!-- Clickable Employer Card -->
  <a href="backend/view_profile.php?user_id=${job.employer_id}" 
     style="display:flex; align-items:center; gap:12px; text-decoration:none; padding:12px; border-radius:12px; transition:all 0.2s ease; background:transparent;"
     onmouseover="this.style.background='#f9fafb'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';"
     onmouseout="this.style.background='transparent'; this.style.boxShadow='none';">
    
    <!-- Avatar -->
    <div style="width:48px; height:48px; background:linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%); border-radius:50%; display:flex; align-items:center; justify-content:center; overflow:hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex-shrink:0;">
       ${job.employer_photo ? `<img src="${job.employer_photo}" style="width:100%; height:100%; object-fit:cover;">` : '<i class="fas fa-building" style="color:#6b7280; font-size:20px;"></i>'}
    </div>
    
    <!-- Info with Arrow -->
    <div style="flex:1; min-width:0;">
      <div style="font-size:15px; font-weight:600; color:#8a1538; display:flex; align-items:center; gap:6px;">
        ${job.employer_name || 'Verified Employer'}
        <i class="fas fa-external-link-alt" style="font-size:11px; opacity:0.7;"></i>
      </div>
      <div style="font-size:12px; color:#6b7280;">
        <i class="fas fa-briefcase"></i> Hiring Manager • Click to view profile
      </div>
    </div>
  </a>
</div>
  `;

  if (job.latitude && job.longitude) {
    initMap(job.latitude, job.longitude, container);
  }
}

    function initMap(lat, lng, container) {
      let mapDiv = document.getElementById('job-location-map');
      if (mapDiv) mapDiv.remove();

      const mapSection = document.createElement('div');
      mapSection.innerHTML = `<h3 style="font-size:15px; font-weight:600; margin:20px 0 10px;">Location</h3><div id="job-location-map" style="height:200px; width:100%; border-radius:10px; z-index:1;"></div>`;
      container.appendChild(mapSection);

      setTimeout(() => {
        if(currentJobMap) currentJobMap.remove();
        currentJobMap = L.map('job-location-map').setView([lat, lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: 'OSM' }).addTo(currentJobMap);
        L.marker([lat, lng]).addTo(currentJobMap).bindPopup("Job Location").openPopup();
      }, 100);
    }
  </script>
</body>
</html>