<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
require_once "config.php";

// Set Timezone
date_default_timezone_set('Asia/Manila');
$today = date("Y-m-d");

// ✅ Fetch categories
$categories = $conn->query("SELECT id, name FROM categories ORDER BY id DESC");

// ✅ CATCH DATA FROM DASHBOARD
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'All';

// ✅ LOGIC SWITCH: Determine which SQL to run based on status
if ($status_filter == 'Absent') {
    // === ABSENT MODE: Show users who have NOT scanned ===
    // FIXED LOGIC: Used LEFT JOIN to find users with no matching log for today.
    $sql = "SELECT 
                u.id AS user_id, 
                NULL AS scan_time, 
                'No Scan' AS action, 
                'Absent' AS status, 
                u.name, 
                u.role
            FROM users u
            LEFT JOIN attendance_logs a 
                ON u.id = a.user_id AND DATE(a.scan_time) = '$today'
            WHERE a.user_id IS NULL
            /* AND u.role != 'admin'  <-- REMOVED so you can see everyone who is absent */
            ORDER BY u.name ASC";
} else {
    // === ACTIVITY MODE: Show users who HAVE scanned ===
    $sql = "SELECT 
                a.user_id, 
                a.scan_time, 
                a.action, 
                a.status, 
                u.name, 
                u.role
            FROM attendance_logs a
            JOIN users u ON a.user_id = u.id
            WHERE DATE(a.scan_time) = '$today'
            ORDER BY a.scan_time DESC";
}

$logs = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance Logs</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ========== PAGE BASE STYLES ========== */
body {
    background-color: #F9F7E8;
    font-family: 'Inter', sans-serif;
    color: #1F4015;
    margin: 0; padding: 0;
}
.content { margin-left: 270px; padding: 25px; }

.datetime-display {
    font-size: 18px; font-weight: 600; margin-bottom: 25px; color: #1F4015;
}

/* ========== CONTROLS CONTAINER ========== */
.search-filter-container {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;
}
.left-controls { display: flex; align-items: center; gap: 10px; }

.search-bar {
    background-color: #F4DD97; border: 1px solid #EEC752; color: #1F4015;
    padding: 8px 12px; border-radius: 6px; width: 230px; outline: none; font-size: 14px;
}

