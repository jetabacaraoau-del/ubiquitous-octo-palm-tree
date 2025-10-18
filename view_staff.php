<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('location:login.php');
    exit;
}

// Get staff ID from URL
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $staffId = $_GET['id'];

    $staffQuery = "SELECT id, first_name, last_name, email, password, created_at FROM staff WHERE id = '$staffId'";
    $staffResult = mysqli_query($conn, $staffQuery);
    $staffRow = mysqli_fetch_assoc($staffResult);

    if (!$staffRow) {
        echo "Staff member not found!";
        exit;
    }
} else {
    echo "Invalid staff ID!";
    exit;
}

include('inc/header.php');
include('inc/nav.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Staff Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            background-color: #ffffff; /* pure white */
        }

        .main-content {
            padding: 75px 20px;
        }

        h2 {
            font-weight: 700;
            margin-bottom: 30px;
            color: #004d40; /* dark teal accent */
            text-align: center;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
            border: none;
            background-color: #ffffff;
        }

        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
            font-size: 1.2rem;
            color: #00796b; /* teal accent */
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .staff-details th, .staff-details td {
            padding: 15px 20px;
            text-align: left;
            vertical-align: middle;
        }

        .staff-details th {
            background-color: #00796b; /* teal header */
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .staff-details td {
            background-color: #f9f9f9; /* light gray for contrast */
        }

        .staff-details tr + tr td {
            border-top: 1px solid #e0e0e0;
        }

        .btn-back {
            background-color: #00796b; /* teal */
            color: white;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background-color: #004d40; /* darker teal on hover */
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 10px;
            }

            .staff-details th, .staff-details td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid main-content">
    <h2>Staff Details</h2>
    <div class="card mx-auto" style="max-width: 700px;">
        <div class="card-header">
            Staff Information
            <a href="manage_staff.php" class="btn-back">Back to Staff List</a>
        </div>
        <div class="card-body">
            <table class="table staff-details">
                <tr>
                    <th>Full Name</th>
                    <td><?= htmlspecialchars($staffRow['first_name'] . ' ' . $staffRow['last_name']) ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?= htmlspecialchars($staffRow['email']) ?></td>
                </tr>
                <tr>
                    <th>Created At</th>
                    <td><?= htmlspecialchars($staffRow['created_at']) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php mysqli_close($conn); ?>
