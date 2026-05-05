<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Fetch users (🔴 THIS IS THE CRITICAL FIX)
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$faculty_sql = "SELECT id, name, email, position, department, role, status 
                FROM users 
                WHERE status != 'Archived' AND (
                   name LIKE '%$search%' 
                   OR email LIKE '%$search%' 
                   OR department LIKE '%$search%'
                   OR position LIKE '%$search%'
                   OR role LIKE '%$search%'
                )
                ORDER BY id DESC 
                LIMIT $start, $limit";
$faculty_result = $conn->query($faculty_sql);

// Count total rows (🔴 IGNORE ARCHIVED USERS HERE TOO)
$total_result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE status != 'Archived'");
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Count total rows (IGNORE ARCHIVED USERS)
$total_result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE status != 'Archived'");
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Count total rows
$total_result = $conn->query("SELECT COUNT(*) AS total FROM users");
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta charset="UTF-8">
<title>Faculty Management</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body {
    background-color: #F9F7E8;
    font-family: Arial, sans-serif;
    color: #1F4015;
    margin: 0;
    padding: 0;
}
.content { margin-left: 270px; padding: 25px; }
h1 { margin-bottom: 15px; }

.top-controls {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px; margin-bottom: 20px;
}
.search-bar {
    background-color: #F4DD97; border: 1px solid #EEC752;
    color: #1F4015; padding: 8px 12px; border-radius: 6px;
    width: 230px; outline: none; font-size: 14px;
}
.action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
.action-btn {
    background-color: #AAB396; border: 1px solid #1F4015; color: #1F4015;
    border-radius: 6px; padding: 8px 14px; cursor: pointer;
    font-weight: 600; transition: 0.2s;
}
.action-btn:hover { background-color: #8F9D7C; }

table { width: 100%; border-collapse: collapse; background-color: white; }
th, td {
    border: 1px solid #1F4015; text-align: center;
    padding: 10px; color: #1F4015;
}
th { background-color: #AAB396; }
.view-btn, .delete-btn {
    background-color: #F4DD97; border: 1px solid #1F4015;
    border-radius: 6px; color: #1F4015; padding: 5px 10px;
    cursor: pointer; font-weight: 600; margin: 0 2px;
}
.view-btn:hover, .delete-btn:hover { background-color: #D6B540; }

/* Modal */
.modal {
    display: none; position: fixed; top: 0; left: 0;
    width: 100%; height: 100%; background: #f9f7e836;
    justify-content: center; align-items: center; z-index: 10;
}
.modal-content {
    background-color: #AAB396; color: #1F4015;
    border: 1px solid #1F4015; border-radius: 10px;
    padding: 25px; width: 450px; position: relative;
    text-align: center; max-height: 90vh; overflow-y: auto;
}

/* INPUT STYLING */
.modal input:not([type="checkbox"]), .modal select {
    width: 90%; 
    background-color: #F4DD97; 
    border: 1px solid #EEC752;
    border-radius: 6px; 
    padding: 10px;
    color: #1F4015; 
    margin-bottom: 10px;
    font-weight: bold;
}

/* DISABLED INPUTS (Solid, Not Faded) */
.modal input:not([type="checkbox"]):disabled, .modal select:disabled {
    background-color: #d1cba8; 
    color: #2c3e1e; 
    border: 1px solid #8c8c8c; 
    cursor: not-allowed; 
    opacity: 1; 
}

.modal label { display: block; text-align: left; margin-left: 20px; font-weight: 600; }
.modal-btn {
    background-color: #EEC752; border: 1px solid #1F4015;
    border-radius: 6px; color: #1F4015; font-weight: 600;
    padding: 8px 12px; cursor: pointer; margin: 6px;
}
.modal-btn:hover { background-color: #D6B540; }
.cancel-btn {
    background-color: #F9F7E8; border: 2px solid #1F4015;
    color: #1F4015; border-radius: 6px; font-weight: 600;
    padding: 8px 12px; cursor: pointer; margin-top: 8px;
}
.cancel-btn:hover { background-color: #F4DD97; }

.csv-link, .csv-link:visited, .csv-link:hover, .csv-link:active {
  color: #1F4015; text-decoration: none; font-weight: bold; cursor: pointer;
}
.csv-link:hover { text-decoration: underline; color: #D6B540; }

/* Edit icon */
.edit-icon {
    position: absolute; top: 10px; right: 15px;
    cursor: pointer; font-weight: bold; font-size: 18px;
    border: 2px solid #1F4015; border-radius: 100%;
    padding: 5px; color: #1F4015; background-color: #F4DD97;
    transition: 0.3s;
}
.edit-icon:hover { background-color: #D6B540; }

.category-section { margin-top: 15px; text-align: left; }
.category-item {
    background-color: #F4DD97; border: 1px solid #EEC752;
    color: #1F4015; border-radius: 6px; padding: 6px 10px;
    margin: 4px 0; display: flex; justify-content: space-between; align-items: center;
}
.category-item button {
    background: #d33; border: 1px solid #1F4015;
    color: white; border-radius: 6px; cursor: pointer;
    padding: 4px 8px; font-weight: 600;
}
.category-item button:hover { background-color: #d33; }

.input-group { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
.input-group input { flex: 1; border-radius: 6px; padding: 6px 10px; }
.add-btn {
  background-color: #1F4015; color: white; border: none; border-radius: 6px;
  font-weight: bold; padding: 6px 10px; cursor: pointer;
}
.add-btn:hover { background-color: #366a2a; }

.category-list {
  max-height: 150px; overflow-y: auto; padding: 5px;
  border: 1px solid #EEC752; border-radius: 6px; background: #fffbe8;
}

.pagination { text-align: center; margin-top: 15px; }
.pagination a {
    background-color: #AAB396; color: #1F4015; padding: 6px 12px;
    text-decoration: none; border-radius: 5px; border: 1px solid #1F4015; margin: 0 2px;
}
.pagination a:hover { background-color: #8F9D7C; }
.pagination .active { background-color: #D6B540; font-weight: bold; }

/* ✅ NEW CSS: TOGGLE SWITCH */
.switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 26px;
}
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
  position: absolute; cursor: pointer;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: #ccc;
  transition: .4s;
  border-radius: 34px;
  border: 1px solid #1F4015;
}
.slider:before {
  position: absolute; content: "";
  height: 18px; width: 18px;
  left: 3px; bottom: 3px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}
input:checked + .slider { background-color: #1F4015; }
input:checked + .slider:before { transform: translateX(24px); }

/* 🟢 TOGGLE FADE WHEN DISABLED */
input:disabled + .slider {
    background-color: #ccc;
    opacity: 0.5; /* Fades out when disabled to show it's locked */
    cursor: not-allowed;
}


.status-badge-active { color: green; font-weight: bold; }
.status-badge-inactive { color: red; font-weight: bold; }
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="content">
    <h1>Faculty Management</h1>

    <div class="top-controls">
        <input type="text" class="search-bar" id="searchInput" placeholder="Search user..." value="<?= htmlspecialchars($search) ?>">
        <div class="action-buttons">
            <button class="action-btn" onclick="openAddUser()">Add User</button>
            <button class="action-btn" onclick="openCategories()">Manage Categories</button>
            <button class="action-btn" onclick="openImport()">Import CSV</button>
            <button class="action-btn" onclick="window.location.href='archive.php'">Archive</button>
        </div>
    </div>

    <table id="userTable">
        <thead>
            <tr>
                <th>User ID</th><th>Name</th><th>Role</th>
                <th>Position</th><th>Department</th><th>Email</th>
                <th>Status</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($faculty_result->num_rows > 0): ?>
            <?php while ($row = $faculty_result->fetch_assoc()): ?>
                <tr id="row-<?= $row['id'] ?>">
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= (strtolower($row['role']) === 'admin') ? 'Admin' : 'Faculty'; ?></td>
                    <td><?= htmlspecialchars($row['position']) ?></td>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    
                    <td>
                        <?php 
                        $status = isset($row['status']) ? $row['status'] : 'Active'; 
                        if ($status == 'Active') {
                            echo '<span class="status-badge-active">Active</span>';
                        } else {
                            echo '<span class="status-badge-inactive">Inactive</span>';
                        }
                        ?>
                    </td>

                    <td>
                        <button class="view-btn" onclick="viewUser(<?= $row['id'] ?>)">View</button>
                        <button class="delete-btn" onclick="deleteUser(<?= $row['id'] ?>)">Delete</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8">No records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>


<div class="modal" id="addUserModal">
    <div class="modal-content">
        <h3>Add New User</h3>
      <form id="addUserForm" enctype="multipart/form-data">
  <label>Name:</label>
  <input type="text" name="name" id="userName" required>

  <label>Email:</label>
  <input type="email" name="email" id="userEmail" required>

  <div class="form-group mb-3">
    <label><b>Take Face Photo (Required)</b></label>
    <div style="text-align:center; margin-bottom:10px;">
      <img id="preview" src="images/default_face.jpg"
        style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:2px solid #ccc;">
    </div>

    <button type="button" onclick="openCamera()">📷 Take Picture</button>

    <div id="camera" style="display:none; text-align:center; margin-top:10px;">
      <video id="video" width="240" height="180" autoplay style="transform: scaleX(1);"></video><br>
      <button type="button" onclick="capturePhoto()">Capture</button>
      <canvas id="canvas" width="240" height="180" style="display:none;"></canvas>
    </div>

    <input type="hidden" name="captured_image" id="captured_image">
    <input type="hidden" name="face_descriptor" id="face_descriptor">
  </div>

  <input type="hidden" name="password" value="@Spsfaculty123">

  <label>Role:</label>
  <select name="role" id="userRole" required>
      <option value="Faculty">Faculty</option>
      <option value="Admin">Admin</option>
  </select>

  <label>Position:</label>
  <select name="position" id="userPosition" required></select>

  <label>Department:</label>
  <select name="department" id="userDepartment" required></select>

  <button class="modal-btn" type="submit">Add User</button>
  <button class="cancel-btn" type="button" onclick="closeModal()">Cancel</button>
</form>
    </div>
</div>

<div class="modal" id="viewUserModal">
  <div class="modal-content">
    <span class="edit-icon" onclick="enableEdit()">✎</span>
    <h3>User Details</h3>
    
    <form id="viewUserForm">
       <div style="text-align:center; margin-bottom:10px;">
          <img id="editPreview" src="images/default_face.jpg"
               style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid #1F4015;">
        </div>

        <div id="editPhotoButtons" style="display:none; text-align:center; margin-top:8px;">
          <button type="button" onclick="openEditCamera()">📷 Take New Photo</button>
        </div>

        <div id="editCameraContainer" style="display:none; text-align:center;">
          <video id="editVideo" width="240" height="180" autoplay style="transform: scaleX(1);"></video><br>
          <button type="button" onclick="captureEditPhoto()">Capture</button>
          <canvas id="editCanvas" width="240" height="180" style="display:none;"></canvas>
        </div>
        <input type="hidden" name="captured_edit_image" id="captured_edit_image">
        <input type="hidden" name="edit_face_descriptor" id="edit_face_descriptor">

      <input type="hidden" id="editUserId">
      <label>Name:</label><input type="text" id="editName" disabled>
      <label>Email:</label><input type="email" id="editEmail" disabled>

      <label>Role:</label>
        <select id="editRole" disabled>
            <option value="Faculty">Faculty</option>
            <option value="Admin">Admin</option>
        </select>

        <label>Position:</label>
        <select id="editPosition" disabled></select>

        <label>Department:</label>
        <select id="editDepartment" disabled></select>

        <div style="display: flex; justify-content: center; align-items: center; gap: 15px; margin: 20px 0;">
            <label style="margin:0; font-weight:bold;">Status:</label>
            <label class="switch">
                <input type="checkbox" id="editStatusToggle" disabled>
                <span class="slider"></span>
            </label>
            <span id="statusLabel" style="font-weight:bold; min-width:60px; text-align:left;">Active</span>
        </div>

      <button class="modal-btn" type="button" id="saveBtn" style="display:none" onclick="saveUser()">Save</button>
      <button class="cancel-btn" type="button" onclick="closeView()">Close</button>
    </form>
  </div>
</div>

<div class="modal" id="importModal">
    <div class="modal-content">
        <h3>Import CSV File</h3>
        <form id="importForm" enctype="multipart/form-data">
            <input type="file" name="csv" id="csvFile" accept=".csv" required>
            <button class="modal-btn" type="button" onclick="importCSV()">Import</button>
            <button class="cancel-btn" type="button" onclick="closeImport()">Cancel</button>
        </form>
        <a href="#" class="csv-link" onclick="toggleCSVExample(event)">CSV Format Example:</a>
        <div id="csvExample" style="display: none; margin-top: 10px; color: #1F4015;">
          <p>name,role,position,department,email</p>
        </div>
    </div>
</div>

<div class="modal" id="categoryModal">
  <div class="modal-content">
    <h3>Manage Categories</h3>

    <div class="category-section" id="positionsSection">
      <h4>Positions</h4>
      <div class="input-group">
        <input type="text" id="newPosition" placeholder="Add new position...">
        <button class="add-btn" onclick="addCategory('position')">Add</button>
      </div>
      <div id="positionsList" class="category-list"></div>
    </div>

    <div class="category-section" id="departmentsSection">
      <h4>Departments</h4>
      <div class="input-group">
        <input type="text" id="newDepartment" placeholder="Add new department...">
        <button class="add-btn" onclick="addCategory('department')">Add</button>
      </div>
      <div id="departmentsList" class="category-list"></div>
    </div>

    <button class="cancel-btn" onclick="closeCategories()">Close</button>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
    // --- GLOBAL VARIABLES ---
    let cameraStream = null;
    let editCameraStream = null;
    let modelsAreReady = false;
    const MODEL_URL = './models';
    const swalTheme = { confirmButtonColor: "#1F4015", cancelButtonColor: "#AAB396" };

    // --- INITIALIZATION ---
    console.log("🔄 Loading AI...");

    // Safety Check
    if (typeof faceapi === 'undefined') {
        alert("CRITICAL ERROR: Face-API library not loaded. Check internet.");
    } else {
        // Load Models
        Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
        ]).then(() => {
            console.log("✅ Models Loaded");
            modelsAreReady = true;
        }).catch(err => {
            console.error(err);
            alert("❌ Model Error: " + err);
        });
    }

    // ==========================================
    //  PART 1: CAMERA & FACE API LOGIC
    // ==========================================

    function openCamera() {
        document.getElementById('camera').style.display = 'block';
        const video = document.getElementById('video');
        navigator.mediaDevices.getUserMedia({ video: {} })
            .then(stream => {
                video.srcObject = stream;
                cameraStream = stream;
            })
            .catch(err => alert("Camera Error: " + err));
    }

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        document.getElementById('camera').style.display = 'none';
    }

    async function capturePhoto() {
        if (!modelsAreReady) {
            Swal.fire({icon: 'warning', title: 'Loading', text: 'AI models are still loading. Please wait...'});
            return;
        }

        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const context = canvas.getContext('2d');

        // Draw to canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataURL = canvas.toDataURL('image/jpeg');

        document.getElementById('preview').src = dataURL;
        document.getElementById('captured_image').value = dataURL;

        // Detect Face
        try {
            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                Swal.fire({icon: 'error', title: 'No Face Found', text: 'Please ensure your face is clearly visible.'});
                return;
            }

            // Save Math
            const descriptorArray = Array.from(detection.descriptor);
            document.getElementById("face_descriptor").value = JSON.stringify(descriptorArray);
            
            // ✅ SWEET ALERT 2 SUCCESS
            Swal.fire({
                icon: 'success',
                title: 'Face Captured!',
                text: 'Face data calculated successfully.',
                timer: 1500,
                showConfirmButton: false
            });

        } catch (err) {
            Swal.fire({icon: 'error', title: 'Error', text: err});
        }
    }

    // --- EDIT CAMERA LOGIC ---
    function openEditCamera() {
        document.getElementById('editCameraContainer').style.display = 'block';
        const video = document.getElementById('editVideo');
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                editCameraStream = stream;
                video.srcObject = stream;
            })
            .catch(err => alert("Camera Error: " + err));
    }

    async function captureEditPhoto() {
        const video = document.getElementById('editVideo');
        const canvas = document.getElementById('editCanvas');
        const context = canvas.getContext('2d');

        // Draw video to canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataURL = canvas.toDataURL('image/jpeg');

        document.getElementById('editPreview').src = dataURL;
        document.getElementById('captured_edit_image').value = dataURL;

        // 🟢 Calculate Face Descriptor for the Update
        try {
            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (detection) {
                const descriptorArray = Array.from(detection.descriptor);
                document.getElementById("edit_face_descriptor").value = JSON.stringify(descriptorArray);
                
                Swal.fire({
                    icon: 'success',
                    title: 'New Face Captured!',
                    text: 'Face data updated successfully.',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({ icon: 'warning', title: 'No Face', text: 'Photo taken, but no face detected for recognition.' });
            }
        } catch (err) {
            console.error(err);
        }
        
        stopEditCamera();
    }

    function stopEditCamera() {
        if (editCameraStream) {
            editCameraStream.getTracks().forEach(track => track.stop());
            editCameraStream = null;
        }
        document.getElementById('editCameraContainer').style.display = 'none';
    }


    // ==========================================
    //  PART 2: MODAL & MANAGEMENT LOGIC
    // ==========================================

    function openAddUser() { document.getElementById('addUserModal').style.display = 'flex'; loadDropdownsForAddUser(); }
    function closeModal() { document.getElementById('addUserModal').style.display = 'none'; stopCamera(); }
    function openImport() { document.getElementById('importModal').style.display = 'flex'; }
    function closeImport() { document.getElementById('importModal').style.display = 'none'; }
    function closeView() { document.getElementById('viewUserModal').style.display = 'none'; stopEditCamera(); }
    function openCategories() { document.getElementById('categoryModal').style.display = 'flex'; loadCategories(); }
    function closeCategories() { document.getElementById('categoryModal').style.display = 'none'; }

    // Close modals on outside click
    window.onclick = function(e) {
      if (e.target == document.getElementById("viewUserModal")) closeView();
      if (e.target == document.getElementById("addUserModal")) closeModal();
      if (e.target == document.getElementById("importModal")) closeImport();
      if (e.target == document.getElementById("categoryModal")) closeCategories();
    };

    // --- FORM SUBMIT (ADD USER) ---
    document.getElementById("addUserForm").addEventListener("submit", function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        // ✅ AUTO TRANSLATE: Dropdown 'Faculty' -> Database 'user'
        let role = document.getElementById("userRole").value;
        if(role === 'Faculty') {
            formData.set('role', 'user');
        } else if (role === 'Admin') {
            formData.set('role', 'admin');
        }

        fetch("save_user.php", { method: "POST", body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === "success") {
                    Swal.fire({icon: "success", title: "Success", text: "User Added!", ...swalTheme})
                    .then(() => location.reload());
                } else {
                    Swal.fire({icon: "error", title: "Error", text: data.message, ...swalTheme});
                }
            });
    });

    // --- TABLE ACTIONS (VIEW/DELETE) ---
    function viewUser(id) {
        fetch("get_user.php?id=" + id)
            .then(r => r.json())
            .then(u => {
                document.getElementById("editUserId").value = u.id;
                document.getElementById("editName").value = u.name;
                document.getElementById("editEmail").value = u.email;
                
                let roleForDropdown = (u.role === 'admin') ? 'Admin' : 'Faculty';
                document.getElementById("editRole").value = roleForDropdown;

                loadDropdownsForEditUser(u.position, u.department);

                document.getElementById("editPreview").src = u.face_path ? u.face_path : "images/default_face.jpg";
                
                // ✅ NEW: Set Toggle State based on DB status
                const toggle = document.getElementById("editStatusToggle");
                const label = document.getElementById("statusLabel");
                
                // If DB says "Inactive", toggle off. Otherwise default Active.
                let currentStatus = u.status || 'Active';
                if(currentStatus === 'Active'){
                    toggle.checked = true;
                    label.innerText = "Active";
                    label.style.color = "green";
                } else {
                    toggle.checked = false;
                    label.innerText = "Inactive";
                    label.style.color = "red";
                }

                // Add Listener for Toggle Change
                toggle.onchange = function() {
                    if(this.checked) {
                        label.innerText = "Active";
                        label.style.color = "green";
                    } else {
                        label.innerText = "Inactive";
                        label.style.color = "red";
                    }
                };

                document.getElementById("viewUserModal").style.display = "flex";
            });
    }

    function enableEdit() {
        document.querySelectorAll('#viewUserForm input, #viewUserForm select').forEach(el => el.disabled = false);
        // Also enable the status toggle
        document.getElementById('editStatusToggle').disabled = false;
        
        document.getElementById('saveBtn').style.display = 'inline-block';
        document.getElementById('editPhotoButtons').style.display = 'block';
    }

    function saveUser() {
        const formData = new FormData();
        formData.append("id", document.getElementById('editUserId').value);
        formData.append("name", document.getElementById('editName').value);
        formData.append("email", document.getElementById('editEmail').value);
        
        // Role Logic
        let roleVal = document.getElementById('editRole').value;
        if(roleVal === 'Faculty') roleVal = 'user';
        if(roleVal === 'Admin') roleVal = 'admin';
        formData.append("role", roleVal);

        formData.append("position", document.getElementById('editPosition').value);
        formData.append("department", document.getElementById('editDepartment').value);
        formData.append("captured_edit_image", document.getElementById('captured_edit_image').value);
        
        // Face Data
        const descriptorValue = document.getElementById('edit_face_descriptor') ? document.getElementById('edit_face_descriptor').value : "";
        formData.append("edit_face_descriptor", descriptorValue);

        // Status Logic
        const statusVal = document.getElementById('editStatusToggle').checked ? 'Active' : 'Inactive';
        formData.append("status", statusVal);

        fetch("update_user.php", { method: "POST", body: formData })
            .then(r => r.json())
            .then(d => {
                // 🟢 UPDATED ALERT LOGIC
                let iconType = d.status;
                let titleText = "Updated";
                
                if (d.status === "info") {
                    iconType = "info";
                    titleText = "Verification Sent";
                } else if (d.status === "error") {
                    iconType = "error";
                    titleText = "Error";
                } else if (d.status === "warning") {
                    iconType = "warning";
                    titleText = "Warning";
                }

                Swal.fire({
                    icon: iconType,
                    title: titleText,
                    text: d.message,
                    confirmButtonColor: "#1F4015"
                }).then(() => {
                    // Always reload on success or info (pending email)
                    if (d.status === "success" || d.status === "info") {
                        location.reload();
                    }
                });
            })
            .catch(err => {
                console.error(err);
                Swal.fire({icon: "error", title: "System Error", text: "Something went wrong.", ...swalTheme});
            });
    }

    function deleteUser(id) {
        Swal.fire({title: "Delete?", text: "Cannot be undone", icon: "warning", showCancelButton: true, ...swalTheme})
        .then(res => {
            if (res.isConfirmed) {
                fetch("delete_user.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({ id })
                }).then(r => r.json()).then(d => {
                    if (d.status === "success") {
                        document.getElementById("row-" + id).remove();
                        Swal.fire({icon: "success", title: "Deleted", ...swalTheme});
                    }
                });
            }
        });
    }

    // --- DROPDOWNS ---
    function loadDropdownsForAddUser() {
        fetch("get_category_faculty.php").then(r => r.json()).then(data => {
             populate("userPosition", data.positions);
             populate("userDepartment", data.departments);
        });
    }
    function loadDropdownsForEditUser(pos, dept) {
        fetch("get_category_faculty.php").then(r => r.json()).then(data => {
             populate("editPosition", data.positions, pos);
             populate("editDepartment", data.departments, dept);
        });
    }
    function populate(id, items, selected = "") {
        const el = document.getElementById(id);
        el.innerHTML = "";
        items.forEach(i => {
            const opt = document.createElement("option");
            opt.value = i.name; opt.innerText = i.name;
            if(i.name.toLowerCase() === selected.toLowerCase()) opt.selected = true;
            el.appendChild(opt);
        });
    }

    // --- CATEGORIES ---
    function loadCategories() {
        fetch("get_category_faculty.php").then(r => r.json()).then(data => {
            const render = (listId, items, type) => {
                const el = document.getElementById(listId);
                if (el) {
                    el.innerHTML = items.map(i => 
                        `<div class="category-item">${i.name}<button onclick="deleteCategory('${type}', ${i.id})">Delete</button></div>`
                    ).join('') || "<p>None found.</p>";
                }
            };
            render("positionsList", data.positions, "position");
            render("departmentsList", data.departments, "department");
        });
    }
    function addCategory(type) {
        const value = document.getElementById(`new${capitalize(type)}`).value.trim();
        if (!value) return;
        fetch("add_category_faculty.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ type, value })
        }).then(r => r.json()).then(d => {
            if(d.status==="success") { loadCategories(); document.getElementById(`new${capitalize(type)}`).value = ""; }
        });
    }
    function deleteCategory(type, id) {
        if(confirm("Delete category?")) {
            fetch("delete_category_faculty.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ type, id })
            }).then(r => r.json()).then(d => { if(d.status==="success") loadCategories(); });
        }
    }
    function capitalize(str) { return str.charAt(0).toUpperCase() + str.slice(1); }

    // --- IMPORT ---
    function importCSV(){
        const fd = new FormData(document.getElementById('importForm'));
        fetch("import_csv.php", {method:"POST", body:fd}).then(r=>r.json()).then(d=>{
            Swal.fire({icon:d.status, title:d.status, text:d.message, ...swalTheme}).then(()=>location.reload());
        });
    }
    function toggleCSVExample(e) { e.preventDefault(); const ex = document.getElementById('csvExample'); ex.style.display = ex.style.display==='none'?'block':'none'; }

    // Search
    let typingTimer;
    document.getElementById("searchInput").addEventListener("keyup", function(){
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            const val = this.value.trim();
            const url = new URL(window.location.href);
            url.searchParams.set('search', val);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }, 500);
    });
</script>
</body>
</html>