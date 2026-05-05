<?php
// 1. Load PHPMailer (Manual Loading)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require_once "config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";
$step = 1; // Default to email entry step
$email_to_reset = "";
$token_from_url = "";

// --- CASE 1: Handling Email Request (Step 1 Submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_reset'])) {
    $email = trim($_POST["email"]);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Generate Token
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Save Token to DB
        $update = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
        $update->bind_param("sss", $token, $expiry, $email);
        $update->execute();

        // Send Email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.ionos.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'system@sjpspanasahan.com'; 
            $mail->Password   = '@Sjpspanasahan2025'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('system@sjpspanasahan.com', 'SJPS System');
            $mail->addAddress($email);

            // Create Link
            $reset_link = "http://sjpspanasahan.com/forgot_password.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Confirmation';
            $mail->Body    = "
                <div style='font-family: Arial; color: #1F4015;'>
                    <h3>Password Reset Request</h3>
                    <p>Click the link below to verify your email and set a new password:</p>
                    <p><a href='$reset_link' style='background:#1F4015; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>Reset Password</a></p>
                    <p>Or copy this link: <br> $reset_link</p>
                    <p>This link expires in 1 hour.</p>
                </div>";

            $mail->send();
            $message = "<p class='success'>Confirmation link sent! Please check your email.</p>";
        } catch (Exception $e) {
            $message = "<p class='error'>Mailer Error. Please try again later.</p>";
        }
    } else {
        $message = "<p class='error'>Email address not found.</p>";
    }
}

// --- CASE 2: Handling the Token from Email (Step 2 View) ---
if (isset($_GET['token'])) {
    $token_from_url = $_GET['token'];
    $stmt = $conn->prepare("SELECT email FROM users WHERE reset_token = ? AND token_expiry > NOW()");
    $stmt->bind_param("s", $token_from_url);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user_data = $res->fetch_assoc();
        $email_to_reset = $user_data['email'];
        $step = 2; // Switch to password entry step
    } else {
        $message = "<p class='error'>Invalid or expired reset link. Please request a new one.</p>";
    }
}

