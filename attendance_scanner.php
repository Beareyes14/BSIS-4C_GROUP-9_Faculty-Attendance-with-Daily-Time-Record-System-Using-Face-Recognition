<?php
// attendance_scanner.php
require_once "config.php";
date_default_timezone_set("Asia/Manila");

// ==========================================
//  FETCH SETTINGS
// ==========================================
$settings = [];
$res = $conn->query("SELECT name, value FROM settings");
while ($row = $res->fetch_assoc()) {
    $settings[$row['name']] = $row['value'];
}

$school_lat = floatval($settings['school_lat'] ?? 14.8527); 
$school_lon = floatval($settings['school_lng'] ?? 120.8160); 
$allowed_radius_meters = floatval($settings['school_radius_m'] ?? 75);
$location_mode = $settings['location_mode'] ?? 'school_only';

// ------------------------------------------
//  FETCH USERS (STRICT MODE)
// ------------------------------------------
$active_users = [];
$user_names_map = []; 

$user_q = $conn->query("SELECT id, name, face_path FROM users WHERE face_path IS NOT NULL AND face_path != ''"); 

while($u = $user_q->fetch_assoc()){
    if (file_exists($u['face_path'])) {
        $active_users[] = [
            'label' => $u['id'], 
            'path'  => $u['face_path']
        ];
        $user_names_map[$u['id']] = $u['name'];
    }
}

function getDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; 
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}

