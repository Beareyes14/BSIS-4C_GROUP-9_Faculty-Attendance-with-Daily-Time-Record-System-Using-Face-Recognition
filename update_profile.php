<?php
session_start();
require_once "config.php";

// Load PHPMailer Manually
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION["user_id"];
$name = trim($_POST['name'] ?? '');
$new_email = trim($_POST['email'] ?? '');

$current_email = $_SESSION['email']; // Get current email from session

// --- 1. Handle Photo Upload (Standard Logic) ---
$face_path = null;
$face_sql_part = "";
if (!empty($_POST['captured_image'])) {
    $imgData = base64_decode(str_replace('data:image/jpeg;base64,', '', $_POST['captured_image']));
    $fileName = "face_" . $user_id . "_" . time() . ".jpg";
    $targetFile = "uploads/faces/" . $fileName;
    if (!is_dir("uploads/faces/")) mkdir("uploads/faces/", 0777, true);
    file_put_contents($targetFile, $imgData);
    $face_path = $targetFile;
    $face_sql_part = ", face_path=?";
}

// --- 2. Update Name & Photo (These update immediately) ---
if ($face_path) {
    $stmt = $conn->prepare("UPDATE users SET name=? $face_sql_part WHERE id=?");
    $stmt->bind_param("ssi", $name, $face_path, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE users SET name=? WHERE id=?");
    $stmt->bind_param("si", $name, $user_id);
}
$stmt->execute();
$_SESSION["name"] = $name; // Update session name

// --- 3. Handle Email Change Logic ---
$response_msg = "Profile updated successfully!";
$status = "success";

// Only trigger if email is actually different
if ($new_email !== $current_email) {
    
    // Check if new email is taken by someone else
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $new_email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "That email is already in use."]);
        exit();
    }

    // Generate Token
    $token = bin2hex(random_bytes(32));
    $link = "http://sjpspanasahan.com/verify_email_change.php?token=" . $token;

    // Save "Pending Email" to database (Do NOT update real email yet)
    $upd_token = $conn->prepare("UPDATE users SET pending_email = ?, email_token = ? WHERE id = ?");
    $upd_token->bind_param("ssi", $new_email, $token, $user_id);
    $upd_token->execute();

    // Send Verification Email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.ionos.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'system@sjpspanasahan.com'; 
        $mail->Password = '@Sjpspanasahan2025'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('system@sjpspanasahan.com', 'SJPS Security');
        $mail->addAddress($new_email); // Send to the NEW email address

        $mail->isHTML(true);
        $mail->Subject = 'Verify Your New Email';
        $mail->Body = "
            <div style='font-family: Arial; color: #1F4015;'>
                <h3>Confirm Email Change</h3>
                <p>You requested to change your email to <b>$new_email</b>.</p>
                <p>Click the button below to confirm this change:</p>
                <p><a href='$link' style='background:#1F4015; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Verify Email</a></p>
                <p>If you did not request this, please ignore this email.</p>
            </div>";
        $mail->send();

        // Change response to inform user
        $response_msg = "Profile saved. A verification link has been sent to $new_email. Please check your inbox.";
        $status = "info"; // Use blue info icon instead of green success

    } catch (Exception $e) {
        $response_msg = "Profile saved, but could not send verification email.";
        $status = "warning";
    }
}

echo json_encode(["status" => $status, "message" => $response_msg]);
?>