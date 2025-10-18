<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
  header('Location: login.php');
  exit;
}

// Handle form submission FIRST (before includes/HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
  $productname = $_POST['productname'];
  $productdescription = $_POST['productdescription'];
  $productcategory = $_POST['productcategory'];
  $productprice = $_POST['productprice'];
  $thumb = null;

  if (isset($_FILES['productimage']) && $_FILES['productimage']['error'] === UPLOAD_ERR_OK) {
    $name = $_FILES['productimage']['name'];
    $size = $_FILES['productimage']['size'];
    $type = $_FILES['productimage']['type'];
    $tmp_name = $_FILES['productimage']['tmp_name'];
    $max_size = 10000000; // 10MB
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (($extension === "jpg" || $extension === "jpeg") && $type === "image/jpeg" && $size <= $max_size) {
      $upload_dir = "uploads/";
      $unique_name = uniqid("img_") . "." . $extension;
      $filepath = $upload_dir . $unique_name;

      if (move_uploaded_file($tmp_name, $filepath)) {
        $thumb = $filepath;
      } else {
        $message = "❌ Failed to upload image.";
      }
    } else {
      $message = "❌ Only JPG/JPEG images under 10MB are allowed.";
    }
  }

  if (!isset($message)) {
    if ($thumb) {
      $stmt = $conn->prepare("INSERT INTO products (product_name, cat_id, price, product_description, thumb) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param("sssss", $productname, $productcategory, $productprice, $productdescription, $thumb);
    } else {
      $stmt = $conn->prepare("INSERT INTO products (product_name, cat_id, price, product_description) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("ssss", $productname, $productcategory, $productprice, $productdescription);
    }

    if ($stmt->execute()) {
      $stmt->close();
      $conn->close();
      header("Location: products.php?added=success");
      exit; // 🚀 prevent further output
    }

    $stmt->close();
  }

  $conn->close();
}
?>

<?php include('inc/header.php'); ?>
<?php include('inc/nav.php'); ?>

<style>
/* Shift down and add margins for mobile */
.shift-down {
    margin-top: 100px; /* move down on desktop */
}
@media (max-width: 768px) {
    .shift-down {
        margin-top: 70px;      /* smaller top margin on mobile */
        margin-left: 350px;     /* left margin for mobile */
    }
}

/* Optional card styling */
.card {
    border-radius: 16px;
    overflow: hidden;
}
.card-header {
    border: none;
    padding: 1rem 1.5rem;
}
.card-body {
    padding: 1.5rem;
}
</style>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8 shift-down">
      <div class="card shadow rounded-4 mb-5">
       <div class="card-header rounded-top-4" 
             style="background-color: #ffffff; color: #333333;">
          <h5 class="mb-0">Add Product</h5>
        </div>

        <div class="card-body">
          <?php if (isset($message)) : ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <?= htmlspecialchars($message); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data" action="addProducts.php">
            <div class="mb-3">
              <label for="Productname" class="form-label">Product Name</label>
              <input type="text" class="form-control" name="productname" id="Productname" required>
            </div>

            <div class="mb-3">
              <label for="productdescription" class="form-label">Product Description</label>
              <textarea class="form-control" name="productdescription" rows="3" required></textarea>
            </div>

            <div class="mb-3">
              <label for="productcategory" class="form-label">Product Category</label>
              <select class="form-select" id="productcategory" name="productcategory" required>
                <option value="" selected disabled>--- SELECT CATEGORY ---</option>
                <?php
                $catQuery = mysqli_query($conn, "SELECT * FROM category");
                while ($row = mysqli_fetch_assoc($catQuery)) {
                  echo "<option value='{$row['cat_id']}'>{$row['cat_name']}</option>";
                }
                ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="productprice" class="form-label">Product Price</label>
              <input type="number" class="form-control" name="productprice" id="productprice" required>
            </div>

            <div class="mb-3">
              <label for="productimage" class="form-label">Product Image</label>
              <input type="file" class="form-control" name="productimage" id="productimage">
              <small class="text-muted">Only JPG/JPEG. Max size: 10MB.</small>
            </div>

            <button type="submit" name="submit" 
                    class="btn w-30 fw-semibold px-4"
                    style="background-color: teal; color: #333333; border: none; 
                           border-radius: 8px; padding: 10px; 
                           transition: all 0.3s ease;">
              Submit
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include('inc/footer.php'); ?>
