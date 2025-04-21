<?php
session_start();
require 'config/db.php';

// Function to ensure that only managers can access this page
function ensureManagerAccess()
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Manager') {
        header('Location: ../login.php');
        exit();
    }
}

// Enforce role check
ensureManagerAccess();

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../a/logout.php');
    exit();
}

$managerID = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Менежер';

// Get total number of venues
$venuesSql = "SELECT COUNT(*) as total FROM Venue WHERE ManagerID = ?";
$stmt = $conn->prepare($venuesSql);
$stmt->bind_param("i", $managerID);
$stmt->execute();
$totalVenues = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get total number of bookings
$bookingsSql = "SELECT COUNT(*) as total FROM Booking b 
                JOIN VenueTimeSlot ts ON b.SlotID = ts.SlotID 
                JOIN Venue v ON ts.VenueID = v.VenueID 
                WHERE v.ManagerID = ?";
$stmt = $conn->prepare($bookingsSql);
$stmt->bind_param("i", $managerID);
$stmt->execute();
$totalBookings = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get revenue statistics
$revenueSql = "SELECT 
                SUM(b.Duration * v.HourlyPrice) as total_revenue,
                COUNT(CASE WHEN b.Status = 'Confirmed' THEN 1 END) as confirmed_bookings,
                COUNT(CASE WHEN b.Status = 'Canceled' THEN 1 END) as canceled_bookings
               FROM Booking b
               JOIN VenueTimeSlot ts ON b.SlotID = ts.SlotID
               JOIN Venue v ON ts.VenueID = v.VenueID
               WHERE v.ManagerID = ?";
$stmt = $conn->prepare($revenueSql);
$stmt->bind_param("i", $managerID);
$stmt->execute();
$revenueStats = $stmt->get_result()->fetch_assoc();
$totalRevenue = $revenueStats['total_revenue'] ?? 0;
$confirmedBookings = $revenueStats['confirmed_bookings'] ?? 0;
$canceledBookings = $revenueStats['canceled_bookings'] ?? 0;
$stmt->close();

// Get bookings by month for chart
$bookingsByMonthSql = "SELECT 
                         MONTH(b.BookingDate) as month,
                         COUNT(*) as booking_count
                       FROM Booking b
                       JOIN VenueTimeSlot ts ON b.SlotID = ts.SlotID
                       JOIN Venue v ON ts.VenueID = v.VenueID
                       WHERE v.ManagerID = ?
                       GROUP BY MONTH(b.BookingDate)
                       ORDER BY MONTH(b.BookingDate)";
$stmt = $conn->prepare($bookingsByMonthSql);
$stmt->bind_param("i", $managerID);
$stmt->execute();
$result = $stmt->get_result();
$bookingsByMonth = [];
$monthNames = ['1' => 'Январь', '2' => 'Февраль', '3' => 'Март', '4' => 'Апрель', 
               '5' => 'Май', '6' => 'Июнь', '7' => 'Июль', '8' => 'Август', 
               '9' => 'Сентябрь', '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь'];

while ($row = $result->fetch_assoc()) {
    $bookingsByMonth[$monthNames[$row['month']]] = $row['booking_count'];
}
$stmt->close();

// Get most booked venues
$popularVenuesSql = "SELECT 
                       v.Name as venue_name,
                       COUNT(*) as booking_count
                     FROM Booking b
                     JOIN VenueTimeSlot ts ON b.SlotID = ts.SlotID
                     JOIN Venue v ON ts.VenueID = v.VenueID
                     WHERE v.ManagerID = ?
                     GROUP BY v.VenueID
                     ORDER BY booking_count DESC
                     LIMIT 5";
$stmt = $conn->prepare($popularVenuesSql);
$stmt->bind_param("i", $managerID);
$stmt->execute();
$result = $stmt->get_result();
$popularVenues = [];
while ($row = $result->fetch_assoc()) {
    $popularVenues[$row['venue_name']] = $row['booking_count'];
}
$stmt->close();

// Fetch recent notifications
$notificationsSql = "SELECT NotificationID, Title, Message, Date, IsRead 
                     FROM Notifications 
                     WHERE UserID = ? 
                     ORDER BY Date DESC 
                     LIMIT 5";
$stmt = $conn->prepare($notificationsSql);
$stmt->bind_param("i", $managerID);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// Convert data for chart.js
$bookingMonthsData = json_encode(array_keys($bookingsByMonth));
$bookingCountsData = json_encode(array_values($bookingsByMonth));

$venueNamesData = json_encode(array_keys($popularVenues));
$venueBookingsData = json_encode(array_values($popularVenues));
?>

