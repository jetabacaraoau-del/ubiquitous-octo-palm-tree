<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
    $productname = mysqli_real_escape_string($conn, $_POST['productname']);
    $productdescription = mysqli_real_escape_string($conn, $_POST['productdescription']);
    $productcategory = mysqli_real_escape_string($conn, $_POST['productcategory']);
    $productprice = mysqli_real_escape_string($conn, $_POST['productprice']);

    $update_sql = "
        UPDATE products 
        SET product_name = ?, product_description = ?, cat_id = ?, price = ?
        WHERE product_id = ?
    ";

    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ssssi", $productname, $productdescription, $productcategory, $productprice, $product_id);

    $success = $stmt->execute();
    $stmt->close();

    // If a new image is uploaded
    if (!empty($_FILES['productimage']['name'])) {
        $name = $_FILES['productimage']['name'];
        $size = $_FILES['productimage']['size'];
        $type = $_FILES['productimage']['type'];
        $tmp_name = $_FILES['productimage']['tmp_name'];
        $max_size = 10 * 1024 * 1024;
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (($extension === "jpg" || $extension === "jpeg") && $type === "image/jpeg" && $size <= $max_size) {
            $uniqueName = uniqid('img_') . '.' . $extension;
            $upload_path = "uploads/" . $uniqueName;

            if (move_uploaded_file($tmp_name, $upload_path)) {
                $thumb_update = $conn->prepare("UPDATE products SET thumb = ? WHERE product_id = ?");
                $thumb_update->bind_param("si", $upload_path, $product_id);
                $thumb_update->execute();
                $thumb_update->close();
            }
        }
    }

    if ($success) {
        header('Location: products.php');
        exit();
    } else {
        echo "<div style='padding:10px;background:#fdd;color:#900;'>❌ Failed to update product.</div>";
    }
} else {
    header('Location: products.php');
    exit();
}
?>
