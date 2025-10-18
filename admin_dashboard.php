<?php
session_start();
include('../config/db.php');

$dateFilterType = $_GET['date_filter'] ?? 'monthly';
$selectedYear = $_GET['year'] ?? date('Y');
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';

// Default condition
$dateCondition = "1";
$dateFormat = '%Y-%m'; // default format for grouping

switch ($dateFilterType) {
    case 'daily':
        $dateCondition = "DATE(o.timestamp) = CURDATE()";
        $dateFormat = '%Y-%m-%d';
        break;

    case 'weekly':
        $dateCondition = "YEARWEEK(o.timestamp, 1) = YEARWEEK(CURDATE(), 1)";
        $dateFormat = '%x-W%v';
        break;

    case 'annually':
        $dateCondition = "1"; // all years
        $dateFormat = '%Y';
        break;

    case 'monthly':
    default:
        $dateCondition = "YEAR(o.timestamp) = $selectedYear";
        $dateFormat = '%Y-%m';
        break;
}

// ✅ Override with manual start/end date if both are set
if (!empty($startDate) && !empty($endDate)) {
    $dateCondition = "DATE(o.timestamp) BETWEEN '$startDate' AND '$endDate'";
}

// Category filter
// Define selected category ID from GET parameter (with default)
$selectedCategoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Now you can safely build the condition
$categoryCondition = ($selectedCategoryId > 0) ? "p.cat_id = $selectedCategoryId" : "1";

$sql = "
    SELECT o.*, oi.*, p.*, c.cat_name
    FROM orders o
    JOIN ordersitems oi ON o.id = oi.orderid
    JOIN products p ON oi.productid = p.product_id
    JOIN category c ON p.cat_id = c.cat_id
    WHERE o.orderstatus = 'delivered'
      AND $dateCondition
      AND $categoryCondition
";

// Execute query dito gamit mysqli or PDO
$result = mysqli_query($conn, $sql);


// === DAILY PRODUCT-WISE PROFIT ===
$sql = "
    SELECT 
        p.product_name,
        DATE(o.timestamp) AS order_date,
        SUM(oi.quantity * oi.productprice) AS profit
    FROM orders o
    JOIN ordersitems oi ON o.id = oi.orderid
    JOIN products p ON oi.productid = p.product_id
    WHERE o.orderstatus = 'delivered' AND $dateCondition
    GROUP BY p.product_name, order_date
    ORDER BY order_date, p.product_name
";
$res = $conn->query($sql);

$data = $dates = [];
while ($row = $res->fetch_assoc()) {
    $product = $row['product_name'];
    $date    = $row['order_date'];
    $profit  = (float)$row['profit'];

    $dates[$date] = true;
    $data[$product][$date] = $profit;
}
$dates = array_keys($dates);
sort($dates);

// === FORMAT FOR CHART.JS ===
$datasets = [];
$colors = ['#4dc9f6', '#f67019', '#f53794', '#537bc4', '#acc236', '#166a8f', '#00a950', '#58595b', '#8549ba'];
foreach ($data as $product => $profits) {
    $datasets[] = [
        'label' => $product,
        'data' => array_map(fn($d) => $profits[$d] ?? 0, $dates),
        'borderColor' => $colors[array_rand($colors)],
        'fill' => false,
        'tension' => 0.1
    ];
}

// === DAILY SALES & PROFIT PER PRODUCT ===
$sql = "
    SELECT 
        p.product_name,
        DATE(o.timestamp) AS date,
        SUM(oi.quantity) AS total_sold,
        SUM(oi.quantity * oi.productprice) AS total_profit
    FROM ordersitems oi
    JOIN orders o ON oi.orderid = o.id
    JOIN products p ON oi.productid = p.product_id
    WHERE o.orderstatus = 'delivered' AND $dateCondition
    GROUP BY p.product_name, date
    ORDER BY p.product_name, date
";
$res = $conn->query($sql);
$productData = $dateLabels = [];

while ($row = $res->fetch_assoc()) {
    $product = $row['product_name'];
    $date = $row['date'];

    if (!in_array($date, $dateLabels)) {
        $dateLabels[] = $date;
    }

    $productData[$product][$date] = [
        'total_sold'   => (int)$row['total_sold'],
        'total_profit' => (float)$row['total_profit']
    ];
}
sort($dateLabels);



// === Initialize monthly sales array for all 12 months ===
$monthlySales = array_fill(1, 12, 0);

// === Fetch monthly sales data (only delivered orders) ===
$sql = "
    SELECT DATE_FORMAT(o.timestamp, '%Y-%m') AS ym, SUM(o.totalprice) * 0.5 AS total
    FROM orders o
    WHERE o.orderstatus = 'delivered'
    GROUP BY ym
    ORDER BY ym
";


$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $monthlySales[(int)$row['month']] = (float)$row['total'];
}

// === Step 1: Build Monthly Sales by Year & Month ===
$monthlySales = [];

$sql = "
    SELECT DATE_FORMAT(o.timestamp, '$dateFormat') AS period, SUM(o.totalprice) * 0.5 AS total
    FROM orders o
    WHERE o.orderstatus = 'delivered' AND $dateCondition
    GROUP BY period
    ORDER BY period
";


$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $period = $row['period'];
    $monthlySales[$period] = (float)$row['total'];
}

// === Step 1: Build Monthly Sales by Year & Month (at 50% of totalprice) ===
$monthlySales = [];

$sql = "
    SELECT DATE_FORMAT(o.timestamp, '%Y-%m') AS ym, SUM(o.totalprice) AS total
    FROM orders o
    WHERE o.orderstatus = 'delivered'
    GROUP BY ym
    ORDER BY ym
";

$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $ym = $row['ym']; // format: YYYY-MM
        $monthlySales[$ym] = (float)$row['total'] * 0.5; // Apply 50% adjustment
    }
} else {
    die("Query failed: " . $conn->error);
}

// === Step 2: Calculate Month-over-Month Sales Growth ===
$salesGrowth = [];
$highlightedGrowth = [];
$months = array_keys($monthlySales);

for ($i = 1; $i < count($months); $i++) {
    $prevMonth = $months[$i - 1];
    $currMonth = $months[$i];
    $prev = $monthlySales[$prevMonth];
    $curr = $monthlySales[$currMonth];

    if ($prev > 0) {
        $percent = round((($curr - $prev) / $prev) * 100, 2);
        $salesGrowth[$currMonth] = $percent;

        if ($percent >= 10) {
            $highlightedGrowth[$currMonth] = $percent;
        }
    } else {
        $salesGrowth[$currMonth] = ($curr > 0) ? 100 : 0;

        if ($curr > 0) {
            $highlightedGrowth[$currMonth] = 100;
        }
    }
}

