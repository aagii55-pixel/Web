<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
$userName = $_SESSION['user_name'] ?? 'Хэрэглэгч';
$userRoleID = $_SESSION['role_id'] ?? null;

// Check if this is a manager viewing as a user
$isManagerViewingAsUser = isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Manager' && $_SESSION['user_role'] === 'User';

// 🔍 Одоогийн хуудасны нэр (жишээ: home.php)
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="bg-white shadow-sm border-b">
    <div class="max-w-screen-xl mx-auto px-4 py-3 flex flex-col md:flex-row justify-between items-center gap-2">
        <!-- Зүүн тал: Logo + Самбар руу буцах -->
        <div class="flex items-center gap-6">
            <a href="user_dashboard.php" class="text-blue-700 text-xl font-bold">Заал</a>
        </div>

        <!-- Баруун тал: Navigation -->
        <div class="flex flex-wrap justify-center md:justify-end items-center gap-3 text-sm text-gray-700">
            <a href="home.php" class="hover:text-blue-600">Заал хайх</a>
            <a href="view_bookings.php" class="hover:text-blue-600">Миний захиалгууд</a>
            <a href="edit_profile.php" class="hover:text-blue-600">Профайл</a>
            
            <?php if (!$isManagerViewingAsUser): ?>
                <a href="PromotionRequest.php" class="hover:text-blue-600">Хүсэлт илгээх</a>
                
            <?php endif; ?>
            
            <span class="font-medium text-blue-800"><?= htmlspecialchars($userName) ?></span>
            <a href="logout.php" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">Гарах</a>
        </div>
    </div>
</nav>