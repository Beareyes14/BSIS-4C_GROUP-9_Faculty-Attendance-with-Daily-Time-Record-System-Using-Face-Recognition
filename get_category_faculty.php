<?php
require_once "config.php";

header('Content-Type: application/json');

function fetchAll($conn, $table) {
    $data = [];
    $result = $conn->query("SELECT id, name FROM $table ORDER BY id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row; // includes both id and name
        }
    }
    return $data;
}

echo json_encode([
    "roles" => fetchAll($conn, "roles"),
    "positions" => fetchAll($conn, "positions"),
    "departments" => fetchAll($conn, "departments")
]);
?>
