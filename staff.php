<?php
session_start();
include('../config/db.php');

// Handle form submission
$message = '';
if (isset($_POST['submit'])) {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $password   = mysqli_real_escape_string($conn, $_POST['password']);

    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (empty($password)) $errors[] = "Password is required.";

    if (empty($errors)) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $check_email = mysqli_query($conn, "SELECT * FROM staff WHERE email = '$email'");
        if (mysqli_num_rows($check_email) > 0) {
            $message = "Email already exists!";
        } else {
            $insert = "INSERT INTO staff (first_name, last_name, email, password)
                       VALUES ('$first_name', '$last_name', '$email', '$password_hashed')";
            $message = mysqli_query($conn, $insert) ? "Staff added successfully!" : "Failed to add staff.";
        }
    } else {
        $message = implode('<br>', $errors);
    }
}

include('inc/header.php');
include('inc/nav.php');
?>

<div class="main-content d-flex justify-content-center align-items-center" style="min-height:100vh; padding:20px;">
    <div class="card shadow-lg rounded-4 border-0 p-4 signup-card">
        <div class="text-center mb-4">
            <h3 class="fw-bold" style="color: teal;">Staff Signup</h3>
            <p class="text-muted">Create your account</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" class="signup-form">
            <div class="form-floating mb-3">
                <input type="text" name="first_name" class="form-control" id="firstName" placeholder="First Name" required>
                <label for="firstName">First Name</label>
            </div>

            <div class="form-floating mb-3">
                <input type="text" name="last_name" class="form-control" id="lastName" placeholder="Last Name" required>
                <label for="lastName">Last Name</label>
            </div>

            <div class="form-floating mb-3">
                <input type="email" name="email" class="form-control" id="email" placeholder="Email" required>
                <label for="email">Email Address</label>
            </div>

            <div class="form-floating mb-4 position-relative">
                <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                <label for="password">Password</label>
                <button type="button" class="btn btn-sm btn-outline-secondary password-toggle" onclick="togglePassword()">Show</button>
            </div>

            <button type="submit" name="submit" class="btn w-100 fw-bold py-2 btn-teal">Sign Up</button>
        </form>
    </div>
</div>

<style>
/* ---------- Card ---------- */
.signup-card {
    max-width: 400px;
    width: 100%;
    background: #ffffff;
    transition: all 0.3s ease;
    margin: 0 auto;
}
.signup-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

/* ---------- Form Controls ---------- */
.form-control {
    border-radius: 8px;
    padding: 12px;
    border: 1px solid #ddd;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.form-control:focus {
    border-color: teal;
    box-shadow: 0 0 0 0.2rem rgba(0,128,128,0.25);
    outline: none;
}

/* Floating labels */
.form-floating label {
    transition: all 0.2s ease-in-out;
}
.form-floating input:focus + label,
.form-floating input:not(:placeholder-shown) + label {
    font-size: 0.85rem;
    top: -10px;
    left: 12px;
    color: teal;
    background: #fff;
    padding: 0 5px;
}

/* Password toggle button */
.password-toggle {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    font-size: 0.8rem;
    border: none;
    background: none;
    color: teal;
}

/* Button */
.btn-teal {
    background-color: teal;
    color: white;
    border-radius: 8px;
    padding: 14px 20px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
}
.btn-teal:hover {
    background-color: #004d40;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,77,64,0.3);
}

/* Responsive */
@media (max-width: 576px) {
    .main-content {
        padding: 20px 10px;
    }
    .signup-card {
        padding: 20px;
    }
}
</style>

<script>
function togglePassword() {
    const passwordField = document.getElementById('password');
    const btn = document.querySelector('.password-toggle');
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        btn.textContent = 'Hide';
    } else {
        passwordField.type = 'password';
        btn.textContent = 'Show';
    }
}
</script>

<?php include('inc/footer.php'); ?>
