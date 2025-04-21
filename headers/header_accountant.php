<!-- headers/header_accountant.php -->
<nav class="bg-red-600 p-4">
    <div class="container mx-auto flex justify-between items-center">
        <a href="accountant_dashboard.php" class="text-white font-bold text-xl">💰 Нягтлан</a>

        <div class="flex items-center space-x-4">
            <span class="text-white">Сайн байна уу, <?= htmlspecialchars($userName) ?></span>
            <a href="logout.php"
                class="text-white border border-white rounded px-3 py-1 hover:bg-white hover:text-red-600">Гарах</a>
        </div>
    </div>
</nav>