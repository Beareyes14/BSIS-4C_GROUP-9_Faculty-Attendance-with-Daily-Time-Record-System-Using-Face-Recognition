<?php
$host = "db5018982215.hosting-data.io";  //  IONOS MySQL host
$user = "dbu2939454";                    //  Your IONOS DB username
$pass = "@St.Joseph2025";        //  Enter your actual IONOS DB password here
$dbname = "dbs14951258";                 //  Your IONOS database name

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1. Set Timezone (Recommended for accurate timeout calculations)
date_default_timezone_set("Asia/Manila");

// 2. Start Session (Safe Check)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


//  AUTO LOGOUT CONFIGURATION (10 MINUTES)


// Set timeout duration (in seconds)
// 300 seconds = 10 Minutes
$timeout_duration = 600; 

// Check if "Last Activity" is set in the session
if (isset($_SESSION['last_activity'])) {
    // Calculate how many seconds have passed since the last action
    $duration = time() - $_SESSION['last_activity'];
    
    // If the duration is greater than the limit (10 mins)
    if ($duration > $timeout_duration) {
        // A. Clear all session variables
        session_unset(); 
        // B. Destroy the session completely
        session_destroy(); 
        // C. Redirect to login page with a timeout message
        header("Location: face_login.php?timeout=1"); 
        exit();
    }
}

// Update "Last Activity" time stamp to RIGHT NOW
$_SESSION['last_activity'] = time();
?>
