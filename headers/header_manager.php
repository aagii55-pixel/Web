<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Зөвхөн менежер эрхтэй хэрэглэгчид нэвтрэх боломжтой
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Manager') {
    header('Location: ../login.php');
    exit();
}

// Одоогийн хуудасны нэрийг авах
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="bg-gray-800 p-4">
    <div class="container mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="flex items-center w-full md:w-auto justify-between">
                <a href="Manager_dashboard.php" class="text-white font-bold text-xl">GoZaal</a>
                <button id="menuToggle" class="md:hidden text-white focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                    </svg>
                </button>
            </div>
            
            <div id="mobileMenu" class="hidden md:flex flex-col md:flex-row w-full md:w-auto mt-4 md:mt-0">
                <div class="flex flex-col md:flex-row md:space-x-4">
                    <a href="manager_dashboard.php" class="<?php echo $currentPage == 'manager_dashboard.php' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium mb-2 md:mb-0">
                        Хянах самбар
                    </a>
                    <a href="manager_venues.php" class="<?php echo $currentPage == 'manager_venues.php' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium mb-2 md:mb-0">
                        Заал
                    </a>
                    <a href="manager_bookings.php" class="<?php echo $currentPage == 'manager_bookings.php' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium mb-2 md:mb-0">
                        Захиалга
                    </a>
                    <a href="manager_payments.php" class="<?php echo $currentPage == 'manager_payments.php' ? 'bg-gray-900' : 'hover:bg-gray-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium mb-2 md:mb-0">
                        Төлбөр
                    </a>
                    
                </div>
                <div class="flex items-center mt-4 md:mt-0 md:ml-6">
    <span class="text-white mr-4">Сайн байна уу, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Менежер') ?></span>
    
    <!-- Add role switch button -->
    <a href="switch_role.php?role=user" class="text-white border border-yellow-300 rounded-md px-3 py-1 mr-3 hover:bg-yellow-500 hover:text-gray-800">
    <i class="fas fa-exchange-alt mr-1"></i> Хэрэглэгчийн горимд шилжих
</a>
    
    <a href="logout.php" class="text-white border border-white rounded px-3 py-1 hover:bg-white hover:text-gray-800">
        Гарах
    </a>
</div>
            </div>
        </div>
    </div>
</nav>

<script>
    // Гар утасны цэсийг нээх, хаах
    document.addEventListener('DOMContentLoaded', function() {
        const menuToggle = document.getElementById('menuToggle');
        const mobileMenu = document.getElementById('mobileMenu');
        
        menuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });

        // Том дэлгэцэнд цэс харагдах
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) { // md breakpoint in Tailwind
                mobileMenu.classList.remove('hidden');
            }
        });
    });
</script>