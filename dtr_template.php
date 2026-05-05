<style>
body { font-family: Arial, sans-serif; font-size: 12px; color: #1F4015; }
h2 { text-align: center; margin-bottom: 5px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { border: 1px solid #000; padding: 6px; text-align: center; }
th { background: #AAB396; }
.header-info { text-align: center; margin-bottom: 10px; }
</style>

<div class="header-info">
    <h2>ST. JOSEPH PAROCHIAL SCHOOL</h2>
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
