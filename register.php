<?php 
session_start();
include('../config/db.php');

$fname = $lname = $email = $password = "";
$fname_err = $lname_err = $email_err = $password_err = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["fname"])) || empty(trim($_POST["lname"]))) {
        $fname_err = $lname_err = "Please enter your full name.";
    } else {
        $fname = trim($_POST["fname"]);
        $lname = trim($_POST["lname"]);
    }

    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Invalid email format.";
        }
    }

    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty($fname_err) && empty($lname_err) && empty($email_err) && empty($password_err)) {
        $sql = "INSERT INTO admin_data (FirstName, LastName, email, password) VALUES (?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssss", $param_fname, $param_lname, $param_email, $param_password);

            $param_fname = $fname;
            $param_lname = $lname;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); 

            if ($stmt->execute()) {
                $success_message = "Your account has been successfully created.";
                $_SESSION['success_message'] = $success_message;
                header("location: login.php");
                exit;
            } else {
                echo "Something went wrong. Please try again later.";
            }

            $stmt->close();
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register</title>
<style>
    body {
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        background-image: url('inc/buger.jpg');
        background-size: cover;
    }
    .container {
        width: 80%;
        height: 600px;
        display: flex; 
        box-shadow: 0 4px 8px rgba(0, 0, 1, 1);
        background-color: white;
        border-radius: 10px;
    }
    .image-side {
        flex: 60%;
        background-image: url('inc/medyolangit.jpg');
        background-size: cover;
        background-position: center;
        position: relative;
        border-radius: 10px;
    }

    .register-form {
        flex: 40%;
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }
    .register-form-inner {
        padding: 20px;
        border-radius: 5px;
        display: flex;
        flex-direction: column;
        align-items: center; 
    }
    .register-form h2 {
        text-align: center;
        font-size: 50px;
        }
        .register-form h3 {
        text-align: center;
        font-size: 20px;
        }
        
    .register-form input[type="text"],
    .register-form input[type="password"],
    .register-form input[type="email"],
    .register-form input[type="submit"] {
        width: 95%;
        margin-bottom: 15px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 3px;
    }
    .register-form input[type="submit"] {
        width: 60%;
        margin: 0 auto;
        margin-bottom: 15px;
        padding: 10px;
        font-size: 15px;
        border: 1px solid #ccc;
        border-radius: 3px;
        display: block;
        background-color: #CA8F68;
        color: white;
        cursor: pointer;
    }
    .register-form img {
        margin-top: 20px;
        height: 80px;  
        margin-bottom: 10px;
    }
    a{
        text-decoration-color: none;
        text-decoration: none;
        color: black;
    }
    a{
        text-decoration: none;
        color: inherit;
    }
    h2{
        margin-top: 50px;
        margin-bottom: 50px;
    }

</style>
</head>
<body>
    <div class="container">
        <div class="image-side">
        </div>
        <div class="register-form">
            <div class="register-form-inner">
                <h2>Welcome</h2>
                <h3>Register Now</h3>
                <?php if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])): ?>
                    <p style="color: green;"><?php echo $_SESSION['success_message']; ?></p>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                <form method="post">
                    <input type="text" name="fname" placeholder="First Name" required>
                    <input type="text" name="lname" placeholder="Last Name" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <input type="submit" value="Register">
                </form>
                <a href="login.php"><h4>Already have an account?</h4></a>
            </div>
        </div>
    </div>
</body>
</html>
