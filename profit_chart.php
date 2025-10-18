<?php
// Simulated values for demonstration
$gross_profit = 150000; // Example gross profit
$net_profit = 120000;   // Example net profit
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Gross and Net Profit Chart</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h2>Profit Charts</h2>
    <div style="width: 400px; margin-bottom: 40px;">
        <canvas id="grossProfitChart"></canvas>
    </div>
    <div style="width: 400px;">
        <canvas id="netProfitChart"></canvas>
    </div>

    <script>
        // PHP variables passed to JavaScript
        const grossProfit = <?php echo json_encode($gross_profit); ?>;
        const netProfit = <?php echo json_encode($net_profit); ?>;

        // Gross Profit Chart
        const ctxGross = document.getElementById('grossProfitChart').getContext('2d');
        const grossChart = new Chart(ctxGross, {
            type: 'bar',
            data: {
                labels: ['Gross Profit'],
                datasets: [{
                    label: '₱',
                    data: [grossProfit],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    barPercentage: 0.5
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Net Profit Chart
        const ctxNet = document.getElementById('netProfitChart').getContext('2d');
        const netChart = new Chart(ctxNet, {
            type: 'bar',
            data: {
                labels: ['Net Profit'],
                datasets: [{
                    label: '₱',
                    data: [netProfit],
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    barPercentage: 0.5
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