.filter-button, .categories-button {
    background-color: #AAB396; border: 1px solid #1F4015; color: #1F4015;
    padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 14px;
    position: relative; font-weight: 600;
}
.filter-button:hover { background-color: #8F9D7C; }
.categories-button { background-color: #5D6F47; color: #fff; }

/* ========== FILTER DROPDOWN ========== */
.filter-dropdown {
    display: none; position: absolute; top: 38px; left: 0;
    background-color: #F9F7E8; border: 1px solid #1F4015; border-radius: 6px;
    width: 200px; z-index: 10;
}
.filter-dropdown button {
    display: flex; align-items: center; gap: 8px; width: 100%;
    background: none; border: none; padding: 8px 12px; text-align: left;
    cursor: pointer; color: #1F4015; font-size: 14px;
}
.filter-dropdown button:hover { background-color: #F4DD97; }

/* ========== STATUS COLORS ========== */
.status-badge {
    padding: 5px 12px; border-radius: 15px; font-weight: bold; font-size: 12px; display: inline-block; width: 100px;
}
.ontime { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
.late { background: #FFF3CD; color: #856404; border: 1px solid #FFEEBA; }
.absent { background: #F8D7DA; color: #721C24; border: 1px solid #F5C6CB; }
.overtime { background: #E2E3E5; color: #383D41; border: 1px solid #D6D8DB; }
.timeout { background: #CCE5FF; color: #004085; border: 1px solid #B8DAFF; }

/* ========== TABLE ========== */
table { width: 100%; border-collapse: collapse; background-color: white; border: 1px solid #1F4015; }
th, td { border: 1px solid #1F4015; padding: 10px; text-align: center; color: #1F4015; }
th { background-color: #AAB396; font-weight: 700; }

/* ========== MODAL ========== */
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.2); justify-content: center; align-items: center; z-index: 10; }
.modal-content { background-color: #AAB396; color: #1F4015; border: 1px solid #1F4015; border-radius: 10px; padding: 25px; width: 420px; text-align: center; }
.modal-content input { width: 90%; background-color: #F4DD97; border: 1px solid #EEC752; border-radius: 6px; padding: 8px; color: #1F4015; margin-bottom: 10px; }
.modal-btn { background-color: #EEC752; border: 1px solid #1F4015; border-radius: 6px; color: #1F4015; font-weight: 600; padding: 8px 12px; cursor: pointer; margin: 6px; }
.category-list { margin-top: 15px; text-align: left; max-height: 250px; overflow-y: auto; }
.category-item { background-color: #F4DD97; border: 1px solid #EEC752; color: #1F4015; border-radius: 6px; padding: 8px 12px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; }
.category-item button { background: #D22B2B; border: 1px solid #1F4015; color: white; border-radius: 6px; cursor: pointer; padding: 4px 8px; font-weight: 600; }
</style>
</head>

<body>
<?php include "sidebar.php"; ?>

<div class="content">
  <div class="datetime-display" id="datetimeDisplay"></div>
  
  <h2 id="pageTitle"> Attendance Logs (<?= htmlspecialchars($status_filter) ?>)</h2>

  <div class="search-filter-container">
    <div class="left-controls">
      <input type="text" class="search-bar" id="searchInput" placeholder="Search name...">
      <div class="filter-button" id="filterBtn">Filter ▼
        <div class="filter-dropdown" id="filterDropdown">
          <button data-special="All">📁 Activity Log (All Scans)</button>
          <button data-special="Absent">🔴 Absent List</button>

          <button data-status="On Time">🟢 On Time</button>
          <button data-status="Late">🟡 Late</button>
          <button data-status="Undertime">🟠 Undertime</button>
          <button data-status="Overtime">🔵 Overtime</button>
          <button data-status="Time Out">🕒 Time Out</button>
          <?php while ($cat = $categories->fetch_assoc()): ?>
            <button data-action="<?= htmlspecialchars($cat['name']) ?>">📁 <?= htmlspecialchars($cat['name']) ?></button>
          <?php endwhile; ?>
        </div>
      </div>
    </div>
    <button class="categories-button" id="manageCategoriesBtn">🗂 Categories</button>
  </div>

  <table id="attendanceTable">
    <thead>
      <tr><th>ID</th><th>Name</th><th>Role</th><th>Action</th><th>Time</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php if ($logs->num_rows > 0): ?>
          <?php while ($row = $logs->fetch_assoc()): 
              $status = $row['status'];
              $action = $row['action'];
              
              // Display Tweaks
              $roleDisplay = ($row['role'] === 'user') ? 'Faculty' : ucfirst($row['role']);
              $timeDisplay = ($row['scan_time'] !== null) ? date("h:i A", strtotime($row['scan_time'])) : "--";

              // Color Logic
              $cssClass = "overtime";
              if ($status == 'Absent') $cssClass = "absent";
              elseif ($status == 'On Time') $cssClass = "ontime";
              elseif ($status == 'Late') $cssClass = "late";
              elseif (strpos($action, 'Time Out') !== false) $cssClass = "timeout";
          ?>
          <tr data-status="<?= $status ?>" data-action="<?= $action ?>">
              <td><?= $row['user_id'] ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= $roleDisplay ?></td> <td><?= htmlspecialchars($action) ?></td>
              <td style="font-weight:bold;"><?= $timeDisplay ?></td>
              <td><span class="status-badge <?= $cssClass ?>"><?= $status ?></span></td>
          </tr>
          <?php endwhile; ?>
      <?php else: ?>
          <tr><td colspan="6" style="padding:20px; color:#777;">
            <?= $status_filter == 'Absent' ? 'Everyone is present! (No absences)' : 'No records found.' ?>
          </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="modal" id="categoryManagerModal">
  <div class="modal-content">
    <h3>Manage Categories</h3>
    <input type="text" id="newCategoryInput" placeholder="Enter new category..." />
    <div>
      <button class="modal-btn" type="button" id="addCategoryBtn">Add Category</button>
      <button class="modal-btn" style="background:#F9F7E8;" type="button" id="closeCategoryModal">Close</button>
    </div>
    <hr style="border:1px solid #1F4015; margin:15px 0;">
    <div class="category-list" id="categoryList"></div>
  </div>
</div>

<script>
const swalTheme={confirmButtonColor:"#1F4015",cancelButtonColor:"#AAB396"};
const dropdown=document.getElementById("filterDropdown");
const rows=document.querySelectorAll("#attendanceTable tbody tr");

// 🕒 Live Clock
function updateDateTime(){
  const now=new Date();
  const opt={weekday:'long',year:'numeric',month:'long',day:'numeric'};
  document.getElementById('datetimeDisplay').textContent=
  now.toLocaleDateString('en-US',opt)+" | "+
  now.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',second:'2-digit',hour12:true});
}
setInterval(updateDateTime,1000);updateDateTime();

//  Filter Dropdown Toggle
document.getElementById("filterBtn").addEventListener("click",()=>dropdown.style.display=dropdown.style.display==="block"?"none":"block");

//  HANDLE DASHBOARD FILTERS (URL Params)
// This code automatically filters the rows if you came from the Dashboard
const urlParams = new URLSearchParams(window.location.search);
const currentStatus = urlParams.get('status');

//  FIXED: Only filter via JS if it's NOT 'Absent' (Absent is handled by SQL)
if (currentStatus && currentStatus !== 'Absent' && currentStatus !== 'All') {
    applyJsFilter(currentStatus);
}

//  BUTTON CLICKS
document.querySelectorAll(".filter-dropdown button").forEach(btn=>{
  btn.addEventListener("click",()=>{
    
    // 1. RELOAD page for SQL switches (Absent vs All)
    if (btn.dataset.special) {
        window.location.href = "?status=" + btn.dataset.special;
        return;
    }

    // 2. JS FILTER for subsets (Late, On Time, etc.)
    const filter = (btn.dataset.status||btn.dataset.action||"All");
    applyJsFilter(filter);
    dropdown.style.display="none";
  });
});

function applyJsFilter(filter) {
    filter = filter.toLowerCase().trim();
    
    rows.forEach(row=>{
      const status=(row.dataset.status||"").toLowerCase();
      const action=(row.dataset.action||"").toLowerCase();
      
      if(filter==="all" || status.includes(filter) || action.includes(filter)) {
          row.style.display = "";
      } else {
          row.style.display = "none";
      }
    });

    // Update Title
    const titleStatus = filter.charAt(0).toUpperCase() + filter.slice(1);
    document.getElementById("pageTitle").textContent=`📝 Attendance Logs (${titleStatus})`;
}

// 🔍 Search
document.getElementById("searchInput").addEventListener("keyup",function(){
  const f=this.value.toLowerCase();
  rows.forEach(r=>r.style.display=r.cells[1].textContent.toLowerCase().includes(f)?"":"none");
});

// 🗂 Category Modal Logic (Same as before)
const modal=document.getElementById("categoryManagerModal");
document.getElementById("manageCategoriesBtn").onclick=()=>{modal.style.display="flex";loadCategories();};
document.getElementById("closeCategoryModal").onclick=()=>modal.style.display="none";
window.onclick=e=>{if(e.target==modal)modal.style.display="none";};

function loadCategories(){
  fetch("get_categories.php").then(r=>r.json()).then(data=>{
    const list=document.getElementById("categoryList");
    list.innerHTML="";
    if(data.length===0){list.innerHTML="<p style='text-align:center;'>No categories yet.</p>";return;}
    data.forEach(c=>{
      const div=document.createElement("div");
      div.className="category-item";
      div.innerHTML=`<span>${c.name}</span><button onclick="deleteCategory(${c.id},'${c.name}')">Delete</button>`;
      list.appendChild(div);
    });
  });
}

document.getElementById("addCategoryBtn").onclick=()=>{
  const name=document.getElementById("newCategoryInput").value.trim();
  if(!name){Swal.fire({icon:"warning",title:"Missing Input",text:"Enter category name.",...swalTheme});return;}
  fetch("add_category.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({name})})
  .then(r=>r.json()).then(d=>{
    if(d.status==="success"){
      Swal.fire({icon:"success",title:"Added!",text:d.message,...swalTheme});
      loadCategories();
      document.getElementById("newCategoryInput").value="";
      setTimeout(() => location.reload(), 1500);
    }else{
      Swal.fire({icon:"error",title:"Error",text:d.message,...swalTheme});
    }
  });
};

function deleteCategory(id,name){
  Swal.fire({
    title:"Delete Category?",
    text:`Delete '${name}'?`,
    icon:"warning",
    showCancelButton:true,
    confirmButtonText:"Yes, Delete",
    cancelButtonText:"Cancel",
    ...swalTheme
  }).then(res=>{
    if(res.isConfirmed){
      fetch("delete_category.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({id})})
      .then(r=>r.json()).then(d=>{
        if(d.status==="success"){
          Swal.fire({icon:"success",title:"Deleted!",text:d.message,...swalTheme});
          loadCategories();
          setTimeout(() => location.reload(), 1500);
        }else{
          Swal.fire({icon:"error",title:"Error",text:d.message,...swalTheme});
        }
      });
    }
  });
}
</script>
</body>
</html>