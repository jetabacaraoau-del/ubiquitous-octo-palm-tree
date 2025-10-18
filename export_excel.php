<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header('location:login.php');
    exit;
}

$startDate = $_GET['start_date'] ?? '2025-01-01';
$endDate = $_GET['end_date'] ?? '2025-12-31';

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="dashboard_report.csv"');

$output = fopen('php://output', 'w');

// --- Report Title ---
fputcsv($output, ['DASHBOARD REPORT']);
fputcsv($output, ['Generated Date: ' . date('Y-m-d H:i:s')]);
fputcsv($output, ['This report provides a comprehensive overview of the business performance, including orders summary, top-selling products, category-wise sales, peak ordering times, monthly gross and net profits, and monthly sales growth percentages.']);
fputcsv($output, []);

// --- Section 1: Orders Summary ---
fputcsv($output, ['This section lists all orders placed during the reporting period. It includes the order ID, customer name, order status, and total price. Use this section to quickly review the volume and status of all transactions.']);
fputcsv($output, ['Order ID', 'Customer Name', 'Status', 'Total Price']);

$sqlOrders = "
    SELECT o.id, u.first_name, u.last_name, o.orderstatus, o.totalprice
    FROM orders o
    JOIN users u ON o.userid = u.id
    WHERE o.timestamp BETWEEN '$startDate' AND '$endDate'
    ORDER BY o.timestamp DESC
";
$resultOrders = mysqli_query($conn, $sqlOrders);
while ($row = mysqli_fetch_assoc($resultOrders)) {
    $fullname = $row['first_name'] . ' ' . $row['last_name'];
    fputcsv($output, [$row['id'], $fullname, $row['orderstatus'], $row['totalprice']]);
}

// --- Section 2: Top-Selling Products ---
fputcsv($output, []);
fputcsv($output, ['This section highlights the products that generated the highest sales volume and revenue during the reporting period. Useful for identifying best-performing products and stocking planning.']);
fputcsv($output, ['Product Name', 'Total Sold', 'Total Revenue']);

$sqlTopProducts = "
    SELECT p.product_name, SUM(oi.quantity) as total_sold, SUM(o.totalprice) as total_revenue
    FROM ordersitems oi
    JOIN products p ON oi.productid = p.product_id
    JOIN orders o ON oi.orderid = o.id
    WHERE o.timestamp BETWEEN '$startDate' AND '$endDate' AND o.orderstatus = 'delivered'
    GROUP BY oi.productid
    ORDER BY total_sold DESC
";
$resultTopProducts = mysqli_query($conn, $sqlTopProducts);
while ($row = mysqli_fetch_assoc($resultTopProducts)) {
    fputcsv($output, [$row['product_name'], $row['total_sold'], $row['total_revenue']]);
}

// --- Section 3: Sales by Category ---
fputcsv($output, []);
fputcsv($output, ['This section aggregates total quantities sold by product category. It helps understand category performance and can guide marketing and stocking strategies.']);
fputcsv($output, ['Category Name', 'Total Quantity Sold']);

$sqlCategorySales = "
    SELECT cat.cat_name, SUM(oi.quantity) AS total_sold
    FROM ordersitems oi
    JOIN orders o ON oi.orderid = o.id
    JOIN products p ON oi.productid = p.product_id
    JOIN category cat ON p.cat_id = cat.cat_id
    WHERE o.timestamp BETWEEN '$startDate' AND '$endDate'
      AND o.orderstatus = 'delivered'
    GROUP BY cat.cat_id
    ORDER BY total_sold DESC
";
$resultCategorySales = mysqli_query($conn, $sqlCategorySales);
while ($row = mysqli_fetch_assoc($resultCategorySales)) {
    fputcsv($output, [$row['cat_name'], $row['total_sold']]);
}

// --- Section 4: Peak Ordering Times ---
fputcsv($output, []);
fputcsv($output, ['This section identifies the day of the week and hour when most orders were placed. This information can help optimize staffing and marketing campaigns.']);
fputcsv($output, ['Most Frequent Day', 'Most Frequent Hour']);

$sqlPeakDay = "
    SELECT DAYNAME(timestamp) AS order_day, COUNT(*) AS total
    FROM orders
    WHERE timestamp BETWEEN '$startDate' AND '$endDate'
    GROUP BY order_day
    ORDER BY total DESC
    LIMIT 1
";
$sqlPeakHour = "
    SELECT HOUR(timestamp) AS order_hour, COUNT(*) AS total
    FROM orders
    WHERE timestamp BETWEEN '$startDate' AND '$endDate'
    GROUP BY order_hour
    ORDER BY total DESC
    LIMIT 1
";
$resultPeakDay = mysqli_query($conn, $sqlPeakDay);
$resultPeakHour = mysqli_query($conn, $sqlPeakHour);

$dayRow = mysqli_fetch_assoc($resultPeakDay);
$hourRow = mysqli_fetch_assoc($resultPeakHour);
$peakDay = $dayRow ? $dayRow['order_day'] : 'N/A';
$peakHour = $hourRow ? $hourRow['order_hour'] . ':00' : 'N/A';
fputcsv($output, [$peakDay, $peakHour]);

// --- Section 5: Monthly Gross and Net Profit ---
fputcsv($output, []);
fputcsv($output, ['This section shows the gross profit from delivered orders and calculates net profit by deducting labor costs per delivery day. It gives insights into overall profitability.']);
fputcsv($output, ['Month', 'Gross Profit', 'Net Profit']);

$dailyCost = 1000; // labor cost per delivery day
$sqlProfit = "
    SELECT 
        DATE_FORMAT(timestamp, '%Y-%m') AS month,
        SUM(totalprice) AS gross_profit,
        COUNT(DISTINCT DATE(timestamp)) AS delivery_days
    FROM orders
    WHERE LOWER(orderstatus) = 'delivered'
      AND timestamp BETWEEN '$startDate' AND '$endDate'
    GROUP BY month
    ORDER BY month ASC
";
$resultProfit = mysqli_query($conn, $sqlProfit);
while ($row = mysqli_fetch_assoc($resultProfit)) {
    $month = $row['month'];
    $gross = (float)$row['gross_profit'];
    $deliveryDays = (int)$row['delivery_days'];
    $net = $gross - ($deliveryDays * $dailyCost);
    fputcsv($output, [$month, $gross, $net]);
}

// --- Section 6: Monthly Sales Growth ---
fputcsv($output, []);
fputcsv($output, ['This section calculates the month-over-month sales growth percentages for delivered orders. Positive growth indicates higher sales than the previous month, while negative indicates a drop.']);
fputcsv($output, ['Month', 'Total Sales', 'Growth %']);

$sqlGrowth = "
    SELECT 
        DATE_FORMAT(timestamp, '%Y-%m') AS month,
        SUM(totalprice) AS total_sales
    FROM orders
    WHERE LOWER(orderstatus) = 'delivered'
      AND timestamp BETWEEN '$startDate' AND '$endDate'
    GROUP BY month
    ORDER BY month ASC
";
$resultGrowth = mysqli_query($conn, $sqlGrowth);
$prevSales = null;
while ($row = mysqli_fetch_assoc($resultGrowth)) {
    $month = $row['month'];
    $totalSales = (float)$row['total_sales'];
    $growth = 'N/A';
    if ($prevSales !== null && $prevSales > 0) {
        $growth = round((($totalSales - $prevSales) / $prevSales) * 100, 2) . '%';
    }
    fputcsv($output, [$month, $totalSales, $growth]);
    $prevSales = $totalSales;
}

fclose($output);
exit;
?>