// === Step 3: Current vs Last Month KPI ===
$lastIndex = count($months) - 1;
$currentMonthKey = $months[$lastIndex] ?? null;
$lastMonthKey = $months[$lastIndex - 1] ?? null;

$currentMonthSales = $monthlySales[$currentMonthKey] ?? 0;
$lastMonthSales = $monthlySales[$lastMonthKey] ?? 0;

if ($lastMonthSales > 0) {
    $growthPercent = round((($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100, 2);
} else {
    $growthPercent = ($currentMonthSales > 0) ? 100 : 0;
}

// === Step 4: Minimum Growth Display ===
if ($currentMonthSales >= 50000) {
    $minDisplayGrowth = 20;
} elseif ($currentMonthSales >= 20000) {
    $minDisplayGrowth = 15;
} else {
    $minDisplayGrowth = 10;
}

if ($growthPercent > 0 && $growthPercent < $minDisplayGrowth) {
    $displayGrowthPercent = $minDisplayGrowth;
} else {
    $displayGrowthPercent = $growthPercent;
}

$growthClass = ($displayGrowthPercent >= 0) ? 'text-success' : 'text-danger';

// === Step 5: Chart Data Preparation ===
$salesGrowthLabels = [];
$salesGrowthData = [];
$barColors = [];

foreach ($monthlySales as $ym => $amount) {
    $salesGrowthLabels[] = date('M Y', strtotime($ym));
    $salesGrowthData[] = $amount;

    if (isset($salesGrowth[$ym]) && $salesGrowth[$ym] >= 10) {
        $barColors[] = 'rgba(75, 192, 192, 0.7)'; // High growth
    } elseif (isset($salesGrowth[$ym]) && $salesGrowth[$ym] < 0) {
        $barColors[] = 'rgba(255, 99, 132, 0.7)'; // Decline
    } else {
        $barColors[] = 'rgba(255, 206, 86, 0.7)'; // Neutral
    }
}


// === Step 6: Detect Growth Trend Direction ===
$growthTrendDirection = 'stable';
$positiveStreak = 0;
$negativeStreak = 0;

foreach ($salesGrowth as $percent) {
    if ($percent > 0) {
        $positiveStreak++;
        $negativeStreak = 0;
    } elseif ($percent < 0) {
        $negativeStreak++;
        $positiveStreak = 0;
    } else {
        $positiveStreak = 0;
        $negativeStreak = 0;
    }

    if ($positiveStreak >= 3) {
        $growthTrendDirection = 'rising';
        break;
    } elseif ($negativeStreak >= 3) {
        $growthTrendDirection = 'declining';
        break;
    }
}

// === TODAY'S PROFIT ===
$todayProfit = (float)($conn->query("
    SELECT SUM(totalprice) AS today_profit
    FROM orders
    WHERE DATE(timestamp) = CURDATE() AND orderstatus = 'delivered'
")->fetch_assoc()['today_profit'] ?? 0);
$todayProfitFormatted = number_format($todayProfit, 2);

// === DELIVERY DAYS & LABOR COST ===
$deliveryDays = (int)($conn->query("
    SELECT COUNT(DISTINCT DATE(o.timestamp)) AS delivery_days
    FROM orders o 
    WHERE o.orderstatus = 'delivered' AND $dateCondition
")->fetch_assoc()['delivery_days'] ?? 0);

// 1. Count delivery days
$deliveryDaysQuery = "
    SELECT COUNT(DISTINCT DATE(o.timestamp)) AS delivery_days
    FROM orders o
    WHERE o.orderstatus = 'delivered' AND $dateCondition
";
$deliveryDaysResult = $conn->query($deliveryDaysQuery);
$deliveryDays = (int)($deliveryDaysResult->fetch_assoc()['delivery_days'] ?? 0);

// 2. Total revenue
$deliveredRevenueQuery = "
    SELECT SUM(o.totalprice) AS total_revenue
    FROM orders o
    WHERE o.orderstatus = 'delivered' AND $dateCondition
";
$revenueResult = $conn->query($deliveredRevenueQuery);
$totalRevenue = (float)($revenueResult->fetch_assoc()['total_revenue'] ?? 0);

// 3. Expenses from database (separated by YEAR + MONTH)
$monthlyExpenses = []; 
$expensesQuery = "
    SELECT 
        YEAR(timestamp) AS y,
        MONTH(timestamp) AS m,
        MAX(total_amount) AS total_amount
    FROM expenses
    GROUP BY YEAR(timestamp), MONTH(timestamp)
";
$expensesResult = $conn->query($expensesQuery);
while ($row = $expensesResult->fetch_assoc()) {
    $year  = (int)$row['y'];
    $month = (int)$row['m'];
    $monthlyExpenses["{$year}-{$month}"] = (float)$row['total_amount'];
}

// 4. Monthly gross profit (based on delivered revenue)
$monthlyGrossProfit = [];
$sql = "
    SELECT YEAR(o.timestamp) AS year, MONTH(o.timestamp) AS month, SUM(o.totalprice) AS revenue
    FROM orders o
    WHERE o.orderstatus = 'delivered' AND $dateCondition
    GROUP BY YEAR(o.timestamp), MONTH(o.timestamp)
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $year  = (int)$row['year'];
    $month = (int)$row['month'];
    $monthlyGrossProfit["{$year}-{$month}"] = (float)$row['revenue'];
}

// 5. Monthly net profit — now year-specific
$monthlyNetProfit = [];
foreach ($monthlyGrossProfit as $key => $gross) {
    $expense = $monthlyExpenses[$key] ?? 0;
    $monthlyNetProfit[$key] = max(0, $gross - $expense);
}

// 6. Total expenses (sum by unique year-month)
$totalExpensesQuery = "
    SELECT SUM(total_amount) AS total_expenses
    FROM (
        SELECT 
            YEAR(timestamp) AS y,
            MONTH(timestamp) AS m,
            MAX(total_amount) AS total_amount
        FROM expenses
        GROUP BY YEAR(timestamp), MONTH(timestamp)
    ) AS unique_months
";
$totalExpensesResult = $conn->query($totalExpensesQuery);
$totalExpenses = (float)($totalExpensesResult->fetch_assoc()['total_expenses'] ?? 0);

// 7. Gross and Net totals
$grossProfit = $totalRevenue;
$netProfit   = max(0, $grossProfit - $totalExpenses);

$grossProfitFormatted = number_format($grossProfit, 2);
$netProfitFormatted   = number_format($netProfit, 2);

// 8. Daily profit chart (use correct year-month expense per day)
$profitLabels = [];
$grossProfits = [];
$netProfits   = [];

$sql = "
    SELECT DATE(o.timestamp) AS day, SUM(o.totalprice) AS revenue
    FROM orders o
    WHERE o.orderstatus = 'delivered' AND $dateCondition
    GROUP BY day
    ORDER BY day
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $day = $row['day'];
    $revenue = (float)$row['revenue'];

    $year  = (int)date('Y', strtotime($day));
    $month = (int)date('n', strtotime($day));
    $expenseKey = "{$year}-{$month}";

    $monthlyExpense = $monthlyExpenses[$expenseKey] ?? 0;

    $gross = $revenue;
    $net   = max(0, $revenue - $monthlyExpense);

    $profitLabels[] = $day;
    $grossProfits[] = round($gross, 2);
    $netProfits[]   = round($net, 2);
}

// 9. Product-wise profit
$productProfits = [];
$sql = "
    SELECT p.product_name, SUM(oi.quantity * oi.productprice) AS gross
    FROM ordersitems oi
    JOIN orders o ON oi.orderid = o.id
    JOIN products p ON oi.productid = p.product_id
    WHERE o.orderstatus = 'delivered' AND $dateCondition
    GROUP BY p.product_name
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $product = $row['product_name'];
    $gross = (float)$row['gross'];
    $productProfits[$product] = [
        'gross' => number_format($gross, 2),
        'net'   => number_format($gross * 0.8, 2),
    ];
}

// 10. Dashboard stats
$dashboardStats = [
    'grossProfit'      => $grossProfitFormatted,
    'netProfit'        => $netProfitFormatted,
    'totalExpenses'    => number_format($totalExpenses, 2),
    'labels'           => $profitLabels,
    'grossChartData'   => $grossProfits,
    'netChartData'     => $netProfits,
    'productProfits'   => $productProfits,
    'monthlyGross'     => $monthlyGrossProfit,
    'monthlyNet'       => $monthlyNetProfit,
];

// === PRODUCT SALES TREND DATA ===
$productSalesDatasets = [];
foreach ($productData as $product => $datesData) {
    $sold = [];
    $profit = [];
    foreach ($dateLabels as $d) {
        $sold[] = $datesData[$d]['total_sold'] ?? 0;
        $profit[] = $datesData[$d]['total_profit'] ?? 0;
    }

    $productSalesDatasets[] = [
        'label' => $product,
        'data' => $sold,
        'borderColor' => $colors[array_rand($colors)],
        'fill' => false,
        'tension' => 0.1,
        'profitData' => $profit
    ];
}
// === TOTAL PRODUCTS SOLD ===
$totalProductsSoldRow = $conn->query("
    SELECT SUM(oi.quantity) AS total_products_sold
    FROM ordersitems oi
    JOIN orders o ON oi.orderid = o.id
    WHERE o.orderstatus = 'delivered' AND $dateCondition
")->fetch_assoc();

$totalProductsSold = (int)($totalProductsSoldRow['total_products_sold'] ?? 0);

// === MONTHLY SALES TOTALS ===
$monthlySales = array_fill(1, 12, 0);
$sql = "
    SELECT MONTH(o.timestamp) AS month, SUM(o.totalprice) AS total
    FROM orders o
    WHERE $dateCondition AND o.orderstatus = 'delivered'
    GROUP BY MONTH(o.timestamp)
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $monthlySales[(int)$row['month']] = (float)$row['total'];
}

// AOV Query
$sql = "
    SELECT 
        SUM(o.totalprice) / COUNT(o.id) AS overall_aov
    FROM orders o
    WHERE o.orderstatus = 'delivered' AND $dateCondition
";
$res = $conn->query($sql);
$row = $res->fetch_assoc();
$overallAOV = number_format((float)$row['overall_aov'], 2);

// === TOP PRODUCT SALES (HIGHEST REVENUE) ===
$sql = "
    SELECT 
        p.product_name,
        SUM(oi.quantity) AS total_quantity,
        SUM(oi.quantity * oi.productprice) AS total_revenue
    FROM ordersitems oi
    JOIN orders o ON oi.orderid = o.id
    JOIN products p ON oi.productid = p.product_id
    WHERE o.orderstatus = 'delivered' AND $dateCondition
    GROUP BY p.product_name
    ORDER BY total_revenue DESC
    LIMIT 2
";
$result = $conn->query($sql);
$topProduct = $result->fetch_assoc();

$topProductName = $topProduct['product_name'] ?? 'N/A';
$topProductRevenue = isset($topProduct['total_revenue']) ? number_format((float)$topProduct['total_revenue'], 2) : '0.00';
$topProductQty = $topProduct['total_quantity'] ?? 0;

$categoryCondition = $selectedCategoryId > 0 ? "p.cat_id = $selectedCategoryId" : "1";

$sql = "
    SELECT 
        p.product_name,
        SUM(oi.quantity) AS total_sold,
        SUM(oi.quantity * oi.productprice) AS total_profit
    FROM orders o
    JOIN ordersitems oi ON o.id = oi.orderid
    JOIN products p ON oi.productid = p.product_id
    WHERE o.orderstatus = 'delivered'
      AND $dateCondition
      AND $categoryCondition
    GROUP BY p.product_id
    ORDER BY total_sold DESC
    LIMIT 10
";

$res = $conn->query($sql);

$labels = $totalSold = $totalProfit = [];
while ($row = $res->fetch_assoc()) {
    $labels[] = $row['product_name'];
    $totalSold[] = (int)$row['total_sold'];
    $totalProfit[] = (float)$row['total_profit'];
}

$salesPerDay = $conn->query("
    SELECT DATE(timestamp) as day, SUM(totalprice) as total_sales
    FROM orders
    WHERE timestamp >= CURDATE() - INTERVAL 7 DAY
    GROUP BY DATE(timestamp)
    ORDER BY day ASC
");

$connection = new mysqli('localhost', 'u756235277_seventeasdiner', 'Seventeasdiner#2025', 'u756235277_shopping_cart');
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Query to get top 10 delivered products
$productQuery = "
    SELECT 
        p.product_name,
        SUM(oi.quantity) AS total_quantity_sold,
        SUM(oi.quantity * oi.productprice) AS total_sales
    FROM ordersitems oi
    JOIN products p ON oi.productid = p.product_id
    JOIN orders o ON oi.orderid = o.id
    JOIN category c ON p.cat_id = c.cat_id
    WHERE o.orderstatus = 'delivered'
      AND $dateCondition
      AND $categoryCondition
    GROUP BY p.product_name
    ORDER BY total_sales DESC
    LIMIT 10
";
$productResult = $connection->query($productQuery);

$productNames = [];
$productQuantities = [];
$productSales = [];

while ($row = $productResult->fetch_assoc()) {
    $productNames[] = $row['product_name'];
    $productQuantities[] = (int)$row['total_quantity_sold'];
    $productSales[] = (float)$row['total_sales'];
}

$sql = "
    SELECT 
        c.cat_name,
        SUM(oi.quantity * oi.productprice) AS total_sales
    FROM orders o
    JOIN ordersitems oi ON o.id = oi.orderid
    JOIN products p ON oi.productid = p.product_id
    JOIN category c ON p.cat_id = c.cat_id
    WHERE o.orderstatus = 'delivered'
      AND $dateCondition
      AND $categoryCondition
    GROUP BY c.cat_name
    ORDER BY total_sales DESC
";

$categoryResult = $connection->query($sql);


$categoryNames = [];
$categorySales = [];

while ($row = $categoryResult->fetch_assoc()) {
    $categoryNames[] = $row['cat_name'];
    $categorySales[] = (float)$row['total_sales'];
}

$segmentationQuery = "
    SELECT 
        o.userid,
        COUNT(*) AS total_orders,
        SUM(oi.quantity * oi.productprice) AS total_spent
    FROM orders o
    JOIN ordersitems oi ON o.id = oi.orderid
    WHERE o.orderstatus = 'delivered'
    AND $dateCondition
    GROUP BY o.userid
";

$segmentationResult = $conn->query($segmentationQuery);

$frequentCustomers = 0;
$newCustomers = 0;
$highValueCustomers = 0;

while ($row = $segmentationResult->fetch_assoc()) {
    if ($row['total_orders'] == 1) {
        $newCustomers++;
    }
    if ($row['total_orders'] >= 3) {
        $frequentCustomers++;
    }
    if ($row['total_spent'] >= 1000) {
        $highValueCustomers++;
    }
}

$sqlAOVChart = "SELECT DATE(o.timestamp) AS order_date, 
                       ROUND(AVG(o.totalprice), 2) AS avg_order
                FROM orders o
                WHERE o.orderstatus = 'delivered' AND $dateCondition
                GROUP BY DATE(o.timestamp)
                ORDER BY order_date";
$resultAOVChart = mysqli_query($conn, $sqlAOVChart);

$labelsAOV = [];
$dataAOV = [];

while ($row = mysqli_fetch_assoc($resultAOVChart)) {
    $labelsAOV[] = $row['order_date'];
    $dataAOV[] = $row['avg_order'];
}


// Build the combined condition (you must have $dateCondition and $categoryCondition defined earlier)
$combinedCondition = "AND $dateCondition AND $categoryCondition";

// Now fix the SQL using that
$sqlPeakDay = "
    SELECT DAYNAME(o.timestamp) as day, COUNT(*) as count 
    FROM orders o
    WHERE orderstatus = 'delivered' $combinedCondition 
    GROUP BY day 
    ORDER BY count DESC 
    LIMIT 1
";

$resultPeakDay = mysqli_query($conn, $sqlPeakDay);
$peakDay = $resultPeakDay && mysqli_num_rows($resultPeakDay) > 0
    ? mysqli_fetch_assoc($resultPeakDay)['day']
    : 'N/A';

// Remove any 'o.' in case it's from aliased filters
$cleanDateCondition = str_replace('o.', '', $dateCondition);
$combinedCondition = "AND $cleanDateCondition AND $categoryCondition";

// Corrected SQL query for peak hour
$sqlPeakHour = "
    SELECT HOUR(timestamp) as hour, COUNT(*) as count 
    FROM orders 
    WHERE orderstatus = 'delivered' $combinedCondition 
    GROUP BY hour 
    ORDER BY count DESC 
    LIMIT 1
";

$resultPeakHour = mysqli_query($conn, $sqlPeakHour);
$hourData = $resultPeakHour && mysqli_num_rows($resultPeakHour) > 0 ? mysqli_fetch_assoc($resultPeakHour) : null;

// Format the peak hour to something like "3 PM"
$peakHour = $hourData ? date('g A', mktime($hourData['hour'])) : 'N/A';

// --- Predictive Analytics for Sales --- //
$query = "
    SELECT 
        DATE_FORMAT(o.timestamp, '%Y-%m') AS month, 
        SUM(oi.quantity) AS total_sold
    FROM orders o
    JOIN ordersitems oi ON o.id = oi.orderid
    WHERE o.orderstatus = 'delivered'
    GROUP BY YEAR(o.timestamp), MONTH(o.timestamp)
    ORDER BY YEAR(o.timestamp), MONTH(o.timestamp)
";
$result = mysqli_query($conn, $query);

$months = [];
$sales = [];

while ($row = mysqli_fetch_assoc($result)) {
    $months[] = $row['month'];
    $sales[] = (int)$row['total_sold'];
}

// Fill missing months for continuity
if (count($months) > 0) {
    $start = new DateTime($months[0]);
    $end = new DateTime(end($months));
    $filledMonths = [];
    $filledSales = [];

    while ($start <= $end) {
        $currentMonth = $start->format('Y-m');
        $index = array_search($currentMonth, $months);
        $filledMonths[] = $currentMonth;
        $filledSales[] = ($index !== false) ? $sales[$index] : 0;
        $start->modify('+1 month');
    }

    $months = $filledMonths;
    $sales = $filledSales;
}

// Filter logic
$filter = $_GET['filter'] ?? 'monthly';
$doPrediction = in_array($filter, ['monthly', 'annually']);

$n = count($sales);
$predictedMonths = [];
$predictedSales = [];

if ($doPrediction && $n >= 1) {
    $x = range(1, $n);
    $y = $sales;

    if ($n == 1) {
        $m = 0;
        $b = $y[0];
    } else {
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];
        }

        $m = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $b = ($sumY - $m * $sumX) / $n;
    }

    // Predict next months (continuous from last)
    $lastDate = new DateTime(end($months));
    $numFutureMonths = 6;

    for ($i = 1; $i <= $numFutureMonths; $i++) {
        $lastDate->modify('+1 month');
        $predictedMonths[] = $lastDate->format('Y-m');
        $nextX = $n + $i;
        $predictedSales[] = max(0, round($m * $nextX + $b));
    }
}

// Combine into single timeline
$allMonths = array_merge($months, $predictedMonths);
$actualSales = array_merge($sales, array_fill(0, count($predictedMonths), null)); // keep null for predicted range
$predictedLine = array_merge(array_fill(0, count($months), null), $predictedSales);

// --- Monthly Breakdown Expenses (by Description) --- //
$monthlyExpenses = [];
$descriptions = [];

// use filter variables already defined earlier
$dateField = "e.timestamp";
$dateCondition = "1";
$dateFormat = '%Y-%m'; // default for monthly

switch ($dateFilterType) {
    case 'daily':
        $dateCondition = "DATE($dateField) = CURDATE()";
        $dateFormat = '%Y-%m-%d';
        break;
    case 'weekly':
        $dateCondition = "YEARWEEK($dateField, 1) = YEARWEEK(CURDATE(), 1)";
        $dateFormat = '%x-W%v';
        break;
    case 'annually':
        $dateCondition = "1"; // all years
        $dateFormat = '%Y';
        break;
    case 'monthly':
    default:
        $dateCondition = "YEAR($dateField) = $selectedYear";
        $dateFormat = '%Y-%m';
        break;
}

// ✅ If date range filter is set
if (!empty($startDate) && !empty($endDate)) {
    $dateCondition = "DATE($dateField) BETWEEN '$startDate' AND '$endDate'";
}

$sql = "
    SELECT 
        DATE_FORMAT(e.timestamp, '$dateFormat') AS period_label,
        e.description,
        SUM(e.amount) AS total_amount
    FROM expenses e
    WHERE e.timestamp IS NOT NULL AND $dateCondition
    GROUP BY DATE_FORMAT(e.timestamp, '$dateFormat'), e.description
    ORDER BY MIN(e.timestamp)
";
$result = $conn->query($sql);

$monthlyExpenses = [];
$descriptions = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if (empty($row['period_label'])) continue; // prevent 1970
        $label = $row['period_label'];
        $desc = $row['description'];
        $amount = (float)$row['total_amount'];

        $monthlyExpenses[$label][$desc] = $amount;
        $descriptions[$desc] = true;
    }
}

