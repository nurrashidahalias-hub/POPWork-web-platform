<?php
session_start();
include("backend/db_connect.php");

// Ensure only employers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
    header("Location: login.html");
    exit();
}

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

// Sabah districts
$sabah_districts = [
    "Beaufort", "Beluran", "Keningau", "Kinabatangan", "Kota Belud", "Kota Kinabalu", "Kota Marudu", "Kuala Penyu",
    "Kudat", "Kunak", "Lahad Datu", "Nabawan", "Papar", "Penampang", "Pitas", "Ranau", "Sandakan", "Semporna",
    "Sipitang", "Tambunan", "Tamparuli", "Tawau", "Telupid", "Tenom", "Tongod", "Tuaran", "Putatan", "Kalabakan"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editing ? 'Edit Job' : 'Post New Job' ?> | POP!Work</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    
    <!-- Leaflet CSS for map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #8a1538;
            --primary-dark: #6d1028;
            --primary-light: #fdf2f4;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-color: #f9fafb;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-color);
            color: var(--text-primary);
        }

        .form-container {
            padding: 25px;
            max-width: 900px;
            margin: 0 auto;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .page-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }

        .form-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-label .required {
            color: #ef4444;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(138, 21, 56, 0.1);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Geofence Section Styles */
        .geofence-section {
            background: var(--bg-color);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .geofence-toggle {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid var(--border-color);
        }

        .geofence-toggle:hover {
            border-color: var(--primary-color);
            background: var(--primary-light);
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
            background: #cbd5e1;
            border-radius: 13px;
            transition: background 0.3s;
            cursor: pointer;
        }

        .toggle-switch.active {
            background: var(--primary-color);
        }

        .toggle-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .toggle-switch.active .toggle-slider {
            transform: translateX(24px);
        }

        .toggle-label {
            flex: 1;
        }

        .toggle-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .toggle-description {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .geofence-details {
            margin-top: 20px;
            display: none;
        }

        .geofence-details.active {
            display: block;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            gap: 12px;
        }

        .info-box i {
            color: #3b82f6;
            font-size: 18px;
            margin-top: 2px;
        }

        .info-content {
            flex: 1;
            font-size: 13px;
            color: #1e40af;
            line-height: 1.5;
        }

        #location-map {
            height: 400px;
            border-radius: 10px;
            margin-top: 15px;
            border: 2px solid var(--border-color);
        }

        .radius-slider-container {
            margin-top: 15px;
        }

        .radius-value {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .radius-display {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .range-slider {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(to right, 
                #10b981 0%, 
                #10b981 20%, 
                #f59e0b 20%, 
                #f59e0b 60%, 
                #ef4444 60%, 
                #ef4444 100%);
            outline: none;
            -webkit-appearance: none;
        }

        .range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        .range-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        .range-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 20px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(138, 21, 56, 0.3);
        }

        .cancel-btn {
            background: #6b7280;
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .cancel-btn:hover {
            background: #4b5563;
        }

        .coordinates-display {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .coord-box {
            flex: 1;
            background: white;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .coord-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .coord-value {
            font-weight: 600;
            color: var(--text-primary);
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>

<div class="main-content">
    <div class="form-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <?= $editing ? '✏️ Edit Job' : '➕ Post New Job' ?>
            </h1>
            <p class="page-subtitle">
                <?= $editing ? 'Update your job details and geofence settings' : 'Create a new job posting with optional location tracking' ?>
            </p>
        </div>

        <form id="job-form" action="backend/<?= $editing ? 'update_job.php' : 'create_job.php' ?>" method="POST">
            <?php if ($editing): ?>
                <input type="hidden" name="job_id" value="<?= $job_data['job_id'] ?>">
            <?php endif; ?>
            
            <!-- Basic Information -->
            <div class="form-card">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Basic Information
                </h2>
                
                <div class="form-group">
                    <label class="form-label">
                        Job Title <span class="required">*</span>
                    </label>
                    <input type="text" name="title" class="form-input" 
                           value="<?= $editing ? htmlspecialchars($job_data['title']) : '' ?>"
                           placeholder="e.g., Warehouse Assistant, Delivery Driver" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Category <span class="required">*</span>
                        </label>
                        <select name="category" class="form-select" required>
                            <option value="Gig" <?= ($editing && $job_data['category'] == 'Gig') ? 'selected' : '' ?>>Gig</option>
                            <option value="Part-Time" <?= ($editing && $job_data['category'] == 'Part-Time') ? 'selected' : '' ?>>Part-Time</option>
                            <option value="Full-Time" <?= ($editing && $job_data['category'] == 'Full-Time') ? 'selected' : '' ?>>Full-Time</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Pay Rate (RM) <span class="required">*</span>
                        </label>
                        <input type="number" name="pay_rate" class="form-input" step="0.01" min="0"
                               value="<?= $editing ? $job_data['pay_rate'] : '' ?>"
                               placeholder="e.g., 15.00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Location (District) <span class="required">*</span>
                    </label>
                    <select name="location" id="location-select" class="form-select" required>
                        <option value="">Select District</option>
                        <?php foreach ($sabah_districts as $district): ?>
                            <option value="<?= $district ?>" 
                                <?= ($editing && $job_data['location'] == $district) ? 'selected' : '' ?>>
                                <?= $district ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Job Description</label>
                    <textarea name="description" class="form-textarea" 
                              placeholder="Describe the job responsibilities and requirements..."><?= $editing ? htmlspecialchars($job_data['description']) : '' ?></textarea>
                </div>
            </div>

            <!-- Location & Geofence Settings -->
            <div class="form-card">
                <h2 class="section-title">
                    <i class="fas fa-map-marker-alt"></i>
                    Location & Attendance Tracking
                </h2>

                <div class="geofence-section">
                    <div class="geofence-toggle" onclick="toggleGeofence()">
                        <div class="toggle-switch <?= ($editing && $job_data['require_onsite_attendance']) ? 'active' : '' ?>" 
                             id="geofence-switch">
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

                    <div class="geofence-details <?= ($editing && $job_data['require_onsite_attendance']) ? 'active' : '' ?>" 
                         id="geofence-details">
                        
                        <div class="info-box">
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

            <!-- Submit Buttons -->
            <button type="submit" class="submit-btn">
                <i class="fas fa-check-circle"></i>
                <?= $editing ? 'Update Job' : 'Create Job' ?>
            </button>
            
            <a href="employer_jobs.php" class="cancel-btn">
                <i class="fas fa-times-circle"></i> Cancel
            </a>
        </form>

    </div>
</div>

<script>
let map = null;
let marker = null;
let circle = null;
let jobLat = <?= ($editing && $job_data['job_latitude']) ? $job_data['job_latitude'] : 'null' ?>;
let jobLng = <?= ($editing && $job_data['job_longitude']) ? $job_data['job_longitude'] : 'null' ?>;

// Initialize map
function initMap() {
    const defaultCenter = jobLat && jobLng ? [jobLat, jobLng] : [5.9804, 116.0735]; // Sabah center
    
    map = L.map('location-map').setView(defaultCenter, jobLat && jobLng ? 15 : 10);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Add existing marker if editing
    if (jobLat && jobLng) {
        addMarker(jobLat, jobLng);
    }
    
    // Click to set location
    map.on('click', function(e) {
        addMarker(e.latlng.lat, e.latlng.lng);
    });
}

// Add or update marker
function addMarker(lat, lng) {
    // Remove existing marker and circle
    if (marker) {
        map.removeLayer(marker);
    }
    if (circle) {
        map.removeLayer(circle);
    }
    
    // Create custom marker icon
    const customIcon = L.divIcon({
        className: 'custom-marker',
        html: `<div style="background: #8a1538; width: 40px; height: 40px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.4);"><i class="fas fa-briefcase" style="color: white; transform: rotate(45deg); font-size: 18px;"></i></div>`,
        iconSize: [40, 40],
        iconAnchor: [20, 40]
    });
    
    // Add new marker
    marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);
    marker.bindPopup('<strong>Job Location</strong>').openPopup();
    
    // Add geofence circle
    const radius = parseInt(document.getElementById('radius-slider').value);
    circle = L.circle([lat, lng], {
        radius: radius,
        color: '#3b82f6',
        fillColor: '#3b82f6',
        fillOpacity: 0.2,
        weight: 2
    }).addTo(map);
    
    // Update form fields
    document.getElementById('job-lat').value = lat.toFixed(8);
    document.getElementById('job-lng').value = lng.toFixed(8);
    document.getElementById('lat-display').textContent = lat.toFixed(8);
    document.getElementById('lng-display').textContent = lng.toFixed(8);
    
    jobLat = lat;
    jobLng = lng;
    
    // Fit bounds to show marker and circle
    map.fitBounds(circle.getBounds());
}

// Update geofence radius
function updateRadius(value) {
    document.getElementById('radius-display').textContent = value + 'm';
    
    // Update circle if marker exists
    if (circle && jobLat && jobLng) {
        map.removeLayer(circle);
        circle = L.circle([jobLat, jobLng], {
            radius: parseInt(value),
            color: '#3b82f6',
            fillColor: '#3b82f6',
            fillOpacity: 0.2,
            weight: 2
        }).addTo(map);
    }
}

// Toggle geofence settings
function toggleGeofence() {
    const switchElem = document.getElementById('geofence-switch');
    const detailsElem = document.getElementById('geofence-details');
    const inputElem = document.getElementById('geofence-input');
    
    switchElem.classList.toggle('active');
    detailsElem.classList.toggle('active');
    
    if (switchElem.classList.contains('active')) {
        inputElem.value = '1';
        // Initialize map if not already initialized
        setTimeout(() => {
            if (!map) {
                initMap();
            } else {
                map.invalidateSize();
            }
        }, 100);
    } else {
        inputElem.value = '0';
    }
}

// Form validation
document.getElementById('job-form').addEventListener('submit', function(e) {
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
});

// Initialize map if geofence is already enabled (editing mode)
<?php if ($editing && $job_data['require_onsite_attendance']): ?>
    window.addEventListener('load', function() {
        initMap();
    });
<?php endif; ?>
</script>

</body>
</html>