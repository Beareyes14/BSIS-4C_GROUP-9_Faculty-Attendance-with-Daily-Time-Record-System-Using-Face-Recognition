<?php
// ✅ FORCE BROWSER TO NEVER CACHE THIS PAGE (Fixes the "last person" ghosting issue)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require_once "config.php"; 
date_default_timezone_set("Asia/Manila");

// ✅ SESSION CLEARING (Completely destroys old login data)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    session_unset();     
    session_destroy();   
    session_start();     
}

// ==========================================
// 1. AJAX HANDLER: PROCESS FACE LOGIN & ATTENDANCE
// ==========================================
// If the request is an AJAX POST (JSON), handle it here and stop the script.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
    
    $payload = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($payload['face_user'] ?? 0); 
    $scan_mode = $payload['scan_mode'] ?? 'login'; 
    $user_lat = floatval($payload['lat'] ?? 0);
    $user_lon = floatval($payload['lon'] ?? 0);

    if ($user_id === 0) {
        echo json_encode(["success" => false, "message" => "Invalid User ID."]);
        exit();
    }

    // Fetch Settings
    $settings = [];
    $res = $conn->query("SELECT name, value FROM settings");
    while ($row = $res->fetch_assoc()) {
        $settings[$row['name']] = $row['value'];
    }
    $school_lat = floatval($settings['school_lat'] ?? 14.8527); 
    $school_lon = floatval($settings['school_lng'] ?? 120.8160); 
    $allowed_radius_meters = floatval($settings['school_radius_m'] ?? 75);
    $location_mode = $settings['location_mode'] ?? 'school_only';

    function getDistance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371000; 
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth_radius * $c;
    }

    $stmt = $conn->prepare("SELECT id, name, role, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        
        // 🛑 Check Active Status
        if ($user['status'] === 'inactive' || $user['status'] === 'Archived') {
            echo json_encode(["success" => false, "message" => "Account is inactive or archived. Please contact Admin."]);
            exit();
        }

        $valid_location = true;
        
        // ---------------------------------------------------------
        // 🕒 AUTOMATIC ATTENDANCE LOGIC
        // ---------------------------------------------------------
        if ($scan_mode === 'attendance') { 
            
            // A. Location Check
            if ($location_mode === 'school_only' || $location_mode === 'restricted' || $location_mode === 'location_restricted' || $location_mode === 'face_to_face') {
                if ($user_lat === 0 || $user_lon === 0) {
                    echo json_encode(["success" => false, "message" => "⚠️ Attendance Failed! Could not detect your GPS location."]);
                    exit();
                } else {
                    $distance = getDistance($school_lat, $school_lon, $user_lat, $user_lon);
                    if ($distance > $allowed_radius_meters) {
                        echo json_encode(["success" => false, "message" => "📍 Attendance Failed! You are outside the designated school premises. (Distance: " . round($distance) . "m)"]);
                        exit();
                    }
                }
            }

            // B. Time & Status Logic
            $now = new DateTime("now", new DateTimeZone("Asia/Manila"));
            $curr_time = $now->format("H:i"); 
            $scan_date = $now->format("Y-m-d");
            $scan_time_db = $now->format("Y-m-d H:i:s");

            $currentAction = "";
            $currentStatus = "";

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
                echo json_encode(["success" => false, "message" => "❌ Scanner Closed. (Operating Hours: 4AM - 9PM)"]);
                exit();
            }

            if ($currentAction !== "") {
                
                $existing_logs = [];
                $log_query = $conn->query("SELECT action FROM attendance_logs WHERE user_id = $user_id AND DATE(scan_time) = '$scan_date'");
                while($row = $log_query->fetch_assoc()) {
                    $existing_logs[] = $row['action'];
                }

                // CHECK IF ALREADY SCANNED FOR THIS PERIOD
                if (in_array($currentAction, $existing_logs)) {
                    echo json_encode(["success" => false, "message" => "❌ You have already recorded your $currentAction for today."]);
                    exit();
                }

                // CHECK COOLDOWN
                $cooldown_q = $conn->query("SELECT scan_time FROM attendance_logs WHERE user_id = $user_id ORDER BY scan_time DESC LIMIT 1");
                if ($row = $cooldown_q->fetch_assoc()) {
                    if ((time() - strtotime($row['scan_time'])) < 1800) {
                        echo json_encode(["success" => false, "message" => "❌ Please wait 30 minutes between scans."]);
                        exit();
                    }
                }
                
                // Prevent overtime if already timed out PM
                if ($currentAction === "Overtime" && in_array("Time Out (PM)", $existing_logs)) {
                    echo json_encode(["success" => false, "message" => "❌ Cannot record Overtime after Time Out (PM)."]);
                    exit();
                }

                // Proceed to Log
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

                $stmt_log = $conn->prepare("INSERT INTO attendance_logs (user_id, action, scan_time, status) VALUES (?, ?, ?, ?)");
                $stmt_log->bind_param("isss", $user_id, $currentAction, $scan_time_db, $currentStatus);
                $stmt_log->execute();
                
                $rep_check = $conn->query("SELECT * FROM attendance_reports WHERE faculty_id=$user_id AND date='$scan_date'")->fetch_assoc();
                if (!$rep_check) $conn->query("INSERT INTO attendance_reports (faculty_id, name, date) VALUES ($user_id, '{$user['name']}', '$scan_date')");
                
                if ($currentAction == "Time In (AM)") $conn->query("UPDATE attendance_reports SET time_in_am = TIME('$scan_time_db') WHERE faculty_id=$user_id AND date='$scan_date'");
                elseif ($currentAction == "Time Out (AM)") $conn->query("UPDATE attendance_reports SET time_out_am = TIME('$scan_time_db') WHERE faculty_id=$user_id AND date='$scan_date'");
                elseif ($currentAction == "Time In (PM)") $conn->query("UPDATE attendance_reports SET time_in_pm = TIME('$scan_time_db') WHERE faculty_id=$user_id AND date='$scan_date'");
                elseif ($currentAction == "Time Out (PM)" || $currentAction == "Overtime") $conn->query("UPDATE attendance_reports SET time_out_pm = TIME('$scan_time_db') WHERE faculty_id=$user_id AND date='$scan_date'");
                
                $conn->query("UPDATE attendance_reports 
                    SET 
                    time_out_am = IF(time_out_am IS NULL AND EXISTS(SELECT 1 FROM attendance_logs WHERE user_id=$user_id AND date(scan_time)='$scan_date' AND action='Time Out (AM)'), '12:00:00', time_out_am),
                    time_in_pm = IF(time_in_pm IS NULL AND EXISTS(SELECT 1 FROM attendance_logs WHERE user_id=$user_id AND date(scan_time)='$scan_date' AND action='Time In (PM)'), '13:00:00', time_in_pm)
                    WHERE faculty_id=$user_id AND date='$scan_date'");

                $conn->query("UPDATE attendance_reports SET total_hours = (
                    (TIME_TO_SEC(IFNULL(time_out_am, '00:00:00')) - TIME_TO_SEC(IFNULL(time_in_am, '00:00:00'))) / 3600 + 
                    (TIME_TO_SEC(IFNULL(time_out_pm, '00:00:00')) - TIME_TO_SEC(IFNULL(time_in_pm, '00:00:00'))) / 3600
                ) WHERE faculty_id = $user_id AND date = '$scan_date'");
                
                $_SESSION['just_clocked_in'] = "$currentAction recorded as $currentStatus.";
            } else {
                echo json_encode(["success" => false, "message" => "❌ Attendance cannot be recorded at this time."]);
                exit();
            }
        }

        // ✅ Perform the actual Login
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        $redirect_url = ($user['role'] === 'admin') ? "admin_dashboard.php" : "user_dashboard.php";

        echo json_encode(["success" => true, "redirect" => $redirect_url]);
        exit();

    } else {
        echo json_encode(["success" => false, "message" => "Face recognized but user data missing."]);
        exit();
    }
}

