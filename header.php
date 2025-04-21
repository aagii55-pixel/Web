<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config/db.php';

// ... session, user info, role redirect
?>
<nav class="bg-gray-800 p-4">
    <div class="container mx-auto flex justify-between">
        <a href="home.php" class="text-white font-bold text-xl">🏟️ Заал</a>
        <div>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php" class="text-gray-300 hover:text-white px-3">Нэвтрэх</a>
                <a href="register.php" class="text-gray-300 hover:text-white px-3">Бүртгүүлэх</a>
            <?php else: ?>
                <span class="text-white mr-4">Сайн байна уу, <?= htmlspecialchars($userName ?? '') ?></span>
                <a href="logout.php"
                    class="text-white border border-white rounded px-3 py-1 hover:bg-white hover:text-gray-800">Гарах</a>
            <?php endif; ?>
        </div>
    </div>
</nav>