<?php
// 🔴 TEMPORARY ERROR REPORTER: Para makita natin kung may error imbes na white screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "config.php";

// 1. I-check kung naka-login talaga
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 2. Linisin ang Role (Tanggal spaces at gawing small letters)
$clean_role = strtolower(trim($_SESSION["role"]));

// 3. Kick out Admin pabalik sa login
if ($clean_role === "admin") {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Fetch user info
$stmt = $conn->prepare("SELECT name FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name);
$stmt->fetch();
$stmt->close();

// ✅ Greeting Logic
date_default_timezone_set('Asia/Manila'); 
$today = date("Y-m-d");
$currentHour = date('H');
$currentYear = date('Y');
$currentMonthNum = date('n');

if ($currentHour < 12) {
    $greeting = "Good Morning";
} elseif ($currentHour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// ==========================================
// 📊 CHART DATA LOGIC (Monthly Overview: Jan - Dec)
// ==========================================

$labels = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$onTimeData = array_fill(0, 12, 0);
$lateData = array_fill(0, 12, 0);
$absentData = array_fill(0, 12, 0);

// 1. Get On Time & Late counts
$stmt = $conn->prepare("SELECT MONTH(scan_time) as mth, status, COUNT(*) as cnt FROM attendance_logs WHERE user_id = ? AND YEAR(scan_time) = ? GROUP BY MONTH(scan_time), status");
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $currentYear);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) {
        $mIndex = $row['mth'] - 1; 
        if($row['status'] === "On Time") $onTimeData[$mIndex] = $row['cnt'];
        if($row['status'] === "Late") $lateData[$mIndex] = $row['cnt'];
    }
    $stmt->close();
}

// 2. Calculate Absences
$daysLoggedIn = array_fill(0, 12, 0);
$stmt = $conn->prepare("
    SELECT MONTH(scan_time) as mth, COUNT(DISTINCT DATE(scan_time)) as days 
    FROM attendance_logs 
    WHERE user_id = ? AND YEAR(scan_time) = ? AND DAYOFWEEK(scan_time) BETWEEN 2 AND 6 
    GROUP BY MONTH(scan_time)
");
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $currentYear);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) {
        $daysLoggedIn[$row['mth'] - 1] = $row['days'];
    }
    $stmt->close();
}

for ($m = 1; $m <= 12; $m++) {
    if ($m > $currentMonthNum) break; 
    
    $daysInMonth = date('t', strtotime("$currentYear-$m-01"));
    $weekdays = 0;
    
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date_str = sprintf("%04d-%02d-%02d", $currentYear, $m, $d);
        if ($date_str >= $today) continue; 
        
        $dayOfWeek = date('N', strtotime($date_str)); 
        if ($dayOfWeek <= 5) { 
            $weekdays++;
        }
    }
    
    $absences = $weekdays - $daysLoggedIn[$m - 1];
    $absentData[$m - 1] = ($absences > 0) ? $absences : 0;
}

// ==========================================
// 🃏 CARD TOTALS LOGIC (Current Month)
// ==========================================
$total_on_time = $onTimeData[$currentMonthNum - 1] ?? 0;
$total_late = $lateData[$currentMonthNum - 1] ?? 0;
$total_absent = $absentData[$currentMonthNum - 1] ?? 0; 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            background-color: #F9F7E8; 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            padding: 0; 
            overflow-x: hidden;
        }

        .content {
            margin-left: 270px; 
            padding: 30px;
            width: calc(100% - 270px); 
            box-sizing: border-box; 
            min-height: 100vh;
        }

        .datetime {
            color: #1F4015;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: right; 
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

        .insights {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 40px;
            width: 100%;
        }

        .card {
            flex: 1;
            min-width: 200px; 
            background: #AAB396;
            border: 2px solid #1F4015;
            color: #1F4015;
            padding: 20px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer; 
            box-sizing: border-box;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            background-color: #C9D3B2;
        }

        .card h3 { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
        .card p { font-size: 28px; font-weight: bold; margin: 0; }

        .chart-container {
            background: #FFF;
            border: 10px solid #F4DD97;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 100%; 
            margin: 0 auto;
            text-align: center;
            box-sizing: border-box;
        }

        .chart-container h3 { color: #1F4015; font-weight: 800; margin-bottom: 20px; }

        .canvas-wrapper {
            position: relative;
            height: 400px;
            width: 100%;
        }

        @media (max-width: 900px) {
            .content {
                margin-left: 0;
                width: 100%;
                padding: 70px 15px 15px 15px;
            }

            .datetime { font-size: 14px; text-align: center; }
            .welcome-msg, h2 { text-align: center; }
            
            .insights { flex-direction: column; }

            .chart-container {
                padding: 15px;
                border-width: 5px;
            }
            .canvas-wrapper { height: 250px; }
        }
    </style>
</head>
<body>

<?php include "user_sidebar.php"; ?>

<div class="content">
    <div class="datetime" id="datetime"></div>

    <div class="welcome-msg">
        <?= $greeting . ", " . htmlspecialchars($name); ?>! 
    </div>

    <h2>Insights for <?php echo date('F Y'); ?></h2>
    
    <div class="insights">
        <div class="card" onclick="window.location.href='user_attendance_logs.php?status=On%20Time'">
            <h3> On Time</h3>
            <p><?php echo $total_on_time; ?></p>
        </div>
        <div class="card" onclick="window.location.href='user_attendance_logs.php?status=Late'">
            <h3> Late</h3>
            <p><?php echo $total_late; ?></p>
        </div>
        <div class="card" onclick="window.location.href='user_attendance_logs.php?status=Absent'">
            <h3> Absent (This Month)</h3> 
            <p><?php echo $total_absent; ?></p>
        </div>
    </div>

    <div class="chart-container">
        <h3> Monthly Attendance Overview (<?= $currentYear ?>)</h3>
        <div class="canvas-wrapper">
            <canvas id="attendanceChart"></canvas>
        </div>
    </div>
</div>

<script>
    // 🕒 DateTime
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('datetime').textContent =
            `${now.toLocaleDateString('en-US', options)} | ${now.toLocaleTimeString('en-US', { hour12: true })}`;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // 📊 Chart
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels); ?>, 
            datasets: [
                {
                    label: 'On Time',
                    data: <?php echo json_encode($onTimeData); ?>,
                    backgroundColor: '#5D6F47',
                    borderColor: '#1F4015',
                    borderWidth: 2,
                },
                {
                    label: 'Late',
                    data: <?php echo json_encode($lateData); ?>,
                    backgroundColor: '#EEC752',
                    borderColor: '#1F4015',
                    borderWidth: 2,
                },
                {
                    label: 'Absent',
                    data: <?php echo json_encode($absentData); ?>,
                    backgroundColor: '#C5543B',
                    borderColor: '#1F4015',
                    borderWidth: 2,
                },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                x: { grid: { display: false } },
                y: { 
                    grid: { color: '#F4DD97' },
                    ticks: { precision: 0, stepSize: 1 },
                    beginAtZero: true
                }
            }
        }
    });
</script>

</body>
</html>