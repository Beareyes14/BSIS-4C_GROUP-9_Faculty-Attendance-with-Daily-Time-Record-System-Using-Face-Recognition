<?php
//archive.php - Admin page to view and manage archived users
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

// 🟢 FIX: Count archived rows from the 'users' table directly
$total_result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE status = 'Archived'");
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// 🟢 FIX: Fetch archived users from the 'users' table
$sql = "SELECT * FROM users WHERE status = 'Archived' ORDER BY id DESC LIMIT $start, $limit";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Archived Users</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    padding: 25px;
}

h1 {
    margin-bottom: 15px;
}

/* Table */
table {
    width: 100%;
    border-collapse: collapse;
    background-color: white;
}
th, td {
    border: 1px solid #1F4015;
    text-align: center;
    padding: 10px;
    color: #1F4015;
}
th {
    background-color: #AAB396;
}

/* Buttons */
.action-btn, .bulk-btn, .back-btn {
    border: 1px solid #1F4015;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    padding: 8px 14px;
    margin: 4px;
    transition: 0.2s;
}
.action-btn {
    background-color: #F4DD97;
    color: #1F4015;
}
.action-btn:hover { background-color: #D6B540; }

.bulk-btn {
    background-color: #AAB396;
    color: #1F4015;
}
.bulk-btn:hover { background-color: #8F9D7C; }

.delete-all {
    background-color: #D22B2B;
    color: white;
}
.delete-all:hover { background-color: #A31515; }

.back-btn {
    background-color: #AAB396;
    color: #1F4015;
    margin-bottom: 10px;
}
.back-btn:hover { background-color: #8F9D7C; }

.top-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.right-buttons {
    display: flex;
    gap: 10px;
}
/* Pagination */
.pagination {
    margin-top: 15px;
    text-align: center;
}
.pagination a {
    background-color: #AAB396;
    color: #1F4015;
    padding: 6px 12px;
    text-decoration: none;
    border-radius: 5px;
    border: 1px solid #1F4015;
    margin: 0 2px;
}
.pagination a:hover { background-color: #8F9D7C; }
.pagination .active {
    background-color: #D6B540;
    font-weight: bold;
}
</style>
</head>
<body>
<?php include "sidebar.php"; ?>

<div class="content">
    <h1>Archived Users</h1>

    <div class="top-controls">
  <button class="back-btn" onclick="window.location.href='faculty_management.php'">← Back to Faculty Management</button>
  <div class="right-buttons">
      <button class="bulk-btn" onclick="restoreAll()">Restore All</button>
      <button class="bulk-btn delete-all" onclick="deleteAll()">Delete All (Permanent)</button>
  </div>
</div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Position</th>
                <th>Department</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= ucfirst($row['role']) ?></td>
                        <td><?= htmlspecialchars($row['position']) ?></td>
                        <td><?= htmlspecialchars($row['department']) ?></td>
                        <td><b style="color:#D22B2B;"><?= htmlspecialchars($row['status']) ?></b></td>
                        <td>
                            <button class="action-btn" onclick="restoreUser(<?= $row['id'] ?>)">Restore</button>
                            <button class="action-btn delete-all" onclick="deleteUser(<?= $row['id'] ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8">No archived users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>

<script>
function restoreUser(id) {
  Swal.fire({
    title: "Restore User?",
    text: "This user will be moved back to Faculty Management.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Yes, restore",
    cancelButtonText: "Cancel",
    confirmButtonColor: "#1F4015",
    cancelButtonColor: "#AAB396"
  }).then(result => {
    if (result.isConfirmed) {
      fetch("restore_user.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ id })
      })
      .then(res => res.json())
      .then(data => {
        Swal.fire(data.status === "success" ? "Restored!" : "Error", data.message, data.status);
        if (data.status === "success") setTimeout(() => location.reload(), 1500);
      });
    }
  });
}

function deleteUser(id) {
  Swal.fire({
    title: "Delete permanently?",
    text: "WARNING: This will also erase all their attendance logs forever.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Yes, delete",
    cancelButtonText: "Cancel",
    confirmButtonColor: "#D22B2B",
    cancelButtonColor: "#AAB396"
  }).then(result => {
    if (result.isConfirmed) {
      fetch("delete_archive_user.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ id })
      })
      .then(res => res.json())
      .then(data => {
        Swal.fire(data.status === "success" ? "Deleted!" : "Error", data.message, data.status);
        if (data.status === "success") setTimeout(() => location.reload(), 1500);
      });
    }
  });
}

function restoreAll() {
  Swal.fire({
    title: "Restore All Users?",
    text: "All archived users will be restored to Active.",
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Yes, restore all",
    cancelButtonText: "Cancel",
    confirmButtonColor: "#1F4015",
    cancelButtonColor: "#AAB396"
  }).then(result => {
    if (result.isConfirmed) {
      fetch("restore_all_users.php", { method: "POST" })
      .then(res => res.json())
      .then(data => {
        Swal.fire(data.status === "success" ? "Restored!" : "Error", data.message, data.status);
        if (data.status === "success") setTimeout(() => location.reload(), 1500);
      });
    }
  });
}

function deleteAll() {
  Swal.fire({
    title: "Delete All Records?",
    text: "WARNING: This will permanently remove ALL archived users AND their attendance logs.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Yes, delete all",
    cancelButtonText: "Cancel",
    confirmButtonColor: "#D22B2B",
    cancelButtonColor: "#AAB396"
  }).then(result => {
    if (result.isConfirmed) {
      fetch("delete_all_archived.php", { method: "POST" })
      .then(res => res.json())
      .then(data => {
        Swal.fire(data.status === "success" ? "Deleted!" : "Error", data.message, data.status);
        if (data.status === "success") setTimeout(() => location.reload(), 1500);
      });
    }
  });
}
</script>

</body>
</html>