$months = array_keys($monthlyExpenses);
$descriptions = array_keys($descriptions);

// ✅ Build datasets for Chart.js
$datasets = [];
$colors = [
    '#ffb74d', '#4db6ac', '#9575cd', '#f06292',
    '#64b5f6', '#81c784', '#ba68c8', '#ff8a65',
    '#7986cb', '#a1887f', '#e57373', '#4fc3f7'
];

foreach ($descriptions as $i => $desc) {
    $data = [];
    foreach ($months as $month) {
        $data[] = $monthlyExpenses[$month][$desc] ?? 0;
    }

    $datasets[] = [
        'label' => $desc,
        'data' => $data,
        'backgroundColor' => $colors[$i % count($colors)],
        'borderColor' => $colors[$i % count($colors)],
        'borderWidth' => 1,
        'borderRadius' => 6,
        'hoverBackgroundColor' => $colors[$i % count($colors)],
    ];
}

?>

<?php include('inc/header.php'); ?>
<?php include('inc/nav.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
<style>
/* General Body & Typography */
body {
    margin-left: 190px;
  background: #f9f9f9;
  font-family: 'Segoe UI', sans-serif;
  color: #333;
}

h2 {
  font-size: 2.5rem;
  font-weight: 600;
  color: #343a40;
  margin-bottom: 10.5rem;
}

/* Unified KPI & Report Cards Layout */
.kpi-card, .report-card, .bg-custom-top-product {
  background: #ffffff;
  color: #333;
  border-radius: 1rem;
  padding: 1.25rem;
  min-height: 160px;        /* same height for all cards */
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  border: 1px solid #e0e0e0;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
  text-align: center;
  transition: all 0.3s ease-in-out;
}

.kpi-card:hover, .report-card:hover, .bg-custom-top-product:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08);
}

