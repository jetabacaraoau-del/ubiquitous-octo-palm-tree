<?php
session_start();
include('../config/db.php');
if(!isset($_SESSION['email']) || empty($_SESSION['email'])){
    header('location:login.php');
}
?> 
<?php include('inc/header.php'); ?>
<?php include('inc/nav.php'); ?>

<style>
  body {
    background-color: #f5f7fa;
    font-family: 'Poppins', sans-serif;
  }

  .container {
    max-width: 1200px;
    margin: 90px auto 50px auto;
    margin-left: 350px;
  }

  .card {
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    padding: 40px 30px;
    transition: all 0.3s ease;
  }

  .card h2 {
    margin-bottom: 20px;
    font-weight: 700;
    color: #333;
  }

  .top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
  }

  .add-btn {
    background: teal;
    color: #fff;
    padding: 10px 20px;
    font-weight: 600;
    border: none;
    border-radius: 50px;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 6px 12px rgba(0,128,128,0.4);
  }

  .add-btn:hover {
    background: #006666;
    color: #fff;
  }

  table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
  }

  th {
    text-align: left;
    padding: 12px 20px;
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 13px;
  }

  td {
    background: #fff;
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 15px;
    color: #444;
    vertical-align: middle;
  }

  tr:hover td {
    background-color: #f0f4f8;
    transition: 0.3s;
  }

  .action-buttons a {
    padding: 6px 16px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 6px;
    text-decoration: none;
    color: #fff;
    margin: 0 5px;
    display: inline-block;
    transition: all 0.3s ease;
  }

  .edit-btn {
    background-color: #fcc404;
  }

  .edit-btn:hover {
    background-color: #d39e00;
  }

  .delete-btn {
    background-color: #dc3545;
  }

  .delete-btn:hover {
    background-color: #b02a37;
  }

  @media (max-width: 768px) {
    .container { margin-left: 20px; padding: 0 10px; }
    .card { padding: 30px 20px; }
    th, td { padding: 10px 15px; }
    .add-btn { padding: 8px 16px; font-size: 14px; }
  }
</style>

<div class="container">
  <div class="card">
    <div class="top-bar">
      <h2>Category List</h2>
      <a href="addCategory.php" class="add-btn">+ Add Category</a>
    </div>

    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sql = "SELECT * FROM category";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                ?>
                <tr>
                  <td><?= htmlspecialchars($row["cat_name"]) ?></td>
                  <td class="action-buttons">
                    <a href='editCategory.php?id=<?= $row["cat_id"] ?>' class="edit-btn">Edit</a>
                    <a href='delCategory.php?id=<?= $row["cat_id"] ?>' class="delete-btn">Delete</a>
                  </td>
                </tr>
            <?php
            }
        } else {
            echo '<tr><td colspan="2" style="text-align:center; background:#fff;">No categories found.</td></tr>';
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<?php include('inc/footer.php'); ?>
