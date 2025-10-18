<?php
session_start();
include('../config/db.php');
include('inc/nav.php');
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
  header('location:login.php');
  exit;
}

$staffQuery = "SELECT id, first_name, last_name, email, password, created_at FROM staff";
$staffResult = mysqli_query($conn, $staffQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Staff - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
  margin: 0;
  font-family: 'Poppins', sans-serif;
  min-height: 100vh;
  background-color: #f4f6f9;
}

/* Content */
.content-wrapper {
  padding: 90px 30px 30px;
  min-height: 100vh;
  box-sizing: border-box;
  background-color: #f4f6f9;
  margin-left: 300px;
  width: 1300px;
}

/* Card/Table */
.card {
  border-radius: 15px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.08);
  border: none;
  background: #fff;
  overflow: hidden;
}

.card-header {
  background: #fff;
  padding: 1.25rem 2rem;
  font-weight: 600;
  font-size: 1.25rem;
  color: #495057;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-radius: 15px 15px 0 0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.card-body {
  padding: 1.5rem;
  overflow-x: auto;
}

.staff-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 0.85rem;
  margin-top: 10px;
}

.staff-table th, .staff-table td {
  vertical-align: middle;
  padding: 14px 20px;
  background: #fff;
  border-radius: 12px;
  text-align: center;
  font-weight: 500;
  color: #495057;
  white-space: nowrap;
}

.staff-table th {
  background: transparent;
  color: #6c757d;
  font-weight: 700;
  font-size: 0.9rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.staff-table tbody tr:hover td {
  background: #e9f2ff;
}

.btn-teal {
  background-color: #00796b; /* Teal color */
  color: white;
  font-weight: 500;
  border-radius: 8px;
  padding: 8px 18px;
  transition: all 0.3s ease;
  text-decoration: none;
}

.btn-teal:hover {
  background-color: #004d40;
  transform: translateY(-2px);
}

</style>
</head>
<body>

<!-- MAIN CONTENT -->
<div class="content-wrapper">
  <div class="container">
    <div class="card">
      <div class="card-header">
        Staff List
        <a href="staff.php" class="btn btn-teal">+ Add Staff</a>
      </div>
      <div class="card-body">
        <table class="staff-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (mysqli_num_rows($staffResult) > 0): ?>
              <?php while ($staffRow = mysqli_fetch_assoc($staffResult)): ?>
                <tr>
                  <td><?= htmlspecialchars($staffRow['id']) ?></td>
                  <td><?= htmlspecialchars($staffRow['first_name'] . ' ' . $staffRow['last_name']) ?></td>
                  <td><?= htmlspecialchars($staffRow['email']) ?></td>
                  <td>
                    <a href="view_staff.php?id=<?= $staffRow['id'] ?>" class="btn btn-info">View</a>
                    <a href="edit_staff.php?id=<?= $staffRow['id'] ?>" class="btn btn-warning">Edit</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="text-center text-muted">No staff found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php mysqli_close($conn); ?>
