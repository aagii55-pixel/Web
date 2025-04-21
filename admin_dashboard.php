<?php
session_start();
require 'config/db.php';

function ensureAdminAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Admin') {
        header('Location: ../login.php');
        exit();
    }
}

ensureAdminAccess();

$timeout = 600;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: ../a/login.php');
    exit();
}
$pendingCountQuery = "SELECT COUNT(*) as pendingCount FROM PromotionRequest WHERE RequestStatus = 'Pending'";
$pendingCountResult = $conn->query($pendingCountQuery);
$pendingCountRow = $pendingCountResult->fetch_assoc();
$pendingCount = $pendingCountRow['pendingCount'];

$_SESSION['last_activity'] = time();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../a/logout.php');
    exit();
}

$managersSql = "SELECT COUNT(*) as totalManagers FROM User WHERE RoleID = (SELECT RoleID FROM Role WHERE RoleName = 'Manager')";
$result = $conn->query($managersSql);
$totalManagers = $result->fetch_assoc()['totalManagers'];

$usersSql = "SELECT COUNT(*) as totalUsers FROM User WHERE RoleID = (SELECT RoleID FROM Role WHERE RoleName = 'User')";
$result = $conn->query($usersSql);
$totalUsers = $result->fetch_assoc()['totalUsers'];

$venuesSql = "SELECT COUNT(*) as totalVenues FROM Venue";
$result = $conn->query($venuesSql);
$totalVenues = $result->fetch_assoc()['totalVenues'];

$weekOrdersSql = "SELECT COUNT(*) as weekOrders FROM Booking WHERE BookingDate >= CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY";
$result = $conn->query($weekOrdersSql);
$weekOrders = $result->fetch_assoc()['weekOrders'];

$monthOrdersSql = "SELECT COUNT(*) as monthOrders FROM Booking WHERE MONTH(BookingDate) = MONTH(CURDATE())";
$result = $conn->query($monthOrdersSql);
$monthOrders = $result->fetch_assoc()['monthOrders'];

$yearOrdersSql = "SELECT COUNT(*) as yearOrders FROM Booking WHERE YEAR(BookingDate) = YEAR(CURDATE())";
$result = $conn->query($yearOrdersSql);
$yearOrders = $result->fetch_assoc()['yearOrders'];

$usersMonthSql = "SELECT COUNT(*) as usersThisMonth FROM User WHERE MONTH(CreatedAt) = MONTH(CURDATE())";
$result = $conn->query($usersMonthSql);
$usersThisMonth = $result->fetch_assoc()['usersThisMonth'];

$totalBookingsSql = "SELECT COUNT(*) as totalBookings FROM Booking";
$result = $conn->query($totalBookingsSql);
$totalBookings = $result->fetch_assoc()['totalBookings'];

$totalPaymentsSql = "SELECT SUM(Amount) as totalPayments FROM Payment";
$result = $conn->query($totalPaymentsSql);
$totalPayments = $result->fetch_assoc()['totalPayments'];

$avgBookingSql = "SELECT COUNT(*) / COUNT(DISTINCT UserID) as avgBookingsPerUser FROM Booking";
$result = $conn->query($avgBookingSql);
$avgBookingsPerUser = $result->fetch_assoc()['avgBookingsPerUser'];

$totalIncomeSql = "SELECT SUM(Amount) as totalIncome FROM Payment";
$result = $conn->query($totalIncomeSql);
$totalIncome = $result->fetch_assoc()['totalIncome'];

// Get monthly bookings for chart
$monthlyBookingsSql = "SELECT 
                          MONTH(BookingDate) as month, 
                          COUNT(*) as booking_count 
                        FROM Booking 
                        WHERE YEAR(BookingDate) = YEAR(CURDATE()) 
                        GROUP BY MONTH(BookingDate)
                        ORDER BY MONTH(BookingDate)";
$monthlyResult = $conn->query($monthlyBookingsSql);
$monthlyBookings = [];
$monthNames = [
    1 => '1', 2 => '2', 3 => '3', 4 => '4', 
    5 => '5', 6 => '6', 7 => '7', 8 => '8', 
    9 => '9', 10 => '10', 11 => '11', 12 => '12F'
];

// Initialize all months with zero
for ($i = 1; $i <= 12; $i++) {
    $monthlyBookings[$i] = 0;
}

