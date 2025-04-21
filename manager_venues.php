<?php
session_start();
require 'config/db.php';

// Менежер эрхээр нэвтэрсэн эсэхийг шалгах
function ensureManagerAccess()
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Manager') {
        header('Location: ../login.php');
        exit();
    }
}

// Эрхийн шалгалт хийх
ensureManagerAccess();

$managerID = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Менежер';

// Олон спортын төрөлтэй заалыг хайх
$venuesSql = "SELECT v.VenueID, v.Name, v.Location, v.HourlyPrice, 
             GROUP_CONCAT(DISTINCT vs.SportType SEPARATOR ', ') AS SportTypes,
             (SELECT COUNT(*) FROM booking b WHERE b.VenueID = v.VenueID) AS BookingCount,
             (SELECT COUNT(*) FROM payment p 
              JOIN booking b ON p.BookingID = b.BookingID 
              WHERE b.VenueID = v.VenueID AND p.Status = 'Paid') AS PaidBookingCount
             FROM Venue v
             LEFT JOIN VenueSports vs ON v.VenueID = vs.VenueID
             WHERE v.ManagerID = ?
             GROUP BY v.VenueID";
$stmt = $conn->prepare($venuesSql);
$stmt->bind_param("i", $managerID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $venues = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $venues = [];
}
$stmt->close();

// Заал устгах
if (isset($_GET['delete_venue_id'])) {
    $venueID = $_GET['delete_venue_id'];

    // Өгөгдлийн бүрэн бүтэн байдлыг хангахын тулд харилцан хамааралтай хүснэгтүүдээс устгах
    $conn->begin_transaction();

    try {
        // Эхлээд VenueSports хүснэгтээс устгах (гадаад түлхүүрийн хязгаарлалт)
        $deleteVenueSportsSql = "DELETE FROM VenueSports WHERE VenueID = ?";
        $stmt = $conn->prepare($deleteVenueSportsSql);
        $stmt->bind_param("i", $venueID);
        $stmt->execute();
        $stmt->close();

        // Дараа нь Venue хүснэгтээс устгах
        $deleteSql = "DELETE FROM Venue WHERE VenueID = ? AND ManagerID = ?";
        $stmt = $conn->prepare($deleteSql);
        $stmt->bind_param("ii", $venueID, $managerID);
        $stmt->execute();

        // Транзакцийг баталгаажуулах
        $conn->commit();

        $_SESSION['success_message'] = "Заал амжилттай устгагдлаа!";
    } catch (Exception $e) {
        // Алдаа гарвал транзакцийг буцаах
        $conn->rollback();
        $_SESSION['error_message'] = "Заал устгахдаа алдаа гарлаа: " . $e->getMessage();
    }

    $stmt->close();

    // Дахин устгах үйлдлийг хийхгүйн тулд шинэчлэх
    header("Location: manager_venues.php");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="mn">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <title>Таны нэмсэн заал</title>
    <style>
        .action-button {
            @apply bg-blue-500 text-white px-4 py-2 rounded text-center cursor-pointer transition-colors;
        }
        .action-button:hover {
            @apply bg-blue-600;
        }
        .action-button.edit {
            @apply bg-yellow-500;
        }
        .action-button.edit:hover {
            @apply bg-yellow-600;
        }
        .action-button.delete {
            @apply bg-red-500;
        }
        .action-button.delete:hover {
            @apply bg-red-600;
        }
        .action-button.gallery {
            @apply bg-green-500;
        }
        .action-button.gallery:hover {
            @apply bg-green-600;
        }
        .action-button.staff {
            @apply bg-purple-500;
        }
        .action-button.staff:hover {
            @apply bg-purple-600;
        }
        .action-button.assign {
            @apply bg-indigo-500;
        }
        .action-button.assign:hover {
            @apply bg-indigo-600;
        }
        .action-button.bookings {
            @apply bg-teal-500;
        }
        .action-button.bookings:hover {
            @apply bg-teal-600;
        }
        .action-button.payments {
            @apply bg-orange-500;
        }
        .action-button.payments:hover {
            @apply bg-orange-600;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include 'headers/header_manager.php'; ?>
    
    <div class="container mx-auto py-6 px-4">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Таны нэмсэн заал</h2>
                <a href="add_venue.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded shadow-md">
                    <i class="fas fa-plus mr-2"></i> Шинэ заал нэмэх
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Заалны жагсаалт -->
            <?php if (count($venues) > 0): ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php foreach ($venues as $venue): ?>
                        <div class="border rounded-lg shadow-sm overflow-hidden">
                            <div class="bg-blue-50 p-4">
                                <h3 class="text-lg font-semibold text-blue-800"><?php echo htmlspecialchars($venue['Name']); ?></h3>
                                <p class="text-gray-600"><i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($venue['Location']); ?></p>
                            </div>
                            
                            <div class="p-4">
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <?php 
                                    if (!empty($venue['SportTypes'])) {
                                        $sportTypes = explode(', ', $venue['SportTypes']);
                                        foreach ($sportTypes as $sport) {
                                            echo '<span class="bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">' . htmlspecialchars($sport) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div class="bg-gray-50 p-3 rounded">
                                        <p class="text-sm text-gray-500">Цагийн үнэ</p>
                                        <p class="text-lg font-semibold"><?php echo number_format($venue['HourlyPrice'], 0, '.', ','); ?>₮</p>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded">
                                        <p class="text-sm text-gray-500">Нийт захиалга</p>
                                        <p class="text-lg font-semibold"><?php echo $venue['BookingCount']; ?> <span class="text-xs text-gray-500">(<?php echo $venue['PaidBookingCount']; ?> төлөгдсөн)</span></p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-2">
                                    <a href="edit_venue.php?venue_id=<?php echo $venue['VenueID']; ?>" class="action-button edit">
                                        <i class="fas fa-edit mr-1"></i> Засах
                                    </a>
                                    <a href="?delete_venue_id=<?php echo $venue['VenueID']; ?>" 
                                       class="action-button delete"
                                       onclick="return confirm('Та энэ заалыг устгахдаа итгэлтэй байна уу?');">
                                        <i class="fas fa-trash mr-1"></i> Устгах
                                    </a>
                                    <a href="add_venue_image.php?venue_id=<?php echo $venue['VenueID']; ?>" 
                                       class="action-button gallery">
                                        <i class="fas fa-images mr-1"></i> Зураг
                                    </a>
                                    <a href="staff_show.php?venue_id=<?php echo $venue['VenueID']; ?>" 
                                       class="action-button staff">
                                        <i class="fas fa-users mr-1"></i> Туслах
                                    </a>
                                    <a href="assign_staff.php?venue_id=<?php echo $venue['VenueID']; ?>" 
                                       class="action-button assign">
                                        <i class="fas fa-user-plus mr-1"></i> Ажилтан оноох
                                    </a>
                                    <a href="venue_bookings.php?venue_id=<?php echo $venue['VenueID']; ?>" 
                                       class="action-button bookings">
                                        <i class="fas fa-calendar-alt mr-1"></i> Захиалга
                                    </a>
                                    <a href="venue_payments.php?venue_id=<?php echo $venue['VenueID']; ?>" 
                                       class="action-button payments col-span-2">
                                        <i class="fas fa-money-bill-wave mr-1"></i> Төлбөрүүд
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 my-4">
                    <p>Та ямар ч заал нэмээгүй байна. Шинэ заал нэмэхийн тулд дээрх товчийг дарна уу.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Font Awesome иконы нэмэх -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>

</html>