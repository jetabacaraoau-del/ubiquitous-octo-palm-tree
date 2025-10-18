<?php 
session_start();
include('../config/db.php');

$message = '';

if(isset($_POST['submit'])){
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['pswd']; // No need to hash here

    $sql = "SELECT * FROM admin_data WHERE email='$email'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($password, $row['password'])) { // Using password_verify
            $_SESSION['email'] = $email;
            header('location:admin_dashboard.php');
        } else {
            $message = 'Incorrect Credentials';
        }
    } else {
        $message = 'Incorrect Credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<style>
body {
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;  /* horizontally center */
    align-items: flex-start;  /* push container slightly down */
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    position: relative;
    overflow: hidden; /* prevents blur overflow */
}

/* Blurred background image */
body::before {
    content: "";
    position: fixed;
    top: 0; left: 0;
    width: 100%;
    height: 100%;
    background-image: url('inc/bg.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    filter: blur(8px);      /* blur effect */
    transform: scale(1.05); /* avoid edges showing unblurred */
    z-index: -2;            /* behind everything */
}

/* Dark overlay for contrast */
body::after {
    content: "";
    position: fixed;
    top: 0; left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.45);
    z-index: -1;
}

.container {
    width: 90%;
    max-width: 400px;
    padding: 50px 45px;
    margin: 60px 0; /* consistent top/bottom spacing */
    background-color: rgba(255, 255, 255, 0.5);
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15), 0 4px 12px rgba(0,0,0,0.1);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    text-align: center;
    transition: transform 0.3s ease;
}

.container:hover {
    transform: translateY(-5px);
}

h2 {
    font-weight: 700;
    font-size: 32px;
    margin: 0 0 12px 0; /* consistent bottom margin */
    color: #2c3e50;
}

h3 {
    font-weight: 500;
    font-size: 16px;
    margin: 0 0 24px 0;
    letter-spacing: 0.03em;
    color: #555;
}

input[type="text"],
input[type="password"] {
    width: 100%;
    padding: 14px 0px;
    font-size: 15px;
    border: 1.8px solid #ccc;
    border-radius: 12px;
    outline-offset: 2px;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    font-family: inherit;
    margin-bottom: 16px; /* consistent spacing between inputs */
}

input[type="text"]:focus,
input[type="password"]:focus {
    border-color: #6FB75A;
    box-shadow: 0 0 8px rgba(111, 183, 90, 0.5);
}

input[type="submit"] {
    width: 100%;
    padding: 14px 0;
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    border: none;
    border-radius: 14px;
    cursor: pointer;
    box-shadow: 0 6px 15px rgba(111, 183, 90, 0.5);
    transition: background-color 0.3s ease, transform 0.15s ease;
    letter-spacing: 0.05em;
    user-select: none;
    margin: 16px 0; /* consistent spacing from inputs above */
    background-color: #6FB75A;
}

input[type="submit"]:hover {
    background-color: #5aa24a;
    transform: scale(1.05);
}

.message p {
    margin: 0 0 16px 0; /* consistent spacing below message */
    font-weight: 600;
    color: #d9534f;
}

.forgot-password {
    margin-top: 20px;
}

.forgot-password a {
    color: #2c3e50;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
}

.forgot-password a:hover {
    color: #6FB75A;
    text-decoration: underline;
}

.password-wrapper {
    position: relative;
    width: 100%;
    margin-bottom: 16px;
}

.password-wrapper input[type="password"] {
    padding-right: 70px;
}

.toggle-password {
    position: absolute;
    top: 50%;
    right: 14px;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    color: #888;
    transition: color 0.3s ease;
    user-select: none;
    font-weight: 600;
    padding: 0 8px;
    border-radius: 8px;
}

.toggle-password:hover {
    color: #6FB75A;
}

    .message p {
      margin: 0 0 12px;
      font-weight: 600;
      color: #d9534f; /* Bootstrap-like red */
    }

    .forgot-password {
      margin-top: 18px;
    }

    .forgot-password a {
      color: #2c3e50;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .forgot-password a:hover {
      color: #6FB75A;
      text-decoration: underline;
    }
  </style>
</head>
<body>
    <div class="container">
        <div class="image-side">
        </div>
        <div class="login-form">
            <div class="login-form-inner">
                <h2>Welcome</h2>
                <h3>Admin Login</h3>
                <form method="post">
                    <div class="message">
                        <?php if(!empty($message)) echo "<p>$message</p>"; ?>
                    </div>
                    <input type="text" name="email" placeholder="Username or E-mail Address" required>
                    <input type="password" name="pswd" placeholder="Password" required>
                    <input type="submit" name="submit" value="Login">
                </form>
            </div>
        </div>
    </div>
</body>
</html>
