<?php
session_start();
require 'config/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Check if viewing details of a specific booking
$showDetails = false;
$bookingDetails = null;

if (isset($_GET['view_details']) && !empty($_GET['view_details'])) {
    $bookingID = $_GET['view_details'];
    $showDetails = true;
    
    // Fetch detailed booking information
    $detailSql = "
        SELECT 
            b.BookingID,
            v.Name AS VenueName,
            v.Location AS VenueLocation,
            v.SportType,
            t.StartTime,
            t.EndTime,
            t.DayOfWeek,
            b.BookingDate,
            b.Duration,
            b.Status AS BookingStatus,
            p.Status AS PaymentStatus,
            p.Amount,
            p.PaymentDate,
            p.BankName,
            p.AccountNumber,
            p.TransactionID
        FROM Booking b
        INNER JOIN Venue v ON b.VenueID = v.VenueID
        INNER JOIN VenueTimeSlot t ON b.SlotID = t.SlotID
        LEFT JOIN Payment p ON b.BookingID = p.BookingID
        WHERE b.BookingID = ? AND b.UserID = ?
    ";
    
    $detailStmt = $conn->prepare($detailSql);
    $detailStmt->bind_param('ii', $bookingID, $userID);
    $detailStmt->execute();
    $bookingDetails = $detailStmt->get_result()->fetch_assoc();
    
    if (!$bookingDetails) {
        $showDetails = false;
    }
}

// Fetch user's bookings from the database
$sql = "
    SELECT 
        b.BookingID,
        v.Name AS VenueName,
        v.Location AS VenueLocation,
        t.StartTime,
        t.EndTime,
        b.BookingDate,
        b.Duration,
        b.Status AS BookingStatus,
        p.Status AS PaymentStatus,
        p.Amount
    FROM Booking b
    INNER JOIN Venue v ON b.VenueID = v.VenueID
    INNER JOIN VenueTimeSlot t ON b.SlotID = t.SlotID
    LEFT JOIN Payment p ON b.BookingID = p.BookingID
    WHERE b.UserID = ?
    ORDER BY b.BookingDate DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userID);
$stmt->execute();
$result = $stmt->get_result();

// Check if a cancel request is made
if (isset($_GET['cancel_booking_id'])) {
    $cancelBookingID = $_GET['cancel_booking_id'];

    // Update the booking status to "Canceled"
    $cancelSql = "UPDATE Booking SET Status = 'Canceled' WHERE BookingID = ? AND UserID = ?";
    $cancelStmt = $conn->prepare($cancelSql);
    $cancelStmt->bind_param('ii', $cancelBookingID, $userID);
    $cancelStmt->execute();

    // Update the associated time slot's status to "Available"
    $slotUpdateSql = "
        UPDATE VenueTimeSlot 
        SET Status = 'Available' 
        WHERE SlotID = (
            SELECT SlotID 
            FROM Booking 
            WHERE BookingID = ?
        )
    ";
    $slotUpdateStmt = $conn->prepare($slotUpdateSql);
    $slotUpdateStmt->bind_param('i', $cancelBookingID);
    $slotUpdateStmt->execute();

    // Redirect to avoid re-submitting the cancel request
    header("Location: view_bookings.php");
    exit();
}

// Translate status values to Mongolian
function translateStatus($status) {
    $translations = [
        'Pending' => 'Хүлээгдэж буй',
        'Confirmed' => 'Баталгаажсан',
        'Canceled' => 'Цуцлагдсан',
        'Paid' => 'Төлөгдсөн',
        'Refunded' => 'Буцаагдсан',
        'Partially Refunded' => 'Хэсэгчлэн буцаагдсан',
        'Failed' => 'Амжилтгүй',
        'Available' => 'Боломжтой'
    ];
    
    return isset($translations[$status]) ? $translations[$status] : $status;
}

// Translate day of week
function translateDayOfWeek($day) {
    $translations = [
        'Sunday' => 'Ням',
        'Monday' => 'Даваа',
        'Tuesday' => 'Мягмар',
        'Wednesday' => 'Лхагва',
        'Thursday' => 'Пүрэв',
        'Friday' => 'Баасан',
        'Saturday' => 'Бямба'
    ];
    
    return isset($translations[$day]) ? $translations[$day] : $day;
}

