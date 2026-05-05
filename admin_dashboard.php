<?php
if (!isset($_SESSION)) session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');
$today = date("Y-m-d");

// ✅ NEW: Greeting Logic (Time & Name)
$currentHour = date('H');
if ($currentHour < 12) {
    $greeting = "Good Morning";
} elseif ($currentHour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// ✅ NEW: Fetch Admin Name
$adminId = $_SESSION["user_id"];
$nameQuery = $conn->query("SELECT name FROM users WHERE id = '$adminId'");
$adminName = "Admin"; // Default fallback
if ($nameQuery->num_rows > 0) {
    $row = $nameQuery->fetch_assoc();
    $adminName = $row['name'];
}

// ✅ Get all active users (Needed for Absent Calculation)
$userQuery = $conn->query("SELECT id FROM users WHERE role IN ('admin', 'user')");
$totalUsers = $userQuery->num_rows;

// ✅ Get today’s logs
$logQuery = $conn->query("SELECT user_id, scan_time, status FROM attendance_logs WHERE DATE(scan_time) = '$today'");

// 🟢 FIX: Count the actual scans found today
$totalScans = $logQuery->num_rows; 

$onTime = 0;
$late = 0;
$logged = [];

while ($row = $logQuery->fetch_assoc()) {
    $logged[] = $row['user_id'];
    if ($row['status'] === "On Time") $onTime++;
    elseif ($row['status'] === "Late") $late++;
}

// Absent = Total Registered Users - Unique People who Scanned
$absent = $totalUsers - count(array_unique($logged));

//  Weekly chart data
$labels = [];
$onTimeData = [];
$lateData = [];
$absentData = [];

for ($i = 6; $i >= 0; $i--) {
    $day = date("Y-m-d", strtotime("-$i days"));
    $dayLabel = date("M j", strtotime($day));

    $logs = $conn->query("SELECT user_id, status FROM attendance_logs WHERE DATE(scan_time) = '$day'");
    $usersLogged = [];
    $dayOn = 0; $dayLate = 0;

    while ($r = $logs->fetch_assoc()) {
        $usersLogged[] = $r['user_id'];
        if ($r['status'] === "On Time") $dayOn++;
        elseif ($r['status'] === "Late") $dayLate++;
    }

    $dayAbsent = $totalUsers - count(array_unique($usersLogged));

    $labels[] = $dayLabel;
    $onTimeData[] = $dayOn;
    $lateData[] = $dayLate;
    $absentData[] = $dayAbsent;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background-color: #F9F7E8;
}
.content {
    margin-left: 270px;
    padding: 30px;
}
.datetime {
    color: #1F4015;
    text-align: right;
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 10px;
}
.welcome-msg {
    color: #1F4015;
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 25px;
}
h2 {
    color: #1F4015;
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 25px;
}
/* Cards */
.insights {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 40px;
}
a.card {
    flex: 1;
    min-width: 180px;
    background: #AAB396;
    border: 2px solid #1F4015;
    color: #1F4015;
    padding: 20px;
    border-radius: 20px;
    text-align: center;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
}
a.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.2);
    background-color: #C9D3B2;
}
.card h3 { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
.card p { font-size: 28px; font-weight: bold; }

/* Chart */
.chart-container {
    background: #FFF;
    border: 10px solid #F4DD97;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    width: 80%;
    max-width: 1000px;
    margin: 0 auto;
    text-align: center;
}
.chart-container h3 {
    color: #1F4015;
    font-weight: 800;
    margin-bottom: 20px;
}
canvas { width: 100% !important; height: 400px !important; }

@media (max-width: 900px) {
    .insights { flex-direction: column; }
    .chart-container { width: 100%; }
}
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
    <div class="datetime" id="datetime"></div>

    <div class="welcome-msg">
        <?= $greeting . ", " . htmlspecialchars($adminName); ?>! 
    </div>

    <h2>Insights</h2>
    <div class="insights">
        <a href="attendance_logs.php?status=On Time" class="card">
            <h3> On Time</h3>
            <p><?= $onTime ?></p>
        </a>
        <a href="attendance_logs.php?status=Late" class="card">
            <h3> Late</h3>
            <p><?= $late ?></p>
        </a>
        <a href="attendance_logs.php?status=Absent" class="card">
            <h3> Absent</h3>
            <p><?= $absent ?></p>
        </a>
        <a href="attendance_logs.php?status=All" class="card">
            <h3> All Scans</h3>
            <p><?= $totalScans ?></p>
        </a>
    </div>

    <div class="chart-container">
        <h3> Weekly Attendance Overview</h3>
        <canvas id="attendanceChart"></canvas>
    </div>
</div>

<script>
// 🕒 DateTime
function updateDateTime() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const date = now.toLocaleDateString('en-US', options);
    const time = now.toLocaleTimeString('en-US', { hour12: true });
    document.getElementById('datetime').textContent = `${date} | ${time}`;
}
updateDateTime();
setInterval(updateDateTime, 1000);

// 📊 Chart
const ctx = document.getElementById('attendanceChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            { label: 'On Time', data: <?= json_encode($onTimeData) ?>, backgroundColor: '#5D6F47', borderColor: '#1F4015', borderWidth: 2 },
            { label: 'Late', data: <?= json_encode($lateData) ?>, backgroundColor: '#EEC752', borderColor: '#1F4015', borderWidth: 2 },
            { label: 'Absent', data: <?= json_encode($absentData) ?>, backgroundColor: '#C5543B', borderColor: '#1F4015', borderWidth: 2 },
        ]
    },
    options: {
        plugins: { legend: { position: 'bottom' } },
        scales: {
            x: { grid: { display: false } },
            y: {
                grid: { color: '#F4DD97' },
                ticks: {
                    precision: 0,
                    stepSize: 1,
                    callback: function(value) {
                        if (Number.isInteger(value)) return value;
                    }
                },
                beginAtZero: true
            }
        }
    }
});
</script>
</body>
</html>