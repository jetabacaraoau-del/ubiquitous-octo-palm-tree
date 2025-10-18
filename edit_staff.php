<?php
session_start();
include('../config/db.php');
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('location:login.php');
    exit;
}

// Get staff ID
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $staff_id = $_GET['id'];
    $staffQuery = "SELECT id, first_name, last_name, email FROM staff WHERE id = '$staff_id'";
    $staffResult = mysqli_query($conn, $staffQuery);
    if (mysqli_num_rows($staffResult) == 0) {
        echo "Staff not found.";
        exit;
    }
    $staffRow = mysqli_fetch_assoc($staffResult);
} else {
    echo "No staff ID provided.";
    exit;
}

// Handle update
if (isset($_POST['update'])) {
    $first_name = $_POST['first_name'];
    $last_name  = $_POST['last_name'];
    $email      = $_POST['email'];

    $updateQuery = "UPDATE staff SET first_name='$first_name', last_name='$last_name', email='$email' WHERE id='$staff_id'";
    if (mysqli_query($conn, $updateQuery)) {
        header("Location: manage_staff.php");
        exit;
    } else {
        echo "Error updating staff: " . mysqli_error($conn);
    }
}

include('inc/header.php');
include('inc/nav.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Staff</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    font-family: 'Open Sans', sans-serif;
    background-color: #f8f9fa;
    margin: 0;
    display: flex;
}

/* Sidebar & Main Content Layout */
.main-content {
    flex: 1;
    padding: 75px 40px;
    min-height: 100vh;
    box-sizing: border-box;
}

/* Card Design */
.card {
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    border: none;
    overflow: hidden;
    background-color: #ffffff;
}

.card-body {
    padding: 40px;
}

/* Header */
h2 {
    font-weight: 600;
    margin-bottom: 30px;
    color: #004d40;
    text-align: center;
}

/* Form Styling */
.form-label {
    font-weight: 600;
    color: #555;
}

.form-control {
    border-radius: 8px;
    padding: 12px 15px;
    border: 1px solid #ced4da;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #00796b;
    box-shadow: 0 0 5px rgba(0, 121, 107, 0.3);
}

/* Button */
.btn-teal {
    background-color: #00796b;
    color: white;
    border-radius: 8px;
    padding: 14px 20px;
    width: 100%;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s ease;
}

.btn-teal:hover {
    background-color: #004d40;
    transform: translateY(-2px);
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .sidebar {
        display: none; /* hide sidebar on smaller screens */
    }
    .main-content {
        padding: 30px 20px;
    }
}

@media (max-width: 576px) {
    .card-body {
        padding: 25px 15px;
    }
}
</style>
</head>
<body>

<div class="main-content">
    <h2>Edit Staff</h2>
    <div class="card mx-auto" style="max-width: 600px;">
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($staffRow['first_name']) ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($staffRow['last_name']) ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($staffRow['email']) ?>" required>
                </div>
                <button type="submit" name="update" class="btn-teal">Update Staff</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>

<?php mysqli_close($conn); ?>
