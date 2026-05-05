<?php
if (!isset($_SESSION)) {
    session_start();
}
?>

<!--  Include SweetAlert2 (added) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="images/logo.png" alt="School Logo">
        <span>St. Joseph Parochial School</span>
        
    </div>

    <!-- User Navigation Menu -->
    <a href="user_dashboard.php"> Dashboard</a>
    <a href="attendance_scanner.php"> Attendance Scanner</a>
    <a href="user_attendance_logs.php"> Attendance Logs</a>
    <a href="my_dtr.php"> Reports</a>
    <a href="profile.php"> My Profile</a>

    <div class="bottom-links">
        <!--  Modified only this line -->
        <a href="#" id="logout-btn"> Logout</a>
        
    </div>
</div>



<!--  Added SweetAlert Script for Logout -->
<script>
document.getElementById('logout-btn').addEventListener('click', function (e) {
    e.preventDefault(); // Prevent immediate navigation
    Swal.fire({
        title: 'Are you sure?',
        text: "You’ll be logged out of your account.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#5D6F47',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log out',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect to logout.php if confirmed
            window.location.href = 'logout.php';
        }
    });
});
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
        top: 0;
        left: 0;
        display: flex;
        flex-direction: column;
        padding-top: 0;
    }

    .sidebar-header {
        background: #EEC752;
        color: #1F4015;
        display: flex;
        align-items: center;
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
        position: absolute;
        bottom: 30px;
        width: 100%;
    }
</style>
