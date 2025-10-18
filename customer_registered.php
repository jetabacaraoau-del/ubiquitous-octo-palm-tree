<?php
session_start();
include('../config/db.php');

// Redirect if not logged in
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('location:login.php');
    exit;
}

// Archive customers inactive for 90+ days
$ninetyDaysAgo = date('Y-m-d H:i:s', strtotime('-90 days'));
$stmt = $conn->prepare("UPDATE users SET archived = 1 WHERE last_active < ? AND archived = 0");
$stmt->bind_param("s", $ninetyDaysAgo);
$stmt->execute();
$archivedCount = $stmt->affected_rows;
$stmt->close();

// Pagination settings
$limit = 10; // display 5 rows per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total count of active users
$totalQuery = "SELECT COUNT(*) as total FROM users WHERE archived = 0";
$totalResult = mysqli_query($conn, $totalQuery);
$totalRow = mysqli_fetch_assoc($totalResult);
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $limit);

// Get active users for current page
$query = "SELECT * FROM users WHERE archived = 0 ORDER BY id DESC LIMIT $limit OFFSET $offset"; 
$result = mysqli_query($conn, $query);
?>

<?php include('inc/header.php'); ?>
<?php include('inc/nav.php'); ?>

<div class="main-content" style="padding: 50px 150px; min-height: 100vh; background-color: #f8f9fa;">
    <div class="container" style="max-width: 1250px; margin-left: 250px; margin-top: 40px;">
        <?php if ($archivedCount > 0): ?>
            <div class="alert alert-info text-center shadow-sm">
                <?= $archivedCount ?> customer(s) archived due to 90+ days of inactivity.
            </div>
        <?php endif; ?>

        <div class="card shadow-lg rounded-4 border-0 mb-4">
            <div class="card-header text-center bg-teal text-white py-3" style="font-size: 1.5rem; font-weight: 600; border-radius: 0.5rem 0.5rem 0 0;">
                Registered Customers
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th>ID</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Last Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td class="text-center"><?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['first_name']) ?></td>
                                        <td><?= htmlspecialchars($row['last_name']) ?></td>
                                        <td><?= htmlspecialchars($row['email']) ?></td>
                                        <td class="text-center"><?= date('F j, Y, g:i a', strtotime($row['last_active'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No registered customers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center mt-3">
                            <!-- Previous Page Link -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">&laquo;</a>
                            </li>

                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Page Link -->
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<style>
body {
    font-family: 'Open Sans', sans-serif;
    background-color: #f8f9fa;
}

.bg-teal { background-color: #00796b !important; }

.card {
    background: #ffffff;
    max-height: 700px; /* control how tall the card appears */
    display: flex;
    flex-direction: column;
}

.card-body {
    overflow-y: auto; /* allows scrolling inside the card body */
    max-height: 550px; /* adjusts scrollable area height */
    padding: 20px;
}

/* Optional: make the scrollbar look cleaner */
.card-body::-webkit-scrollbar {
    width: 8px;
}

.card-body::-webkit-scrollbar-thumb {
    background-color: #b2dfdb;
    border-radius: 10px;
}

.card-body::-webkit-scrollbar-thumb:hover {
    background-color: #00796b;
}


.table {
    border-collapse: separate;
    border-spacing: 0 0.75rem;
}
.table th, .table td {
    vertical-align: middle;
    padding: 12px 15px;
    border-radius: 8px;
}
.table-hover tbody tr:hover {
    background-color: #e0f2f1 !important;
    transition: 0.3s ease;
}
.table thead th {
    border-bottom: none;
    color: #00796b;
    font-weight: 600;
}

.pagination .page-item.active .page-link {
    background-color: #00796b;
    border-color: #00796b;
    color: #fff;
}

.pagination .page-link {
    color: #00796b;
}
.pagination .page-item.disabled .page-link {
    color: #aaa;
    pointer-events: none;
}

@media (max-width: 768px) {
    .main-content { padding: 20px 15px; }
    .card-header { font-size: 1.25rem; }
    .table th, .table td { font-size: 0.85rem; padding: 10px; }
}
</style>
