<?php
require_once "config.php";

// Check if email is provided in the URL (Matches the CSV email link)
if (isset($_GET['email'])) {
    // 🟢 FIX 1: Added urldecode to ensure special characters like '@' or '+' don't break the email format
    $email = trim(urldecode($_GET['email'])); 

    // 1. Check if user exists first using their email
    $check = $conn->prepare("SELECT id, status FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($user['status'] === 'Active') {
            // Already active? Just go to login
             echo "
            <div style='text-align:center; margin-top:50px; font-family:Arial;'>
                <h1 style='color:orange;'>Account Already Active</h1>
                <p>You can proceed to login.</p>
                <a href='login.php' style='background-color: #1F4015; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login</a>
            </div>";
        } else {
            // 2. Activate the user by email
            $update = $conn->prepare("UPDATE users SET status='Active' WHERE email=?");
            $update->bind_param("s", $email);
            $update->execute();
            
            // 🟢 FIX 2: Strictly check if a row was actually changed in the database
            if ($update->affected_rows > 0) {
                // Success!
                echo "
                <div style='text-align:center; margin-top:50px; font-family:Arial;'>
                    <h1 style='color:green;'>Account Activated!</h1>
                    <p>Your account is now active.</p>
                    <a href='login.php' style='background-color: #1F4015; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a>
                </div>";
            } else {
                // If it fails silently, we will now see this error instead of a fake success
                echo "
                <div style='text-align:center; margin-top:50px; font-family:Arial;'>
                    <h1 style='color:red;'>Activation Failed</h1>
                    <p>Database error: The system found your email but could not update your status. Please contact the Admin.</p>
                </div>";
            }
            $update->close(); // Clean up memory
        }
    } else {
        echo "
        <div style='text-align:center; margin-top:50px; font-family:Arial;'>
            <h1 style='color:red;'>User Not Found</h1>
            <p>This link appears to be invalid or the email is not registered.</p>
        </div>";
    }
    $check->close(); // Clean up memory
} else {
    // No email provided
    header("Location: login.php");
    exit();
}
$conn->close();
?>