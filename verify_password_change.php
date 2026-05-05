<?php
session_start();
require_once "config.php";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Find user with this token and a pending password
    $stmt = $conn->prepare("SELECT id, pending_password FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $new_hash = $row['pending_password'];
        $id = $row['id'];

        if ($new_hash) {
            // Apply the new password
            $update = $conn->prepare("UPDATE users SET password = ?, pending_password = NULL, reset_token = NULL WHERE id = ?");
            $update->bind_param("si", $new_hash, $id);
            
            if ($update->execute()) {
                echo "
                <div style='font-family: Arial; text-align: center; margin-top: 50px;'>
                    <h1 style='color: green;'>Password Changed!</h1>
                    <p>Your password has been successfully updated.</p>
                    <a href='profile.php' style='background: #1F4015; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Back to Profile</a>
                </div>";
            }
        } else {
            echo "Error: No pending password found.";
        }
    } else {
        echo "<h2 style='color: red; text-align: center;'>Invalid or Expired Link.</h2>";
    }
} else {
    header("Location: login.php");
}
?>