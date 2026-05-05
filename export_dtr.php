<?php
session_start();
require_once "config.php";

// 1. Security Check
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    die("Unauthorized.");
}

// 2. Validation
if (empty($_POST['selected_ids'])) {
    die("No users selected.");
}

$ids = $_POST['selected_ids']; // Array of IDs
$month = $_POST['month'] ?? date("Y-m");

// Fetch School Head from settings to use throughout the script
$setting_res = $conn->query("SELECT value FROM settings WHERE name = 'school_head' LIMIT 1");
$setting_row = $setting_res->fetch_assoc();
$school_head = $setting_row['value'] ?? 'REV. FR. MENALD S. LEONARDO';

// 3. Reusable DTR Rendering Function (Exact copy of your print_dtr.php logic)
function renderUserDTR($conn, $faculty_id, $month, $school_head) {
    // A. Fetch Info
    $info = $conn->prepare("SELECT name, position, department FROM users WHERE id=?");
    $info->bind_param("i", $faculty_id);
    $info->execute();
    $res = $info->get_result();
    if($res->num_rows == 0) return; // Skip if user deleted
    $faculty = $res->fetch_assoc();
    $name = strtoupper($faculty['name']);

    // B. Fetch Attendance
    $first_day = date("Y-m-01", strtotime($month));
    $last_day  = date("Y-m-t", strtotime($month));
    $month_label = date("F Y", strtotime($month));

    $stmt = $conn->prepare("SELECT * FROM attendance_reports WHERE faculty_id=? AND date BETWEEN ? AND ?");
    $stmt->bind_param("iss", $faculty_id, $first_day, $last_day);
    $stmt->execute();
    $logs_res = $stmt->get_result();

    $logs = [];
    while ($r = $logs_res->fetch_assoc()) {
        $logs[$r['date']] = $r;
    }

    // C. Render TWO Forms (Left & Right)
    for($i=0; $i<2; $i++) { 
    ?>
    <div class="dtr-box">
        <div class="header">
            <div class="logo-area"><img src="images/logo.png" alt="Logo"></div>
            <div class="school-name">
                <h3>ST. JOSEPH PAROCHIAL SCHOOL</h3>
                <p>Panasahan, City of Malolos, Bulacan</p>
            </div>
        </div>

        <div class="sub-header">
            <p style="float:left; font-size: 8px; font-style:italic;">Civil Service Form No. 48</p> <br>
            <h2>DAILY TIME RECORD</h2>
        </div>

        <div class="user-name">
            <h3 style="border-bottom: 2px solid black; display:inline-block; width: 90%; margin-bottom: 2px;"><?= htmlspecialchars($name) ?></h3>
            <p>(Name)</p>
        </div>

        <div class="meta-info">
            <div class="left">For the month of <span style="border-bottom: 1px solid black; font-weight:bold; padding: 0 5px;"><?= $month_label ?></span></div>
            <div class="right">Regular days _______ Saturdays _______</div>
        </div>

        <table class="dtr-table">
            <thead>
                <tr>
                    <th rowspan="2" width="8%">Day</th>
                    <th colspan="2">A.M.</th>
                    <th colspan="2">P.M.</th>
                    <th colspan="2">Total</th>
                </tr>
                <tr>
                    <th>Arr</th><th>Dep</th><th>Arr</th><th>Dep</th><th>Hrs</th><th>Mins</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total_hrs = 0;
                for ($d = 1; $d <= 31; $d++) {
                    $current_date_str = date("Y-m-", strtotime($month)) . str_pad($d, 2, '0', STR_PAD_LEFT);
                    $day_exists = checkdate(date("m", strtotime($month)), $d, date("Y", strtotime($month)));
                    
                    if ($day_exists) {
                        $log = $logs[$current_date_str] ?? null;
                        $fmt = function($val) { return ($val && $val != '00:00:00') ? date("h:i", strtotime($val)) : ''; };
                        
                        // Data logic
                        $am_in = $fmt($log['time_in_am'] ?? '');
                        $am_out = $fmt($log['time_out_am'] ?? '');
                        $pm_in = $fmt($log['time_in_pm'] ?? '');
                        $pm_out = $fmt($log['time_out_pm'] ?? '');
                        
                        $disp_hr = ''; $disp_min = '';
                        if ($log && $log['total_hours'] > 0) {
                            $total_hrs += $log['total_hours'];
                            $disp_hr = floor($log['total_hours']);
                            $min_dec = $log['total_hours'] - $disp_hr;
                            $disp_min = round($min_dec * 60);
                            if ($disp_min == 0) $disp_min = '';
                        }
                        echo "<tr><td>$d</td><td>$am_in</td><td>$am_out</td><td>$pm_in</td><td>$pm_out</td><td>$disp_hr</td><td>$disp_min</td></tr>";
                    } else {
                        echo "<tr><td>$d</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                    }
                }
                
                $fin_hr = floor($total_hrs);
                $fin_min = round(($total_hrs - $fin_hr) * 60);
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right; font-weight:bold;">Total</td>
                    <td><?= $fin_hr ?: '' ?></td><td><?= $fin_min ?: '' ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="footer-text">
            <p>I certify on my honor that the above is a true and correct report...</p>
        </div>

        <div class="signatures">
            <div class="user-sign"><div class="line"></div><p>(Name and Signature)</p></div>
            <p style="font-size: 8px;">VERIFIED as to prescribed hours:</p>
            <div class="head-sign"><strong style="text-decoration: underline;"><?= htmlspecialchars(strtoupper($school_head)) ?></strong><p>School Head</p></div>
        </div>
    </div>
    <?php } // End loop for 2 copies ?>
<?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bulk Print DTR</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap');
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body { background: #555; font-family: Arial, sans-serif; }

    /* PAPER */
    .paper {
        width: 8.5in;
        height: 11in;
        background: white;
        padding: 0 0.3in;
        margin: 20px auto; /* Centered in browser */
        
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.2in;
        box-shadow: 0 0 15px rgba(0,0,0,0.5);
        
        /* 🟢 KEY FOR BULK PRINTING: Page Break */
        page-break-after: always;
    }

    /* Remove page break from the very last page to avoid empty sheet */
    .paper:last-child { page-break-after: auto; }

    /* YOUR EXACT STYLES */
    .dtr-box { flex: 1; height: fit-content; border: 1px solid black; padding: 8px; display: flex; flex-direction: column; }
    .header { display: flex; align-items: center; justify-content: center; gap: 5px; margin-bottom: 2px; }
    .logo-area img { width: 60px; height: 60px; }
    .school-name { text-align: center; }
    .school-name h3 { font-size: 15px; font-weight: bold; margin: 0; }
    .school-name p { font-size: 12px; margin: 0; }
    .sub-header { text-align: center; margin-bottom: 5px; }
    .sub-header h2 { font-size: 13px; font-weight: bold; margin: 0; }
    .user-name { text-align: center; margin-bottom: 2px; }
    .user-name h3 { font-size: 15px; margin: 0; }
    .user-name p { font-size: 10px; font-style: italic; }
    .meta-info { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 2px; }
    .meta-info .right { text-align: right; }
    .dtr-table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 2px; }
    .dtr-table th, .dtr-table td { border: 1px solid black; text-align: center; height: 11px; padding: 0; }
    .dtr-table th { background: #f0f0f0; }
    .footer-text { font-size: 8px; font-style: italic; text-align: justify; margin: 5px 0; line-height: 1.1; }
    .signatures { text-align: center; }
    .signatures .line { border-bottom: 1px solid black; width: 80%; margin: 25px auto 0 auto; }
    .signatures p { font-size: 8px; margin: 1px 0; }
    .head-sign { margin-top: 45px; }
    .head-sign strong { font-size: 9px; display: block; }

    /* CONTROLS */
    .no-print { position: fixed; top: 0; left: 0; width: 100%; background: #333; padding: 10px; text-align: center; z-index: 100; }
    button { padding: 10px 20px; font-weight: bold; cursor: pointer; background: #1F4015; color: white; border: none; border-radius: 5px; }
    button.back { background: #d9534f; margin-right: 10px; }

    /* PRINT SETTINGS */
    @media print {
        @page { size: letter; margin: 0; }
        body { background: none; -webkit-print-color-adjust: exact; }
        .no-print { display: none; }
        .paper { margin: 0; box-shadow: none; height: 100vh; width: 100vw; }
        .dtr-box { border: 1px solid black !important; }
    }
</style>
</head>
<body>

    <div class="no-print">
        <button class="back" onclick="window.close()">Close</button>
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    <?php foreach ($ids as $id): ?>
        <div class="paper">
            <?php renderUserDTR($conn, $id, $month, $school_head); ?>
        </div>
    <?php endforeach; ?>

</body>
</html>