<?php
// test_email.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Starting Manual Load Test...</h1>";

// 1. Manually Load Files (Bypassing Vendor)
// Make sure you created the folders: PHPMailer/src/
if (!file_exists('PHPMailer/src/PHPMailer.php')) {
    die("<h2 style='color:red'>CRITICAL ERROR: Files missing!</h2><p>Please create a folder named 'PHPMailer', inside it a folder named 'src', and upload Exception.php, PHPMailer.php, and SMTP.php there.</p>");
}

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<p>✅ PHPMailer classes loaded manually.</p>";

// 2. Setup Email
$mail = new PHPMailer(true);

try {
    // Server Settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.ionos.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'system@sjpspanasahan.com'; 
    $mail->Password   = '@Sjpspanasahan2025'; // Your Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Debugging (Shows the conversation)
    $mail->SMTPDebug = 2; 

    // Recipients
    $mail->setFrom('system@sjpspanasahan.com', 'Test System');
    $mail->addAddress('system@sjpspanasahan.com'); // Send to yourself

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Manual Load Test Successful';
    $mail->Body    = 'If you see this, the manual file loading worked!';

    echo "<p>Attempting to send...</p>";
    $mail->send();
    echo "<h2 style='color:green'>✅ SUCCESS! Email Sent.</h2>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ EMAIL FAILED</h2>";
    echo "<pre>Error: " . $mail->ErrorInfo . "</pre>";
}
?>