<!-- headers/header_venu_staff.php -->
<nav class="bg-purple-700 p-4">
    <div class="container mx-auto flex justify-between items-center">
        <a href="venue_staff_dashboard.php" class="text-white font-bold text-xl">🏟️ Ажилтан</a>

        <div class="flex items-center space-x-4">
            <span class="text-white">Сайн байна уу, <?= htmlspecialchars($userName) ?></span>
            <a href="logout.php"
                class="text-white border border-white rounded px-3 py-1 hover:bg-white hover:text-purple-700">Гарах</a>
        </div>
    </div>
</nav>