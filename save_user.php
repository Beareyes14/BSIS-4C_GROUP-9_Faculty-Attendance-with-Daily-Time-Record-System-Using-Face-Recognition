<?php
// 🟢 1. MANUALLY LOAD PHPMAILER (Bypassing broken vendor folder)
// Ensure these files exist in: PHPMailer/src/
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 🟢 2. Load Config
require_once "config.php";

header('Content-Type: application/json');

// 🟢 Get fields
$face_descriptor = $_POST['face_descriptor'] ?? null;
$name = $_POST['name'] ?? null;
$email = $_POST['email'] ?? null;
$role = strtolower(trim($_POST['role'] ?? 'user'));
if ($role === 'faculty') {
    $role = 'user'; 
}
$position = $_POST['position'] ?? null;
$department = $_POST['department'] ?? null;
$password = $_POST['password'] ?? "@Spsfaculty123";
$hashed = password_hash($password, PASSWORD_DEFAULT);

// 🟢 Validation
if (!$name || !$email) {
    echo json_encode(["status" => "error", "message" => "Please fill out all required fields!"]);
    exit;
}

// 🟢 Check duplicate email
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "This email is already registered."]);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

// 🟢 Image save logic
$facePath = null;
$uploadDir = "img_faces/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!empty($_FILES['face_image']['tmp_name'])) {
    $ext = pathinfo($_FILES['face_image']['name'], PATHINFO_EXTENSION);
    $fileName = strtolower(str_replace(' ', '_', $name)) . "_" . time() . "." . $ext;
    $targetPath = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['face_image']['tmp_name'], $targetPath)) {
        $facePath = $targetPath;
    }
} elseif (!empty($_POST['captured_image'])) {
    $imgData = $_POST['captured_image'];
    $imgData = str_replace('data:image/jpeg;base64,', '', $imgData);
    $imgData = base64_decode($imgData);
    $fileName = strtolower(str_replace(' ', '_', $name)) . "_" . time() . ".jpg";
    $targetPath = $uploadDir . $fileName;
    file_put_contents($targetPath, $imgData);
    $facePath = $targetPath;
}

// 🟢 Save to database (Status = Inactive)
$stmt = $conn->prepare("INSERT INTO users (name, email, role, position, department, password, face_path, face_descriptor, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Inactive')");
$stmt->bind_param("ssssssss", $name, $email, $role, $position, $department, $hashed, $facePath, $face_descriptor);

if ($stmt->execute()) {
    
    // 🟢 GET THE NEW USER ID (Critical for Activation Link)
    $new_user_id = $stmt->insert_id;

    // 🟡 Flask Reload (Optional)
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        $flask_url = "http://127.0.0.1:5000/reload_faces";
        @file_get_contents($flask_url);
    }

    // =========================================================
    // 📧 START PHPMAILER LOGIC
    // =========================================================
    $mail = new PHPMailer(true);
    $email_status = "Email sent successfully."; // ✅ Default success message
    $debug_log = ""; // ✅ Variable to capture debug info

    try {
        // 🛑 SILENT MODE (Important so JSON doesn't break)
        $mail->SMTPDebug = 0; 
        
        // Capture debug info just in case of error
        $mail->Debugoutput = function($str, $level) use (&$debug_log) {
            $debug_log .= "$str\n";
        };

        // Server Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.ionos.com';
        $mail->SMTPAuth   = true;
        
        // ✅ YOUR CREDENTIALS
        $mail->Username   = 'system@sjpspanasahan.com'; 
        $mail->Password   = '@Sjpspanasahan2025'; 
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & Recipient
        $mail->setFrom('system@sjpspanasahan.com', 'SJPS ADMIN');
        $mail->addAddress($email, $name); 

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Activate Your Account - SJPS Faculty';
        
        // 🟢 NEW ACTIVATION LINK (Uses activate.php + EMAIL to match your new system)
        $activation_link = "https://sjpspanasahan.com/activate.php?email=" . urlencode($email);
        
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; color: #1F4015; max-width: 600px; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                <h2 style='text-align: center; color: #1F4015;'>Welcome to SJPS!</h2>
                <p>Hello <strong>$name</strong>,</p>
                <p>Your faculty account has been successfully created. However, it is currently <strong>Inactive</strong>.</p>
                
                <div style='background: #f4f4f4; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>📧 Email:</strong> $email</p>
                    <p style='margin: 5px 0;'><strong>🔑 Default Password:</strong> $password</p>
                </div>

                <p>You MUST click the button below to activate your account before logging in:</p>
                
                <div style='text-align: center; margin-top: 20px;'>
                    <a href='$activation_link' style='background-color: #1F4015; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>ACTIVATE ACCOUNT</a>
                </div>
                
                <br>
                <p style='font-size: 12px; color: #666;'>If the button doesn't work, copy this link: $activation_link</p>
            </div>
        ";

        $mail->send();
        
    } catch (Exception $e) {
        // Capture the error but DO NOT break the JSON
        $email_status = "Email Failed: " . $mail->ErrorInfo;
        // Append error to debug log
        $debug_log .= "Mailer Error: " . $mail->ErrorInfo;
    }
    // =========================================================
    // 📧 END PHPMAILER LOGIC
    // =========================================================

    // ✅ Return JSON
    echo json_encode([
        "status" => "success",
        "title" => "User Added!",
        "message" => "User $name added. $email_status",
        "face" => $facePath,
        "debug_log" => $debug_log 
    ]);

} else {
    echo json_encode([
        "status" => "error",
        "title" => "Database Error",
        "message" => $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>