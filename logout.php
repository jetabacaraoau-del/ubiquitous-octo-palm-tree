<?php
session_start();
session_destroy();

// Redirect to the main Seventeasdiner page
header('Location: login.php');
exit;
?>
