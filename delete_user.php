<?php
// delete_user.php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

$id = intval($_POST["id"] ?? 0);
if (!$id) {
    echo json_encode(["status" => "error", "message" => "Missing user ID"]);
    exit;
}

// 🟢 NEW LOGIC: Copy current 'status' into 'previous_status', THEN set to 'Archived'
$archive = $conn->prepare("UPDATE users SET previous_status = status, status = 'Archived' WHERE id = ?");
$archive->bind_param("i", $id);

if ($archive->execute()) {
    echo json_encode(["status" => "success", "message" => "User archived successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to archive user."]);
}

$archive->close();
$conn->close();
?>