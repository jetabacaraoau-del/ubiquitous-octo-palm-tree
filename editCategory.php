<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('location:login.php');
    exit;
}

$catid = '';
$catName = '';
$error = '';
$success = '';

if (isset($_GET['id'])) {
    $catid = $_GET['id'];
    $sql = "SELECT * FROM category WHERE cat_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $catid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $catName = $row['cat_name'];
    } else {
        $error = "Category not found.";
    }
    $stmt->close();
}

if (isset($_POST['submit'])) {
    $hiddenID = $_POST['hiddenID'];
    $catName = trim($_POST['catName']);

    if (empty($catName)) {
        $error = "Category name cannot be empty.";
    } else {
        $sql = "UPDATE category SET cat_name = ? WHERE cat_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $catName, $hiddenID);
        if ($stmt->execute()) {
            header('Location: categories.php?updated=1');
            exit;
        } else {
            $error = "Error updating record: " . $conn->error;
        }
        $stmt->close();
    }
}
?>

<?php include('inc/header.php'); ?>
<?php include('inc/nav.php'); ?>

<style>
    .shift-down {
        margin-top: 150px; /* move down on desktop */
    }

    @media (max-width: 768px) {
        .shift-down {
            margin-top: 150px;      /* smaller top margin for mobile */
            margin-left: 350px;     /* add left margin on mobile */
        }
    }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 shift-down">
            <div class="card shadow rounded-4 border-0">
         <div class="card-header d-flex justify-content-between align-items-center" 
             style="background-color: #ffffff; color: #333333; border-radius: 1rem 1rem 0 0; 
                    border-bottom: 1px solid #ddd;">
            <h4 class="mb-0 fw-semibold">Edit Category</h4>
        </div>
        
                <div class="card-body">
                    <?php if (!empty($error)) : ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success)) : ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="editCategory.php?id=<?= htmlspecialchars($catid) ?>" method="post" novalidate>
                        <div class="mb-3">
                            <label for="catName" class="form-label">Category Name</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="catName" 
                                name="catName" 
                                value="<?= htmlspecialchars($catName); ?>" 
                                required
                            >
                        </div>
                        <input type="hidden" name="hiddenID" value="<?= htmlspecialchars($catid); ?>">
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="submit" 
                                class="btn w-50 fw-semibold px-4"
                                style="background-color: teal; color: #fff; border: none; 
                                       border-radius: 8px; padding: 10px; 
                                       transition: all 0.3s ease;">
                          Update
                        </button>

                            <a href="categories.php" class="btn btn-outline-secondary px-4 w-50 ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include('inc/footer.php'); ?>