// --- CASE 3: Saving the New Password (Step 2 Submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_password'])) {
    $email = $_POST['reset_email'];
    $token = $_POST['reset_token']; // Security check
    $new_pass = trim($_POST["new_password"]);
    $confirm_pass = trim($_POST["confirm_password"]);

    if ($new_pass === $confirm_pass) {
        // Double check token is still valid before saving
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND token_expiry > NOW()");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE email = ?");
            $update->bind_param("ss", $hashed_pass, $email);
            
            if ($update->execute()) {
                $message = "<p class='success'>Password updated successfully! <br>";
                $step = 3; // Finished
            } else {
                $message = "<p class='error'>Database error.</p>";
            }
        } else {
            $message = "<p class='error'>Session expired. Please try again.</p>";
        }
    } else {
        $message = "<p class='error'>Passwords do not match.</p>";
        $step = 2; // Stay on reset form
        $email_to_reset = $email;
        $token_from_url = $token;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* [KEEPING YOUR EXISTING STYLES] */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: url("images/bg.JPG") no-repeat center center/cover; position: relative; overflow: hidden; }
        body::before { content: ""; position: absolute; inset: 0; background: #1F4015; opacity: 0.6; }
        
        /* Background shapes */
        .shape-yellow { position: absolute; width: 385px; height: 1100px; right: 160px; top: -350px; background: #EEC752; border-radius: 200px; transform: rotate(-135deg); z-index: 0; }
        .shape-white { position: absolute; width: 385px; height: 1100px; right: 160px; bottom: -350px; background: #FFF8F8; border-radius: 200px; border: 1px solid #FFFFFF; transform: rotate(135deg); z-index: 0; }
        .shape-grey1, .shape-grey2, .shape-grey3 { position: absolute; width: 340px; height: 340px; background: rgba(217,217,217,0.5); border: 1px solid #F9F7E8; border-radius: 14px; transform: rotate(45deg); z-index: 0; }
        .shape-grey1 { top: -200px; left: 55.5%; } .shape-grey2 { top: 290px; right: -150px; } .shape-grey3 { bottom: -175px; left: 53%; }

        .login-wrapper { position: relative; z-index: 1; display: flex; align-items: center; justify-content: center; gap: 40px; }
        .login-container { background: #F9F7E8; position: absolute; left: -510px; border-radius: 30px; padding: 50px 40px; width: 600px; box-shadow: 0px 0px 30px rgba(0,0,0,0.5); }
        .login-container h2 { text-align: center; font-size: 36px; font-weight: 800; margin-bottom: 20px; color: #000; }
        
        .input-box { margin-bottom: 20px; position: relative; }
        .input-box label { display: block; font-size: 16px; margin-bottom: 5px; color: #3c4d18ff; text-shadow: 0px 2px 4px rgba(0,0,0,0.25); }
        .input-box input { width: 100%; padding: 12px; padding-right: 45px; border-radius: 15px; border: none; background: #AAB396; font-size: 16px; }
        
        .toggle-eye { position: absolute; right: 15px; top: 42px; cursor: pointer; color: #1F4015; font-size: 18px; }
        .validation-list { list-style: none; padding: 10px; margin-top: 5px; font-size: 13px; background: rgba(255,255,255,0.5); border-radius: 10px; }
        .validation-list li { margin-bottom: 3px; display: flex; align-items: center; gap: 8px; color: #555; transition: 0.3s; }
        .valid { color: #006400; font-weight: bold; } .valid::before { content: "✅"; }
        .invalid { color: #8B0000; } .invalid::before { content: "❌"; }
        
        button { width: 100%; padding: 14px; background-color: #5D6F47; color: #ECE5E5; border: none; border-radius: 15px; cursor: pointer; font-size: 18px; font-weight: 800; text-shadow: 0px 2px 4px rgba(0,0,0,0.25); }
        button:hover { background-color: #4a5b36; }
        
        a { display: block; margin-top: 10px; font-size: 14px; color: #586E29; text-decoration: none; text-align: right; }
        a:hover { text-decoration: underline; }
        .error { color: red; font-size: 14px; text-align: center; margin-bottom: 10px; background: #ffe6e6; padding: 10px; border-radius: 8px; }
        .success { color: green; font-size: 15px; text-align: center; margin-bottom: 10px; background: #e6ffe6; padding: 10px; border-radius: 8px; }
        
        .logo-section { position: absolute; right: -540px; top: 50%; transform: translateY(-50%); z-index: 2; }
        .logo-section img { width: 380px; height: 380px; object-fit: contain; border-radius: 50%; box-shadow: 0px 0px 30px rgba(0,0,0,1); }
        @media (max-width: 900px) { .login-wrapper { flex-direction: column; } .logo-section img { width: 180px; height: 180px; margin-top: 20px; } }
    </style>
</head>
<body>
    <div class="shape-yellow"></div>
    <div class="shape-white"></div>
    <div class="shape-grey1"></div>
    <div class="shape-grey2"></div>
    <div class="shape-grey3"></div>

    <div class="login-wrapper">
        <div class="login-container">
            <?= $message ?>

            <?php if ($step == 1): ?>
                <h2>Forgot Password</h2>
                <p style="text-align:center; margin-bottom:20px; color:#555;">Enter your email to receive a confirmation link.</p>
                
                <form method="POST">
                    <div class="input-box">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" placeholder="Enter your email" required>
                    </div>
                    <button type="submit" name="request_reset">SEND LINK</button>
                </form>
                <a href="login.php">Back to Login</a>

            <?php elseif ($step == 2): ?>
                <h2>Reset Password</h2>
                <form method="POST" onsubmit="return validateForm()">
                    <input type="hidden" name="reset_email" value="<?= htmlspecialchars($email_to_reset) ?>">
                    <input type="hidden" name="reset_token" value="<?= htmlspecialchars($token_from_url) ?>">

                    <div class="input-box">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="new_password" placeholder="Enter new password" required>
                        <i class="fa-solid fa-eye toggle-eye" onclick="togglePass('new_password', this)"></i>
                        
                        <ul class="validation-list">
                            <li id="len" class="invalid">At least 6 characters</li>
                            <li id="upper" class="invalid">At least 1 Uppercase Letter</li>
                            <li id="num" class="invalid">At least 1 Number</li>
                            <li id="sym" class="invalid">At least 1 Symbol (!@#$%)</li>
                        </ul>
                    </div>

                    <div class="input-box">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter new password" required>
                        <i class="fa-solid fa-eye toggle-eye" onclick="togglePass('confirm_password', this)"></i>
                    </div>

                    <button type="submit" name="save_password">UPDATE PASSWORD</button>
                </form>
            
            <?php elseif ($step == 3): ?>
                <div style="text-align:center; margin-top:20px;">
                    <a href="login.php" style="text-align:center; font-size:18px;">Return to Login</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="logo-section">
            <img src="images/logo.png" alt="School Logo">
        </div>
    </div>

    <script>
        function togglePass(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        const passwordInput = document.getElementById('new_password');
        let isPasswordValid = false;

        if (passwordInput) {
            passwordInput.addEventListener('keyup', function () {
                const val = passwordInput.value;
                const hasLen = val.length >= 6;
                const hasUpper = /[A-Z]/.test(val);
                const hasNum = /[0-9]/.test(val);
                const hasSym = /[!@#$%^&*(),.?":{}|<>]/.test(val);

                function updateUI(id, isValid) {
                    const el = document.getElementById(id);
                    if (isValid) { el.classList.remove('invalid'); el.classList.add('valid'); } 
                    else { el.classList.remove('valid'); el.classList.add('invalid'); }
                }

                updateUI('len', hasLen);
                updateUI('upper', hasUpper);
                updateUI('num', hasNum);
                updateUI('sym', hasSym);

                isPasswordValid = hasLen && hasUpper && hasNum && hasSym;
            });
        }

        function validateForm() {
            if (!isPasswordValid) {
                alert("Please ensure your password meets all requirements (Green Checks).");
                return false;
            }
            const pass = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            if (pass !== confirm) {
                alert("Passwords do not match!");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>