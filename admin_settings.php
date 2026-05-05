<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

// 1. Load existing settings from database
$settings = [];
$res = $conn->query("SELECT name, value FROM settings");
while ($row = $res->fetch_assoc()) {
    $settings[$row['name']] = $row['value'];
}

$saved = false;
$error = "";

// 2. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $location_mode = $_POST['location_mode'] ?? 'school_only';
    $school_lat = trim($_POST['school_lat'] ?? '');
    $school_lng = trim($_POST['school_lng'] ?? '');
    $school_radius_m = trim($_POST['school_radius_m'] ?? '150');
    $school_head = trim($_POST['school_head'] ?? ''); // New Field

    if ($location_mode === 'school_only') {
        if ($school_lat === '' || $school_lng === '') {
            $error = "Please fill in the latitude and longitude.";
        }
        if ($school_radius_m === '' || !is_numeric($school_radius_m)) {
            $error = "Please enter a valid radius in meters.";
        }
    }

    if (!$error) {
        // SAVE TO DATABASE (Upsert 5 keys now)
        $save = $conn->prepare("
            INSERT INTO settings (name, value)
            VALUES 
              ('location_mode', ?),
              ('school_lat', ?),
              ('school_lng', ?),
              ('school_radius_m', ?),
              ('school_head', ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ");
        $save->bind_param("sssss", $location_mode, $school_lat, $school_lng, $school_radius_m, $school_head);
        $saved = $save->execute();
        $save->close();
        
        // Refresh local array
        $settings['location_mode'] = $location_mode;
        $settings['school_lat'] = $school_lat;
        $settings['school_lng'] = $school_lng;
        $settings['school_radius_m'] = $school_radius_m;
        $settings['school_head'] = $school_head;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Settings</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { background-color: #F9F7E8; color: #1F4015; font-family: 'Inter', sans-serif; margin: 0; }
.content { margin-left: 270px; padding: 40px; }
h1 { text-align: center; color: #1F4015; margin-bottom: 30px; }

.card {
  background-color: #AAB396;
  border: 2px solid #1F4015;
  border-radius: 12px;
  padding: 30px;
  color: #1F4015;
  max-width: 600px;
  margin: 0 auto 20px auto;
  box-shadow: 3px 3px 10px rgba(31,64,21,0.2);
}
.card h3 { border-bottom: 2px solid #1F4015; padding-bottom: 10px; margin-top: 0; text-align: center;}
label { display: block; font-weight: bold; margin-top: 15px; }
input[type="text"], input[type="number"], select {
  width: 100%; box-sizing: border-box;
  background-color: #F4DD97; border: 1px solid #EEC752;
  border-radius: 6px; padding: 10px; color: #1F4015; margin-top: 5px; font-size: 16px;
}
button.save {
  background-color: #EEC752; border: 1px solid #1F4015;
  padding: 12px; width: 100%; margin-top: 25px;
  border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 16px;
}
button.save:hover { background-color: #D6B540; }
.status-badge {
    text-align: center; margin-bottom: 15px; font-weight: bold;
    padding: 10px; border-radius: 5px; background: rgba(255,255,255,0.3);
}
.error-msg { color: #8B0000; text-align: center; margin-bottom: 10px; font-weight: bold; }
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="content">
  <h1>System Configuration</h1>

  <?php if($error): ?>
    <div class="error-msg"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="card">
      <h3>School Authority</h3>
      <label>School Head</label>
      <input type="text" name="school_head" 
             value="<?= htmlspecialchars($settings['school_head'] ?? '') ?>" 
             placeholder="Enter Full Name (e.g., Dr. Juan Dela Cruz)">
    </div>

    <div class="card">
      <h3>Class Modality</h3>
      
      <div class="status-badge">
        Current Mode: 
        <?php if (($settings['location_mode'] ?? '') === 'school_only'): ?>
            <span style="color: darkgreen;">Face-to-Face (Strict GPS)</span>
        <?php else: ?>
            <span style="color: #C5543B;">Synchronous / Asynchronous (Flexible)</span>
        <?php endif; ?>
      </div>

      <label>Select Modality:</label>
      <select name="location_mode" id="modeSelector">
        <option value="school_only" <?= (($settings['location_mode'] ?? '')==='school_only') ? 'selected' : '' ?>>
            Face-to-Face (Require School Location)
        </option>
        <option value="anywhere" <?= (($settings['location_mode'] ?? '')==='anywhere') ? 'selected' : '' ?>>
            Synchronous / Asynchronous (Allow Any Location)
        </option>
      </select>

      <div id="gpsFields">
          <label>School Latitude</label>
          <input type="text" name="school_lat" value="<?= htmlspecialchars($settings['school_lat'] ?? '') ?>" placeholder="e.g., 14.8589">

          <label>School Longitude</label>
          <input type="text" name="school_lng" value="<?= htmlspecialchars($settings['school_lng'] ?? '') ?>" placeholder="e.g., 120.8109">

          <label>Allowed Radius (meters)</label>
          <input type="number" name="school_radius_m" 
                 value="<?= htmlspecialchars($settings['school_radius_m'] ?? '150') ?>" 
                 placeholder="Enter radius (e.g. 50, 100, 200)">
      </div>

      <button type="submit" class="save">Update All Settings</button>
    </div>
  </form>
</div>

<script>
const modeSelector = document.getElementById('modeSelector');
const gpsFields = document.getElementById('gpsFields');
const inputs = gpsFields.querySelectorAll('input');

function toggleFields() {
    if (modeSelector.value === 'anywhere') {
        gpsFields.style.opacity = '0.5';
        inputs.forEach(input => input.readOnly = true);
    } else {
        gpsFields.style.opacity = '1';
        inputs.forEach(input => input.readOnly = false);
    }
}
modeSelector.addEventListener('change', toggleFields);
toggleFields();
</script>

<?php if ($saved): ?>
<script>Swal.fire({icon: "success", title: "Saved!", text: "Configuration updated successfully.", confirmButtonColor: "#1F4015"});</script>
<?php endif; ?>

</body>
</html>