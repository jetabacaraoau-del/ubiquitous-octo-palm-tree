<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: login.php');
    exit();
}

// Pagination setup
$limit = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Total records
$totalResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products");
$totalRow = mysqli_fetch_assoc($totalResult);
$totalPages = ceil($totalRow['total'] / $limit);

// Fetch paginated products
$sql = "SELECT p.*, c.cat_name FROM products p 
        LEFT JOIN category c ON p.cat_id = c.cat_id 
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
?>

<?php include('inc/header.php'); ?>
<?php include('inc/nav.php'); ?>

<style>
  body { background: #f5f7fa; font-family: 'Poppins', sans-serif; }
  

.card {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    margin-left: 70px;
    margin-top: 30px; /* moves the card down */
    width: 1200px;
}

  .card-header {
    background: #ffffff;
    color: #333;
    border-bottom: 1px solid #e0e0e0;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .card-body { padding: 1.5rem; }

  /* Buttons */
  .btn-custom {
    background: teal;
    color: #fff;
    font-weight: 600;
    border: none;
    transition: all 0.3s ease;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
  }
  .btn-custom:hover {
    background: #006666;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.25);
    color: #fff;
  }
  .btn-info { background: #3182ce; border: none; }
  .btn-info:hover { background: #2b6cb0; }
  .btn-warning { background: #ecc94b; border: none; color: #fff; }
  .btn-warning:hover { background: #d69e2e; color: #fff; }

  /* Table Styling */
  .table { border-radius: 12px; overflow: hidden; }
  .table thead { background: #f8fafc; font-weight: 600; letter-spacing: 0.5px; }
  .table tbody tr:hover { background: #f1f5f9; transition: background 0.2s ease; }
  td img { border-radius: 8px; border: 1px solid #ddd; }

  /* Badges */
  .badge-success { background-color: #38a169; }
  .badge-secondary { background-color: #718096; }

  /* Pagination */
  .pagination .page-link {
    border-radius: 50px;
    margin: 0 4px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    transition: 0.3s;
  }
  .pagination .page-link:hover { background: teal; color: #fff; }
  .pagination .active .page-link { background: teal; border-color: teal; }

  .shift-down { margin-top: 100px; }

  @media (max-width: 768px) {
    .shift-down { margin-top: 90px; margin-left: 20px; }
    .card-body { padding: 1rem; }
  }
</style>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-md-10 shift-down">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 fw-bold">📦 All Products</h5>
          <a href="addProducts.php" class="btn btn-custom btn-sm rounded-pill">+ Add Product</a>
        </div>
        <div class="card-body">

          <!-- Success Message -->
          <?php if (isset($_GET['added']) && $_GET['added'] === 'success'): ?>
          <div class="alert alert-success alert-dismissible fade show rounded-pill px-4" role="alert">
            ✅ Product successfully added!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php endif; ?>

          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Image</th>
                  <th>Product Name</th>
                  <th>Category</th>
                  <th>Thumbnail</th>
                  <th class="text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                  <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                      <td>
                        <?php if (!empty($row["thumb"])): ?>
                          <img src="<?= $row["thumb"]; ?>" alt="Product Image" width="60" height="60" class="shadow-sm">
                        <?php else: ?>
                          <span class="text-muted">No Image</span>
                        <?php endif; ?>
                      </td>
                      <td class="fw-semibold"><?= htmlspecialchars($row["product_name"]); ?></td>
                      <td><?= htmlspecialchars($row["cat_name"] ?? 'Uncategorized'); ?></td>
                      <td>
                        <span class="badge <?= !empty($row["thumb"]) ? 'badge-success' : 'badge-secondary'; ?>">
                          <?= (!empty($row["thumb"])) ? 'Yes' : 'No'; ?>
                        </span>
                      </td>
                      <td class="text-center">
                        <a href="editProducts.php?id=<?= $row["product_id"]; ?>" class="btn btn-warning btn-sm me-1">Edit</a>
                        <a href="delProducts.php?id=<?= $row["product_id"]; ?>" onclick="return confirm('Do you want to delete this product?')" class="btn btn-danger btn-sm">Delete</a>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="5" class="text-center text-muted">No products found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

         <!-- Pagination -->
        <nav>
          <ul class="pagination justify-content-center mt-4">
        
            <!-- Previous Arrow -->
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=<?= $page - 1 ?>">&laquo;</a>
            </li>
        
            <?php
            $maxLinks = 5; // Number of page numbers to show
            $start = max(1, $page - floor($maxLinks / 2));
            $end = min($totalPages, $start + $maxLinks - 1);
        
            // Adjust start if near the end
            if ($end - $start < $maxLinks - 1) {
                $start = max(1, $end - $maxLinks + 1);
            }
        
            for ($i = $start; $i <= $end; $i++): ?>
              <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
        
            <!-- Next Arrow -->
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=<?= $page + 1 ?>">&raquo;</a>
            </li>
        
          </ul>
        </nav>


        </div>
      </div>
    </div>
  </div>
</div>

<?php include('inc/footer.php'); ?>

<script>
  // Auto-hide success message
  setTimeout(() => {
    let alert = document.querySelector('.alert');
    if (alert) { new bootstrap.Alert(alert).close(); }
  }, 4000);
</script>