// Fill in actual data
while ($row = $monthlyResult->fetch_assoc()) {
    $monthlyBookings[$row['month']] = (int)$row['booking_count'];
}

// Get booking status data for pie chart
$bookingStatusSql = "SELECT Status, COUNT(*) as count FROM Booking GROUP BY Status";
$statusResult = $conn->query($bookingStatusSql);
$bookingStatus = [];

while ($row = $statusResult->fetch_assoc()) {
    $bookingStatus[$row['Status']] = (int)$row['count'];
}

// Get top 5 most booked venues
$topVenuesSql = "SELECT v.Name, COUNT(b.BookingID) as booking_count 
                 FROM Booking b 
                 JOIN Venue v ON b.VenueID = v.VenueID 
                 GROUP BY b.VenueID 
                 ORDER BY booking_count DESC 
                 LIMIT 5";
$topVenuesResult = $conn->query($topVenuesSql);
$topVenues = [];

while ($row = $topVenuesResult->fetch_assoc()) {
    $topVenues[$row['Name']] = (int)$row['booking_count'];
}

$conn->close();

// Prepare data for charts
$monthlyLabels = json_encode(array_values($monthNames));
$monthlyData = json_encode(array_values($monthlyBookings));

$statusLabels = json_encode(array_keys($bookingStatus));
$statusData = json_encode(array_values($bookingStatus));

$venueLabels = json_encode(array_keys($topVenues));
$venueData = json_encode(array_values($topVenues));

