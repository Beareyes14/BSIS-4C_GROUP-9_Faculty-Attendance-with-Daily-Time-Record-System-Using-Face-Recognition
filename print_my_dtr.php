<?php
// print_my_dtr.php
session_start();
require_once "config.php";

// 1. Check if ANY user is logged in (Admin or User)
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 2. Get ID from Session (Secure)
$user_id = $_SESSION["user_id"];
$month = $_GET['month'] ?? date("Y-m");

// 3. Fetch User Info
$info = $conn->prepare("SELECT name FROM users WHERE id=?");
$info->bind_param("i", $user_id);
$info->execute();
$faculty = $info->get_result()->fetch_assoc();
$name = strtoupper($faculty['name']);

// 4. Fetch Attendance Data
$first_day = date("Y-m-01", strtotime($month));
$last_day  = date("Y-m-t", strtotime($month));
$month_label = date("F Y", strtotime($month));

$stmt = $conn->prepare("SELECT * FROM attendance_reports WHERE faculty_id=? AND date BETWEEN ? AND ?");
$stmt->bind_param("iss", $user_id, $first_day, $last_day);
$stmt->execute();
$res = $stmt->get_result();

$logs = [];
while ($r = $res->fetch_assoc()) {
    $logs[$r['date']] = $r;
}

// 5. Fetch School Head from settings
$setting_res = $conn->query("SELECT value FROM settings WHERE name = 'school_head' LIMIT 1");
$setting_row = $setting_res->fetch_assoc();
$school_head = $setting_row['value'] ?? 'REV. FR. MENALD S. LEONARDO';

