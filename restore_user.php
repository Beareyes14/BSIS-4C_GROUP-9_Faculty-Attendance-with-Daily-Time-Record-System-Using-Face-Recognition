<?php
// restore_user.php
require_once "config.php";
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = isset($_POST["id"]) ? intval($_POST["id"]) : 0;

    if ($id > 0) {
        // 🟢 NEW LOGIC: Put the previous_status back into status, and clear the memory bank
        $stmt = $conn->prepare("UPDATE users SET status = IFNULL(previous_status, 'Active'), previous_status = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "User restored to their original status!"]);
            } else {
                echo json_encode(["status" => "warning", "message" => "User is already restored or not found."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid user ID."]);
    }
}
$conn->close();
?>