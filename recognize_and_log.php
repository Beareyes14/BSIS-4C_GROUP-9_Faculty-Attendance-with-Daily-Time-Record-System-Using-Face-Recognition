<?php
session_start();
require_once "config.php";
date_default_timezone_set("Asia/Manila");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Helper function to write to a log file
function debugLog($message) {
    $time = date("Y-m-d H:i:s");
    file_put_contents("scanner_debug.txt", "[$time] " . $message . PHP_EOL, FILE_APPEND);
}

debugLog("--- NEW SCAN ATTEMPT STARTED ---");

// 1. CHECK SCANNER STATUS
$st = $conn->query("SELECT status FROM scanner_status WHERE id=1")->fetch_assoc();
if (!$st || $st['status'] !== 'active') {
    debugLog("ERROR: Scanner inactive or unauthorized.");
    echo json_encode(['success'=>false, 'message'=>'Scanner inactive.']);
    exit();
}

// 2. READ PAYLOAD DESCRIPTOR
$raw_data = file_get_contents('php://input');
debugLog("Received Raw Data length: " . strlen($raw_data));

$payload = json_decode($raw_data, true);
$desc = $payload['descriptor'] ?? null;
if (!$desc || !is_array($desc)) {
    debugLog("ERROR: No descriptor provided by the frontend Javascript.");
    echo json_encode(['success'=>false, 'message'=>'No descriptor provided.']);
    exit();
}

// 3. MATCH FACE ALGORITHM
$res = $conn->query("SELECT id, name, face_descriptor FROM users WHERE face_descriptor IS NOT NULL AND face_descriptor != ''");
if (!$res) {
    debugLog("SQL ERROR (users table): " . $conn->error);
}

$bestUserId = null;
$bestUserName = null;
$bestDist = 999;

function euclidean($a, $b) {
    $sum = 0.0;
    $n = min(count($a), count($b));
    for ($i=0; $i<$n; $i++) $sum += ($a[$i]-$b[$i])**2;
    return sqrt($sum);
}

while ($row = $res->fetch_assoc()) {
    $enc = json_decode($row['face_descriptor'], true);
    if (!$enc || !is_array($enc)) continue;
    
    $dist = euclidean($enc, $desc);
    if ($dist < $bestDist) {
        $bestDist = $dist;
        $bestUserId = (int)$row['id'];
        $bestUserName = $row['name'];
    }
}

debugLog("Best Match: ID $bestUserId ($bestUserName) with Distance: $bestDist");

// 🔧 If 0.52 is too strict, the face won't match. We log the distance to see.
$THRESHOLD = 0.52;

if ($bestDist > $THRESHOLD || !$bestUserId) {
    debugLog("FAILED: Face not recognized (Distance $bestDist is > $THRESHOLD)");
    echo json_encode(['success'=>false, 'message'=>'Face not recognized']);
    exit();
}

debugLog("SUCCESS: Face matched! Proceeding to time logic...");

// 4. ATTENDANCE TIME LOGIC
$user_id = $bestUserId;
$now = new DateTime("now", new DateTimeZone("Asia/Manila"));
$curr_time = $now->format("H:i");
$scan_date = $now->format("Y-m-d");
$scan_time_db = $now->format("Y-m-d H:i:s");

// ... (simplified for debugging) ...
$currentAction = "Time In (AM)"; // Hardcoded temporarily just to see if DB works
$currentStatus = "On Time";

// 5. DATABASE LOGGING
debugLog("Attempting to insert into attendance_logs...");
$stmt = $conn->prepare("INSERT INTO attendance_logs (user_id, action, scan_time, status) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $currentAction, $scan_time_db, $currentStatus);
$ok = $stmt->execute();

if (!$ok) {
    debugLog("SQL ERROR (attendance_logs): " . $stmt->error);
    echo json_encode(["success" => false, "message" => "Database Error Logs."]);
    exit;
}
debugLog("Successfully inserted into attendance_logs.");

debugLog("Attempting to update attendance_reports...");
$rep_check = $conn->query("SELECT * FROM attendance_reports WHERE faculty_id=$user_id AND date='$scan_date'")->fetch_assoc();
if (!$rep_check) {
    $insert_rep = $conn->query("INSERT INTO attendance_reports (faculty_id, name, date, time_in_am) VALUES ($user_id, '{$bestUserName}', '$scan_date', TIME('$scan_time_db'))");
    if (!$insert_rep) {
        debugLog("SQL ERROR (attendance_reports insert): " . $conn->error);
    }
} else {
    $update_rep = $conn->query("UPDATE attendance_reports SET time_in_am = TIME('$scan_time_db') WHERE faculty_id=$user_id AND date='$scan_date'");
    if (!$update_rep) {
         debugLog("SQL ERROR (attendance_reports update): " . $conn->error);
    }
}

debugLog("--- SCAN COMPLETE ---");
echo json_encode(["success" => true, "message" => "Test log successful."]);
exit;
?>