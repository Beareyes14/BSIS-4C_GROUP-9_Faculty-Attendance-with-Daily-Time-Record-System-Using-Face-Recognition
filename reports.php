<?php
session_start();
require_once "config.php";

// Allow only admin users
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

// Get all users
$sql = "SELECT id, name, role, position, department FROM users ORDER BY name ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports</title>
<style>
body {
    background-color: #F9F7E8;
    font-family: Arial, sans-serif;
    color: #1F4015;
    margin: 0;
    padding: 0;
}
.content {
    margin-left: 270px;
    padding: 25px 40px;
    min-height: 100vh;
}
h1 {
    color: #1F4015;
    text-align: center;
    margin-bottom: 25px;
    font-size: 28px;
}
.filter-bar {
    display: flex;
    align-items: center; /* Centered alignment */
    justify-content: flex-start;
    gap: 10px;
    margin-bottom: 25px;
}
.filter-bar input[type="text"] {
    background-color: #F4DD97;
    border: 2px solid #EEC752;
    color: #1F4015;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 14px;
    width: 250px;
}
/* New Style for Month Picker */
.filter-bar input[type="month"] {
    background-color: #F4DD97;
    border: 2px solid #EEC752;
    color: #1F4015;
    padding: 7px 10px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
}
#exportBtn {
    display: none;
    background-color: #AAB396;
    color: #1F4015;
    border: 1px solid #1F4015;
    padding: 9px 16px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.2s;
}
#exportBtn:hover {
    background-color: #8F9D7C;
}
table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border: 2px solid #1F4015;
    box-shadow: 2px 2px 8px rgba(31,64,21,0.1);
}
th, td {
    padding: 12px;
    border: 1px solid #EEC752;
    text-align: center;
    font-size: 15px;
    color: #1F4015;
}
th {
    background-color: #AAB396;
    font-weight: bold;
}
tr:hover {
    background-color: #F4DD97;
    cursor: pointer;
}
.selected {
    background-color: #EEC752 !important;
}
.checkbox-col {
    width: 40px;
}
.checkbox {
    display: none;
}
.selected .checkbox {
    display: inline-block;
}
.notice {
    text-align: center;
    color: #888;
    margin-top: 30px;
}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="content">
    <h1>Employee Attendance Reports</h1>

    <div class="filter-bar">
        <input type="text" id="searchInput" placeholder="Search name...">
        
        <input type="month" id="monthPicker" value="<?= date('Y-m') ?>">
        
        <button id="exportBtn">Export Selected (PDF)</button>
    </div>

    <form id="exportForm" action="export_dtr.php" method="post" target="_blank">
        
        <input type="hidden" name="month" id="formMonth">

        <table id="reportTable">
            <thead>
                <tr>
                    <th class="checkbox-col"></th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Position</th>
                    <th>Department</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr data-id="<?= htmlspecialchars($row['id']) ?>" data-name="<?= htmlspecialchars($row['name']) ?>">
                            <td><input type="checkbox" class="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($row['id']) ?>"></td>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($row['role'])) ?></td>
                            <td><?= htmlspecialchars($row['position']) ?></td>
                            <td><?= htmlspecialchars($row['department']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="notice">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>

<script>
//  Live search
document.getElementById("searchInput").addEventListener("keyup", function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("#reportTable tbody tr");
    rows.forEach(row => {
        const name = row.cells[2].textContent.toLowerCase();
        row.style.display = name.includes(filter) ? "" : "none";
    });
});

//  Row select toggle
const rows = document.querySelectorAll("#reportTable tbody tr");
rows.forEach(row => {
    row.addEventListener("click", () => {
        row.classList.toggle("selected");
        const checkbox = row.querySelector(".checkbox");
        checkbox.checked = row.classList.contains("selected");
        updateExportButton();
    });

    // Double-click → Open user DTR
    row.addEventListener("dblclick", () => {
        const id = row.dataset.id;
        const name = encodeURIComponent(row.dataset.name);
        const month = document.getElementById("monthPicker").value; // Use the picker value
        window.location.href = `user_reports_admin.php?faculty_id=${id}&name=${name}&month=${month}`;
    });
});

function updateExportButton() {
    const checked = document.querySelectorAll(".checkbox:checked").length;
    const btn = document.getElementById("exportBtn");
    btn.style.display = checked > 0 ? "inline-block" : "none";
    // Update button text to show count
    if(checked > 0) {
        btn.innerText = `Export ${checked} Selected (PDF)`;
    }
}

//  Export Selected Logic
document.getElementById("exportBtn").addEventListener("click", () => {
    // 1. Get the selected month from the picker
    const selectedMonth = document.getElementById("monthPicker").value;
    
    // 2. Put it into the hidden form input
    document.getElementById("formMonth").value = selectedMonth;
    
    // 3. Submit the form
    document.getElementById("exportForm").submit();
});

//  ESC key = deselect all
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
        rows.forEach(row => {
            row.classList.remove("selected");
            row.querySelector(".checkbox").checked = false;
        });
        updateExportButton();
    }

    // Ctrl + A = Select all
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "a") {
        e.preventDefault();
        rows.forEach(row => {
            if(row.style.display !== 'none') { // Only select visible rows
                row.classList.add("selected");
                row.querySelector(".checkbox").checked = true;
            }
        });
        updateExportButton();
    }
});
</script>
</body>
</html>