/* KPI Value */
.kpi-value {
  font-size: 1.8rem;
  font-weight: 700;
  margin: 0.5rem 0;
  color: #333;
}

/* Card Titles */
.card-title {
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
  color: #444;
}

/* Small gray text */
.text-muted, .small {
  color: #6c757d !important;
  font-size: 0.85rem;
}

/* Smaller Top Product Card */
.bg-custom-top-product {
  min-height: 50px;        /* smaller than other KPI cards */
  padding: 0.75rem 1rem;    /* reduced padding */
  border-radius: 0.75rem;   /* slightly smaller rounded corners */
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  border: 1px solid #e0e0e0;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  background: #ffffff;
  color: #333;
  transition: all 0.3s ease-in-out;
}

.bg-custom-top-product:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}

.bg-custom-top-product .card-header {
  font-size: 0.95rem;      /* smaller header */
  font-weight: 600;
}

.bg-custom-top-product .card-title {
  font-size: 0.95rem;      /* smaller title */
}

.bg-custom-top-product .card-text {
  font-size: 1rem;       /* smaller text */
}

/* Buttons */
.btn-primary {
  background-color: #007bff;
  border: none;
  font-weight: 600;
}

.btn-primary:hover {
  background-color: #0056b3;
}

/* Text Colors */
.text-dark { color: #212529 !important; }
.text-success { color: #28a745 !important; }
.text-danger { color: #dc3545 !important; }

/* Chart Container */
.chart-container {
  background: #fff;
  padding: 25px;
  border-radius: 20px;
  box-shadow: 0 5px 25px rgba(0, 0, 0, 0.05);
}

/* Responsive Typography */
@media (max-width: 768px) {
  .kpi-value { font-size: 1.5rem; }
  .card-title { font-size: 1rem; }
  .kpi-card, .report-card, .bg-custom-top-product {
    padding: 1rem;
    min-height: 140px;
  }
}

/* Footer */
.footer {
  text-align: center;
  font-size: 0.9rem;
  color: #aaa;
  margin-top: 50px;
}

.custom-btn {
    background: linear-gradient(180deg, #4fd1c5, #38b2ac); /* teal gradient */
    color: #333333; /* charcoal text */
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
    font-weight: 500;
    transition: 0.3s ease;
}

.custom-btn:hover {
    opacity: 0.85; /* subtle hover effect */
}
</style>

</head>

<body>

  <!-- Main Content -->
  <div class="main-content">

    <div class="container py-5">
      <h2 class="text-center mb-2">Admin Dashboard</h2>

        <a href="export_excel.php?year=2025&start_date=2025-01-01&end_date=2025-12-31" class="btn custom-btn">
            Export to Excel (.XLSX)
        </a>


      <div class="row g-4">
        <!-- Today's Profit -->
        <div class="col-md-3">
          <div class="kpi-card elegant-card">
            <div class="text-muted fw-bold fs-5">Today's Sale</div>
            <div class="kpi-value">₱<?= number_format($todayProfit, 2) ?></div>
          </div>
        </div>

        <!-- Gross Profit -->
        <div class="col-md-3">
          <div class="kpi-card elegant-card">
            <div class="text-muted fw-bold fs-5">Gross Profit</div>
            <div class="kpi-value">₱<?= $grossProfitFormatted ?></div>
          </div>
        </div>

        <!-- Net Profit -->
        <div class="col-md-3">
          <div class="kpi-card elegant-card">
            <div class="text-muted fw-bold fs-5">Net Profit</div>
            <div class="kpi-value">₱<?= $netProfitFormatted ?></div>
          </div>
        </div>

        <!-- Sales Growth -->
    <div class="col-md-3">
      <div class="kpi-card elegant-card text-center">
        <div class="text-muted fw-bold fs-5">Sales Growth</div>
    
        <!-- Main growth percentage with color -->
        <div class="kpi-value <?= $growthClass ?>">
          <?= ($displayGrowthPercent >= 0 ? '▲ +' : '▼ ') . number_format(abs($displayGrowthPercent), 2) ?>%
        </div>
    
        <div class="small text-muted">
          vs <?= date('F', strtotime('first day of last month')) ?>
        </div>
    
        <?php
          // Determine trend color based on growth
          $trendColor = ($growthPercent >= 0) ? 'text-success' : 'text-danger';
          $trendIcon  = ($growthPercent >= 0) ? '📈' : '📉';
          $trendText  = ($growthPercent >= 0) ? 'Rising this month' : 'Falling this month';
        ?>
    
        <div class="<?= $trendColor ?> small mt-1"><?= $trendIcon ?> <?= $trendText ?></div>
      </div>
    </div>


      <!-- Customer Stats -->
      <div class="row g-4 mt-2">
        <div class="col-md-4">
          <div class="kpi-card elegant-card text-center">
            <div class="text-muted fw-bold fs-5">New Customers</div>
            <div class="kpi-value text-primary"><?= $newCustomers ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="kpi-card elegant-card text-center">
            <div class="text-muted fw-bold fs-5">Frequent Customers (3+ orders)</div>
            <div class="kpi-value text-success"><?= $frequentCustomers ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="kpi-card elegant-card text-center">
            <div class="text-muted fw-bold fs-5">High-Value Customers (₱1000+)</div>
            <div class="kpi-value text-warning"><?= $highValueCustomers ?></div>
          </div>
        </div>
      </div>

      <!-- Top Product / Peak Day / Peak Hour -->
      <div class="row g-4 mt-4">
        <div class="col-sm-6 col-lg-4">
          <div class="card bg-custom-top-product shadow-sm rounded-4 h-100">
            <div class="card-header fs-5" style="font-weight: 700;">Top Product</div>
            <div class="card-body">
              <h5 class="card-title"><?= htmlspecialchars($topProductName) ?></h5>
              <p class="card-text">Sold: <?= $topProductQty ?> pcs</p>
              <p class="card-text">Sales: ₱<?= $topProductRevenue ?></p>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-4">
          <div class="kpi-card elegant-card text-center">
            <div class="card-title fw-bold fs-5">Peak Ordering Day</div>
            <h2 class="kpi-value text-success"><?= $peakDay ?></h2>
            <div class="text-muted small">Busiest day by order count</div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-4">
          <div class="kpi-card elegant-card text-center">
            <div class="card-title fw-bold fs-5">Peak Ordering Hour</div>
            <h2 class="kpi-value text-danger"><?= $peakHour ?></h2>
            <div class="text-muted small">Time with most orders</div>
          </div>
        </div>
      </div>

      <!-- Date Filter -->
        <form method="GET" class="row g-3 align-items-center mt-4 mb-4">
          <div class="col-auto">
            <label for="date_filter" class="form-label visually-hidden">Date Filter</label>
            <select name="date_filter" id="date_filter" class="form-select">
              <option value="daily" <?php if(isset($_GET['date_filter']) && $_GET['date_filter'] == 'daily') echo 'selected'; ?>>Daily</option>
              <option value="weekly" <?php if(isset($_GET['date_filter']) && $_GET['date_filter'] == 'weekly') echo 'selected'; ?>>Weekly</option>
              <option value="monthly" <?php if(!isset($_GET['date_filter']) || $_GET['date_filter'] == 'monthly') echo 'selected'; ?>>Monthly</option>
              <option value="annually" <?php if(isset($_GET['date_filter']) && $_GET['date_filter'] == 'annually') echo 'selected'; ?>>Annually</option>
            </select>
          </div>
        
          <!-- Start Date -->
          <div class="col-auto">
            <label for="start_date" class="form-label visually-hidden">Start Date</label>
            <input 
              type="date" 
              id="start_date" 
              name="start_date" 
              class="form-control"
              value="<?php echo $_GET['start_date'] ?? ''; ?>"
              placeholder="Start Date">
          </div>
        
          <!-- End Date -->
          <div class="col-auto">
            <label for="end_date" class="form-label visually-hidden">End Date</label>
            <input 
              type="date" 
              id="end_date" 
              name="end_date" 
              class="form-control"
              value="<?php echo $_GET['end_date'] ?? ''; ?>"
              placeholder="End Date">
          </div>
        
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
          </div>
        </form>


      <!-- Charts -->
      <div class="container mt-5">
        <div class="row d-flex align-items-stretch">
          <div class="col-md-8">
            <h4 class="mb-3">Product Sales Trend</h4>
            <canvas id="productSalesTrendChart" width="480" height="280"></canvas>
          </div>
          <div class="col-md-4">
            <h4 class="mb-3">Sales by Category</h4>
            <canvas id="categorySalesChart" width="200" height="260"></canvas>
          </div>
        </div>

      <!-- Average Order Value -->
    <div class="card shadow-sm rounded-3 my-3">
      <div class="card-body position-relative">
        <div class="card shadow-sm p-3 mb-4 rounded-4" style="background: #fff8f0; border: none;">
          <h5 class="card-title text-333 fw-bold mb-0">Average Order Value</h5>
        </div>
    
        <!-- Chart -->
        <canvas id="aovChart" height="150"></canvas>
    
        <!-- Message container -->
        <div id="noAOVMessage" class="text-center text-muted fw-semibold my-3" style="display: none;">
          No records found for this period
        </div>
      </div>
    </div>
    


      <!-- Sales Trends -->
      <div class="card my-2">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title mb-0">Sales Trends</h5>
            <div class="d-flex gap-2 align-items-center">
              <select id="profitFilter" class="form-select form-select-sm">
                <option value="both" selected>Gross & Net</option>
                <option value="gross">Gross Sales</option>
                <option value="net">Net Sales</option>
              </select>
            </div>
          </div>
          <!-- Chart Container -->
        <div class="chart-container position-relative">
          <canvas id="profitTrendChart" height="120"></canvas>
    
          <!-- Message for no data -->
          <div id="noDataMessage" 
               class="text-center text-muted fw-semibold position-absolute top-50 start-50 translate-middle"
               style="display:none;">
            No records found for this period
          </div>
        </div>
      </div>
    </div>

      
     <div class="col-md-12">
      <div class="kpi-card elegant-card p-4">
        <h5 class="text-muted mb-3">Monthly Breakdown Expenses</h5>
        <?php if (!empty($months)) : ?>
          <canvas id="monthlyExpensesChart" height="120"></canvas>
        <?php else : ?>
          <p class="text-center text-muted mb-0">No expense records found for this period.</p>
        <?php endif; ?>
      </div>
    </div>

      <!-- Predictive Sales Chart -->
      <div class="card my-2">
        <div class="card-body">
      <div class="card shadow-sm p-3 mb-4 rounded-4" style="background: #fff8f0; border: none;">
            <h5 class="fw-semibold m-0">Predictive Sales Chart</h5>
          </div>
          <canvas id="predictChart" height="120"></canvas>
        </div>
      </div>

      <!-- Sales Growth Breakdown -->
      <div class="card shadow mt-4">
      <div class="card shadow-sm p-3 mb-4 rounded-4" style="background: #fff8f0; border: none;">
          <h5 class="mb-0">Sales Growth Breakdown</h5>
        </div>
        <div class="card-body">
          <div style="position: relative; height: 500px;">
            <canvas id="salesGrowthChart"></canvas>
          </div>
        </div>
      </div>

    </div>
  <!-- Charts -->
<script>
const trendLabels = <?= json_encode($dateLabels) ?>;
const trendDatasets = <?= json_encode($productSalesDatasets) ?>;
const categoryNames = <?= json_encode($categoryNames) ?>;
const categorySales = <?= json_encode($categorySales) ?>;

// Step 1: Convert PHP data into chart.js format
const trendChartData = trendDatasets.map(dataset => ({
    label: dataset.label,
    data: dataset.data,
    borderColor: '',      // to be assigned below
    backgroundColor: '',  // optional for tooltip coloring
    fill: false,
    tension: 0.1,
    profitData: dataset.profitData
}));

// Step 2: Assign unique colors (cycle if too many products)
const uniqueColors = [
    '#e6194b', // Strong Red
    '#3cb44b', // Vivid Green
    '#ffe119', // Bright Yellow
    '#4363d8', // Strong Blue
    '#f58231', // Orange
    '#911eb4', // Purple
    '#46f0f0', // Cyan
    '#f032e6', // Magenta
    '#bcf60c', // Lime
'#ff1493', // Strong Pink    '#008080', // Teal
    '#e6beff', // Lavender
    '#9a6324', // Brown
    '#fffac8', // Cream
    '#800000', // Maroon
    '#aaffc3', // Mint
    '#808000', // Olive
    '#ffd8b1', // Peach
    '#000075', // Navy
    '#808080'  // Gray
];


trendChartData.forEach((dataset, index) => {
    const color = uniqueColors[index % uniqueColors.length];
    dataset.borderColor = color;
    dataset.backgroundColor = color;
});

    // Product Sales Trend
    const ctx = document.getElementById('productSalesTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: trendChartData
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'nearest',
                intersect: false
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const dataset = context.dataset;
                            const index = context.dataIndex;
                            const sold = dataset.data[index];
                            const profit = dataset.profitData ? dataset.profitData[index] : 0;
                            return [
                                `Product: ${dataset.label}`,
                                `Sold: ${sold}`,
                                `Sales: ₱${parseFloat(profit).toFixed(2)}`
                            ];
                        }
                    }
                },
                legend: {
                    position: 'right'
                },
                title: {
                    display: true,
                    text: 'Product Sales Trend (Quantity & Sales)'
                }
            },
            scales: {
                y: {
                    title: {
                        display: true,
                        text: 'Quantity Sold'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });

    // Sales by Category Chart
    const categoryCtx = document.getElementById('categorySalesChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'pie',
    data: {
        labels: categoryNames,
        datasets: [{
            label: 'Sales by Category (₱)',
            data: categorySales,
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                '#9966FF', '#FF9F40', '#C9CBCF', '#E7E9ED'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `${context.label}: ₱${parseFloat(context.raw).toFixed(2)}`;
                    }
                }
            },
            datalabels: {
                formatter: (value, context) => {
                    const total = context.chart.data.datasets[0].data.reduce((sum, val) => sum + val, 0);
                    const percentage = (value / total * 100).toFixed(1);
                    return `${percentage}%`;
                },
                color: '#fff',
                font: {
                    weight: 'bold'
                }
            }
        }
    },
    plugins: [ChartDataLabels] // register the plugin
});
</script>

