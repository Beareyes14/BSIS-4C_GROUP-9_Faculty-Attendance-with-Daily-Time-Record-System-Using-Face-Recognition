<?php
require_once "config.php";

if (!isset($_GET['id'])) {
    echo json_encode(["status" => "error", "message" => "Missing user ID"]);
    exit;
}

$id = intval($_GET['id']);

// 🟢 Include face_path AND status in the query
$stmt = $conn->prepare("SELECT id, name, email, role, position, department, face_path, status FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // 🟢 Check if user has a saved face image
    // 🟢 Handle photo path correctly for browser display
    if (!empty($user['face_path'])) {
        // Convert relative path to full web path
        $user['face_path'] = $user['face_path'];
        if (!file_exists(__DIR__ . '/' . $user['face_path'])) {
            // If the file doesn't exist on the server, use default
            $user['face_path'] = "images/default_face.jpg";
        }
    } else {
        $user['face_path'] = "images/default_face.jpg";
    }

    echo json_encode($user);
} else {
    echo json_encode(["status" => "error", "message" => "User not found"]);
}

$stmt->close();
$conn->close();
?>