<?php
session_start();

if (!isset($_SESSION['available_roles']) || empty($_SESSION['available_roles'])) {
    header("Location: user_dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['user_role'] = $_POST['selected_role'];
    switch ($_SESSION['user_role']) {
        case 'VenueStaff':
            header("Location: venue_staff_dashboard.php");
            break;
        case 'Accountant':
            header("Location: accountant_dashboard.php");
            break;
        default:
            header("Location: user_dashboard.php");
            break;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <title>Role Selection</title>
</head>
<body>
    <h2>Та өөрийн үүргийг сонгоно уу</h2>
    <form method="POST">
        <select name="selected_role">
            <?php foreach ($_SESSION['available_roles'] as $role) : ?>
                <option value="<?= $role ?>"><?= $role ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Үргэлжлүүлэх</button>
    </form>
</body>
</html>
