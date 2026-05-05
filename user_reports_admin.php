<?php
session_start();
require_once "config.php";

//user_reports_admin.php - Admin View for Faculty Attendance Reports (Form 48)
// Only allow admin
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$faculty_id = $_GET['faculty_id'] ?? null;
$month = $_GET['month'] ?? date("Y-m");

if (!$faculty_id) {
    die("No faculty selected.");
}

// 1. Setup Date Range
$first_day = date("Y-m-01", strtotime($month));
$last_day  = date("Y-m-t", strtotime($month));
$month_label = date("F Y", strtotime($month));

// 2. Fetch Faculty Info
$info = $conn->prepare("SELECT name, position, department FROM users WHERE id = ?");
$info->bind_param("i", $faculty_id);
$info->execute();
$faculty = $info->get_result()->fetch_assoc();

// 3. Fetch Attendance Data
$stmt = $conn->prepare("SELECT * FROM attendance_reports WHERE faculty_id=? AND date BETWEEN ? AND ?");
$stmt->bind_param("iss", $faculty_id, $first_day, $last_day);
$stmt->execute();
$res = $stmt->get_result();

$logs = [];
while ($r = $res->fetch_assoc()) {
    $day_num = (int)date("d", strtotime($r['date']));
    $logs[$day_num] = $r;
}

// 4. Fetch School Head from settings
$setting_res = $conn->query("SELECT value FROM settings WHERE name = 'school_head' LIMIT 1");
$setting_row = $setting_res->fetch_assoc();
$school_head = $setting_row['value'] ?? 'REV. FR. MENALD S. LEONARDO';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title> <?= htmlspecialchars($faculty['name']) ?> - DTR Report</title>
<style>
body {
  background: #F9F7E8;
  font-family: Arial, sans-serif;
  color: #1F4015;
  margin: 0; padding: 0;
}
.content {
  margin-left: 270px;
  padding: 40px;
  min-height: 100vh;
}
.back-btn {
  background: #EEC752; border: 2px solid #1F4015;
  color: #1F4015; padding: 8px 16px; border-radius: 6px;
  font-weight: bold; text-decoration: none; display: inline-block; margin-bottom: 20px;
}
.back-btn:hover { background: #D6B540; }

/* --- FORM 48 CONTAINER --- */
.form-container {
    background: white;
    width: 100%;
    max-width: 800px;
    margin: 0 auto;
    padding: 30px;
    border: 1px solid #999;
    box-shadow: 5px 5px 15px rgba(0,0,0,0.2);
    color: black;
}

/* 1. SCHOOL HEADER (Logo + Text) */
.school-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 15px;
}
.school-header img {
    width: 60px; /* Adjust logo size */
    height: 60px;
}
.school-text {
    text-align: center;
}
.school-text h3 {
    margin: 0;
    font-size: 16px;
    font-weight: bold;
    text-transform: uppercase;
}
.school-text p {
    margin: 2px 0 0 0;
    font-size: 12px;
}

/* 2. FORM NO & TITLE */
.cs-form-no { 
    font-size: 14px; /* Bigger Text */
    font-style: italic; 
    font-weight: bold;
    margin-bottom: 5px; 
}
.form-title { 
    text-align: center;
    font-size: 20px; 
    font-weight: bold; 
    margin-bottom: 20px; 
}

/* 3. NAME SECTION */
.user-section { text-align: center; margin-bottom: 15px; }
.user-name { 
    font-size: 18px; font-weight: bold; text-transform: uppercase; 
    border-bottom: 2px solid black; display: inline-block; 
    width: 60%; margin-bottom: 2px;
}
.label-text { font-size: 12px; font-style: italic; }

/* 4. MONTH LINE (Replaces Position/Dept) */
.month-section {
    text-align: center;
    margin-bottom: 20px;
    font-size: 14px; /* Bigger Text */
}
.month-underline {
    border-bottom: 2px solid black;
    font-weight: bold;
    padding: 0 10px;
}

/* 5. HOURS & DAYS INFO */
.meta-row { 
    display: flex; 
    justify-content: space-between; 
    font-size: 13px; /* Bigger Text */
    margin-bottom: 10px; 
}
.meta-left { text-align: left; }
.meta-right { text-align: right; }

