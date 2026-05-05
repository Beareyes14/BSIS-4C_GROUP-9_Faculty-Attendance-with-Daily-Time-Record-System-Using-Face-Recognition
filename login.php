<?php
session_start();
require_once "config.php";

$sweetAlertError = ""; // Variable to trigger JS alert

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT id, name, role, password, status FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $name, $role, $hashed_password, $status);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            
            // 🛑 CHECK STATUS
            if ($status === 'inactive') {
                // Set flag for SweetAlert
                $sweetAlertError = "inactive";
            } else {
                $_SESSION["user_id"] = $id;
                $_SESSION["name"] = $name;
                $_SESSION["role"] = $role;

                if ($role == "admin") {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit();
            }

        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No account found with that email.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="css/style.css">
    <meta charset="UTF-8">
    <title>Login</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        
        body { 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: url("images/bg.JPG") no-repeat center center/cover; 
            position: relative; 
            overflow: hidden; /* Prevents scrolling from shapes */
            padding: 20px;
        }
        
        body::before { 
            content: ""; 
            position: absolute; 
            inset: 0; 
            background: #1F4015; 
            opacity: 0.6; 
            z-index: 0;
        }

        /* EXACT Shapes from Face Login */
        .shape-yellow { position: absolute; width: 385px; height: 1100px; right: 10%; top: -350px; background: #EEC752; border-radius: 200px; transform: rotate(-135deg); z-index: 0; }
        .shape-white { position: absolute; width: 385px; height: 1100px; right: 10%; bottom: -350px; background: #FFF8F8; border-radius: 200px; border: 1px solid #FFFFFF; transform: rotate(135deg); z-index: 0; }
        .shape-grey1, .shape-grey2, .shape-grey3 { position: absolute; width: 340px; height: 340px; background: rgba(217,217,217,0.5); border: 1px solid #F9F7E8; border-radius: 14px; transform: rotate(45deg); z-index: 0; }
        .shape-grey1 { top: -200px; left: 55.5%; }
        .shape-grey2 { top: 290px; right: -150px; }
        .shape-grey3 { bottom: -175px; left: 53%; }

        /* Main Wrapper using Flex */
        .login-wrapper { 
            position: relative; 
            z-index: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 50px; 
            width: 100%; 
            max-width: 1100px;
            flex-wrap: wrap; 
        }

        /* Flexible Container */
        .login-container { 
            background: #F9F7E8; 
            border-radius: 30px; 
            padding: 40px; 
            width: 100%; 
            max-width: 500px; 
            box-shadow: 0px 0px 30px rgba(0,0,0,0.5); 
        }

        .login-container h2 { text-align: center; font-size: clamp(24px, 5vw, 36px); font-weight: 800; margin-bottom: 20px; color: #000; }
        
        .input-box { margin-bottom: 20px; position: relative; }
        .input-box label { display: block; font-size: 16px; margin-bottom: 5px; color: #3c4d18ff; text-shadow: 0px 2px 4px rgba(0,0,0,0.1); font-weight: bold; }
        .input-box input { width: 100%; padding: 12px; padding-right: 40px; border-radius: 15px; border: none; background: #AAB396; font-size: 16px; color: #1F4015; font-weight: 600; }
        .input-box input::placeholder { color: #4a5b36; font-weight: normal; }
        
        .toggle-eye { position: absolute; right: 15px; top: 40px; cursor: pointer; color: #1F4015; font-size: 18px; }
        
        button { width: 100%; padding: 14px; background-color: #5D6F47; color: #ECE5E5; border: none; border-radius: 15px; cursor: pointer; font-size: 18px; font-weight: 800; text-shadow: 0px 2px 4px rgba(0,0,0,0.25); margin-top: 10px; transition: 0.3s; }
        button:hover { background-color: #4a5b36; transform: translateY(-2px); }
        
        a { display: block; margin-top: 10px; font-size: 14px; color: #586E29; text-decoration: none; text-align: right; font-weight: bold; }
        a:hover { text-decoration: underline; }
        
        .error { color: red; font-size: 14px; text-align: center; margin-bottom: 10px; font-weight: bold; }

        /* Logo Section */
        .logo-section { display: flex; justify-content: center; align-items: center; z-index: 2; }
        .logo-section img { 
            width: 90%; 
            
            max-width: 380px; 
            height: auto; 
            object-fit: contain; 
            border-radius: 50%; 
            box-shadow: 0px 0px 30px rgba(0,0,0,0.5); 
        }

        /* Mobile Adjustments */
        @media (max-width: 900px) { 
            .login-wrapper { flex-direction: column-reverse; gap: 30px; } 
            .logo-section img { max-width: 180px; margin-top: 10px; }
            .login-container { padding: 30px 20px; border-radius: 20px; }
            /* Hide shapes on mobile to keep it clean and prevent screen clipping */
            .shape-yellow, .shape-white, .shape-grey1, .shape-grey2, .shape-grey3 { display: none; } 
        }
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
            <h2>Login Now</h2>
            <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
            
            <form method="POST">
                <div class="input-box">
                    <label for="email">Email</label>
                    <input type="email" name="email" placeholder="Enter your email" required>
                </div>
                
                <div class="input-box">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                    <i class="fa-solid fa-eye toggle-eye" id="togglePassword"></i>
                </div>

                <button type="submit">Login</button>
            </form>

            <a href="face_login.php" style="text-align: center;">
                <button type="button" style="background-color: #AAB396; color: #1F4015; margin-top: 15px;">Face Recognition Login</button>
            </a>

            <a href="forgot_password.php">Forgot Password?</a>
        </div>

        <div class="logo-section">
            <img src="images/logo.png" alt="School Logo">
        </div>
    </div>

<script>
    // Eye Toggle
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePassword');

    toggleIcon.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // 🛑 SWEETALERT FOR INACTIVE ACCOUNT
    <?php if ($sweetAlertError === 'inactive'): ?>
    Swal.fire({
        icon: 'error',
        title: 'Account Not Activated',
        text: 'Please check your email to activate your account before logging in!',
        confirmButtonColor: '#1F4015'
    });
    <?php endif; ?>
</script>

</body>
</html>