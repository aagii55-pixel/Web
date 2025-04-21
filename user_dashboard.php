<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userID = $_SESSION['user_id'];

$sql = "SELECT Name FROM User WHERE UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$userName = $user['Name'] ?? 'Хэрэглэгч';
$_SESSION['user_name'] = $userName;
$stmt->close();

function getCount($conn, $sql, $id)
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count;
}

$totalBookings = getCount($conn, "SELECT COUNT(*) FROM Booking WHERE UserID = ?", $userID);
$approved = getCount($conn, "SELECT COUNT(*) FROM Booking WHERE UserID = ? AND Status = 'Approved'", $userID);
$cancelled = getCount($conn, "SELECT COUNT(*) FROM Booking WHERE UserID = ? AND Status = 'Cancelled'", $userID);
$unreadNotifs = getCount($conn, "SELECT COUNT(*) FROM Notifications WHERE UserID = ? AND IsRead = 0", $userID);
$isManagerViewingAsUser = isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Manager' && $_SESSION['user_role'] === 'User';

$conn->close();
?>

<!DOCTYPE html>
<html lang="mn">

<head>
    <meta charset="UTF-8">
    <title>Хяналтын самбар</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-100">
    <?php include 'headers/header_user.php'; ?>

    <?php if ($isManagerViewingAsUser): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 max-w-6xl mx-auto">
        <div class="flex items-center justify-between">
            <div>
                <i class="fas fa-user-secret mr-2"></i>
                <span>Та одоогоор хэрэглэгчийн горимд ажиллаж байна. Бүх хэрэглэгчийн үйлдлүүдийг хийх боломжтой.</span>
            </div>
            <a href="switch_role.php?role=manager" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md">
                <i class="fas fa-exchange-alt mr-1"></i> Менежер горимд буцах
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="max-w-6xl mx-auto px-6 py-10">
        <h1 class="text-3xl font-semibold mb-6 text-center text-blue-800">Таны Хяналтын Самбар</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow p-6 text-center hover:shadow-md transition">
                <div class="text-sm text-gray-500 mb-2">Нийт захиалга</div>
                <div class="text-3xl font-bold text-blue-600"><?= $totalBookings ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 text-center hover:shadow-md transition">
                <div class="text-sm text-gray-500 mb-2">Батлагдсан</div>
                <div class="text-3xl font-bold text-green-600"><?= $approved ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 text-center hover:shadow-md transition">
                <div class="text-sm text-gray-500 mb-2">Цуцлагдсан</div>
                <div class="text-3xl font-bold text-red-500"><?= $cancelled ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 text-center hover:shadow-md transition">
                <div class="text-sm text-gray-500 mb-2">Шинэ мэдэгдэл</div>
                <div class="text-3xl font-bold text-yellow-500"><?= $unreadNotifs ?></div>
            </div>
        </div>
    </div>
</body>

</html>