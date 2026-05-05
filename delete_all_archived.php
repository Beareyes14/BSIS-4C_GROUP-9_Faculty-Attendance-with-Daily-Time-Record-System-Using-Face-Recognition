<?php
//delete_all_archived.php - Endpoint to delete all archived users permanently (AJAX)
require_once "config.php";

// 🟢 FIX: Delete from the users table where status is Archived
if ($conn->query("DELETE FROM users WHERE status = 'Archived'")) {
    echo json_encode(["status" => "success", "message" => "All archived users permanently deleted."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete archived users."]);
}
?>