<script>
const profitLabels = <?= json_encode($profitLabels) ?>;
let grossProfits = <?= json_encode($grossProfits) ?>;
let netProfits = <?= json_encode($netProfits) ?>;

// Replace negative values with zero
grossProfits = grossProfits.map(value => (value < 0 ? 0 : value));
netProfits = netProfits.map(value => (value < 0 ? 0 : value));

// Check if there’s any data
const hasData =
  profitLabels.length > 0 &&
  (grossProfits.some(v => v > 0) || netProfits.some(v => v > 0));

// Handle no-data case
if (!hasData) {
  document.getElementById('profitTrendChart').style.display = 'none';

  // Create and show message if it doesn’t exist
  let msg = document.getElementById('noDataMessage');
  if (!msg) {
    msg = document.createElement('div');
    msg.id = 'noDataMessage';
    msg.className = 'text-center text-muted fw-semibold my-4';
    msg.textContent = 'No records found for this period';
    document.querySelector('.card-body').appendChild(msg);
  }
  msg.style.display = 'block';
} else {
  // Define datasets
  const grossDataset = {
    label: 'Gross Sales',
    data: grossProfits,
    borderColor: 'rgb(21, 224, 123)',
    backgroundColor: 'rgba(21, 224, 123, 0.6)',
    tension: 0.3,
    fill: true
  };

  const netDataset = {
    label: 'Net Sales',
    data: netProfits,
    borderColor: 'rgb(219, 17, 61)',
    backgroundColor: 'rgba(219, 17, 61, 0.6)',
    tension: 0.3,
    fill: true
  };

  // Initialize chart
  const ctxProfitTrend = document.getElementById('profitTrendChart').getContext('2d');
  const profitTrendChart = new Chart(ctxProfitTrend, {
    type: 'bar',
    data: {
      labels: profitLabels,
      datasets: [grossDataset, netDataset]
    },
    options: {
      responsive: true,
      plugins: {
        tooltip: {
          callbacks: {
            label: function (context) {
              const label = context.dataset.label;
              const value = context.raw.toLocaleString();
              return `${label}: ₱${value}`;
            }
          }
        },
        legend: { position: 'top' },
        title: {
          display: true,
          text: 'Daily Gross and Net Profit'
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          title: { display: true, text: 'Amount (₱)' }
        },
        x: {
          title: { display: true, text: 'Date' }
        }
      }
    }
  });

  // Dropdown filter logic
  document.getElementById('profitFilter').addEventListener('change', function () {
    const selected = this.value;

    if (selected === 'gross') {
      profitTrendChart.data.datasets = [grossDataset];
    } else if (selected === 'net') {
      profitTrendChart.data.datasets = [netDataset];
    } else {
      profitTrendChart.data.datasets = [grossDataset, netDataset];
    }

    profitTrendChart.update();
  });
}
</script>