// ==========================================
// 2. LOAD UI AND USERS FOR JAVASCRIPT
// ==========================================
$active_users = [];
$user_names_map = []; 
$user_q = $conn->query("SELECT id, name, face_path FROM users WHERE face_path IS NOT NULL AND face_path != '' AND status != 'Archived' AND status != 'inactive'"); 
while($u = $user_q->fetch_assoc()){
    // ✅ BUG FIX: Do not load users who only have the default face, to prevent false matches
    if (file_exists($u['face_path']) && strpos($u['face_path'], 'default_face') === false) {
        $active_users[] = ['label' => $u['id'], 'path'  => $u['face_path']];
        $user_names_map[$u['id']] = $u['name'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Face Recognition Login & Clock-In</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: url("images/bg.JPG") no-repeat center center/cover; position: relative; overflow: hidden; padding: 20px; }
        body::before { content: ""; position: absolute; inset: 0; background: #1F4015; opacity: 0.6; }
        
        .shape-yellow { position: absolute; width: 385px; height: 1100px; right: 10%; top: -350px; background: #EEC752; border-radius: 200px; transform: rotate(-135deg); z-index: 0; }
        .shape-white { position: absolute; width: 385px; height: 1100px; right: 10%; bottom: -350px; background: #FFF8F8; border-radius: 200px; border: 1px solid #FFFFFF; transform: rotate(135deg); z-index: 0; }
        .shape-grey1, .shape-grey2, .shape-grey3 { position: absolute; width: 340px; height: 340px; background: rgba(217,217,217,0.5); border: 1px solid #F9F7E8; border-radius: 14px; transform: rotate(45deg); z-index: 0; }
        .shape-grey1 { top: -200px; left: 55.5%; }
        .shape-grey2 { top: 290px; right: -150px; }
        .shape-grey3 { bottom: -175px; left: 53%; }
        
        .login-wrapper { position: relative; z-index: 1; display: flex; align-items: center; justify-content: center; gap: 40px; width: 100%; max-width: 1100px; flex-wrap: wrap; }
        .login-container { background: #F9F7E8; border-radius: 30px; padding: 30px; width: 100%; max-width: 600px; box-shadow: 0px 0px 30px rgba(0,0,0,0.5); text-align: center; }
        
        .logo-section { display: flex; justify-content: center; align-items: center; z-index: 2; }
        .logo-section img { width: 100%; max-width: 350px; height: auto; object-fit: contain; border-radius: 50%; box-shadow: 0px 0px 30px rgba(0,0,0,1); }
        
        .face-rectangle { width: 100%; height: auto; min-height: 400px; background: #AAB396; border-radius: 20px; margin-bottom: 25px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; font-weight: 600; text-shadow: 0px 2px 4px rgba(0,0,0,0.3); position: relative; overflow: hidden; }
        .face-rectangle video { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 20px; object-fit: cover; transform: scaleX(-1); }
        
        button { width: 100%; padding: 14px; background-color: #5D6F47; color: #ECE5E5; border: none; border-radius: 15px; cursor: pointer; font-size: 18px; font-weight: 800; text-shadow: 0px 2px 4px rgba(0,0,0,0.25); margin-bottom: 10px; }
        button:hover { background-color: #4a5b36; }
        
        #livenessMsg { position: absolute; bottom: 20px; left: 0; width: 100%; text-align: center; color: #FFF; font-weight: bold; font-size: 20px; text-shadow: 2px 2px 4px #000; background: rgba(0,0,0,0.5); padding: 10px; }
        #locationMsg { display: none; position: absolute; top: 15px; left: 15px; font-size: 14px; background: rgba(0,0,0,0.7); padding: 8px 12px; border-radius: 10px; z-index: 10; color: white; }

        .toggle-container { display: flex; position: relative; background: #D9D9D9; border-radius: 30px; width: 100%; max-width: 360px; margin: 15px auto 25px auto; overflow: hidden; cursor: pointer; box-shadow: inset 0px 2px 4px rgba(0,0,0,0.2); border: 2px solid #1F4015; }
        .toggle-slider { position: absolute; top: 0; left: 0; width: 50%; height: 100%; background: #5D6F47; border-radius: 30px; transition: 0.3s ease; z-index: 1; }
        .toggle-option { flex: 1; padding: 12px 0; text-align: center; z-index: 2; font-weight: bold; font-size: 15px; color: #1F4015; transition: 0.3s ease; }
        .toggle-option.active { color: white; }

        @media (max-width: 900px) { 
            .login-wrapper { flex-direction: column-reverse; gap: 20px; padding-top: 20px; } 
            .logo-section img { max-width: 150px; margin-top: 10px; } 
            .login-container { padding: 20px; border-radius: 20px; }
            .face-rectangle { min-height: 300px; }
            #livenessMsg { font-size: 16px; bottom: 10px; }
            .shape-yellow, .shape-white { display: none; }
        }
    </style>
</head>
<body>

    <?php if (isset($_GET['error'])): ?>
    <script>
        let errText = "Error.";
        if ("<?= $_GET['error'] ?>" === "not_active") errText = "Account is inactive or archived. Please contact Admin.";
        if ("<?= $_GET['error'] ?>" === "not_found") errText = "Face recognized but user data missing.";
        
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: errText,
                confirmButtonColor: '#1F4015'
            }).then(() => {
                window.history.replaceState({}, document.title, "face_login.php");
            });
        });
    </script>
    <?php endif; ?>

    <div class="shape-yellow"></div>
    <div class="shape-white"></div>
    <div class="shape-grey1"></div>
    <div class="shape-grey2"></div>
    <div class="shape-grey3"></div>

    <div class="login-wrapper">
        <div class="login-container">
            <h2 style="color:#1F4015;">Face Recognition Login</h2>

            <div class="toggle-container" onclick="toggleMode()">
                <div class="toggle-slider" id="toggleSlider"></div>
                <div class="toggle-option active" id="optLogin">Login Only</div>
                <div class="toggle-option" id="optAttendance">Attendance</div>
            </div>

            <div class="face-rectangle">
                <div id="locationMsg">Fetching GPS...</div>
                <video id="cameraFeed" autoplay muted playsinline></video>
                <div id="livenessMsg">Initializing...</div>
            </div>

            <a href="login.php">
                <button type="button">Manual Login</button>
            </a>
        </div>

        <div class="logo-section">
            <img src="images/logo.png" alt="School Logo">
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
// ✅ BUG FIX: Forces the page to hard-reload if the user clicks "Back" after logging out
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        window.location.reload();
    }
});

const userData = <?php echo json_encode($active_users); ?>; 
const userNames = <?php echo json_encode($user_names_map); ?>;

let scanMode = 'login'; 

function toggleMode() {
    if (scanMode === 'login') {
        scanMode = 'attendance';
        document.getElementById('toggleSlider').style.left = '50%';
        document.getElementById('optAttendance').classList.add('active');
        document.getElementById('optLogin').classList.remove('active');
        document.getElementById('locationMsg').style.display = 'block'; 
        fetchGPS(); 
    } else {
        scanMode = 'login';
        document.getElementById('toggleSlider').style.left = '0';
        document.getElementById('optLogin').classList.add('active');
        document.getElementById('optAttendance').classList.remove('active');
        document.getElementById('locationMsg').style.display = 'none'; 
    }
}

let currentLat = "";
let currentLon = "";
const locMsg = document.getElementById("locationMsg");

function fetchGPS() {
    if (navigator.geolocation) {
        locMsg.innerText = "📍 Fetching GPS...";
        locMsg.style.color = "white";
        navigator.geolocation.getCurrentPosition(
            (pos) => { 
                currentLat = pos.coords.latitude; 
                currentLon = pos.coords.longitude; 
                locMsg.innerText = "📍 GPS Ready"; 
                locMsg.style.color = "#4CAF50"; 
            },
            (err) => { 
                locMsg.innerText = "⚠️ GPS Error. Attendance may fail."; 
                locMsg.style.color = "#FFC107"; 
            }
        );
    } else {
        locMsg.innerText = "⚠️ GPS Not Supported."; 
    }
}

const video = document.getElementById("cameraFeed");
const msg = document.getElementById("livenessMsg");
let matcher = null;
let isProcessing = false;

// --- REVISED RECOGNITION LOGIC ---
const STRICT_THRESHOLD = 0.40; // Prevent "Rhey Santos" default matching
let lastRatio = 0;
let blinkCount = 0;

console.log("🔄 Loading AI Models...");
Promise.all([
  faceapi.nets.ssdMobilenetv1.loadFromUri("./models"), 
  faceapi.nets.faceLandmark68Net.loadFromUri("./models"),
  faceapi.nets.faceRecognitionNet.loadFromUri("./models")
]).then(startSystem).catch(err => {
  console.error("❌ MODEL ERROR:", err);
  Swal.fire({ icon: 'error', title: 'Error', text: 'Error loading models.' });
});

async function startSystem() {
  console.log("✅ Models Loaded.");
  navigator.mediaDevices.getUserMedia({ video: {} })
    .then(stream => video.srcObject = stream)
    .catch(err => Swal.fire({ icon: 'error', title: 'Camera Error', text: err }));

  console.log("🔄 Processing face database...");
  const labeledDescriptors = await loadLabeledImages();

  if (labeledDescriptors.length > 0) {
    // Apply strict threshold to prevent defaulting to the first user in DB
    matcher = new faceapi.FaceMatcher(labeledDescriptors, STRICT_THRESHOLD); 
    console.log("✅ System Ready");
    msg.innerText = "Blink your eyes to verify";
    startRecognitionLoop();
  } else {
    msg.innerText = "No Users Registered";
  }
}

async function loadLabeledImages() {
  return Promise.all(
      userData.map(async user => {
          const descriptions = [];
          try {
              const img = await faceapi.fetchImage(user.path + '?nocache=' + new Date().getTime());
              const detections = await faceapi.detectSingleFace(img, new faceapi.SsdMobilenetv1Options()).withFaceLandmarks().withFaceDescriptor();
              
              // Only add if the reference image is high enough quality to produce a descriptor
              if(detections && detections.descriptor) {
                  descriptions.push(detections.descriptor);
              } else {
                  console.warn(`Skipping User ${user.label}: Bad reference photo.`);
              }
          } catch(e) { console.log(`Could not load image for User ID ${user.label}`); }
          return new faceapi.LabeledFaceDescriptors(String(user.label), descriptions);
      })
  );
}

function getEyeRatio(eyePoints) {
    const a = Math.hypot(eyePoints[1].x - eyePoints[5].x, eyePoints[1].y - eyePoints[5].y);
    const b = Math.hypot(eyePoints[2].x - eyePoints[4].x, eyePoints[2].y - eyePoints[4].y);
    const c = Math.hypot(eyePoints[0].x - eyePoints[3].x, eyePoints[0].y - eyePoints[3].y);
    return (a + b) / (2.0 * c);
}

function startRecognitionLoop() {
  setInterval(async () => {
    if (!matcher || isProcessing) return;

    const detection = await faceapi
      .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.85 }))
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (detection) {
      const result = matcher.findBestMatch(detection.descriptor);

      if (result.label !== "unknown") {
        const landmarks = detection.landmarks;
        const avgRatio = (getEyeRatio(landmarks.getLeftEye()) + getEyeRatio(landmarks.getRightEye())) / 2;

        // PHOTO PREVENTION: Only count a blink if the eye ratio actually CHANGES.
        // Static photos of closed eyes will have a delta of ~0.
        if (Math.abs(lastRatio - avgRatio) > 0.05) { 
            if (avgRatio < 0.27) { 
                blinkCount++;
            }
        }
        lastRatio = avgRatio;

        if (blinkCount >= 1) {
            if (scanMode === 'attendance' && (!currentLat || !currentLon)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'GPS Not Ready',
                    text: 'Please wait for GPS lock before scanning.',
                    timer: 2000,
                    showConfirmButton: false
                });
                blinkCount = 0; // Reset
                return; 
            }

            isProcessing = true; 
            blinkCount = 0; // Reset
            
            let userNameDisplay = userNames[result.label] || "User";
            msg.innerText = "Verified ✅";
            msg.style.color = "#1F4015";
            
            fetch('face_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    face_user: result.label,
                    scan_mode: scanMode,
                    lat: currentLat,
                    lon: currentLon
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Welcome ' + userNameDisplay + '!',
                        text: 'Logging in...',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        confirmButtonColor: '#1F4015'
                    }).then(() => { window.location.href = data.redirect; });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Scan Blocked',
                        text: data.message,
                        confirmButtonColor: '#1F4015'
                    }).then(() => { isProcessing = false; });
                }
            })
            .catch(error => {
                console.error("Fetch Error:", error);
                isProcessing = false;
            });

        } else {
            msg.innerText = "Blink slowly to verify identity";
            msg.style.color = "yellow";
        }

      } else {
        msg.innerText = "Face not recognized";
        msg.style.color = "red";
      }
    } else {
        msg.innerText = "Looking for face...";
        msg.style.color = "white";
    }
  }, 200); 
}
</script>

</body>
</html>