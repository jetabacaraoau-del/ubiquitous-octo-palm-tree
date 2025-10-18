<?php
session_start();

// show errors while debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../config/db.php');

// require login
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}

$message = "";

// ---------------------
// HANDLE POST / GET (must run BEFORE any output/header includes)
// ---------------------

// Add expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $description = trim($_POST['description'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $timestamp = trim($_POST['timestamp'] ?? '');

    if ($description === '' || $amount === '' || $timestamp === '') {
        $message = "⚠️ All fields are required.";
    } else {
        // Normalize date to Y-m-d
        $timestamp = date('Y-m-d', strtotime($timestamp));
                // Insert new expense
        $stmt = $conn->prepare("INSERT INTO expenses (description, amount, timestamp) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sds", $description, $amount, $timestamp);
            if ($stmt->execute()) {
                // ✅ After insert, compute total for that month
                $month = date('m', strtotime($timestamp));
                $year = date('Y', strtotime($timestamp));
        
                $totalSql = "SELECT SUM(amount) AS total_month FROM expenses WHERE MONTH(timestamp)=? AND YEAR(timestamp)=?";
                $calc = $conn->prepare($totalSql);
                $calc->bind_param("ii", $month, $year);
                $calc->execute();
                $result = $calc->get_result();
                $row = $result->fetch_assoc();
                $totalMonth = (float)($row['total_month'] ?? 0);
                $calc->close();
        
                // ✅ Update all rows for that month with total_amount
                $update = $conn->prepare("UPDATE expenses SET total_amount=? WHERE MONTH(timestamp)=? AND YEAR(timestamp)=?");
                $update->bind_param("dii", $totalMonth, $month, $year);
                $update->execute();
                $update->close();
        
                header("Location: expenses.php?msg=added");
                exit;
            } else {
                $message = "❌ Database error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "❌ Prepare failed: " . $conn->error;
        }
    }
}

// Edit expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_expense'])) {
    $id = intval($_POST['expense_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $timestamp = trim($_POST['timestamp'] ?? '');

    if ($id <= 0 || $description === '' || $amount === '' || $timestamp === '') {
        $message = "⚠️ All fields are required for update.";
    } else {
        $timestamp = date('Y-m-d', strtotime($timestamp));
        $stmt = $conn->prepare("UPDATE expenses SET description=?, amount=?, timestamp=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("sdsi", $description, $amount, $timestamp, $id);
            if ($stmt->execute()) {
                
             // ✅ Recalculate total for that month after update
                $month = date('m', strtotime($timestamp));
                $year = date('Y', strtotime($timestamp));
            
                $totalSql = "SELECT SUM(amount) AS total_month FROM expenses WHERE MONTH(timestamp)=? AND YEAR(timestamp)=?";
                $calc = $conn->prepare($totalSql);
                $calc->bind_param("ii", $month, $year);
                $calc->execute();
                $result = $calc->get_result();
                $row = $result->fetch_assoc();
                $totalMonth = (float)($row['total_month'] ?? 0);
                $calc->close();
            
                // ✅ Update total_amount for that month
                $update = $conn->prepare("UPDATE expenses SET total_amount=? WHERE MONTH(timestamp)=? AND YEAR(timestamp)=?");
                $update->bind_param("dii", $totalMonth, $month, $year);
                $update->execute();
                $update->close();

                header("Location: expenses.php?msg=updated");
                exit;
            } else {
                $message = "❌ Update failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "❌ Prepare failed: " . $conn->error;
        }
    }
}

// Delete expense (GET)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id > 0) {
        if ($conn->query("DELETE FROM expenses WHERE id = $id")) {
            header("Location: expenses.php?msg=deleted");
            exit;
        } else {
            $message = "❌ Delete failed: " . $conn->error;
        }
    } else {
        $message = "⚠️ Invalid id to delete.";
    }
}

// Message alerts (after redirects return here)
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added': $message = "✅ Expense added successfully!"; break;
        case 'updated': $message = "✅ Expense updated successfully!"; break;
        case 'deleted': $message = "🗑️ Expense deleted successfully!"; break;
    }
}

// ---------------------
// FETCH / PAGINATION / GROUPING (still before output)
// ---------------------

// Filter
$filter = $_GET['filter'] ?? 'all';
$dateCondition = "";
switch ($filter) {
    case 'daily':   $dateCondition = "WHERE DATE(timestamp) = CURDATE()"; break;
    case 'weekly':  $dateCondition = "WHERE YEARWEEK(timestamp, 1) = YEARWEEK(CURDATE(), 1)"; break;
    case 'monthly': $dateCondition = "WHERE MONTH(timestamp) = MONTH(CURDATE()) AND YEAR(timestamp)=YEAR(CURDATE())"; break;
    case 'yearly':  $dateCondition = "WHERE YEAR(timestamp) = YEAR(CURDATE())"; break;
    default:        $dateCondition = "";
}

