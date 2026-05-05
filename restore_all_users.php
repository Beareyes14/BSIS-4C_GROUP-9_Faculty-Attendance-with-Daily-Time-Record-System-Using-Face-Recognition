<?php
// restore_all_users.php
require_once "config.php";
header('Content-Type: application/json; charset=utf-8');

// 🟢 NEW LOGIC: Put the previous_status back for EVERYONE, and clear the memory banks
$sql = "UPDATE users SET status = IFNULL(previous_status, 'Active'), previous_status = NULL WHERE status = 'Archived'";

if ($conn->query($sql)) {
    echo json_encode([
        "status" => "success",
        "message" => "✅ All users restored to their original statuses."
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "❌ Error restoring users: " . $conn->error
    ]);
}

$conn->close();
?>