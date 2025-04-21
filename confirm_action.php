<?php
session_start();
require 'config/db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Admin') {
    header('Location: login.php');
    exit();
}

$action = $_GET['action'];
$userId = $_GET['userId'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/admin_dashboard.css">
    <title>Confirm Action</title>
</head>
<body>
    <h2>Confirm Your Action</h2>
    <a href="manage_users.php">back</a>
    <form action="manage_users.php?action=<?php echo $action; ?>&userId=<?php echo $userId; ?>" method="POST">
        <label for="admin_password">Enter your Admin Password to Confirm:</label>
        <input type="password" id="admin_password" name="admin_password" required>
        <button type="submit">Confirm</button>
    </form>
</body>
</html>