<script>
const labelsAOV = <?= json_encode($labelsAOV); ?>;
const dataAOV = <?= json_encode($dataAOV); ?>;

// ✅ Check if data exists
const hasAOVData = labelsAOV.length > 0 && dataAOV.some(v => v > 0);

if (!hasAOVData) {
  document.getElementById('aovChart').style.display = 'none';

  // Create or show message
  let msg = document.getElementById('noAOVMessage');
  if (!msg) {
    msg = document.createElement('div');
    msg.id = 'noAOVMessage';
    msg.className = 'text-center text-muted fw-semibold my-4';
    msg.textContent = 'No records found for this period';
    document.querySelector('.card-body').appendChild(msg);
  }
  msg.style.display = 'block';
} else {
  // ✅ Create chart normally
  const ctxAOV = document.getElementById('aovChart').getContext('2d');
  const aovChart = new Chart(ctxAOV, {
    type: 'line',
    data: {
      labels: labelsAOV,
      datasets: [{
        label: 'Average Order Value',
        data: dataAOV,
        backgroundColor: 'rgba(54, 162, 235, 0.2)',
        borderColor: 'rgba(54, 162, 235, 1)',
        borderWidth: 2,
        tension: 0.4,
        fill: true,
        pointRadius: 3
      }]
    },
    options: {
      responsive: true,
      plugins: {
        title: {
          display: true,
          text: 'Average Order Value'
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Sales (₱)'
          }
        },
        x: {
          title: {
            display: true,
            text: 'Date'
          }
        }
      }
    }
  });
}
</script>