// Pagination
$limit = 16;
$page  = (isset($_GET['page']) && is_numeric($_GET['page'])) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// total count
$countSql = "SELECT COUNT(*) AS total FROM expenses $dateCondition";
$countResult = $conn->query($countSql);
$totalExpenses = 0;
if ($countResult) {
    $row = $countResult->fetch_assoc();
    $totalExpenses = intval($row['total'] ?? 0);
}
$totalPages = ($totalExpenses > 0) ? (int)ceil($totalExpenses / $limit) : 1;

// fetch rows with limit
$sql = "SELECT id, description, amount, timestamp, DATE_FORMAT(timestamp, '%M %Y') AS month_group
        FROM expenses $dateCondition
        ORDER BY timestamp DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$expensesByMonth = [];
if ($stmt) {
    $stmt->bind_param("ii", $limit, $offset); // safe: limit & offset as integers
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $month = $r['month_group'];
        if (!isset($expensesByMonth[$month])) $expensesByMonth[$month] = ['expenses' => [], 'total' => 0];
        $expensesByMonth[$month]['expenses'][] = $r;
        $expensesByMonth[$month]['total'] += (float)$r['amount'];
    }
    $stmt->close();
} else {
    // fallback without prepared statement (shouldn't happen)
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $month = $r['month_group'];
            if (!isset($expensesByMonth[$month])) $expensesByMonth[$month] = ['expenses' => [], 'total' => 0];
            $expensesByMonth[$month]['expenses'][] = $r;
            $expensesByMonth[$month]['total'] += (float)$r['amount'];
        }
    }
}

// ---------------------
// Now safe to include header/nav (no more redirects)
// ---------------------
include('inc/header.php');
include('inc/nav.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,viewport-fit=cover, initial-scale=1">
<title>Expenses</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f8f9fa;
    font-family: 'Segoe UI', sans-serif;
    padding-top: 60px;
}

/* ---------- Cards ---------- */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin: 0 auto 30px auto;
    transition: all 0.3s ease;
    margin-left: 190px;
}

/* ---------- Month Header ---------- */
.month-header {
    background: #ffffff;
    color: #2e2e2e;
    padding: 10px 15px;
    font-weight: 600;
    border-radius: 8px 8px 0 0;
    border: 1px solid #ddd;
    margin-bottom: 5px;
}

/* ---------- Buttons ---------- */
.btn-sm, .btn {
    border-radius: 6px;
    transition: all 0.3s ease;
}
.btn-sm:hover, .btn:hover {
    transform: translateY(-2px);
}

/* Add / Edit Buttons */
.btn-add {
    background-color: #5bc0de;
    color: #fff;
}
.btn-add:hover {
    background-color: #31b0d5;
}
.btn-edit {
    background-color: #f0ad4e;
    color: #fff;
}
.btn-edit:hover {
    background-color: #ec971f;
}
.btn-delete {
    background-color: #d9534f;
    color: #fff;
}
.btn-delete:hover {
    background-color: #c9302c;
}

/* ---------- Table Styling ---------- */
.table {
    border-collapse: separate;
    border-spacing: 0 0.5rem;
}
.table th, .table td {
    vertical-align: middle;
    border-radius: 6px;
}
.table-hover tbody tr:hover {
    background-color: teal;
    transition: 0.3s ease;
}
.table-success {
    background-color: #d4edda !important;
    color: #155724;
}

/* ---------- Pagination ---------- */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}
.pagination a, .pagination span {
    color: #2e2e2e;
    padding: 8px 12px;
    margin: 0 3px;
    border-radius: 5px;
    text-decoration: none;
    border: 1px solid #ddd;
    transition: all 0.3s ease;
}
.pagination a.active {
    background-color: #2e2e2e;
    color: #fff;
    border-color: #2e2e2e;
}
.pagination a:hover {
    background-color: #555;
    color: #fff;
}
.pagination span {
    color: #aaa;
}

/* ---------- Alerts ---------- */
.alert-message {
    max-width: 500px;
    margin: 10px auto;
    border-radius: 8px;
    text-align: center;
}

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
    .month-header { font-size: 0.95rem; padding: 8px 12px; }
    .btn-sm, .btn { font-size: 0.8rem; padding: 6px 10px; }
    .card { margin: 0 15px 20px 15px; }
}

@media (max-width: 576px) {
    .month-header { font-size: 0.9rem; padding: 6px 10px; }
    .btn-sm, .btn { font-size: 0.75rem; padding: 4px 8px; }
}
</style>

