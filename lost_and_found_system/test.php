<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Page</title>
</head>
<body>
    <h1>Login Successful!</h1>
    <p>Welcome, <?php echo $_SESSION['first_name']; ?>!</p>
    <p>Role: <?php echo $_SESSION['user_role']; ?></p>
    <a href="logout.php">Logout</a>
</body>
</html>