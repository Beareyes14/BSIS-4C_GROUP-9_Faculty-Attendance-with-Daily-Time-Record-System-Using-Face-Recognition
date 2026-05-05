<?php
require_once "config.php";
require_once "vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

// Make sure a user ID is provided
if (!isset($_GET['id'])) {
    die("No faculty selected.");
}

$id = intval($_GET['id']);

// Fetch faculty info
$user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_query->bind_param("i", $id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

if (!$user) {
    die("Faculty not found.");
}

// Fetch attendance logs for this user (sorted ascending by date)
$logs_query = $conn->prepare("SELECT * FROM attendance_logs WHERE user_id = ? ORDER BY scan_time ASC");
$logs_query->bind_param("i", $id);
$logs_query->execute();
$logs = $logs_query->get_result();

// Group logs by date
$attendance = [];
while ($row = $logs->fetch_assoc()) {
    $date = date("Y-m-d", strtotime($row["scan_time"]));
    $time = date("h:i A", strtotime($row["scan_time"]));
    $attendance[$date][] = [
        "action" => $row["action"],
        "time" => $time
    ];
}

// If "download" is requested → Generate PDF
if (isset($_GET['download']) && $_GET['download'] === 'true') {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);

    ob_start();
    include "Attendance/templates/dtr_template.php"; // we'll create this file next
    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper('Letter', 'portrait');
    $dompdf->render();

    $filename = "DTR_" . $user['name'] . "_" . date("F_Y") . ".pdf";
    $dompdf->stream($filename, ["Attachment" => true]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View DTR - <?= htmlspecialchars($user['name']) ?></title>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #F9F7E8;
        margin: 0;
        padding: 30px;
        color: #1F4015;
    }

    .dtr-container {
        background: white;
        border: 2px solid #1F4015;
        border-radius: 10px;
        padding: 20px 40px;
        width: 900px;
        margin: 0 auto;
    }

    h2 {
        text-align: center;
        color: #1F4015;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th, td {
        border: 1px solid #1F4015;
        text-align: center;
        padding: 8px;
    }

    th {
        background-color: #AAB396;
    }

    .header-info {
        text-align: center;
        margin-bottom: 10px;
    }

    .download-btn {
        display: block;
        margin: 20px auto;
        background-color: #EEC752;
        color: #1F4015;
        font-weight: bold;
        padding: 10px 20px;
        border: 2px solid #1F4015;
        border-radius: 6px;
        text-decoration: none;
    }

    .download-btn:hover {
        background-color: #5D6F47;
        color: white;
    }
</style>
</head>
<body>

<div class="dtr-container">
    <div class="header-info">
        <h2>St. Joseph Parochial School</h2>
        <p><b>Daily Time Record (DTR)</b></p>
        <p><b>Name:</b> <?= htmlspecialchars($user['name']) ?> |
           <b>Position:</b> <?= htmlspecialchars($user['position']) ?> |
           <b>Department:</b> <?= htmlspecialchars($user['department']) ?></p>
        <p><b>Month:</b> <?= date("F Y") ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Time In (AM)</th>
                <th>Time Out (AM)</th>
                <th>Time In (PM)</th>
                <th>Time Out (PM)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Generate each day row
            $monthDays = date("t");
            $month = date("Y-m");
            for ($d = 1; $d <= $monthDays; $d++) {
                $currentDate = sprintf("%s-%02d", $month, $d);
                $displayDate = date("F d, Y", strtotime($currentDate));

                $times = ["", "", "", ""];
                if (isset($attendance[$currentDate])) {
                    foreach ($attendance[$currentDate] as $entry) {
                        if (stripos($entry["action"], "Time In AM") !== false || stripos($entry["action"], "Early Time In AM") !== false)
                            $times[0] = $entry["time"];
                        if (stripos($entry["action"], "Time Out AM") !== false)
                            $times[1] = $entry["time"];
                        if (stripos($entry["action"], "Time In PM") !== false)
                            $times[2] = $entry["time"];
                        if (stripos($entry["action"], "Time Out PM") !== false)
                            $times[3] = $entry["time"];
                    }
                }

                echo "<tr>
                        <td>$displayDate</td>
                        <td>{$times[0]}</td>
                        <td>{$times[1]}</td>
                        <td>{$times[2]}</td>
                        <td>{$times[3]}</td>
                      </tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<a href="view_dtr.php?id=<?= $id ?>&download=true" class="download-btn">⬇️ Download as PDF</a>

</body>
</html>
            