/* TABLE */
table.dtr-table {
    width: 100%; border-collapse: collapse;
    font-size: 12px;
    margin-bottom: 20px;
}
table.dtr-table th, table.dtr-table td {
    border: 1px solid black; text-align: center; padding: 4px; height: 22px;
}
table.dtr-table th { background-color: #f0f0f0; }
.weekend-row { background-color: #FFF8E1; }

/* PRINT BTN */
.print-btn {
    background: #1F4015; color: white; border: none;
    padding: 10px 20px; border-radius: 5px; cursor: pointer;
    font-weight: bold; font-size: 14px; text-decoration: none;
    display: inline-block; margin-top: 20px;
}
.print-btn:hover { background: #142b0e; }

/* NEW: Auto-Fill Highlight (Light Yellow) */
.autofill { background-color: #FFF59D !important; font-weight: bold; }

</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="content">
  <a href="reports.php" class="back-btn">⬅ Back to Reports</a>

  <div class="form-container">
      
      <div class="school-header">
          <img src="images/logo.png" alt="Logo">
          <div class="school-text">
              <h3>ST. JOSEPH PAROCHIAL SCHOOL</h3>
              <p>Panasahan, City of Malolos, Bulacan</p>
          </div>
      </div>

      <div class="cs-form-no">Civil Service Form No. 48</div>
      <div class="form-title">DAILY TIME RECORD</div>

      <div class="user-section">
          <div class="user-name"><?= htmlspecialchars($faculty['name']) ?></div>
          <div class="label-text">(Name)</div>
      </div>

      <div class="month-section">
          For the month of <span class="month-underline"><?= $month_label ?></span>
      </div>

      <div class="meta-row">
          <div class="meta-left">
              Official hours for arrival and departure
          </div>
          <div class="meta-right">
              Regular days ______________<br>
              Saturdays ______________
          </div>
      </div>

      <table class="dtr-table">
        <thead>
            <tr>
                <th rowspan="2" width="10%">Day</th>
                <th colspan="2">A.M.</th>
                <th colspan="2">P.M.</th>
                <th colspan="2">Total</th>
            </tr>
            <tr>
                <th width="15%">Arrival</th>
                <th width="15%">Departure</th>
                <th width="15%">Arrival</th>
                <th width="15%">Departure</th>
                <th width="10%">Hours</th>
                <th width="10%">Minutes</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $total_monthly_decimal = 0; // Accumulate decimal hours

            for ($d = 1; $d <= 31; $d++) {
                $check_date = date("Y-m-", strtotime($month)) . str_pad($d, 2, '0', STR_PAD_LEFT);
                $isValidDate = checkdate(date("m", strtotime($month)), $d, date("Y", strtotime($month)));
                
                $rowClass = "";
                if ($isValidDate) {
                    $dayName = date("l", strtotime($check_date));
                    if ($dayName == "Saturday" || $dayName == "Sunday") $rowClass = "weekend-row";
                }

                $log = $logs[$d] ?? null;

                //  Highlight Logic: Check for exactly 12:00:00 (AM Out) or 13:00:00 (PM In)
                $am_out_class = ($log && $log['time_out_am'] == '12:00:00') ? 'class="autofill"' : '';
                $pm_in_class  = ($log && $log['time_in_pm'] == '13:00:00') ? 'class="autofill"' : '';

                // Format times
                $am_in = $log && $log['time_in_am'] ? date("h:i", strtotime($log['time_in_am'])) : '';
                $am_out = $log && $log['time_out_am'] ? date("h:i", strtotime($log['time_out_am'])) : '';
                $pm_in = $log && $log['time_in_pm'] ? date("h:i", strtotime($log['time_in_pm'])) : '';
                $pm_out = $log && $log['time_out_pm'] ? date("h:i", strtotime($log['time_out_pm'])) : '';
                
                //  SPLIT HOURS AND MINUTES
                $display_hrs = '';
                $display_mins = '';
                
                if ($log && $log['total_hours'] > 0) {
                    $daily_decimal = $log['total_hours'];
                    $total_monthly_decimal += $daily_decimal;
                    
                    // Convert decimal hours (8.75) to H:M (8:45)
                    $display_hrs = floor($daily_decimal); 
                    $decimal_part = $daily_decimal - $display_hrs;
                    $display_mins = round($decimal_part * 60);
                    
                    if ($display_mins == 0) $display_mins = '';
                }

                if ($isValidDate) {
                    echo "<tr class='$rowClass'>
                        <td>$d</td>
                        <td>$am_in</td>
                        <td $am_out_class>$am_out</td>
                        <td $pm_in_class>$pm_in</td>
                        <td>$pm_out</td>
                        <td>$display_hrs</td>
                        <td>$display_mins</td>
                    </tr>";
                } else {
                    echo "<tr style='background:#eee;'>
                        <td>$d</td><td colspan='6'></td>
                    </tr>";
                }
            }
            
            //  Calculate Grand Total Row
            $final_hrs = 0;
            $final_mins = 0;
            
            if ($total_monthly_decimal > 0) {
                $final_hrs = floor($total_monthly_decimal);
                $final_decimal_mins = $total_monthly_decimal - $final_hrs;
                $final_mins = round($final_decimal_mins * 60);
            }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align:right; padding-right:10px;"><strong>TOTAL</strong></td>
                <td><strong><?= $final_hrs > 0 ? $final_hrs : '' ?></strong></td>
                <td><strong><?= $final_mins > 0 ? $final_mins : '' ?></strong></td>
            </tr>
        </tfoot>
      </table>

      <div style="font-size: 11px; font-style: italic; text-align: justify; margin-bottom: 30px;">
        I certify on my honor that the above is a true and correct report of the hours of work performed, record of which was made daily at the time of arrival and departure from office.
      </div>
      
      <div style="text-align:center; border-top: 1px solid black; width: 60%; margin: 0 auto;">
          <small>(Name and Signature)</small>
      </div>
      
      <div style="font-size: 11px; margin-top: 20px;">
        VERIFIED as to the prescribed office hours:
      </div>
      
       <div style="text-align:center; margin-top: 30px;">
          <div style="font-weight:bold; text-decoration:underline;"><?= htmlspecialchars(strtoupper($school_head)) ?></div>
          <small>School Head</small>
      </div>

  </div> <div style="text-align:center;">
      <a href="print_dtr.php?faculty_id=<?= $faculty_id ?>&month=<?= $month ?>" target="_blank" class="print-btn">
          Export Official Form 48
      </a>
  </div>

</div>
</body>
</html>