</head>
<body>

    <div class="container mt-5">
        <?php if (!empty($message)): ?>
        <div class="alert alert-info alert-message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Filter + Add button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-teal fw-bold" style="margin-left: 190px;">Expenses by Month</h4>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex">
                <select name="filter" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="daily" <?= $filter === 'daily' ? 'selected' : '' ?>>Today</option>
                    <option value="weekly" <?= $filter === 'weekly' ? 'selected' : '' ?>>This Week</option>
                    <option value="monthly" <?= $filter === 'monthly' ? 'selected' : '' ?>>This Month</option>
                    <option value="yearly" <?= $filter === 'yearly' ? 'selected' : '' ?>>This Year</option>
                </select>
            </form>
          <button class="btn fw-semibold" 
            data-bs-toggle="modal" 
            data-bs-target="#addExpenseModal"
            style="background-color: teal; color: #fff; border: none; 
                   border-radius: 8px; padding: 8px 16px; 
                   margin-left: 10px; /* added margin */
                   transition: all 0.3s ease;">
          + Add Expense
        </button>

        </div>
    </div>

    <!-- Expenses by month card -->
    <div class="card shadow mb-5">
        <div class="card-body">
            <?php if (!empty($expensesByMonth)): ?>
                <?php foreach ($expensesByMonth as $month => $data): ?>
                    <div class="month-card bg-white mb-4">
                        <div class="month-header"><?= htmlspecialchars($month) ?></div>
                        <div class="p-3">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-success text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Description</th>
                                        <th>Amount (₱)</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center">
                                    <?php $i = 1; foreach ($data['expenses'] as $exp): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($exp['description']) ?></td>
                                        <td><?= number_format($exp['amount'], 2) ?></td>
                                        <td><?= date('Y-m-d', strtotime($exp['timestamp'])) ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $exp['id'] ?>">Edit</button>
                                            <a href="?delete=<?= $exp['id'] ?>" onclick="return confirm('Delete this expense?');" class="btn btn-danger btn-sm">Delete</a>
                                        </td>
                                    </tr>

                                    <!-- Edit modal (one per item) -->
                                    <div class="modal fade" id="editModal<?= $exp['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header bg-warning">
                                                        <h5 class="modal-title text-dark">Edit Expense</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($exp['description']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Amount (₱)</label>
                                                            <input type="number" name="amount" step="0.01" class="form-control" value="<?= htmlspecialchars($exp['amount']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Date</label>
                                                            <input type="date" name="timestamp" class="form-control" value="<?= date('Y-m-d', strtotime($exp['timestamp'])) ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" name="edit_expense" class="btn" 
                                                            style="background-color: teal; color: #fff; border: none;">
                                                        Save Changes
                                                    </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>

                                    <tr class="table-light fw-bold">
                                        <td colspan="2">Total for <?= htmlspecialchars($month) ?></td>
                                        <td colspan="3">₱<?= number_format($data['total'], 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning text-center shadow-sm mb-0">No expenses found for this filter.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination d-flex justify-content-center mb-5">

    <!-- Previous Button -->
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&filter=<?= urlencode($filter) ?>">&laquo;</a>
    <?php else: ?>
        <span style="color:#ccc; padding:8px 12px;">&laquo;</span>
    <?php endif; ?>

    <?php
    // Limit visible pages to 5
    $maxLinks = 5;
    $start = max(1, $page - floor($maxLinks / 2));
    $end = min($totalPages, $start + $maxLinks - 1);
    
    // Adjust start if near the end
    if ($end - $start < $maxLinks - 1) {
        $start = max(1, $end - $maxLinks + 1);
    }
    ?>

    <!-- Page Numbers -->
    <?php for ($p = $start; $p <= $end; $p++): ?>
        <a href="?page=<?= $p ?>&filter=<?= urlencode($filter) ?>" class="<?= ($page == $p) ? 'active' : '' ?>">
            <?= $p ?>
        </a>
    <?php endfor; ?>

    <!-- Next Button -->
    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>&filter=<?= urlencode($filter) ?>">&raquo;</a>
    <?php else: ?>
        <span style="color:#ccc; padding:8px 12px;">&raquo;</span>
    <?php endif; ?>

</div>
<?php endif; ?>


<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <!-- Modal Header -->
                <div class="modal-header" style="background-color: #ffb74d; color: white;">
                    <h5 class="modal-title fw-bold">Add New Expense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Enter description" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₱)</label>
                        <input type="number" name="amount" step="0.01" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="timestamp" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                   <button type="submit" name="add_expense" class="btn" 
                        style="background-color: teal; color: #fff; border: none;">
                    Save Expense
                </button>

                </div>
            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
