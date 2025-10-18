<?php
include 'db.php';

$sql = "
SELECT 
    p.name AS product_name,
    SUM(oi.quantity) AS total_quantity,
    SUM(oi.quantity * oi.price) AS total_profit
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
JOIN products p ON oi.product_id = p.id
WHERE o.status = 'Delivered'
GROUP BY oi.product_id
ORDER BY total_quantity DESC
";

$result = $conn->query($sql);

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'product' => $row['product_name'],
        'quantity' => (int)$row['total_quantity'],
        'profit' => (float)$row['total_profit']
    ];
}

echo json_encode($data);
?>
