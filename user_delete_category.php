<?php
// user_delete_category.php
session_start();
require_once "config.php";
header('Content-Type: application/json; charset=utf-8');

// ✅ Allow only logged-in users (role = user)
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "user") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

// ✅ Get category ID
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid category ID."]);
    exit();
}

// ✅ Check if category exists
$check = $conn->prepare("SELECT id FROM categories WHERE id = ?");
$check->bind_param("i", $id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $check->close();
    echo json_encode(["status" => "error", "message" => "Category not found."]);
    exit();
}
$check->close();

// ✅ Delete category
$stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Category deleted successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete category."]);
}

$stmt->close();
$conn->close();
?>
