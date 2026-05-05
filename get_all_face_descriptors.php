<?php
require_once "config.php";
header('Content-Type: application/json');

// Select only users who have a face descriptor saved
$sql = "SELECT id, name, face_descriptor FROM users WHERE face_descriptor IS NOT NULL AND face_descriptor != ''";
$result = $conn->query($sql);

$users = [];
while($row = $result->fetch_assoc()) {
    $users[] = [
        'label' => $row['name'], 
        'descriptor' => json_decode($row['face_descriptor'])
    ];
}

echo json_encode($users);
?>