<?php
require_once "config.php";
header("Content-Type: application/json");

// 1. Load PHPMailer Manually
// Make sure the "PHPMailer" folder exists in the same directory as this file
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

/* ---------------- SECURITY CHECK ---------------- */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

/* ---------------- GET DATA ---------------- */
$name       = trim($_POST["name"] ?? "");
$email      = trim($_POST["email"] ?? "");
$role       = trim($_POST["role"] ?? "");
$position   = trim($_POST["position"] ?? "");
$department = trim($_POST["department"] ?? "");
$face_desc  = $_POST["face_descriptor"] ?? null; 

/* ---------------- VALIDATION ---------------- */
if (!$name || !$email || !$role || !$position || !$department) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

if (!$face_desc) {
    echo json_encode(["status" => "error", "message" => "Face not detected. Please capture a clear face photo."]);
    exit;
}

/* ---------------- PREPARE DATA ---------------- */
$default_password = "@Spsfaculty123";
$hashed_password  = password_hash($default_password, PASSWORD_DEFAULT);
$activation_code  = bin2hex(random_bytes(16));
$status           = 'inactive'; // User cannot login until they click email link

/* ---------------- CHECK DUPLICATE EMAIL ---------------- */
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already exists"]);
    exit;
}
$check->close();

/* ---------------- INSERT USER ---------------- */
$sql = "INSERT INTO users (name, email, password, role, position, department, face_descriptor, status, activation_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssss", $name, $email, $hashed_password, $role, $position, $department, $face_desc, $status, $activation_code);

if ($stmt->execute()) {
    $new_user_id = $conn->insert_id; 

    /* ---------------- HANDLE PHOTO UPLOAD ---------------- */
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $target_dir = "img_faces/"; 
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        // Save as {id}.jpg so the face scanner can find it
        $target_file = $target_dir . $new_user_id . ".jpg";
        move_uploaded_file($_FILES['photo']['tmp_name'], $target_file);
    }

    /* ---------------- SEND ACTIVATION EMAIL ---------------- */
    $mail = new PHPMailer(true);
    $email_sent = false;

    try {
        // IONOS SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.ionos.com';
        $mail->SMTPAuth   = true;
        
        // 🔴 UPDATE THESE CREDENTIALS
        $mail->Username   = 'system@sjpspanasahan.com'; 
        $mail->Password   = '@Sjpspanasahan2025'; 
        
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('system@sjpspanasahan.com', 'Admin System');
        $mail->addAddress($email, $name);

        // 🔴 UPDATE THIS LINK
        $base_url = "https://sjpspanasahan.com/"; 
        $link = $base_url . "activate.php?email=" . urlencode($email) . "&code=" . $activation_code;

        $mail->isHTML(true);
        $mail->Subject = 'Activate Your Account';
        $mail->Body    = "
            <h3>Welcome, $name!</h3>
            <p>You have been registered as a <strong>$role</strong>.</p>
            <p>Your default password is: <strong>$default_password</strong></p>
            <p>Please click the link below to activate your account:</p>
            <p><a href='$link' style='background:#1F4015;color:white;padding:10px 15px;text-decoration:none;border-radius:5px;'>Activate Account</a></p> ";

        $mail->send();
        $email_sent = true;

    } catch (Exception $e) {
        // Email failed logic (optional logging)
    }

    /* ---------------- SUCCESS RESPONSE ---------------- */
    echo json_encode([
        "status" => "success",
        "title" => "User Added",
        "message" => $email_sent ? "User registered! Activation email sent." : "User registered, but email failed to send.",
        "default_password" => $default_password
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Database insert failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>