// 6. Reusable Function to Render the Form
function renderDTR($name, $month, $logs, $first_day, $last_day, $school_head) {
    $month_label = date("F Y", strtotime($month));
?>
    <div class="dtr-box">
        <div class="header">
            <div class="logo-area">
                <img src="images/logo.png" alt="Logo"> 
            </div>
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
            <div class="left">
                For the month of <span style="border-bottom: 1px solid black; font-weight:bold; padding: 0 5px;"><?= $month_label ?></span><br>
                Official hours for arrival and departure
            </div>
            <div class="right">
                Regular days ______________<br>
                Saturdays ______________
            </div>
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
                    <th>Arrival</th>
                    <th>Departure</th>
                    <th>Arrival</th>
                    <th>Departure</th>
                    <th>Hours</th>
                    <th>Minutes</th>
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
                        
                        $fmt = function($val) { 
                            return ($val && $val != '00:00:00') ? date("h:i", strtotime($val)) : ''; 
                        };

                        $am_in = $fmt($log['time_in_am'] ?? '');
                        $am_out = $fmt($log['time_out_am'] ?? '');
                        $pm_in = $fmt($log['time_in_pm'] ?? '');
                        $pm_out = $fmt($log['time_out_pm'] ?? '');
                        
                        // Split Hours and Minutes
                        $disp_hr = '';
                        $disp_min = '';
                        
                        if ($log && $log['total_hours'] > 0) {
                            $total_hrs += $log['total_hours'];
                            $disp_hr = floor($log['total_hours']);
                            $min_dec = $log['total_hours'] - $disp_hr;
                            $disp_min = round($min_dec * 60);
                            if ($disp_min == 0) $disp_min = '';
                        }

                        echo "<tr>
                            <td>$d</td>
                            <td>$am_in</td>
                            <td>$am_out</td>
                            <td>$pm_in</td>
                            <td>$pm_out</td>
                            <td>$disp_hr</td>
                            <td>$disp_min</td>
                        </tr>";
                    } else {
                        echo "<tr><td>$d</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                    }
                }
                
                // Final Totals
                $fin_hr = 0; $fin_min = 0;
                if ($total_hrs > 0) {
                    $fin_hr = floor($total_hrs);
                    $fin_min = round(($total_hrs - $fin_hr) * 60);
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align:right; font-weight:bold;">Total</td>
                    <td><?= $fin_hr > 0 ? $fin_hr : '' ?></td>
                    <td><?= $fin_min > 0 ? $fin_min : '' ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="footer-text">
            <p>I certify on my honor that the above is a true and correct report of the hours of work performed, record of which was made daily at the time of arrival and departure from office.</p>
        </div>

        <div class="signatures">
            <div class="user-sign">
                <div class="line"></div>
                <p>(Name and Signature)</p>
            </div>

            <p style="margin: 5px 0 2px 0; font-size: 8px;">VERIFIED as to the prescribed office hours:</p>

            <div class="head-sign">
                <strong style="text-decoration: underline;"><?= htmlspecialchars(strtoupper($school_head)) ?></strong>
                <p>School Head</p>
            </div>
        </div>
    </div>
<?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Print DTR - <?= htmlspecialchars($name) ?></title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap');
    
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body { 
        background: #555; 
        font-family: Arial, sans-serif; 
        /* Center content logic */
        display: flex; 
        justify-content: center; 
        align-items: center; 
        min-height: 100vh;
    }

    /* PAPER FORMAT: Letter Size 8.5 x 11 */
    .paper {
        width: 8.5in;
        height: 11in;
        background: white;
        
        /* MARGIN ADJUSTMENT (0.3 inches left/right) */
        padding: 0 0.3in; 
        
        /* CENTER MIDDLE LOGIC */
        display: flex;
        justify-content: center; /* Center horizontally */
        align-items: center;     /* Center vertically */
        gap: 0.2in;              /* Gap between two forms */
        
        box-shadow: 0 0 15px rgba(0,0,0,0.5);
    }

    /* INDIVIDUAL DTR FORM BOX */
    .dtr-box {
        flex: 1; /* Take equal width */
        /* TIGHT BORDER LOGIC: Fit content height */
        height: fit-content; 
        border: 1px solid black; 
        padding: 8px;
        
        display: flex; 
        flex-direction: column;
    }

    /* HEADER STYLES */
    .header { display: flex; align-items: center; justify-content: center; gap: 5px; margin-bottom: 2px; }
    .logo-area img { width: 60px; height: 60px; }
    .school-name { text-align: center; }
    .school-name h3 { font-size: 15px; font-weight: bold; margin: 0; }
    .school-name p { font-size: 12px; margin-bottom: px; }

    .sub-header { text-align: center; margin-bottom: 5px; }
    .sub-header h2 { font-size: 13px; font-weight: bold; margin: 0; }

    .user-name { text-align: center; margin-bottom: 2px; }
    .user-name h3 { font-size: 15px; margin: 0; }
    .user-name p { font-size: 10px; font-style: italic; }

    .meta-info { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 2px; }
    .meta-info .right { text-align: right; margin-bottom: 5px; }

    /* TABLE STYLES */
    .dtr-table { 
        width: 100%; border-collapse: collapse; font-size: 10px; 
        margin-bottom: 2px; 
    }
    .dtr-table th, .dtr-table td { border: 1px solid black; text-align: center; height: 11px; padding: 0; }
    .dtr-table th { background: #f0f0f0; }

    /* FOOTER STYLES */
    /* Reduced margin to pull border up */
    .footer-text { font-size: 8px; font-style: italic; text-align: justify; margin-bottom: 5px; margin-top: 5px; line-height: 1.1; }
    
    /* SIGNATURES */
    .signatures { text-align: center; margin-top: 0; }
    .signatures .line { border-bottom: 1px solid black; width: 80%; margin: 0 auto; margin-top: 25px; }
    .signatures p { font-size: 8px; margin: 1px 0; }
    .head-sign { margin-top: 45px; } /* Space for Head signature */
    .head-sign strong { font-size: 9px; display: block; }

    /* CONTROLS (Buttons) */
    .no-print { 
        position: fixed; top: 0; left: 0; width: 100%; 
        background: #333; padding: 10px; text-align: center; z-index: 100;
    }
    button { padding: 10px 20px; cursor: pointer; font-weight: bold; background: #1F4015; color: white; border: none; border-radius: 5px; margin: 0 5px; }
    button.back { background: #d9534f; }

    /* PRINT SETTINGS */
    @media print {
        @page { 
            size: letter; 
            margin: 0; /* Reset default margins to control via CSS */
        }
        body { 
            background: none; 
            padding: 0; 
            margin: 0; 
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center; 
        }
        .no-print { display: none; }
        .paper { 
            width: 8.5in; 
            height: 11in; 
            box-shadow: none; 
            margin: 0; 
            
            /* KEEP MARGINS IN PRINT */
            padding: 0 0.3in; 
            
            display: flex; 
            align-items: center; 
            justify-content: center;
        }
        .dtr-box { border: 1px solid black !important; } 
    }
</style>
</head>
<body onload="/*window.print()*/"> 
    <div class="no-print">
        <button class="back" onclick="window.close()">Close</button>
        <button onclick="window.print()"> Print DTR</button>
    </div>

    <div class="paper">
        <?php renderDTR($name, $month, $logs, $first_day, $last_day, $school_head); ?>
        
        <?php renderDTR($name, $month, $logs, $first_day, $last_day, $school_head); ?>
    </div>

</body>
</html>