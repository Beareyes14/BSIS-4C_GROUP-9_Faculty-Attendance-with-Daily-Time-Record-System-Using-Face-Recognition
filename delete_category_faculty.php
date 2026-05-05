<?php
require_once "config.php";

$type = $_POST['type'];
$id = intval($_POST['id']);

$table = ($type == "role") 
    ? "roles" 
    : (($type == "position") ? "positions" : "departments");

if ($conn->query("DELETE FROM $table WHERE id = $id")) {
    echo json_encode(["status" => "success", "message" => ucfirst($type) . " deleted successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete $type."]);
}
?>
