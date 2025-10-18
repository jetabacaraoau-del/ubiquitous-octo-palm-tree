<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['email']) && empty($_SESSION['email'])) {
  header('location:login.php');
  exit;
}

// Initialize monthly sales
$monthlySales = array_fill(1, 12, 0);
$query = "SELECT MONTH(`timestamp`) AS month, SUM(totalprice) AS total 
          FROM orders 
          WHERE YEAR(`timestamp`) = YEAR(CURDATE()) 
          AND `orderstatus` = 'delivered' 
          GROUP BY MONTH(`timestamp`)";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
  $monthlySales[(int)$row['month']] = (float)$row['total'];
}

// Today's profit
$todayProfit = mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT SUM(totalprice) AS today_profit FROM orders WHERE DATE(`timestamp`) = CURDATE() AND `orderstatus` = 'delivered'")
)['today_profit'] ?? 0;

// Total sales profit
$salesProfit = mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT SUM(totalprice) AS total_sales_profit FROM orders WHERE `orderstatus` = 'delivered'")
)['total_sales_profit'] ?? 0;

// Current Month Profit
$currentMonthStart = date('Y-m-01');
$currentMonthEnd = date('Y-m-t');
$currentMonthProfit = mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT SUM(totalprice) AS total_current_month FROM orders WHERE orderstatus = 'delivered' AND `timestamp` BETWEEN '$currentMonthStart' AND '$currentMonthEnd'")
)['total_current_month'] ?? 0;

// Last Month Profit
$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
$lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
$lastMonthProfit = mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT SUM(totalprice) AS total_last_month FROM orders WHERE orderstatus = 'delivered' AND `timestamp` BETWEEN '$lastMonthStart' AND '$lastMonthEnd'")
)['total_last_month'] ?? 0;

// Growth %
$growthPercent = ($lastMonthProfit > 0) 
  ? (($currentMonthProfit - $lastMonthProfit) / $lastMonthProfit) * 100 
  : ($currentMonthProfit > 0 ? 100 : 0);

$growthClass = ($growthPercent >= 0) ? 'text-success' : 'text-danger';
?>
<?php include('inc/header.php'); ?>
<?php include('inc/nav.php'); ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sales Report</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background-color:beige; font-family: 'Inter', sans-serif; }
    h2 { font-weight: 700; color: #343a40; margin-bottom: 30px; }
    .report-card {
      background: #fff; border-radius: 15px; padding: 25px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 20px;
      transition: 0.3s ease;
    }
    .report-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
    }
    .report-title { font-weight: 600; font-size: 1.1rem; color: #555; }
    .report-value { font-size: 2rem; font-weight: 700; color: #007bff; }
    .report-subtext { font-size: 0.95rem; color: #6c757d; }
    canvas { background: #fff; border-radius: 12px; }
    .footer { margin-top: 40px; font-size: 0.9rem; color: #888; }
  </style>
</head>
<body>

<div class="container mt-5">
  <h2 class="text-center">📊 Sales Performance Report</h2>

  <div class="row">
    <div class="col-md-4">
      <div class="report-card">
        <div class="report-title">Today's Profit</div>
        <div class="report-value">₱<?= number_format($todayProfit, 2) ?></div>
        <div class="report-subtext">Sales recorded today.</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="report-card">
        <div class="report-title">Current Month</div>
        <div class="report-value">₱<?= number_format($currentMonthProfit, 2) ?></div>
        <div class="report-subtext"><?= date('F') ?> Delivered Orders</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="report-card">
        <div class="report-title">Last Month</div>
        <div class="report-value">₱<?= number_format($lastMonthProfit, 2) ?></div>
        <div class="report-subtext"><?= date('F', strtotime($lastMonthStart)) ?> Delivered Orders</div>
      </div>
    </div>
  </div>

  <div class="row mt-3">
    <div class="col-md-6">
      <div class="report-card">
        <div class="report-title">Sales Growth</div>
        <div class="report-value <?= $growthClass ?>"><?= number_format($growthPercent, 2) ?>%</div>
        <div class="report-subtext">Compared to <?= date('F', strtotime('first day of last month')) ?></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="report-card">
        <div class="report-title">Total Sales Profit</div>
        <div class="report-value">₱<?= number_format($salesProfit, 2) ?></div>
        <div class="report-subtext">All-time delivered orders.</div>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-md-12">
      <div class="report-card">
        <h5 class="mb-3">Monthly Sales Trend (₱)</h5>
        <canvas id="monthlySalesChart" height="100"></canvas>
      </div>
    </div>
  </div>

  <div class="footer text-center">
    Report generated on <?= date('F j, Y') ?>
  </div>
</div>

<script>
  const monthlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const monthlySalesData = <?= json_encode(array_values($monthlySales)) ?>;

  const ctx = document.getElementById('monthlySalesChart').getContext('2d');
  const salesChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: monthlyLabels,
      datasets: [{
        label: 'Delivered Sales (₱)',
        data: monthlySalesData,
        backgroundColor: 'rgba(0, 123, 255, 0.2)',
        borderColor: '#007bff',
        borderWidth: 2,
        borderRadius: 8
      }]
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: value => '₱' + value.toLocaleString()
          }
        }
      }
    }
  });
</script>

</body>
</html>
