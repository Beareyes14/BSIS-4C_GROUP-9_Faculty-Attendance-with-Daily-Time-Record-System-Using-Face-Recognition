<?php
session_start();
require_once "config.php";

// Load PHPMailer Manually
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION["user_id"])) { exit(json_encode(["status" => "error", "message" => "Unauthorized"])); }

$user_id = $_SESSION["user_id"];
$new_pass = $_POST['newpass'];
$confirm_pass = $_POST['confirm'];

// 1. Validate inputs
if (empty($new_pass)) {
    exit(json_encode(["status" => "warning", "message" => "Please enter a new password."]));
}
if ($new_pass !== $confirm_pass) {
    exit(json_encode(["status" => "error", "message" => "Passwords do not match."]));
}

// 2. Fetch User Email
$stmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$email = $user['email'];

// 3. Process Change (Email Verification Method)
$token = bin2hex(random_bytes(32));
$new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
$link = "http://sjpspanasahan.com/verify_password_change.php?token=" . $token;

// Save pending password and token
$update = $conn->prepare("UPDATE users SET pending_password = ?, reset_token = ? WHERE id = ?");
$update->bind_param("ssi", $new_hash, $token, $user_id);

if ($update->execute()) {
    // Send Email
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
        $mail->addAddress($email); 

        $mail->isHTML(true);
        $mail->Subject = 'Confirm Password Change';
        $mail->Body = "
            <h3>Confirm Password Change</h3>
            <p>Hello {$user['name']},</p>
            <p>We received a request to change your password from your profile.</p>
            <p><b>If this was you, click the link below to finish the change:</b></p>
            <p><a href='$link' style='background:#1F4015; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Confirm New Password</a></p>
            <p>If you did not ask for this, you can safely ignore this email.</p>
        ";
        $mail->send();

        echo json_encode([
            "status" => "info", 
            "message" => "Confirmation email sent! Please check your inbox to finalize the password change."
        ]);

    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Could not send email. Try again."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Database error."]);
}
?>