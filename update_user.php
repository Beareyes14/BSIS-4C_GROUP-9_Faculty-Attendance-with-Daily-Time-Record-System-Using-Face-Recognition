<?php
session_start(); // Ensure session is started for Admin check
require_once "config.php";

// Load PHPMailer Manually (Adjust path if your PHPMailer is in a different folder)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Security Check
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

// 2. Get POST Data
$id = intval($_POST["id"] ?? 0);
$name = trim($_POST["name"] ?? '');
$new_email = trim($_POST["email"] ?? '');
$role = trim($_POST["role"] ?? '');
$position = trim($_POST["position"] ?? '');
$department = trim($_POST["department"] ?? '');
$status = trim($_POST["status"] ?? 'Active'); // 🟢 Status Logic Included

$captured_image = $_POST["captured_edit_image"] ?? null;
$face_descriptor = $_POST["edit_face_descriptor"] ?? null;

if (!$id || !$name || !$new_email) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// 3. Fetch Current Email & Old Image Path (To see if it changed and to delete old image)
$stmt = $conn->prepare("SELECT email, face_path FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$current_email = $current_user['email'];
$old_face_path = $current_user['face_path'];

// 4. Handle Face Image Logic
$facePath = null;
$uploadDir = "img_faces/";
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

// A. Captured Camera Image
if (!empty($captured_image) && strpos($captured_image, 'data:image') === 0) {
    $imgData = str_replace('data:image/jpeg;base64,', '', $captured_image);
    $imgData = base64_decode($imgData);
    $fileName = strtolower(str_replace(' ', '_', $name)) . "_" . time() . ".jpg";
    $targetPath = $uploadDir . $fileName;
    file_put_contents($targetPath, $imgData);
    $facePath = $targetPath;
}
// B. Uploaded File (Fallback)
elseif (!empty($_FILES['edit_face_image']['tmp_name'])) {
    $ext = pathinfo($_FILES['edit_face_image']['name'], PATHINFO_EXTENSION);
    $fileName = strtolower(str_replace(' ', '_', $name)) . "_" . time() . "." . $ext;
    $targetPath = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['edit_face_image']['tmp_name'], $targetPath)) {
        $facePath = $targetPath;
    }
}

// 🟢 DELETE OLD IMAGE FROM SERVER (If a new one was uploaded)
if ($facePath !== null && !empty($old_face_path) && file_exists($old_face_path)) {
    unlink($old_face_path);
}

// 5. Update Database (General Info) - DO NOT update 'email' column yet if changed
// We update Name, Role, Position, Dept, Status immediately.

if ($facePath && $face_descriptor) {
    // Update Image + Descriptor + Info
    $stmt = $conn->prepare("UPDATE users SET name=?, role=?, position=?, department=?, status=?, face_path=?, face_descriptor=? WHERE id=?");
    $stmt->bind_param("sssssssi", $name, $role, $position, $department, $status, $facePath, $face_descriptor, $id);
} elseif ($facePath) {
    // Update Image + Info (No Descriptor)
    $stmt = $conn->prepare("UPDATE users SET name=?, role=?, position=?, department=?, status=?, face_path=? WHERE id=?");
    $stmt->bind_param("ssssssi", $name, $role, $position, $department, $status, $facePath, $id);
} else {
    // Update Info Only
    $stmt = $conn->prepare("UPDATE users SET name=?, role=?, position=?, department=?, status=? WHERE id=?");
    $stmt->bind_param("sssssi", $name, $role, $position, $department, $status, $id);
}

if ($stmt->execute()) {
    $response_msg = "User details updated successfully.";
    $response_status = "success";

    // 6. Handle Email Change Logic
    if ($new_email !== $current_email) {
        
        // Check if new email is already taken
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $new_email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(["status" => "warning", "message" => "Details updated, but Email already exists and was not changed."]);
            exit;
        }

        // Generate Verification Token
        $token = bin2hex(random_bytes(32));
        $link = "http://sjpspanasahan.com/verify_email_change.php?token=" . $token;

        // Save Pending Email & Token to DB
        $upd_token = $conn->prepare("UPDATE users SET pending_email = ?, email_token = ? WHERE id = ?");
        $upd_token->bind_param("ssi", $new_email, $token, $id);
        $upd_token->execute();

        // Send Email via PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.ionos.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'system@sjpspanasahan.com'; 
            $mail->Password = '@Sjpspanasahan2025'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('system@sjpspanasahan.com', 'SJPS Admin');
            $mail->addAddress($new_email); 

            $mail->isHTML(true);
            $mail->Subject = 'Verify Email Update';
            $mail->Body = "
                <div style='font-family: Arial; color: #1F4015;'>
                    <h3>Admin Updated Your Email</h3>
                    <p>The Administrator has updated your registered email address to <b>$new_email</b>.</p>
                    <p>Please click the button below to confirm and activate this email:</p>
                    <p><a href='$link' style='background:#1F4015; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Confirm Email Update</a></p>
                    <p>If you cannot click the button, copy this link: <br> $link</p>
                </div>";
            $mail->send();

            $response_msg = "User updated. A verification link has been sent to the NEW email ($new_email).";
            $response_status = "info"; 

        } catch (Exception $e) {
            $response_msg = "Details updated, but failed to send email verification.";
            $response_status = "warning";
        }
    }

    echo json_encode(["status" => $response_status, "message" => $response_msg]);

} else {
    echo json_encode(["status" => "error", "message" => "Database update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>