<?php
session_start();
include('../config/db.php');

// Redirect if admin not logged in
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['admin_id'];
$uploadError = '';

// Fetch admin data
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $targetDir = "../uploads/admin/";
    $targetFile = $targetDir . basename($_FILES['profile_image']['name']);
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    $validTypes = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($imageFileType, $validTypes)) {
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
            $stmt = $conn->prepare("UPDATE admin SET profile_image = ? WHERE id = ?");
            $stmt->bind_param("si", $_FILES['profile_image']['name'], $admin_id);
            $stmt->execute();
            header("Location: admin_profile.php");
            exit;
        } else {
            $uploadError = "Failed to upload image.";
        }
    } else {
        $uploadError = "Only JPG, JPEG, PNG, GIF files allowed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        .profile-container {
            max-width: 600px;
            margin: 50px auto;
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="container profile-container text-center">
    <h2>Admin Profile</h2>
    <?php if (!empty($admin['profile_image'])): ?>
        <img src="../uploads/admin/<?= htmlspecialchars($admin['profile_image']) ?>" class="profile-image" alt="Profile Image">
    <?php else: ?>
        <img src="../assets/images/default.png" class="profile-image" alt="Default Image">
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="mb-3">
        <input type="file" name="profile_image" class="form-control mb-2">
        <button type="submit" class="btn btn-primary btn-sm">Upload</button>
    </form>
    <p class="text-danger"><?= $uploadError ?></p>

    <div class="text-start mt-3">
        <p><strong>Full Name:</strong> <?= htmlspecialchars($admin['FirstName'] . ' ' . $admin['LastName']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($admin['email']) ?></p>
        <p><strong>Role:</strong> Admin</p>
    </div>

    <a href="edit_admin.php" class="btn btn-warning mt-3">Edit Profile</a>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