<!DOCTYPE html>
<html lang="mn">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Менежерын самбар</title>
    <style>
        .notification-popup {
            display: none;
            position: fixed;
            top: 20%;
            right: 10%;
            background: white;
            border: 1px solid #ccc;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 300px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            z-index: 9999;
        }

        .notification-icon {
            cursor: pointer;
            font-size: 24px;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php 
    // Include header with manager navigation
    $userName = $_SESSION['user_name'] ?? 'Менежер';
    include 'headers/header_manager.php'; 
    ?>

    <div class="container mx-auto py-6">
        <h2 class="text-3xl font-bold text-center mb-6">Менежерын самбар</h2>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Нийт заал</p>
                        <p class="font-bold text-2xl"><?= $totalVenues ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Нийт захиалга</p>
                        <p class="font-bold text-2xl"><?= $totalBookings ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Нийт орлого</p>
                        <p class="font-bold text-2xl"><?= number_format($totalRevenue) ?>₮</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Батлагдсан захиалга</p>
                        <p class="font-bold text-2xl"><?= $confirmedBookings ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold mb-4">Сарын захиалгын тоо</h3>
                <canvas id="bookingsChart"></canvas>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold mb-4">Хамгийн их захиалгатай заалууд</h3>
                <canvas id="venuesChart"></canvas>
            </div>
        </div>

        <!-- Notification Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Сүүлийн мэдэгдлүүд</h3>
                <span class="notification-icon text-blue-500" onclick="toggleNotifications()">🔔</span>
                
                <!-- Notification Popup -->
                <div id="notificationPopup" class="notification-popup">
                    <h4 class="font-bold text-xl mb-2">Мэдэгдлүүд</h4>
                    <?php if (count($notifications) > 0): ?>
                        <ul>
                            <?php foreach ($notifications as $notification): ?>
                                <li class="border-b py-2">
                                    <strong><?php echo htmlspecialchars($notification['Title']); ?></strong>
                                    <p><?php echo htmlspecialchars($notification['Message']); ?></p>
                                    <small class="text-gray-500"><?php echo htmlspecialchars($notification['Date']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-center">Мэдэгдэл олдсонгүй.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (count($notifications) > 0): ?>
                <div class="divide-y">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="py-3">
                            <div class="flex justify-between">
                                <h4 class="font-semibold"><?= htmlspecialchars($notification['Title']) ?></h4>
                                <span class="text-sm text-gray-500"><?= htmlspecialchars($notification['Date']) ?></span>
                            </div>
                            <p class="text-gray-600"><?= htmlspecialchars($notification['Message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center py-4">Мэдэгдэл олдсонгүй.</p>
            <?php endif; ?>
        </div>

        <!-- Quick Links -->
        <!-- Quick Links -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <a href="manager_venues.php" class="bg-blue-500 text-white rounded-lg shadow-md p-6 hover:bg-blue-600 transition">
                <h3 class="text-xl font-semibold mb-2">Таны нэмсэн заал</h3>
                <p>Заал, талбайнуудыг удирдах</p>
            </a>
            <a href="manager_bookings.php" class="bg-green-500 text-white rounded-lg shadow-md p-6 hover:bg-green-600 transition">
                <h3 class="text-xl font-semibold mb-2">Захиалгууд</h3>
                <p>Бүх захиалгуудыг удирдах</p>
            </a>
            <a href="manager_payments.php" class="bg-yellow-500 text-white rounded-lg shadow-md p-6 hover:bg-yellow-600 transition">
                <h3 class="text-xl font-semibold mb-2">Төлбөрүүд</h3>
                <p>Төлбөрүүдийг шалгах, буцаах</p>
            </a>
            <a href="add_venue.php" class="bg-purple-500 text-white rounded-lg shadow-md p-6 hover:bg-purple-600 transition">
                <h3 class="text-xl font-semibold mb-2">Заал нэмэх</h3>
                <p>Шинэ заал нэмэх</p>
            </a>
        </div>
    </div>

    <script>
        // Toggle notifications popup
        function toggleNotifications() {
            var popup = document.getElementById('notificationPopup');
            popup.style.display = (popup.style.display === 'none' || popup.style.display === '') ? 'block' : 'none';
        }

        // Setup Charts
        window.onload = function() {
            // Bookings by month chart
            const monthsData = <?= $bookingMonthsData ?>;
            const bookingCounts = <?= $bookingCountsData ?>;
            
            const bookingsCtx = document.getElementById('bookingsChart').getContext('2d');
            new Chart(bookingsCtx, {
                type: 'bar',
                data: {
                    labels: monthsData,
                    datasets: [{
                        label: 'Захиалгын тоо',
                        data: bookingCounts,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            // Venues popularity chart
            const venueNames = <?= $venueNamesData ?>;
            const venueBookings = <?= $venueBookingsData ?>;
            
            const venuesCtx = document.getElementById('venuesChart').getContext('2d');
            new Chart(venuesCtx, {
                type: 'pie',
                data: {
                    labels: venueNames,
                    datasets: [{
                        data: venueBookings,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)',
                            'rgba(255, 206, 86, 0.5)',
                            'rgba(75, 192, 192, 0.5)',
                            'rgba(153, 102, 255, 0.5)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                }
            });
        }
    </script>
</body>
</html>