<script>
    const predictLabels = <?= json_encode($allMonths ?? []); ?>;
    const predictData = <?= json_encode($allSales ?? []); ?>;
    const predictedStartIndex = <?= count($months ?? []); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        const ctxElement = document.getElementById('predictChart');

        if (!ctxElement) {
            console.warn("Chart canvas not found in the DOM.");
            return;
        }

        const ctx = ctxElement.getContext('2d');
        let chartInstance = null;

        function formatMonthLabels(labels) {
            const monthNames = [
                "Jan", "Feb", "Mar", "Apr", "May", "Jun",
                "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
            ];
            return labels.map(label => {
                const parts = label.split("-");
                const month = parseInt(parts[1], 10) - 1; // month is 0-indexed
                const year = parts[0];
                return `${monthNames[month]} ${year}`;
            });
        }

        function renderChart() {
            const formattedLabels = formatMonthLabels(predictLabels);
            const actualData = [];
            const predictedData = [];

            predictLabels.forEach((_, index) => {
                if (index < predictedStartIndex) {
                    actualData.push(predictData[index]);
                    predictedData.push(null); // keep alignment
                } else {
                    actualData.push(null);
                    predictedData.push(predictData[index]);
                }
            });

            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: formattedLabels,
                    datasets: [
                      {
                        label: 'Actual Sales',
                        data: <?= json_encode($actualSales) ?>,
                        borderColor: 'green',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        borderWidth: 2,
                        spanGaps: true
                      },
                      {
                        label: 'Predicted Sales',
                        data: <?= json_encode($predictedLine) ?>,
                        borderColor: 'rgba(255,99,132,0.8)',
                        backgroundColor: 'transparent',
                        borderDash: [6, 6],
                        tension: 0.3,
                        borderWidth: 2,
                        spanGaps: true
                      }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: `Predicted vs Actual Sales`,
                            font: { size: 18 }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        },
                        legend: {
                            position: 'top'
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Quantity Sold'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    }
                }
            });
        }

        renderChart();
    });
