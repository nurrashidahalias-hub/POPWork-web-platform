<?php
session_start();
include("backend/db_connect.php");

$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($job_id === 0) {
    header("Location: jobs.php");
    exit();
}

// Fetch job details with coordinates
$sql = "SELECT j.*, p.name as employer_name, p.profile_picture, u.email as employer_email 
        FROM jobs j 
        LEFT JOIN users u ON j.employer_id = u.user_id 
        LEFT JOIN profiles p ON u.user_id = p.user_id 
        WHERE j.job_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if (!$job) {
    header("Location: jobs.php");
    exit();
}

// Check if user has applied
$has_applied = false;
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'worker') {
    $check_stmt = $conn->prepare("SELECT application_id FROM applications WHERE job_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $job_id, $_SESSION['user_id']);
    $check_stmt->execute();
    $has_applied = $check_stmt->get_result()->num_rows > 0;
}

// Debug: Log coordinates
error_log("Job ID: $job_id, Latitude: " . ($job['latitude'] ?? 'NULL') . ", Longitude: " . ($job['longitude'] ?? 'NULL'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($job['title']) ?> | POP!Work</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { 
            font-family: 'Poppins'; 
            background: url('uploads/photos/Mount Kinabalu.jpg') no-repeat center center fixed;
            background-size: cover;
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
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }
        .job-detail-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .job-card { background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        
        .job-header { background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%); color: white; padding: 16px; position: relative; }
        .back-link { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            color: white; 
            text-decoration: none; 
            font-weight: 600; 
            margin-bottom: 10px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            transition: all 0.3s;
        }

        .back-link:hover { background: rgba(255, 255, 255, 0.1); }

        .job-title { font-size: 36px; font-weight: 700; margin-bottom: 15px; }
        .job-meta { display: flex; flex-wrap: wrap; gap: 25px; font-size: 14px; }
        .job-meta-item { display: flex; align-items: center; gap: 8px; }
        
        .job-body { padding: 40px; }
        .section { margin-bottom: 35px; }
       .section-title { 
        font-size: 18px; /* Slightly smaller */
        color: #8a1538; 
        font-weight: 700; 
        margin-bottom: 12px; /* Tighter spacing below title */
        display: flex; 
        align-items: center; 
        gap: 10px; 
        border-bottom: 1px solid #eee; /* Added a subtle line for neatness */
        padding-bottom: 8px;
        }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 12px; border-left: 4px solid #8a1538; }
        .info-label { font-size: 12px; color: #666; font-weight: 600; margin-bottom: 5px; }
        .info-value { font-size: 16px; color: #333; font-weight: 600; }
        
        .description { line-height: 0.9; color: #555; white-space: pre-wrap; }
        
        /* Map Section */
        #jobMap { 
            width: 100%; 
            height: 400px; 
            border-radius: 12px; 
            border: 2px solid #8a1538; 
            margin-top: 10px; 
            z-index: 1;
        }
        .map-placeholder { 
            background: #f0f2f5; 
            border: 2px dashed #d0d0d0; 
            border-radius: 12px; 
            padding: 60px 20px; 
            text-align: center; 
            color: #8a1538; 
        }
        .map-placeholder i { font-size: 48px; margin-bottom: 15px; opacity: 0.5; }
        .directions-btn { 
            background: #8a1538; 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            margin-top: 15px; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            transition: all 0.3s;
        }
        .directions-btn:hover { background: #8a1538; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3); }
        
        .map-info { 
            background: #e3f2fd; 
            border-left: 4px solid #8a1538; 
            padding: 15px 20px; 
            border-radius: 10px; 
            margin-top: 15px;
            font-size: 14px;
            color: #555;
        }
        
        .apply-section { 
            background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%); 
            color: white; 
            padding: 30px; 
            border-radius: 15px; 
            text-align: center; 
        }
        .apply-btn { 
            background: white; 
            color: #8a1538; 
            border: none; 
            padding: 15px 40px; 
            border-radius: 30px; 
            font-size: 16px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: all 0.3s; 
            text-decoration: none;
            display: inline-block;
        }
        .apply-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
        .apply-btn.applied { background: #27ae60; color: white; cursor: default; }
        .apply-btn.applied:hover { transform: none; }
        
        @media(max-width: 768px) {
            .job-title { font-size: 24px; }
            .job-meta { flex-direction: column; gap: 10px; }
            #jobMap { height: 300px; }
            .job-body { padding: 25px; }
        }
    </style>
</head>
<body>


    <div class="job-detail-container">
        <div class="job-card">
            <!-- Job Header -->
            <div class="job-header">
                <a href="my_applications.php" class="back-link">
                    <i class="fa fa-arrow-left"></i> <- Back to Jobs
                </a>
               
                <h1 class="job-title"><?= htmlspecialchars($job['title']) ?></h1>
                <div class="job-meta">
                    <div class="job-meta-item">
                        <i class="fa fa-map-marker-alt"></i>
                        <?= htmlspecialchars($job['location']) ?>
                    </div>
                    <div class="job-meta-item">
                        <i class="fa fa-money-bill-wave"></i>
                        RM <?= number_format($job['pay_rate'], 2) ?> <?= htmlspecialchars($job['pay_period'] ?? 'Per Day') ?>
                    </div>
                    <div class="job-meta-item">
                        <i class="fa fa-clock"></i>
                        Posted <?= date('M d, Y', strtotime($job['created_at'])) ?>
                    </div>
                </div>
            </div>

            <!-- Job Body -->
            <div class="job-body">
                <!-- Quick Info -->
                <div class="section">
                    <h2 class="section-title"><i class="fa fa-info-circle"></i> Quick Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Job Type</div>
                            <div class="info-value"><?= htmlspecialchars($job['category']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Experience Level</div>
                            <div class="info-value"><?= htmlspecialchars($job['experience_level'] ?? 'Any') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Positions Available</div>
                            <div class="info-value"><?= $job['positions_available'] ?? 1 ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Duration</div>
                            <div class="info-value"><?= htmlspecialchars($job['job_duration'] ?? 'Permanent') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Employer Info -->
                <div class="section">
                    <h2 class="section-title"><i class="fa fa-user-tie"></i> Employer Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Company/Employer</div>
                            <div class="info-value"><?= htmlspecialchars($job['employer_name'] ?? 'Not specified') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Contact</div>
                            <div class="info-value"><?= htmlspecialchars($job['employer_email']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="section">
                    <h2 class="section-title"><i class="fa fa-file-alt"></i> Job Description</h2>
                    <div class="description"><?= nl2br(htmlspecialchars($job['description'])) ?></div>
                </div>

                <!-- Map Location -->
                <div class="section">
                    <h2 class="section-title"><i class="fa fa-map-marked-alt"></i> Job Location</h2>
                    
                    <?php 
                    // Check if coordinates exist and are valid numbers
                    $hasValidCoordinates =!empty($job['latitude']) && 
                                          !empty($job['longitude']) && 
                                          is_numeric($job['latitude']) && 
                                          is_numeric($job['longitude']);
                    
                    if ($hasValidCoordinates): 
                    ?>
                        <div id="jobMap"></div>
                        <button class="directions-btn" onclick="openGoogleMaps(<?= $job['latitude'] ?>, <?= $job['longitude'] ?>)">
                            <i class="fa fa-directions"></i> Get Directions to This Location
                        </button>
                        <div class="map-info">
                            <strong><i class="fa fa-info-circle"></i> Location Info:</strong> 
                            Coordinates: <?= round($job['latitude'], 4) ?>, <?= round($job['longitude'], 4) ?>
                        </div>
                    <?php else: ?>
                        <div class="map-placeholder">
                            <i class="fa fa-map-marker-alt"></i>
                            <p><strong>Exact location not provided</strong></p>
                            <p style="font-size: 16px; margin-top: 15px; color: #666;">
                                <i class="fa fa-map-pin"></i> General Location: <strong><?= htmlspecialchars($job['location']) ?></strong>
                            </p>
                            <p style="font-size: 14px; margin-top: 10px; color: #999;">
                                Contact the employer for the specific address
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Apply Section -->
                <div class="apply-section">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'worker'): ?>
                        <?php if ($has_applied): ?>
                            <h3>✓ You have already applied for this position</h3>
                            <p style="margin: 15px 0; opacity: 0.9;">The employer will review your application soon</p>
                            <button class="apply-btn applied" disabled>Application Submitted</button>
                        <?php else: ?>
                            <h3>Interested in this position?</h3>
                            <p style="margin: 15px 0; opacity: 0.9;">Submit your application now!</p>
                            <form action="backend/apply_job.php" method="POST" style="margin-top: 20px;">
                                <input type="hidden" name="job_id" value="<?= $job_id ?>">
                                <button type="submit" class="apply-btn">Apply Now</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <h3>Ready to apply?</h3>
                        <p style="margin: 15px 0;">Please log in as a worker to apply for this job</p>
                        <a href="login.html" class="apply-btn">Login to Apply</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <?php if ($hasValidCoordinates): ?>
    <script>
        console.log('Initializing map...');
        console.log('Coordinates:', <?= $job['latitude'] ?>, <?= $job['longitude'] ?>);
        
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const jobLat = <?= $job['latitude'] ?>;
                const jobLng = <?= $job['longitude'] ?>;
                 
                // Add tiles
                if (jobLat && jobLng) {
                const map = L.map('jobMap').setView([jobLat, jobLng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                L.marker([jobLat, jobLng]).addTo(map).bindPopup("<b>Job Location</b>").openPopup();
}
                
                // Custom marker icon
                const customIcon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background:#8a1538; width:40px; height:40px; border-radius:50% 50% 50% 0; transform:rotate(-45deg); display:flex; align-items:center; justify-content:center; border:4px solid white; box-shadow:0 4px 12px rgba(155, 39, 104, 0.4);">
                            <i class="fa fa-briefcase" style="color:white; transform:rotate(45deg); font-size:18px;"></i>
                           </div>`,
                    iconSize: [40, 40],
                    iconAnchor: [20, 40],
                    popupAnchor: [0, -40]
                });
                
                // Add marker with popup
                const marker = L.marker([jobLat, jobLng], { icon: customIcon })
                    .addTo(map)
                    .bindPopup(`
                        <div style="text-align: center;">
                            <strong style="font-size: 16px;"><?= addslashes($job['title']) ?></strong><br>
                            <span style="color: #666;"><?= addslashes($job['location']) ?></span>
                        </div>
                    `)
                    .openPopup();
                
                console.log('Map initialized successfully!');
            } catch (error) {
                console.error('Map initialization error:', error);
            }
        });
        
        function openGoogleMaps(lat, lng) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
            window.open(url, '_blank');
        }
    </script>
    <?php endif; ?>
</body>
</html>