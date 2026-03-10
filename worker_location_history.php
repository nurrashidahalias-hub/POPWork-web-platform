<?php
session_start();
include("backend/db_connect.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get attendance_id from URL
$attendance_id = isset($_GET['attendance_id']) ? intval($_GET['attendance_id']) : 0;

if (!$attendance_id) {
    header("Location: attendance_history.php");
    exit();
}

// Fetch attendance details with authorization check
if ($user_role == 'worker') {
    $attendance_sql = "SELECT 
        a.attendance_id,
        a.clock_in_time,
        a.clock_out_time,
        a.clock_in_latitude,
        a.clock_in_longitude,
        a.clock_out_latitude,
        a.clock_out_longitude,
        a.distance_from_job_location,
        a.location_verified,
        a.total_hours,
        j.title as job_title,
        j.location as job_location,
        j.job_latitude,
        j.job_longitude,
        j.geofence_radius,
        j.require_onsite_attendance,
        u.email as employer_email,
        p.name as employer_name
        FROM attendance a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN users u ON a.employer_id = u.user_id
        LEFT JOIN profiles p ON a.employer_id = p.user_id
        WHERE a.attendance_id = ? AND a.user_id = ?";
} else {
    // Employer view
    $attendance_sql = "SELECT 
        a.attendance_id,
        a.clock_in_time,
        a.clock_out_time,
        a.clock_in_latitude,
        a.clock_in_longitude,
        a.clock_out_latitude,
        a.clock_out_longitude,
        a.distance_from_job_location,
        a.location_verified,
        a.total_hours,
        j.title as job_title,
        j.location as job_location,
        j.job_latitude,
        j.job_longitude,
        j.geofence_radius,
        j.require_onsite_attendance,
        u.email as worker_email,
        p.name as worker_name,
        p.photo_url as worker_photo
        FROM attendance a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN profiles p ON a.user_id = p.user_id
        WHERE a.attendance_id = ? AND j.employer_id = ?";
}

$stmt = $conn->prepare($attendance_sql);
$stmt->bind_param("ii", $attendance_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: attendance_history.php");
    exit();
}

$attendance = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location History | POP!Work</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    
    <!-- Leaflet CSS for maps -->
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
            --success-color: #10b981;
            --warning-color: #f59e0b;
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

        .history-container {
            padding: 25px;
            max-width: 1400px;
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

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
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

        .content-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 25px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-panel {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        .info-header {
            padding: 20px;
            background: var(--bg-color);
            border-bottom: 1px solid var(--border-color);
            border-radius: 12px 12px 0 0;
        }

        .info-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .info-body {
            padding: 20px;
        }

        .info-section {
            margin-bottom: 25px;
        }

        .section-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .info-card {
            background: var(--bg-color);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-badge.verified {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.not-verified {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.active {
            background: #dbeafe;
            color: #1e40af;
        }

        .map-container {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        .map-header {
            padding: 20px;
            background: var(--bg-color);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .map-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        #history-map {
            height: 700px;
            width: 100%;
        }

        .legend {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .legend-title {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .legend-marker {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .legend-marker.clock-in {
            background: #10b981;
        }

        .legend-marker.clock-out {
            background: #ef4444;
        }

        .legend-marker.job-site {
            background: #3b82f6;
        }

        .legend-line {
            height: 3px;
            width: 30px;
        }

        .legend-line.path {
            background: linear-gradient(to right, #10b981, #ef4444);
        }

        .legend-line.geofence {
            background: transparent;
            border: 2px dashed #3b82f6;
            height: 0;
        }

        .worker-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: var(--bg-color);
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .worker-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .worker-avatar.default {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 18px;
        }

        .worker-details h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .worker-details p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }
    </style>
</head>
<body>

<?php include("includes/sidebar.php"); ?>

<div class="main-content">
    <div class="history-container">
        
        <!-- Page Header -->
        <div class="page-header">
            <a href="<?= $user_role == 'worker' ? 'attendance_history.php' : 'employer_tracking_dashboard.php' ?>" 
               class="back-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <h1 class="page-title">📍 Location History</h1>
            <p class="page-subtitle">View shift movement and attendance details</p>
        </div>

        <div class="content-grid">
            <!-- Info Panel -->
            <div class="info-panel">
                <div class="info-header">
                    <h2 class="info-title">Shift Details</h2>
                </div>
                
                <div class="info-body">
                    <?php if ($user_role == 'employer' && isset($attendance['worker_name'])): ?>
                    <div class="worker-info">
                        <?php if (!empty($attendance['worker_photo']) && file_exists($attendance['worker_photo'])): ?>
                            <img src="<?= htmlspecialchars($attendance['worker_photo']) ?>" 
                                 alt="Worker" class="worker-avatar">
                        <?php else: ?>
                            <div class="worker-avatar default">
                                <?= strtoupper(substr($attendance['worker_name'] ?? $attendance['worker_email'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="worker-details">
                            <h3><?= htmlspecialchars($attendance['worker_name'] ?? 'Worker') ?></h3>
                            <p><?= htmlspecialchars($attendance['worker_email']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-value">
                                <?= $attendance['total_hours'] ? number_format($attendance['total_hours'], 2) : '0.00' ?>
                            </div>
                            <div class="stat-label">Hours Worked</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">
                                <?= $attendance['distance_from_job_location'] ? round($attendance['distance_from_job_location']) . 'm' : 'N/A' ?>
                            </div>
                            <div class="stat-label">Distance</div>
                        </div>
                    </div>

                    <div class="info-section">
                        <div class="section-label">Job Information</div>
                        <div class="info-card">
                            <div class="info-row">
                                <span class="info-label">Job Title:</span>
                                <span class="info-value"><?= htmlspecialchars($attendance['job_title']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Location:</span>
                                <span class="info-value"><?= htmlspecialchars($attendance['job_location']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="info-section">
                        <div class="section-label">Time Details</div>
                        <div class="info-card">
                            <div class="info-row">
                                <span class="info-label">🟢 Clock In:</span>
                                <span class="info-value">
                                    <?= date('M d, Y g:i A', strtotime($attendance['clock_in_time'])) ?>
                                </span>
                            </div>
                            <?php if ($attendance['clock_out_time']): ?>
                            <div class="info-row">
                                <span class="info-label">🔴 Clock Out:</span>
                                <span class="info-value">
                                    <?= date('M d, Y g:i A', strtotime($attendance['clock_out_time'])) ?>
                                </span>
                            </div>
                            <?php else: ?>
                            <div class="info-row">
                                <span class="info-label">Status:</span>
                                <span class="status-badge active">
                                    <i class="fas fa-clock"></i> Currently Clocked In
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($attendance['require_onsite_attendance']): ?>
                    <div class="info-section">
                        <div class="section-label">Location Verification</div>
                        <div class="info-card">
                            <div class="info-row">
                                <span class="info-label">Status:</span>
                                <?php if ($attendance['location_verified']): ?>
                                    <span class="status-badge verified">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge not-verified">
                                        <i class="fas fa-times-circle"></i> Not Verified
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Geofence:</span>
                                <span class="info-value"><?= $attendance['geofence_radius'] ?>m radius</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Map Container -->
            <div class="map-container">
                <div class="map-header">
                    <h2 class="map-title">📊 Movement Map</h2>
                </div>
                <div id="history-map"></div>
            </div>
        </div>

    </div>
</div>

<script>
const attendanceData = <?= json_encode($attendance) ?>;
let historyMap = null;

// Initialize map
function initHistoryMap() {
    const clockInLat = parseFloat(attendanceData.clock_in_latitude);
    const clockInLng = parseFloat(attendanceData.clock_in_longitude);
    
    if (!clockInLat || !clockInLng) {
        document.getElementById('history-map').innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; flex-direction: column; color: #6b7280;">
                <i class="fas fa-map-marker-slash" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                <h3 style="font-size: 20px; margin-bottom: 10px;">No Location Data</h3>
                <p>Location data was not recorded for this shift.</p>
            </div>
        `;
        return;
    }
    
    historyMap = L.map('history-map').setView([clockInLat, clockInLng], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(historyMap);
    
    const bounds = [];
    
    // Add clock-in marker
    const clockInIcon = L.divIcon({
        className: 'custom-marker',
        html: `<div style="background: #10b981; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.4);"><i class="fas fa-play" style="color: white; font-size: 16px;"></i></div>`,
        iconSize: [40, 40],
        iconAnchor: [20, 20]
    });
    
    const clockInMarker = L.marker([clockInLat, clockInLng], { icon: clockInIcon }).addTo(historyMap);
    clockInMarker.bindPopup(`
        <div style="min-width: 200px;">
            <h3 style="margin: 0 0 10px 0; color: #10b981;">🟢 Clock In</h3>
            <p style="margin: 5px 0;"><strong>Time:</strong> ${new Date(attendanceData.clock_in_time).toLocaleString()}</p>
            ${attendanceData.distance_from_job_location ? 
                `<p style="margin: 5px 0;"><strong>Distance:</strong> ${Math.round(attendanceData.distance_from_job_location)}m from job site</p>` 
                : ''}
        </div>
    `);
    bounds.push([clockInLat, clockInLng]);
    
    // Add clock-out marker if exists
    if (attendanceData.clock_out_latitude && attendanceData.clock_out_longitude) {
        const clockOutLat = parseFloat(attendanceData.clock_out_latitude);
        const clockOutLng = parseFloat(attendanceData.clock_out_longitude);
        
        const clockOutIcon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background: #ef4444; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.4);"><i class="fas fa-stop" style="color: white; font-size: 16px;"></i></div>`,
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });
        
        const clockOutMarker = L.marker([clockOutLat, clockOutLng], { icon: clockOutIcon }).addTo(historyMap);
        clockOutMarker.bindPopup(`
            <div style="min-width: 200px;">
                <h3 style="margin: 0 0 10px 0; color: #ef4444;">🔴 Clock Out</h3>
                <p style="margin: 5px 0;"><strong>Time:</strong> ${new Date(attendanceData.clock_out_time).toLocaleString()}</p>
                <p style="margin: 5px 0;"><strong>Total Hours:</strong> ${attendanceData.total_hours}h</p>
            </div>
        `);
        bounds.push([clockOutLat, clockOutLng]);
        
        // Draw path between clock-in and clock-out
        const path = L.polyline([[clockInLat, clockInLng], [clockOutLat, clockOutLng]], {
            color: '#8a1538',
            weight: 3,
            opacity: 0.7,
            dashArray: '10, 10'
        }).addTo(historyMap);
    }
    
    // Add job site marker if available
    if (attendanceData.job_latitude && attendanceData.job_longitude) {
        const jobLat = parseFloat(attendanceData.job_latitude);
        const jobLng = parseFloat(attendanceData.job_longitude);
        
        const jobIcon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background: #3b82f6; width: 35px; height: 35px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><i class="fas fa-briefcase" style="color: white; transform: rotate(45deg); font-size: 14px;"></i></div>`,
            iconSize: [35, 35],
            iconAnchor: [17, 35]
        });
        
        const jobMarker = L.marker([jobLat, jobLng], { icon: jobIcon }).addTo(historyMap);
        jobMarker.bindPopup(`
            <div style="min-width: 180px;">
                <h3 style="margin: 0 0 10px 0; color: #3b82f6;">📍 Job Site</h3>
                <p style="margin: 5px 0;"><strong>${attendanceData.job_title}</strong></p>
                <p style="margin: 5px 0;">${attendanceData.job_location}</p>
            </div>
        `);
        bounds.push([jobLat, jobLng]);
        
        // Draw geofence circle
        if (attendanceData.geofence_radius) {
            L.circle([jobLat, jobLng], {
                radius: attendanceData.geofence_radius,
                color: '#3b82f6',
                fillColor: '#3b82f6',
                fillOpacity: 0.1,
                weight: 2,
                dashArray: '5, 5'
            }).addTo(historyMap);
        }
    }
    
    // Add legend
    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'legend');
        div.innerHTML = `
            <div class="legend-title">Map Legend</div>
            <div class="legend-item">
                <div class="legend-marker clock-in"></div>
                <span>Clock In Location</span>
            </div>
            ${attendanceData.clock_out_time ? `
            <div class="legend-item">
                <div class="legend-marker clock-out"></div>
                <span>Clock Out Location</span>
            </div>
            <div class="legend-item">
                <div class="legend-line path"></div>
                <span>Movement Path</span>
            </div>
            ` : ''}
            ${attendanceData.job_latitude ? `
            <div class="legend-item">
                <div class="legend-marker job-site"></div>
                <span>Job Site</span>
            </div>
            <div class="legend-item">
                <div class="legend-line geofence"></div>
                <span>Geofence Area</span>
            </div>
            ` : ''}
        `;
        return div;
    };
    legend.addTo(historyMap);
    
    // Fit map to show all markers
    if (bounds.length > 0) {
        historyMap.fitBounds(bounds, { padding: [50, 50] });
    }
}

// Initialize map when page loads
window.addEventListener('load', initHistoryMap);
</script>

</body>
</html>