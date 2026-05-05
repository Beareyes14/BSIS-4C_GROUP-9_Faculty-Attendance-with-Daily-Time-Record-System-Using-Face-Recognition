<?php
// user_get_categories.php
session_start();
require_once "config.php";
header('Content-Type: application/json; charset=utf-8');

// ✅ Allow only logged-in users (role = user)
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "user") {
    echo json_encode([]);
    exit();
}

// ✅ Fetch all categories
$result = $conn->query("SELECT id, name FROM categories ORDER BY id DESC");

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        "id" => $row['id'],
        "name" => $row['name']
    ];
}

echo json_encode($categories);
$conn->close();
?>
