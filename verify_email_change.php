<?php
session_start();
require_once "config.php";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // 1. Find the user waiting for this specific token
    $stmt = $conn->prepare("SELECT id, pending_email FROM users WHERE email_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $new_email = $row['pending_email'];
        $user_id = $row['id'];

        // 2. Apply the change permanently
        $update = $conn->prepare("UPDATE users SET email = ?, pending_email = NULL, email_token = NULL WHERE id = ?");
        $update->bind_param("si", $new_email, $user_id);
        
        if ($update->execute()) {
            // Update session so they see the new email immediately
            $_SESSION['email'] = $new_email; 
            
            echo "
            <div style='font-family: Arial; text-align: center; margin-top: 50px;'>
                <h1 style='color: green;'>Email Verified!</h1>
                <p>Your email has been successfully changed to <b>$new_email</b>.</p>
                <a href='profile.php' style='background: #1F4015; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Back to Profile</a>
            </div>";
        }
    } else {
        echo "<h2 style='color: red; text-align: center;'>Invalid or Expired Link.</h2>";
    }
} else {
    header("Location: login.php");
}
?>