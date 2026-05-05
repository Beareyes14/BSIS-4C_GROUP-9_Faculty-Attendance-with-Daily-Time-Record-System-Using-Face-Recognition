<?php
// user_add_category.php
session_start();
require_once "config.php";
header('Content-Type: application/json; charset=utf-8');

// ✅ Allow only logged-in users (role = user)
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "user") {
    echo json_encode(["status" => "error", "message" => "Unauthorized access."]);
    exit();
}

// ✅ Get and validate category name
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$name = strip_tags($name);

if ($name === '') {
    echo json_encode(["status" => "error", "message" => "Category name cannot be empty."]);
    exit();
}
if (mb_strlen($name) > 50) {
    echo json_encode(["status" => "error", "message" => "Category name must be 50 characters or less."]);
    exit();
}

// ✅ Check for duplicates (case-insensitive)
$check = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
$check->bind_param("s", $name);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->close();
    echo json_encode(["status" => "error", "message" => "Category already exists."]);
    exit();
}
$check->close();

// ✅ Insert new category
$stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
$stmt->bind_param("s", $name);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Category '$name' added successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to add category."]);
}
$stmt->close();
$conn->close();
?>