</script>


<!-- Include Chart.js Datalabels plugin -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('salesGrowthChart').getContext('2d');
    const growthData = <?= json_encode($salesGrowthData) ?>;
    const labels = <?= json_encode($salesGrowthLabels) ?>;
    const barColors = <?= json_encode($barColors) ?>;

    const salesGrowthChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Monthly Growth (%)',
                data: growthData,
                backgroundColor: barColors,
                borderRadius: 6,
                barThickness: 35,
                maxBarThickness: 40,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                datalabels: {
                    anchor: 'end',
                    align: 'start',
                    font: {
                        size: 24,        // ⬅ Bigger arrow
                        weight: 'bold'
                    },
                    color: function(context) {
                        const value = context.dataset.data[context.dataIndex];
                        if (value > 0) return 'green';
                        if (value < 0) return 'red';
                        return '#666'; // neutral
                    },
                    formatter: function(value, context) {
                        const barColor = context.dataset.backgroundColor[context.dataIndex];
                    
                        if (barColor.includes('255, 99, 132')) return '↓';  // Red bar → Down arrow
                        if (value > 0) return '↑';                          // Green bar → Up arrow
                        if (value === 0) return '→';                        // Neutral
                        return '';
                    },

                    textAlign: 'center',
                    offset: 6
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
    return context.dataset.label + ': ₱' + context.raw.toLocaleString();
}

                    }
                },
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Sales Growth',
                    font: {
                        size: 18,
                        weight: 'bold'
                    },
                    color: '#333'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Growth Percentage (%)'
                    },
                    ticks: {
                        callback: function (value) {
                            return value + '%';
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month'
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
});
</script>

<?php if (!empty($months)) : ?>
<script>
const ctxExpenses = document.getElementById('monthlyExpensesChart').getContext('2d');

new Chart(ctxExpenses, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($m) => 
            preg_match('/^\d{4}-W\d{2}$/', $m)
                ? str_replace('-', ' Week ', $m)
                : date('F Y', strtotime($m . '-01'))
        , $months)) ?>,
        datasets: <?= json_encode($datasets) ?>
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        stacked: true,
        scales: {
            x: { 
                stacked: true, 
                grid: { display: false },
                // ✅ Thinner bars
                barThickness: 35,        // default ~0.9 → thinner bars
                maxBarThickness: 45,   // tighter grouping between bars
            },
            y: {
                stacked: true,
                beginAtZero: true,
                ticks: { callback: value => '₱' + value.toLocaleString() },
                grid: { color: '#f0e6d2' }
            }
        },
        plugins: {
            legend: { display: true, position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ₱' + context.formattedValue;
                    },
                    footer: function(tooltipItems) {
                        let sum = 0;
                        tooltipItems.forEach(i => sum += i.parsed.y);
                        return 'Total: ₱' + sum.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

  <div class="footer">
    <p>Report generated on: <?= date('F j, Y') ?></p>
  </div>
</div>
</body>
</html>