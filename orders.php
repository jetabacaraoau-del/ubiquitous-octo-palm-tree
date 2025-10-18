<?php
session_start();
include('../config/db.php');
if(!isset($_SESSION['email']) && empty($_SESSION['email'])){
    header('location:login.php');
}
?>

<?php include('inc/header.php') ?>
<?php include('inc/nav.php') ?>

<div class="container">
    <div class="card">
        <div class="card-header">
            All Orders
        </div>
        <div class="card-body">
            <section id="content mt-5">
                <div class="content-blog bg-white">
                    <div class="container">

                        <table class="table table-striped mt-4">
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Total Price</th>
                                    <th>Order Status</th>
                                    <th>Payment Mode</th>
                                    <th>Order Placed On</th>
                                    <th>Operations</th>
                                </tr>
                            </thead>
                            <tbody>

                            <?php
                            // Start building the SQL query for filtering
                            $sql = "SELECT orders.totalprice, orders.orderstatus, orders.paymentmode, orders.timestamp, orders.id, user_data.firstname, user_data.lastname 
                                    FROM orders 
                                    JOIN user_data ON orders.userid = user_data.userid WHERE 1 ";

                            // Check for filters and modify the query accordingly
                            if (isset($_GET['order_status']) && !empty($_GET['order_status'])) {
                                $order_status = mysqli_real_escape_string($conn, $_GET['order_status']);
                                $sql .= " AND orders.orderstatus = '$order_status' ";
                            }

                            if (isset($_GET['payment_mode']) && !empty($_GET['payment_mode'])) {
                                $payment_mode = mysqli_real_escape_string($conn, $_GET['payment_mode']);
                                $sql .= " AND orders.paymentmode = '$payment_mode' ";
                            }

                            if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
                                $start_date = mysqli_real_escape_string($conn, $_GET['start_date']);
                                $sql .= " AND orders.timestamp >= '$start_date' ";
                            }

                            if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
                                $end_date = mysqli_real_escape_string($conn, $_GET['end_date']);
                                $sql .= " AND orders.timestamp <= '$end_date' ";
                            }

                            // Add ordering
                            $sql .= " ORDER BY orders.id DESC";

                            // Execute the query
                            $result = mysqli_query($conn, $sql);

                            if (mysqli_num_rows($result) > 0) {
                                // output data of each row
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                <tr>
                                    <td><?php echo $row["firstname"] . " " . $row["lastname"]; ?></td>
                                    <td><?php echo $row["totalprice"]; ?></td>
                                    <td><?php echo $row["orderstatus"]; ?></td>
                                    <td><?php echo $row["paymentmode"]; ?></td>
                                    <td><?php echo date('M j g:i A', strtotime($row["timestamp"])); ?></td>
                                    <td><a href='order-process.php?id=<?php echo $row["id"] ?>'>Change Status</a></td>
                                </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>No results found</td></tr>";
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php include('inc/footer.php') ?>
