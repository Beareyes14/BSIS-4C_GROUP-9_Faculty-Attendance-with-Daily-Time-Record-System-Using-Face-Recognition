<?php
require_once "config.php";
date_default_timezone_set('Asia/Manila');

// Today's date
$today = date("Y-m-d");

// Fetch all active users (faculty + admin)
$users = $conn->query("SELECT id FROM users WHERE role IN ('user','admin')");

// Loop through each user
while ($u = $users->fetch_assoc()) {
    $uid = $u['id'];

    // Check if user already has a scan today
    $check = $conn->prepare("
        SELECT id FROM attendance_logs
        WHERE user_id = ? AND DATE(scan_time) = ?
    ");
    $check->bind_param("is", $uid, $today);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        // No scan yet → mark as Absent
        $insert = $conn->prepare("
            INSERT INTO attendance_logs (user_id, action, scan_time, status)
            VALUES (?, 'No Scan', NOW(), 'Absent')
        ");
        $insert->bind_param("i", $uid);
        $insert->execute();
        $insert->close();
    }

    $check->close();
}

echo "✅ Absent users successfully marked for $today.";
?>
