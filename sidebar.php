<?php
// Prevent session error if already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Only allow admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="images/logo.png" alt="School Logo">
        <span>St. Joseph Parochial School</span>
    </div>

    <a href="admin_dashboard.php"> Dashboard</a>
    <a href="attendance_scanner.php"> Attendance Scanner</a>
    <a href="attendance_logs.php"> Attendance Logs</a>
    <a href="faculty_management.php"> Faculty Management</a>
    <a href="reports.php"> Reports</a>
    <a href="admin_settings.php"> Settings</a>
    <a href="profile.php"> My Profile</a>

    <div class="bottom-links">
        <a href="#" id="logout-btn"> Logout</a>
    </div>
</div>

<script>
document.getElementById('logout-btn').addEventListener('click', function (e) {
    e.preventDefault(); // Stop default link behavior

    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out of the system.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#5D6F47',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log out',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
});
</script>

<script>
    let idleTime = 0;
    const idleLimit = 600; // 10 Minutes (in seconds)

    // Increment idle time every second
    const idleInterval = setInterval(function() {
        idleTime++;
        if (idleTime >= idleLimit) {
            // Redirect to login with timeout message
            window.location.href = "face_login.php?timeout=1";
        }
    }, 1000);

    // Reset idle timer on any user activity
    function resetIdleTimer() {
        idleTime = 0;
    }

    // Listen for mouse or keyboard activity
    document.onmousemove = resetIdleTimer;
    document.onkeypress = resetIdleTimer;
    document.onclick = resetIdleTimer;
    document.onscroll = resetIdleTimer;
</script>

<style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
    }
    .sidebar {
        width: 270px;
        background: #5D6F47;
        color: white;
        height: 100vh;
        position: fixed;
        top: 0; left: 0;
        padding-top: 0;
        display: flex;
        flex-direction: column;
        z-index: 1000; /* Ensure sidebar stays on top */
    }

    /* Header section with logo and school name */
    .sidebar-header {
        background: #EEC752;
        color: #1F4015;
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 10px;
        padding: 15px 20px;
        margin-bottom: 25px;
    }

    .sidebar-header img {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        background: white;
        border: 2px solid #1F4015;
    }

    .sidebar-header span {
        font-size: 12px;
        font-weight: 700;
        line-height: 1.2;
    }

    .sidebar a {
        display: block;
        color: black;
        background: #F9F7E8;
        margin-bottom: 5px;
        padding: 12px 20px;
        text-decoration: none;
        transition: background 0.3s;
        font-weight: 500;
    }

    .sidebar a:hover {
        background: #EEC752;
    }

    .bottom-links {
        margin-top: auto; /* Pushes to bottom flexibly */
        margin-bottom: 20px;
        width: 100%;
    }
</style>