// Calculate user type distribution
$totalPeople = $totalUsers + $totalManagers;
$userPercentage = ($totalPeople > 0) ? round(($totalUsers / $totalPeople) * 100) : 0;
$managerPercentage = ($totalPeople > 0) ? round(($totalManagers / $totalPeople) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ Дашбоард</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
<nav class="bg-gray-800 text-white px-6 py-4 shadow-lg flex justify-between items-center">
    <div class="flex space-x-6">
        <!-- Dashboard Link -->
        <a href="admin_dashboard.php" class="relative <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">Дашбоард</a>
        <a href="admin_search_reports.php" class="relative <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">Хайлт & Тайлан</a>
        <!-- Promotion Requests Link with Pending Count -->
        <a href="admin_promotion_requests.php" class="relative flex items-center <?php echo basename($_SERVER['PHP_SELF']) == 'admin_promotion_requests.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">
            Хамтрах Хүсэлтүүд
            <?php if ($pendingCount > 0): ?>
                <span class="absolute -top-2 -right-3 bg-red-600 text-white text-xs font-semibold rounded-full px-2 py-1"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Manage Users Link -->
        <a href="manage_users.php" class="relative <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">Хэрэглэгчийг удирдах</a>
    </div>
    
    <!-- Logout Button -->
    <a href="?logout=true" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-full transition-all duration-300 ease-in-out hover:scale-105">Гарах</a>
</nav>

<div class="container mx-auto my-8 px-4">
    <h2 class="text-3xl font-bold text-center mb-8">Админ Дашбоард</h2>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white shadow-md rounded-lg p-6 flex items-center">
            <div class="bg-blue-100 p-3 rounded-full mr-4">
                <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Нийт Хэрэглэгчид</p>
                <p class="text-2xl font-bold"><?php echo $totalUsers; ?></p>
            </div>
        </div>
        <div class="bg-white shadow-md rounded-lg p-6 flex items-center">
            <div class="bg-green-100 p-3 rounded-full mr-4">
                <i class="fas fa-user-tie text-green-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Нийт Менежер</p>
                <p class="text-2xl font-bold"><?php echo $totalManagers; ?></p>
            </div>
        </div>
        <div class="bg-white shadow-md rounded-lg p-6 flex items-center">
            <div class="bg-purple-100 p-3 rounded-full mr-4">
                <i class="fas fa-map-marker-alt text-purple-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Нийт Заал</p>
                <p class="text-2xl font-bold"><?php echo $totalVenues; ?></p>
            </div>
        </div>
        <div class="bg-white shadow-md rounded-lg p-6 flex items-center">
            <div class="bg-yellow-100 p-3 rounded-full mr-4">
                <i class="fas fa-clipboard-list text-yellow-600 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Нийт Захиалга</p>
                <p class="text-2xl font-bold"><?php echo $totalBookings; ?></p>
            </div>
        </div>
    </div>

    <!-- Time-based Bookings -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white shadow-md rounded-lg p-6 flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Энэ 7 хоногийн</p>
                <p class="text-2xl font-bold"><?php echo $weekOrders; ?></p>
                <p class="text-xs text-gray-500">Захиалгууд</p>
            </div>
            <div class="bg-indigo-100 p-3 rounded-full">
                <i class="fas fa-calendar-week text-indigo-600 text-xl"></i>
            </div>
        </div>
        <div class="bg-white shadow-md rounded-lg p-6 flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Энэ Сарын</p>
                <p class="text-2xl font-bold"><?php echo $monthOrders; ?></p>
                <p class="text-xs text-gray-500">Захиалгууд</p>
            </div>
            <div class="bg-teal-100 p-3 rounded-full">
                <i class="fas fa-calendar-alt text-teal-600 text-xl"></i>
            </div>
        </div>
        <div class="bg-white shadow-md rounded-lg p-6 flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Энэ Оны</p>
                <p class="text-2xl font-bold"><?php echo $yearOrders; ?></p>
                <p class="text-xs text-gray-500">Захиалгууд</p>
            </div>
            <div class="bg-pink-100 p-3 rounded-full">
                <i class="fas fa-calendar-check text-pink-600 text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Monthly Bookings Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold mb-4">Сарын захиалгууд</h3>
            <div class="h-80">
                <canvas id="monthlyBookingsChart"></canvas>
            </div>
        </div>

        <!-- Booking Status Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold mb-4">Захиалгын төлөв</h3>
            <div class="h-80">
                <canvas id="bookingStatusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Additional Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Top Venues Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold mb-4">Шилдэг 5 хамгийн их захиалгатай газрууд</h3>
            <div class="h-80">
                <canvas id="topVenuesChart"></canvas>
            </div>
        </div>

        <!-- User Type Distribution -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold mb-4">Хэрэглэгчийн хувь</h3>
            <div class="flex justify-center items-center h-80">
                <div class="w-full max-w-xs">
                    <div class="mb-6">
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-blue-600">Хэрэглэгчид</span>
                            <span class="text-sm font-medium text-blue-600"><?php echo $userPercentage; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div class="bg-blue-600 h-4 rounded-full" style="width: <?php echo $userPercentage; ?>%"></div>
                        </div>
                        <div class="text-right text-xs text-gray-500 mt-1"><?php echo $totalUsers; ?> хэрэглэгч</div>
                    </div>
                    
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-green-600">Менежерууд</span>
                            <span class="text-sm font-medium text-green-600"><?php echo $managerPercentage; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4">
                            <div class="bg-green-600 h-4 rounded-full" style="width: <?php echo $managerPercentage; ?>%"></div>
                        </div>
                        <div class="text-right text-xs text-gray-500 mt-1"><?php echo $totalManagers; ?> менежер</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Setup Monthly Bookings Chart
const monthlyCtx = document.getElementById('monthlyBookingsChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: <?php echo $monthlyLabels; ?>,
        datasets: [{
            label: 'Захиалгын тоо',
            data: <?php echo $monthlyData; ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.6)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                precision: 0
            }
        }
    }
});

// Setup Booking Status Chart
const statusCtx = document.getElementById('bookingStatusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'pie',
    data: {
        labels: <?php echo $statusLabels; ?>,
        datasets: [{
            data: <?php echo $statusData; ?>,
            backgroundColor: [
                'rgba(52, 211, 153, 0.7)',
                'rgba(239, 68, 68, 0.7)',
                'rgba(251, 191, 36, 0.7)',
                'rgba(156, 163, 175, 0.7)',
                'rgba(96, 165, 250, 0.7)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});

// Setup Top Venues Chart
const venuesCtx = document.getElementById('topVenuesChart').getContext('2d');
new Chart(venuesCtx, {
    type: 'horizontalBar',
    data: {
        labels: <?php echo $venueLabels; ?>,
        datasets: [{
            label: 'Захиалгын тоо',
            data: <?php echo $venueData; ?>,
            backgroundColor: 'rgba(124, 58, 237, 0.6)',
            borderColor: 'rgba(124, 58, 237, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                precision: 0
            }
        }
    }
});
</script>
</body>
</html>