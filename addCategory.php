<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: login.php');
    exit();
}

$catName = "";
$message = "";
$messageType = "";

if (isset($_POST['submit'])) {
    $catName = trim($_POST['catName']);

    if (empty($catName)) {
        $message = "Category name cannot be empty.";
        $messageType = "danger";
    } else {
        $stmt = $conn->prepare("INSERT INTO category (cat_name) VALUES (?)");
        $stmt->bind_param("s", $catName);

        if ($stmt->execute()) {
            header("Location: categories.php?success=1");
            exit();
        } else {
            $message = "Error adding category. Please try again.";
            $messageType = "danger";
        }
    }
}
?>

<?php include('inc/header.php'); ?>
<?php include('inc/nav.php'); ?>
<style>
    .shift-down {
        margin-top: 150px; /* move down */
    }

 @media (max-width: 768px) {
    .shift-down {
        margin-top: 120px;      /* smaller top margin */
        margin-left: 350px;     /* add left margin on mobile */
    }
}
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 shift-down">
            <div class="card shadow rounded-4 border-0">
                <div class="card-header d-flex justify-content-between align-items-center rounded-top-4"
                     style="background-color: #ffffff; color: #333333;">
                  <h4 class="mb-0 fw-semibold">Add New Category</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="addCategory.php" method="post">
                        <div class="mb-3">
                            <label for="catName" class="form-label">Category Name</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="catName" 
                                name="catName" 
                                value="<?= htmlspecialchars($catName) ?>" 
                                required>
                        </div>
                        <button type="submit" name="submit" 
                            class="btn w-30 fw-semibold px-4"
                            style="background-color: teal; color: #fff; border: none; 
                                   border-radius: 8px; padding: 10px; 
                                   transition: all 0.3s ease;">
                      Add Category
                    </button>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('inc/footer.php'); ?>