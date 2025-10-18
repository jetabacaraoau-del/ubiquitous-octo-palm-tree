<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
  header('Location: login.php');
  exit;
}

// Fetch product
$message = '';
$product_id = $_GET['id'] ?? '';
if ($product_id) {
  $product_id = mysqli_real_escape_string($conn, $product_id);
  $query = "SELECT * FROM products WHERE product_id='$product_id'";
  $result = mysqli_query($conn, $query);
  $product = mysqli_fetch_assoc($result);
}
?>

<?php include('inc/header.php'); ?>
<?php include('inc/nav.php'); ?>

<style>

/* Shift container closer to top */
.shift-down {
    margin-top: 90px; /* moved closer to top */
}
@media (max-width: 768px) {
    .shift-down {
        margin-top: 20px;  /* mobile top margin */
        margin-left: 0;
        padding: 0 15px;
    }
}

/* Card styling */
.card {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.card-header {
    border: none;
    padding: 1rem 1.5rem;
    background-color: #ffffff;
    color: #333333;
    border-bottom: 1px solid #e0e0e0;
}
.card-header h4 {
    margin: 0;
    font-weight: 600;
}
.card-body {
    padding: 1.8rem;
}

/* Form elements */
.form-label {
    font-weight: 500;
    color: #555;
}
.form-control, .form-select {
    border-radius: 8px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    padding: 10px;
    transition: all 0.3s ease;
}
.form-control:focus, .form-select:focus {
    border-color: teal;
    box-shadow: 0 0 0 3px rgba(0,128,128,0.15);
}

/* Buttons */
.btn-teal {
    background-color: teal;
    color: #fff;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    padding: 10px 25px;
    transition: all 0.3s ease;
}
.btn-teal:hover {
    background-color: #006666;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    color: #fff;
}

/* Alert */
.alert {
    border-radius: 12px;
    padding: 12px 20px;
}

/* Image preview */
.img-thumbnail {
    border-radius: 12px;
    object-fit: cover;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
</style>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-8 shift-down">
      <div class="card shadow-sm mb-5">
        <div class="card-header rounded-top-4">
          <h4 class="fw-bold">Edit Product</h4>
        </div>

        <div class="card-body">
          <?php if (!empty($message)) : ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
              <?= htmlspecialchars($message); ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data" action="updateProduct.php">
            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['product_id'] ?? ''); ?>">

            <div class="mb-3">
              <label class="form-label">Product Name</label>
              <input type="text" name="productname" class="form-control" value="<?= htmlspecialchars($product['product_name'] ?? ''); ?>" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Product Description</label>
              <textarea name="productdescription" class="form-control" rows="3" required><?= htmlspecialchars($product['product_description'] ?? ''); ?></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label">Category</label>
              <select name="productcategory" class="form-select" required>
                <option value="" disabled>-- Select Category --</option>
                <?php
                $cats = mysqli_query($conn, "SELECT * FROM category");
                while ($cat = mysqli_fetch_assoc($cats)) {
                  $selected = ($product['cat_id'] ?? '') == $cat['cat_id'] ? 'selected' : '';
                  echo "<option value='{$cat['cat_id']}' $selected>{$cat['cat_name']}</option>";
                }
                ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Product Price</label>
              <input type="number" step="0.01" name="productprice" class="form-control" value="<?= htmlspecialchars($product['price'] ?? ''); ?>" required>
            </div>

            <?php if (!empty($product['thumb'])) : ?>
              <div class="mb-3">
                <label class="form-label">Current Image</label><br>
                <img src="<?= htmlspecialchars($product['thumb']); ?>" alt="Product Image" width="150" height="150" class="img-thumbnail mb-2">
              </div>
            <?php endif; ?>

            <div class="mb-4">
              <label class="form-label">Replace Image (optional)</label>
              <input type="file" name="productimage" class="form-control" accept=".jpg,.jpeg">
              <small class="form-text text-muted">JPG only. Max 10MB.</small>
            </div>

            <button type="submit" name="submit" class="btn btn-teal w-100">Save Product</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include('inc/footer.php'); ?>