//  Handle AJAX Request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["user_id"])) {
    header('Content-Type: application/json');

    // 1. LOCATION CHECK
    if ($location_mode === 'school_only') {
        if (!isset($_POST['lat']) || !isset($_POST['lon'])) {
            echo json_encode(["status" => "error", "message" => "GPS Location is required."]);
            exit;
        }
        $user_lat = floatval($_POST['lat']);
        $user_lon = floatval($_POST['lon']);
        $distance = getDistance($school_lat, $school_lon, $user_lat, $user_lon);
        
        if ($distance > $allowed_radius_meters) {
            echo json_encode(["status" => "error", "message" => "NOT in school premises.\n(Distance: " . round($distance) . "m)"]);
            exit;
        }
    }

    // =========================================================
    // 2. REVISED TIME LOGIC
    // =========================================================
    $user_id = intval($_POST["user_id"]);
    $now = new DateTime("now", new DateTimeZone("Asia/Manila"));
    $curr_time = $now->format("H:i"); 
    $scan_date = $now->format("Y-m-d");
    $scan_time_db = $now->format("Y-m-d H:i:s");

    $currentAction = "";
    $currentStatus = "";

    // --- CHECK COOLDOWN (30 MINUTES) ---
    $cooldown_q = $conn->query("SELECT scan_time FROM attendance_logs WHERE user_id = $user_id ORDER BY scan_time DESC LIMIT 1");
    if ($row = $cooldown_q->fetch_assoc()) {
        $last_scan = strtotime($row['scan_time']);
        $seconds_passed = time() - $last_scan;
        
        if ($seconds_passed < 1800) { 
            echo json_encode(["status" => "warning", "message" => "Already scanned. Please wait before scanning again."]);
            exit;
        }
    }

    // --- DETERMINE CURRENT SLOT AND STATUS ---
    if ($curr_time >= "04:00" && $curr_time <= "11:29") {
        $currentAction = "Time In (AM)";
        $currentStatus = ($curr_time <= "07:00") ? "On Time" : "Late";
    } elseif ($curr_time >= "11:30" && $curr_time <= "12:40") {
        $currentAction = "Time Out (AM)";
        $currentStatus = ($curr_time < "12:00") ? "Undertime" : "Time Out (AM)";
    } elseif ($curr_time >= "12:41" && $curr_time <= "15:44") {
        $currentAction = "Time In (PM)";
        $currentStatus = ($curr_time <= "13:00") ? "On Time" : "Late";
    } elseif ($curr_time >= "15:45" && $curr_time <= "17:30") {
        $currentAction = "Time Out (PM)";
        $currentStatus = ($curr_time < "17:00") ? "Undertime" : "Time Out (PM)";
    } elseif ($curr_time >= "17:31" && $curr_time <= "21:00") {
        $currentAction = "Overtime";
        $currentStatus = "Overtime";
    } else {
        echo json_encode(["status" => "error", "message" => "❌ Scanner Closed. (Operating Hours: 4AM - 9PM)"]);
        exit;
    }

    $existing_logs = [];
    $log_query = $conn->query("SELECT action FROM attendance_logs WHERE user_id = $user_id AND DATE(scan_time) = '$scan_date'");
    while($row = $log_query->fetch_assoc()) {
        $existing_logs[] = $row['action'];
    }

    if (in_array($currentAction, $existing_logs)) {
        echo json_encode(["status" => "warning", "message" => "Already scanned for $currentAction today!"]);
        exit;
    }

    if ($currentAction === "Overtime" && in_array("Time Out (PM)", $existing_logs)) {
        echo json_encode(["status" => "error", "message" => "❌ Scan Failed: You already Timed Out earlier today."]);
        exit;
    }

    // --- AUTO-FILL GAPS ---
    if (in_array($currentAction, ["Time In (PM)", "Time Out (PM)", "Overtime"])) {
        if (in_array("Time In (AM)", $existing_logs) && !in_array("Time Out (AM)", $existing_logs)) {
            $auto_time = $scan_date . " 12:00:00";
            $conn->query("INSERT INTO attendance_logs (user_id, action, scan_time, status) VALUES ($user_id, 'Time Out (AM)', '$auto_time', 'Auto-Fill')");
        }
    }
    if (in_array($currentAction, ["Time Out (PM)", "Overtime"])) {
        if (!in_array("Time In (PM)", $existing_logs)) {
            $auto_time = $scan_date . " 13:00:00";
            $conn->query("INSERT INTO attendance_logs (user_id, action, scan_time, status) VALUES ($user_id, 'Time In (PM)', '$auto_time', 'Auto-Fill')");
        }
    }

    // --- DATABASE LOGGING ---
    $stmt = $conn->prepare("INSERT INTO attendance_logs (user_id, action, scan_time, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $currentAction, $scan_time_db, $currentStatus);
    
    if ($stmt->execute()) {
        $user_info = $conn->query("SELECT name FROM users WHERE id = $user_id")->fetch_assoc();
        $response = ["status" => "success", "message" => " $currentStatus ($currentAction)", "user_name" => $user_info['name']];
        
        $rep_check = $conn->query("SELECT * FROM attendance_reports WHERE faculty_id=$user_id AND date='$scan_date'")->fetch_assoc();
        if (!$rep_check) $conn->query("INSERT INTO attendance_reports (faculty_id, name, date) VALUES ($user_id, '{$user_info['name']}', '$scan_date')");
        
        if ($currentAction == "Time In (AM)") $conn->query("UPDATE attendance_reports SET time_in_am = TIME('$scan_time_db') WHERE faculty_id=$user_id AND date='$scan_date'");
        elseif ($currentAction == "Time Out (AM)") $conn->query("UPDATE attendance_reports SET time_out_am = TIME('$scan_time_db') WHERE faculty_id=$user_id AND date='$scan_date'");
        elseif ($currentAction == "Time In (PM)") $conn->query("UPDATE attendance_reports SET time_in_pm = TIME('$scan_time_db') WHERE faculty_id=$user_id AND date='$scan_date'");
        elseif ($currentAction == "Time Out (PM)" || $currentAction == "Overtime") $conn->query("UPDATE attendance_reports SET time_out_pm = TIME('$scan_time_db') WHERE faculty_id=$user_id AND date='$scan_date'");
        
        // Final Hour Calculation
        $conn->query("UPDATE attendance_reports SET total_hours = (
            (TIME_TO_SEC(IFNULL(time_out_am, '00:00:00')) - TIME_TO_SEC(IFNULL(time_in_am, '00:00:00'))) / 3600 + 
            (TIME_TO_SEC(IFNULL(time_out_pm, '00:00:00')) - TIME_TO_SEC(IFNULL(time_in_pm, '00:00:00'))) / 3600
        ) WHERE faculty_id = $user_id AND date = '$scan_date'");

        echo json_encode($response);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Error"]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Scanner</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    
    <style>
        body { margin: 0; padding: 0; background-color: #000; font-family: 'Inter', sans-serif; color: white; overflow: hidden; }
        .scanner-container { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; display: flex; justify-content: center; align-items: center; z-index: 1; background: black; }
        video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        canvas { position: absolute; top: 0; left: 0; z-index: 2; }
        .location-status { position: absolute; top: 20px; left: 20px; font-size: 14px; background: rgba(0,0,0,0.7); padding: 8px 15px; border-radius: 20px; color: #ccc; z-index: 10; border: 1px solid rgba(255,255,255,0.1); }
        .scan-line { position: absolute; width: 100%; height: 3px; background: #0f0; top: 0; z-index: 5; opacity: 0.8; display:none; box-shadow: 0 0 15px #0f0; animation: scanAnim 2s linear infinite; }
        @keyframes scanAnim { 0% { top: 0; } 50% { top: 100%; } 100% { top: 0; } }
    </style>
</head>
<body>

    <div class="location-status" id="locationStatus">📡 Initializing System...</div>

    <div class="scanner-container">
        <video id="video" autoplay muted playsinline></video>
        <div class="scan-line" id="scanLine"></div>
    </div>

<script>
    const locationMode = "<?= $location_mode ?>"; 
    const userData = <?php echo json_encode($active_users); ?>; 
    const userNames = <?php echo json_encode($user_names_map); ?>;
    
    let currentLat = null;
    let currentLon = null;
    let isProcessing = false;
    let labeledFaceDescriptors = null;
    let faceMatcher = null;

    // --- TUNING FOR SPEED & ACCURACY ---
    const MATCH_THRESHOLD = 0.45; // Stricter match for attendance (lower = stricter)
    const CONSECUTIVE_FRAMES = 5; // Require 5 frames of same face before logging
    
    let frameCounter = 0;
    let lastDetectedUser = null;

    function initLocation() {
        if (locationMode === 'school_only') {
            document.getElementById('locationStatus').innerText = "📍 Fetching GPS...";
            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(
                    (p) => {
                        currentLat = p.coords.latitude;
                        currentLon = p.coords.longitude;
                        document.getElementById('locationStatus').innerText = "✅ GPS Connected";
                        document.getElementById('locationStatus').style.color = "#4CAF50";
                    },
                    (e) => { document.getElementById('locationStatus').innerText = "❌ GPS Error"; },
                    { enableHighAccuracy: true }
                );
            }
        } else {
            document.getElementById('locationStatus').innerText = "🌐 Online Mode";
        }
    }

    async function loadModelsAndStart() {
        try {
            // Load Tiny Face Detector for faster real-time performance
            await faceapi.nets.tinyFaceDetector.loadFromUri('./models');
            await faceapi.nets.faceLandmark68Net.loadFromUri('./models');
            await faceapi.nets.faceRecognitionNet.loadFromUri('./models');
            
            labeledFaceDescriptors = await loadLabeledImages();
            if (labeledFaceDescriptors.length > 0) {
                faceMatcher = new faceapi.FaceMatcher(labeledFaceDescriptors, MATCH_THRESHOLD);
            }
            startVideo();
        } catch (error) {
            console.error(error);
            Swal.fire("Error", "Could not load AI models.", "error");
        }
    }

    function startVideo() {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } }) 
            .then(stream => { document.getElementById('video').srcObject = stream; })
            .catch(err => { console.error("Camera Error:", err); });
    }

    async function loadLabeledImages() {
        return Promise.all(
            userData.map(async user => {
                const descriptions = [];
                try {
                    const img = await faceapi.fetchImage(user.path);
                    const detections = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
                    if(detections) descriptions.push(detections.descriptor);
                } catch(e) { console.warn(`Skipping ID ${user.label}: Image error`); }
                return new faceapi.LabeledFaceDescriptors(String(user.label), descriptions);
            })
        );
    }

    const video = document.getElementById('video');
    video.addEventListener('play', () => {
        const canvas = faceapi.createCanvasFromMedia(video);
        document.querySelector('.scanner-container').append(canvas);
        const displaySize = { width: video.clientWidth, height: video.clientHeight };
        faceapi.matchDimensions(canvas, displaySize);

        setInterval(async () => {
            if(isProcessing || !faceMatcher) return;

            const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptors();
                
            const resizedDetections = faceapi.resizeResults(detections, displaySize);
            canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
            
            const results = resizedDetections.map(d => faceMatcher.findBestMatch(d.descriptor));
            
            if (results.length === 0) {
                frameCounter = 0;
                lastDetectedUser = null;
                document.getElementById('scanLine').style.display = 'none';
            }

            results.forEach((result, i) => {
                const box = resizedDetections[i].detection.box;
                let label = result.label;
                let boxColor = 'red'; 

                if (label !== 'unknown') {
                    boxColor = '#00ff00';
                    document.getElementById('scanLine').style.display = 'block';

                    if (lastDetectedUser === label) {
                        frameCounter++;
                    } else {
                        frameCounter = 0;
                        lastDetectedUser = label;
                    }

                    if (frameCounter >= CONSECUTIVE_FRAMES) {
                        onFaceDetected(result.label);
                        frameCounter = 0; 
                    }
                    
                    if(userNames[label]) label = userNames[label];
                } else {
                    frameCounter = 0;
                }

                new faceapi.draw.DrawBox(box, { label: label, boxColor: boxColor, lineWidth: 2 }).draw(canvas);
            });
        }, 150); // Fast interval for responsive scanning
    });

    function onFaceDetected(detectedUserId) {
        if (isProcessing) return;
        if (locationMode === 'school_only' && (currentLat === null || currentLon === null)) return;

        isProcessing = true;
        document.getElementById('scanLine').style.boxShadow = "0 0 30px #00ff00";

        const formData = new FormData();
        formData.append('user_id', detectedUserId);
        if (currentLat) formData.append('lat', currentLat);
        if (currentLon) formData.append('lon', currentLon);

        fetch('attendance_scanner.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            const Toast = Swal.mixin({
                toast: true, position: "top-end", showConfirmButton: false, timer: 3000,
                timerProgressBar: true, background: "#F9F7E8", color: "#1F4015"
            });

            Toast.fire({
                icon: data.status === 'success' ? 'success' : (data.status === 'warning' ? 'warning' : 'error'),
                title: data.status === 'success' ? 'Recorded!' : 'Attention',
                text: data.message
            });

            setTimeout(() => { 
                isProcessing = false; 
                document.getElementById('scanLine').style.display = 'none';
                document.getElementById('scanLine').style.boxShadow = "0 0 15px #0f0"; 
                lastDetectedUser = null; 
            }, 3000); 
        }).catch(err => { isProcessing = false; });
    }

    initLocation();
    loadModelsAndStart();
</script>
</body>
</html>