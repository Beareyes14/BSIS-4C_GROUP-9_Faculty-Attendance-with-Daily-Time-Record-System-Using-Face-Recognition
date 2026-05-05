<?php
//delete_archive_user.php - Endpoint to delete a single archived user permanently (AJAX)
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = intval($_POST["id"]);

    // 🟢 FIX: Delete from the users table. 
    // (Note: If your database has foreign keys set up correctly, this will auto-delete their logs too).
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND status = 'Archived'");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "User and their records permanently deleted."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error deleting user."]);
    }
}
?>