<?php
session_start();
require_once "config.php";

// Only logged-in users
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "user") {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["name"];

// ✅ 1. FETCH REAL SCANS (For Current Month)
$month_start = date("Y-m-01");
$month_end   = date("Y-m-t"); 
$today       = date("Y-m-d");

$sql = "SELECT * FROM attendance_logs 
        WHERE user_id = $user_id 
        AND DATE(scan_time) BETWEEN '$month_start' AND '$month_end' 
        ORDER BY scan_time DESC";
$result = $conn->query($sql);

$logs_by_date = [];
while ($row = $result->fetch_assoc()) {
    $dateKey = date("Y-m-d", strtotime($row['scan_time']));
    $logs_by_date[$dateKey][] = $row;
}

// ✅ 2. GENERATE DISPLAY LIST
$final_rows = [];
$current = strtotime($month_start);
$end     = strtotime($today);

while ($current <= $end) {
    $dateKey = date("Y-m-d", $current);
    $isWeekend = (date('N', $current) >= 6); 

    if (isset($logs_by_date[$dateKey])) {
        // A. SCANS FOUND: Use Database Status
        foreach ($logs_by_date[$dateKey] as $log) {
            $status = $log['status']; 
            
            // Map status to CSS class
            $class = "default";
            if (strpos($status, 'On Time') !== false) $class = "ontime";
            elseif (strpos($status, 'Late') !== false) $class = "late";
            elseif (strpos($status, 'Undertime') !== false) $class = "undertime";
            elseif (strpos($status, 'Overtime') !== false) $class = "overtime";
            elseif (strpos($status, 'Time Out') !== false) $class = "timeout";
            elseif (strpos($status, 'Auto-Fill') !== false) $class = "autofill";

            $final_rows[] = [
                'date_obj' => $current,
                'date' => date("F d, Y", strtotime($log['scan_time'])),
                'time' => date("h:i A", strtotime($log['scan_time'])),
                'status' => $status,
                'class' => $class,
                'action' => $log['action']
            ];
        }
    } else {
        // B. NO SCANS: Mark Absent
        if (!$isWeekend) {
            $final_rows[] = [
                'date_obj' => $current,
                'date' => date("F d, Y", $current),
                'time' => '-- : --',
                'status' => 'Absent',
                'class' => 'absent',
                'action' => 'None'
            ];
        }
    }
    $current = strtotime("+1 day", $current);
}

// Sort: Newest First
usort($final_rows, function($a, $b) {
    return $b['date_obj'] - $a['date_obj'];
});

// Fetch categories
$categories = $conn->query("SELECT id, name FROM categories ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Attendance Logs</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* CSS Styles */
body { background-color: #F9F7E8; font-family: 'Inter', sans-serif; color: #1F4015; margin: 0; padding: 0; }
.content { margin-left: 270px; padding: 25px; }
h1 { color: #1F4015; margin-bottom: 10px; }
.filter-info { font-size: 16px; font-weight: bold; color: #5D6F47; margin-bottom: 15px; }

/* Filter Container */
.search-filter-container { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.left-controls { display: flex; align-items: center; gap: 10px; }
.search-bar { padding: 8px 12px; border: 1px solid #1F4015; border-radius: 6px; background-color: #F4DD97; color: #1F4015; outline: none; }
.filter-button, .categories-button { background-color: #AAB396; border: 1px solid #1F4015; color: #1F4015; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 14px; position: relative; font-weight: 600; user-select: none; }
.filter-button:hover, .categories-button:hover { background-color: #8F9D7C; }
.categories-button { background-color: #5D6F47; color: #fff; }

/* Dropdown */
.filter-dropdown { display: none; position: absolute; top: 40px; left: 0; background-color: #F9F7E8; border: 1px solid #1F4015; border-radius: 6px; width: 230px; z-index: 100; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.filter-dropdown button { display: block; width: 100%; text-align: left; padding: 10px; background: none; border: none; border-bottom: 1px solid #eee; cursor: pointer; color: #1F4015; font-size: 14px; }
.filter-dropdown button:hover { background-color: #F4DD97; }
.filter-dropdown button:last-child { border-bottom: none; }

/* Table */
table { width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #1F4015; margin-top: 10px; }
th, td { border: 1px solid #1F4015; padding: 10px; text-align: center; }
th { background-color: #AAB396; }

/* Status Indicators */
.status-circle { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
.ontime { background-color: #4CAF50; }    /* Green */
.late { background-color: #FFC107; }      /* Yellow */
.undertime { background-color: #FF9800; } /* Orange */
.overtime { background-color: #9C27B0; }  /* Purple */
.timeout { background-color: #9E9E9E; }   /* Gray */
.absent { background-color: #F44336; }    /* Red */
.autofill { background-color: #9C27B0; }  /* Purple (Auto-filled by system) */

/* Modal Styles */
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.2); justify-content: center; align-items: center; z-index: 10; }
.modal-content { background-color: #AAB396; color: #1F4015; border: 1px solid #1F4015; border-radius: 10px; padding: 25px; width: 420px; text-align: center; }
.modal-content input { width: 90%; background-color: #F4DD97; border: 1px solid #EEC752; border-radius: 6px; padding: 8px; margin-bottom: 10px; }
.category-list { margin-top: 15px; text-align: left; max-height: 250px; overflow-y: auto; }
.category-item { background-color: #F4DD97; border: 1px solid #EEC752; padding: 8px 12px; margin-bottom: 6px; display: flex; justify-content: space-between; }
</style>
</head>
<body>
<?php include "user_sidebar.php"; ?>

<div class="content">
    <h1> My Attendance Logs</h1>
    <p>Viewing records for: <b><?= date("F Y") ?></b></p>

    <div class="search-filter-container">
        <div class="left-controls">
            <input type="text" class="search-bar" id="searchInput" placeholder="Search date or status...">
            
            <div class="filter-button" id="filterBtn">Filter ▼
                <div class="filter-dropdown" id="filterDropdown">
                    <button data-special="All">📁 Activity Log (All Scans)</button>
                    <button data-special="Absent">🔴 Absent List</button>
                    <hr style="border: 0; border-top: 1px solid #ccc; margin: 0;">
                    
                    <button data-status="On Time"><span class="status-circle ontime"></span> On Time</button>
                    <button data-status="Late"><span class="status-circle late"></span> Late</button>
                    <button data-status="Undertime"><span class="status-circle undertime"></span> Undertime</button>
                    <button data-status="Overtime"><span class="status-circle overtime"></span> Overtime</button>
                    <button data-status="Time Out">🕒 Time Out</button>
                    
                    <hr style="border: 0; border-top: 1px solid #ccc; margin: 0;">
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <button data-action="<?= htmlspecialchars($cat['name']) ?>">📁 <?= htmlspecialchars($cat['name']) ?></button>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <button class="categories-button" id="manageCategoriesBtn"> Categories</button>
    </div>

    <p class="filter-info" id="filterLabel">Showing: All Logs</p>

    <table id="attendanceTable">
        <thead>
            <tr><th>Date</th><th>Time</th><th>Action</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php
        if (count($final_rows) > 0) {
            foreach ($final_rows as $r) {
                echo "
                <tr data-status='{$r['status']}'>
                    <td>{$r['date']}</td>
                    <td>{$r['time']}</td>
                    <td>{$r['action']}</td>
                    <td><span class='status-circle {$r['class']}'></span> {$r['status']}</td>
                </tr>";
            }
        } else {
            echo "<tr class='no-records'><td colspan='4'>No attendance records found.</td></tr>";
        }
        ?>
        </tbody>
    </table>
</div>

<div class="modal" id="categoryManagerModal">
  <div class="modal-content">
    <h3>Manage Categories</h3>
    <input type="text" id="newCategoryInput" placeholder="Enter new category..." />
    <div>
      <button class="filter-button" id="addCategoryBtn" style="background:#EEC752">Add</button>
      <button class="filter-button" id="closeCategoryModal">Close</button>
    </div>
    <div class="category-list" id="categoryList"></div>
  </div>
</div>

<script>
// ✅ JS Logic for Search and Filter
const dropdown = document.getElementById("filterDropdown");
const rows = document.querySelectorAll("#attendanceTable tbody tr");
const filterLabel = document.getElementById("filterLabel");
const searchInput = document.getElementById("searchInput");

// Toggle Dropdown
document.getElementById("filterBtn").addEventListener("click", (e) => {
    e.stopPropagation();
    dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
});

// Close dropdown on outside click
document.addEventListener("click", () => {
    dropdown.style.display = "none";
});

// SEARCH Logic
searchInput.addEventListener("keyup", function() {
    const value = this.value.toLowerCase();
    rows.forEach(row => {
        if (row.classList.contains('no-records')) return;
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(value) ? "" : "none";
    });
});

// FILTER Logic
function applyFilter(filterType, filterValue) {
    let count = 0;
    
    rows.forEach(row => {
        if (row.classList.contains('no-records')) return;
        const status = (row.dataset.status || "").toLowerCase();
        
        let match = false;
        
        if (filterType === "special") {
            if (filterValue === "All") match = true;
            if (filterValue === "Absent") match = (status === "absent");
        } else if (filterType === "status") {
            // Partial match (e.g., "Time Out" matches "Time Out (AM)")
            match = status.includes(filterValue.toLowerCase());
        } else if (filterType === "action") {
            // Match custom categories if stored in action or status
            const rowText = row.innerText.toLowerCase();
            match = rowText.includes(filterValue.toLowerCase());
        }

        if (match) {
            row.style.display = "";
            count++;
        } else {
            row.style.display = "none";
        }
    });

    filterLabel.textContent = `Showing: ${filterValue} (${count})`;
}

// Button Click Listeners
document.querySelectorAll(".filter-dropdown button").forEach(btn => {
    btn.addEventListener("click", () => {
        const special = btn.dataset.special;
        const status = btn.dataset.status;
        const action = btn.dataset.action;

        if (special) applyFilter("special", special);
        else if (status) applyFilter("status", status);
        else if (action) applyFilter("action", action);
    });
});

// ✅ Check URL for status filter (from Dashboard click)
const urlParams = new URLSearchParams(window.location.search);
const urlStatus = urlParams.get('status');
if (urlStatus) {
    setTimeout(() => applyFilter("status", urlStatus), 100);
}

// ... (Existing Category Modal JS) ...
const modal = document.getElementById("categoryManagerModal");
document.getElementById("manageCategoriesBtn").onclick = () => { modal.style.display = "flex"; loadCategories(); };
document.getElementById("closeCategoryModal").onclick = () => modal.style.display = "none";

function loadCategories() {
  fetch("user_get_categories.php")
    .then(r => r.json())
    .then(data => {
      const list = document.getElementById("categoryList");
      list.innerHTML = "";
      data.forEach(c => {
        list.innerHTML += `<div class="category-item"><span>${c.name}</span><button onclick="deleteCategory(${c.id})">Del</button></div>`;
      });
    });
}

document.getElementById("addCategoryBtn").onclick = () => {
  const name = document.getElementById("newCategoryInput").value.trim();
  if (!name) return;
  fetch("user_add_category.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ name })
  }).then(r => r.json()).then(d => {
      if (d.status === "success") { loadCategories(); document.getElementById("newCategoryInput").value = ""; }
  });
};

function deleteCategory(id) {
  if(confirm("Delete category?")) {
      fetch("user_delete_category.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ id })
      }).then(r => r.json()).then(d => { if(d.status==="success") loadCategories(); });
  }
}
</script>
</body>
</html>