// Translate sport type
function translateSportType($sport) {
    // Sport types are already in Mongolian, but if any need adjustment
    return $sport;
}

// Format date to Mongolian style
function formatMongolianDate($date) {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return date('Y оны m сарын d', $timestamp);
}

?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Миний захиалгууд</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto my-8 p-6 bg-white rounded-lg shadow-lg">
        <?php if ($showDetails && $bookingDetails): ?>
            <!-- Detailed booking view -->
            <div class="mb-4">
                <a href="view_bookings.php" class="text-blue-600 hover:underline">← Бүх захиалга руу буцах</a>
            </div>
            
            <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Захиалгын дэлгэрэнгүй мэдээлэл</h2>
            
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-300">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-xl font-bold mb-4">Байршлын мэдээлэл</h3>
                        <div class="mb-3">
                            <span class="font-semibold">Байршлын нэр:</span> 
                            <span><?php echo htmlspecialchars($bookingDetails['VenueName']); ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="font-semibold">Хаяг:</span> 
                            <span><?php echo htmlspecialchars($bookingDetails['VenueLocation']); ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="font-semibold">Спортын төрөл:</span> 
                            <span><?php echo translateSportType($bookingDetails['SportType']); ?></span>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-bold mb-4">Захиалгын мэдээлэл</h3>
                        <div class="mb-3">
                            <span class="font-semibold">Захиалгын дугаар:</span> 
                            <span><?php echo htmlspecialchars($bookingDetails['BookingID']); ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="font-semibold">Захиалгын өдөр:</span> 
                            <span><?php echo formatMongolianDate($bookingDetails['BookingDate']); ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="font-semibold">Гарах өдөр:</span> 
                            <span><?php echo translateDayOfWeek($bookingDetails['DayOfWeek']); ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="font-semibold">Эхлэх цаг:</span> 
                            <span><?php echo date('H:i', strtotime($bookingDetails['StartTime'])); ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="font-semibold">Дуусах цаг:</span> 
                            <span><?php echo date('H:i', strtotime($bookingDetails['EndTime'])); ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="font-semibold">Үргэлжлэх хугацаа:</span> 
                            <span><?php echo htmlspecialchars($bookingDetails['Duration']); ?> цаг</span>
                        </div>
                        <div class="mb-3">
                            <span class="font-semibold">Захиалгын төлөв:</span> 
                            <span class="<?php echo $bookingDetails['BookingStatus'] === 'Canceled' ? 'text-red-500' : 'text-green-500'; ?> font-bold">
                                <?php echo translateStatus($bookingDetails['BookingStatus']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <hr class="my-6 border-gray-300">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-xl font-bold mb-4">Төлбөрийн мэдээлэл</h3>
                        <div class="mb-3">
                            <span class="font-semibold">Төлбөрийн дүн:</span> 
                            <span><?php echo number_format($bookingDetails['Amount'], 0, '.', ','); ?>₮</span>
                        </div>
                        <div class="mb-3">
                            <span class="font-semibold">Төлбөрийн төлөв:</span> 
                            <span class="<?php echo $bookingDetails['PaymentStatus'] === 'Paid' ? 'text-green-500' : 'text-yellow-500'; ?> font-bold">
                                <?php echo translateStatus($bookingDetails['PaymentStatus']); ?>
                            </span>
                        </div>
                        <?php if (!empty($bookingDetails['PaymentDate'])): ?>
                        <div class="mb-3">
                            <span class="font-semibold">Төлбөр төлсөн огноо:</span> 
                            <span><?php echo formatMongolianDate($bookingDetails['PaymentDate']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <?php if (!empty($bookingDetails['BankName']) || !empty($bookingDetails['TransactionID'])): ?>
                        <h3 class="text-xl font-bold mb-4">Гүйлгээний мэдээлэл</h3>
                        <?php if (!empty($bookingDetails['BankName'])): ?>
                        <div class="mb-3">
                            <span class="font-semibold">Банкны нэр:</span> 
                            <span><?php echo htmlspecialchars($bookingDetails['BankName']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bookingDetails['AccountNumber'])): ?>
                        <div class="mb-3">
                            <span class="font-semibold">Дансны дугаар:</span> 
                            <span><?php echo htmlspecialchars($bookingDetails['AccountNumber']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($bookingDetails['TransactionID'])): ?>
                        <div class="mb-3">
                            <span class="font-semibold">Гүйлгээний дугаар:</span> 
                            <span><?php echo htmlspecialchars($bookingDetails['TransactionID']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($bookingDetails['BookingStatus'] !== 'Canceled'): ?>
                <div class="mt-8 text-center">
                    <a href="view_bookings.php?cancel_booking_id=<?php echo $bookingDetails['BookingID']; ?>" 
                       class="inline-block px-6 py-2 text-white bg-red-500 rounded-lg hover:bg-red-600 transition duration-200"
                       onclick="return confirm('Та энэ захиалгыг цуцлахдаа итгэлтэй байна уу?')">
                        Захиалга цуцлах
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- Bookings list view -->
            <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Миний захиалгууд</h2>

            <?php if ($result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto border-collapse border border-gray-300">
                        <thead class="bg-blue-600 text-white">
                            <tr>
                                <th class="px-4 py-2 border border-gray-300">#</th>
                                <th class="px-4 py-2 border border-gray-300">Байршлын нэр</th>
                                <th class="px-4 py-2 border border-gray-300">Хаяг</th>
                                <th class="px-4 py-2 border border-gray-300">Огноо</th>
                                <th class="px-4 py-2 border border-gray-300">Цаг</th>
                                <th class="px-4 py-2 border border-gray-300">Үргэлжлэх</th>
                                <th class="px-4 py-2 border border-gray-300">Төлбөр</th>
                                <th class="px-4 py-2 border border-gray-300">Төлбөрийн төлөв</th>
                                <th class="px-4 py-2 border border-gray-300">Захиалгын төлөв</th>
                                <th class="px-4 py-2 border border-gray-300">Үйлдлүүд</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                                <tr class="odd:bg-white even:bg-gray-100 hover:bg-gray-200">
                                    <td class="px-4 py-2 border border-gray-300"><?php echo $i++; ?></td>
                                    <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['VenueName']); ?></td>
                                    <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['VenueLocation']); ?></td>
                                    <td class="px-4 py-2 border border-gray-300"><?php echo date('Y-m-d', strtotime($row['BookingDate'])); ?></td>
                                    <td class="px-4 py-2 border border-gray-300">
                                        <?php echo date('H:i', strtotime($row['StartTime'])); ?> - 
                                        <?php echo date('H:i', strtotime($row['EndTime'])); ?>
                                    </td>
                                    <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['Duration']); ?> цаг</td>
                                    <td class="px-4 py-2 border border-gray-300"><?php echo number_format($row['Amount'], 0, '.', ','); ?>₮</td>
                                    <td class="px-4 py-2 border border-gray-300">
                                        <span class="<?php echo $row['PaymentStatus'] === 'Paid' ? 'text-green-500' : 'text-yellow-500'; ?> font-semibold">
                                            <?php echo translateStatus($row['PaymentStatus']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 border border-gray-300">
                                        <span class="<?php echo $row['BookingStatus'] === 'Canceled' ? 'text-red-500' : 'text-green-500'; ?> font-semibold">
                                            <?php echo translateStatus($row['BookingStatus']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 border border-gray-300 text-center">
                                        <a href="view_bookings.php?view_details=<?php echo $row['BookingID']; ?>"
                                           class="inline-block px-2 py-1 mb-1 text-sm font-semibold text-white bg-blue-500 rounded hover:bg-blue-600">
                                            Дэлгэрэнгүй
                                        </a>
                                        <?php if ($row['BookingStatus'] !== 'Canceled'): ?>
                                            <a href="view_bookings.php?cancel_booking_id=<?php echo $row['BookingID']; ?>" 
                                               class="inline-block px-2 py-1 text-sm font-semibold text-white bg-red-500 rounded hover:bg-red-600"
                                               onclick="return confirm('Та энэ захиалгыг цуцлахдаа итгэлтэй байна уу?')">
                                                Цуцлах
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-600 mt-6">Танд одоогоор захиалга байхгүй байна.</p>
            <?php endif; ?>
        <?php endif; ?>

        <a href="user_dashboard.php" class="block mt-8 w-full max-w-xs mx-auto text-center text-white bg-blue-500 px-4 py-2 rounded-lg hover:bg-blue-600 transition duration-200">
            Хяналтын самбар руу буцах
        </a>
    </div>
</body>
</html>