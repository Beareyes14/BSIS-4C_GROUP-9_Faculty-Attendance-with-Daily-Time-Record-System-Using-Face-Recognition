<?php
require_once "config.php";
$result = $conn->query("SELECT id, name FROM categories ORDER BY id DESC");
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
echo json_encode($categories);
?>
