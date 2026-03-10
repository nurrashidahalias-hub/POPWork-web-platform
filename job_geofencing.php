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
$job_sql = "SELECT * FROM jobs WHERE job_id = ? AND employer_id = ?";
$job_stmt = $conn->prepare($job_sql);
$job_stmt->bind_param("ii", $job_id, $employer_id);
$job_stmt->execute();
$job_result = $job_stmt->get_result();

if ($job_result->num_rows === 0) {
  header("Location: employer_dashboard.php");
  exit();
}

$job = $job_result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $require_onsite = isset($_POST['require_onsite_attendance']) ? 1 : 0;
  $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
  $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
  $radius = isset($_POST['geofence_radius']) ? intval($_POST['geofence_radius']) : 100;

  $update_sql = "UPDATE jobs 
                 SET require_onsite_attendance = ?,
                     job_latitude = ?,
                     job_longitude = ?,
                     geofence_radius = ?
                 WHERE job_id = ? AND employer_id = ?";
  
  $update_stmt = $conn->prepare($update_sql);
  $update_stmt->bind_param("iddiii", $require_onsite, $latitude, $longitude, $radius, $job_id, $employer_id);
  
  if ($update_stmt->execute()) {
    $_SESSION['success_message'] = "Geofencing settings updated successfully!";
    header("Location: job_geofencing.php?job_id=" . $job_id);
    exit();
  } else {
    $error = "Failed to update settings. Please try again.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Location Settings | POP!Work</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
    }

    .page-header {
      background: linear-gradient(135deg, #8a1538 0%, #a61e47 100%);
      color: white;
      padding: 40px 0;
      margin-bottom: 40px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    }

    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: white;
      text-decoration: none;
      margin-bottom: 16px;
      font-weight: 500;
      transition: gap 0.3s;
    }

    .back-link:hover {
      gap: 12px;
    }

    .page-title {
      font-size: 36px;
      margin-bottom: 8px;
      font-weight: 700;
    }

    .page-subtitle {
      font-size: 16px;
      opacity: 0.9;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px 60px;
    }

    .alert {
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 2px solid #c3e6cb;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 2px solid #f5c6cb;
    }

    .settings-card {
      background: white;
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }

    .card-header {
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 2px solid #f0f0f0;
    }

    .card-title {
      font-size: 24px;
      color: #333;
      margin-bottom: 8px;
      font-weight: 700;
    }

    .card-description {
      color: #666;
      line-height: 1.6;
    }

    .form-section {
      margin-bottom: 30px;
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: #333;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .toggle-container {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .toggle-label {
      font-weight: 600;
      color: #333;
    }

    .toggle-description {
      font-size: 14px;
      color: #666;
      margin-top: 4px;
    }

    .toggle-switch {
      position: relative;
      width: 60px;
      height: 30px;
    }

    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 30px;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 22px;
      width: 22px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }

    input:checked + .slider {
      background: linear-gradient(135deg, #27ae60, #2ecc71);
    }

    input:checked + .slider:before {
      transform: translateX(30px);
    }

    #location-settings {
      display: none;
      animation: slideDown 0.3s ease-out;
    }

    #location-settings.active {
      display: block;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .info-box {
      background: linear-gradient(135deg, rgba(138, 21, 56, 0.05), rgba(201, 31, 77, 0.05));
      border-left: 4px solid #8a1538;
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .info-box strong {
      color: #8a1538;
      display: block;
      margin-bottom: 8px;
    }

    .map-container {
      width: 100%;
      height: 500px;
      border-radius: 12px;
      overflow: hidden;
      margin-bottom: 20px;
      border: 2px solid #e0e0e0;
    }

    .coordinates-display {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }

    .coord-box {
      background: #f8f9fa;
      padding: 16px;
      border-radius: 12px;
      text-align: center;
    }

    .coord-label {
      font-size: 12px;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
    }

    .coord-value {
      font-size: 18px;
      font-weight: 700;
      color: #8a1538;
      font-family: 'Courier New', monospace;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }

    input[type="number"],
    select {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.3s;
      font-family: 'Poppins', sans-serif;
    }

    input[type="number"]:focus,
    select:focus {
      outline: none;
      border-color: #8a1538;
      box-shadow: 0 0 0 4px rgba(138, 21, 56, 0.1);
    }

    .btn {
      padding: 14px 32px;
      border: none;
      border-radius: 25px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, #8a1538, #a61e47);
      color: white;
    }

    .btn-secondary {
      background: white;
      color: #8a1538;
      border: 2px solid #8a1538;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 20px rgba(138, 21, 56, 0.3);
    }

    .button-group {
      display: flex;
      gap: 12px;
      margin-top: 30px;
      padding-top: 30px;
      border-top: 2px solid #f0f0f0;
    }

    .location-instructions {
      background: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .location-instructions h4 {
      color: #856404;
      margin-bottom: 12px;
      font-size: 16px;
    }

    .location-instructions ol {
      margin-left: 20px;
      color: #856404;
    }

    .location-instructions li {
      margin-bottom: 8px;
      line-height: 1.6;
    }

    @media (max-width: 768px) {
      .settings-card {
        padding: 24px;
      }

      .map-container {
        height: 400px;
      }

      .button-group {
        flex-direction: column;
      }

      .btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>
  <?php 
    $page_title = "Job Location Settings";
    include 'includes/header.php';
  ?>

  <div class="page-header">
    <div class="header-content">
      <a href="employer_dashboard.php" class="back-link">← Back to Dashboard</a>
      <h1 class="page-title">📍 Job Location Settings</h1>
      <p class="page-subtitle">Configure geofencing for: <?= htmlspecialchars($job['title']); ?></p>
    </div>
  </div>

  <div class="container">
    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="alert alert-success">
        ✅ <?= $_SESSION['success_message']; ?>
      </div>
      <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($error)): ?>
      <div class="alert alert-error">
        ❌ <?= $error; ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="geofencing-form">
      <div class="settings-card">
        <div class="card-header">
          <h2 class="card-title">🛡️ Geofencing Settings</h2>
          <p class="card-description">
            Control whether workers must be physically present at the job location to clock in and out. 
            This is perfect for on-site jobs where you need to ensure workers are at the correct location.
          </p>
        </div>

        <div class="form-section">
          <div class="toggle-container">
            <div>
              <div class="toggle-label">🎯 Require On-Site Attendance</div>
              <div class="toggle-description">Workers must be at job location to clock in/out</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" 
                     name="require_onsite_attendance" 
                     id="require-onsite"
                     <?= $job['require_onsite_attendance'] ? 'checked' : ''; ?>>
              <span class="slider"></span>
            </label>
          </div>
        </div>

        <div id="location-settings" class="<?= $job['require_onsite_attendance'] ? 'active' : ''; ?>">
          <div class="info-box">
            <strong>ℹ️ How Geofencing Works:</strong>
            When enabled, workers can only clock in/out if they are within the specified radius of the job location. 
            This ensures accountability and prevents time fraud.
          </div>

          <div class="location-instructions">
            <h4>📍 How to Set Job Location:</h4>
            <ol>
              <li>Click on the map at the exact job site location</li>
              <li>Or drag the red marker to the correct position</li>
              <li>Set the allowed radius (default: 100 meters)</li>
              <li>Click "Save Settings" to apply</li>
            </ol>
          </div>

          <div class="section-title">🗺️ Set Job Location on Map</div>
          
          <div class="map-container" id="map"></div>

          <div class="coordinates-display">
            <div class="coord-box">
              <div class="coord-label">📐 Latitude</div>
              <div class="coord-value" id="lat-display">
                <?= $job['job_latitude'] ?? 'Not Set'; ?>
              </div>
            </div>
            <div class="coord-box">
              <div class="coord-label">📐 Longitude</div>
              <div class="coord-value" id="lng-display">
                <?= $job['job_longitude'] ?? 'Not Set'; ?>
              </div>
            </div>
            <div class="coord-box">
              <div class="coord-label">📏 Allowed Radius</div>
              <div class="coord-value" id="radius-display">
                <?= $job['geofence_radius'] ?? 100; ?>m
              </div>
            </div>
          </div>

          <input type="hidden" name="latitude" id="latitude" value="<?= $job['job_latitude'] ?? ''; ?>">
          <input type="hidden" name="longitude" id="longitude" value="<?= $job['job_longitude'] ?? ''; ?>">

          <div class="form-group">
            <label for="geofence_radius">📏 Geofence Radius (meters)</label>
            <select name="geofence_radius" id="geofence_radius">
              <option value="50" <?= ($job['geofence_radius'] ?? 100) == 50 ? 'selected' : ''; ?>>50 meters (Very Strict)</option>
              <option value="100" <?= ($job['geofence_radius'] ?? 100) == 100 ? 'selected' : ''; ?>>100 meters (Default)</option>
              <option value="200" <?= ($job['geofence_radius'] ?? 100) == 200 ? 'selected' : ''; ?>>200 meters (Relaxed)</option>
              <option value="500" <?= ($job['geofence_radius'] ?? 100) == 500 ? 'selected' : ''; ?>>500 meters (Very Relaxed)</option>
              <option value="1000" <?= ($job['geofence_radius'] ?? 100) == 1000 ? 'selected' : ''; ?>>1000 meters (Area)</option>
            </select>
          </div>
        </div>

        <div class="button-group">
          <button type="submit" class="btn btn-primary">
            💾 Save Settings
          </button>
          <a href="employer_dashboard.php" class="btn btn-secondary">
            Cancel
          </a>
        </div>
      </div>
    </form>
  </div>

  <?php include 'includes/footer.php'; ?>

  <script>
    // Initialize map
    let map;
    let marker;
    let circle;
    
    // Default location (Kota Kinabalu)
    const defaultLat = <?= $job['job_latitude'] ?? '5.9804'; ?>;
    const defaultLng = <?= $job['job_longitude'] ?? '116.0735'; ?>;
    const defaultRadius = <?= $job['geofence_radius'] ?? '100'; ?>;

    function initMap() {
      // Create map
      map = L.map('map').setView([defaultLat, defaultLng], 15);

      // Add tile layer
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(map);

      // Add marker
      marker = L.marker([defaultLat, defaultLng], {
        draggable: true,
        icon: L.icon({
          iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
          shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
          iconSize: [25, 41],
          iconAnchor: [12, 41],
          popupAnchor: [1, -34],
          shadowSize: [41, 41]
        })
      }).addTo(map);

      // Add circle for radius
      circle = L.circle([defaultLat, defaultLng], {
        color: '#8a1538',
        fillColor: '#8a1538',
        fillOpacity: 0.2,
        radius: defaultRadius
      }).addTo(map);

      // Update coordinates when marker is dragged
      marker.on('dragend', function(e) {
        const position = marker.getLatLng();
        updateCoordinates(position.lat, position.lng);
        circle.setLatLng(position);
      });

      // Update coordinates when map is clicked
      map.on('click', function(e) {
        const { lat, lng } = e.latlng;
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
        updateCoordinates(lat, lng);
      });

      // Update radius when changed
      document.getElementById('geofence_radius').addEventListener('change', function(e) {
        const radius = parseInt(e.target.value);
        circle.setRadius(radius);
        document.getElementById('radius-display').textContent = radius + 'm';
      });
    }

    function updateCoordinates(lat, lng) {
      document.getElementById('latitude').value = lat.toFixed(8);
      document.getElementById('longitude').value = lng.toFixed(8);
      document.getElementById('lat-display').textContent = lat.toFixed(8);
      document.getElementById('lng-display').textContent = lng.toFixed(8);
    }

    // Toggle location settings
    document.getElementById('require-onsite').addEventListener('change', function() {
      const locationSettings = document.getElementById('location-settings');
      if (this.checked) {
        locationSettings.classList.add('active');
      } else {
        locationSettings.classList.remove('active');
      }
    });

    // Try to get user's current location
    if (navigator.geolocation && !<?= $job['job_latitude'] ? 'true' : 'false'; ?>) {
      navigator.geolocation.getCurrentPosition(function(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        map.setView([lat, lng], 15);
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
        updateCoordinates(lat, lng);
      });
    }

    // Initialize map when page loads
    document.addEventListener('DOMContentLoaded', initMap);
  </script>
</body>
</html>