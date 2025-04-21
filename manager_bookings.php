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

// Шүүлтүүрийн утгуудыг авах
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$venueFilter = isset($_GET['venue_id']) ? $_GET['venue_id'] : '';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';

// Өгөгдлийн сангийн холболт шалгах
if (!$conn) {
    die("Холболт амжилтгүй: " . mysqli_connect_error());
}

// Шүүлтүүрийн нөхцөлүүдийг бүрдүүлэх
$whereConditions = ["v.ManagerID = ?"];
$params = [$managerID];
$paramTypes = "i";

if (!empty($statusFilter)) {
    $whereConditions[] = "b.Status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

if (!empty($venueFilter)) {
    $whereConditions[] = "b.VenueID = ?";
    $params[] = $venueFilter;
    $paramTypes .= "i";
}

if (!empty($dateFilter)) {
    $whereConditions[] = "b.BookingDate = ?";
    $params[] = $dateFilter;
    $paramTypes .= "s";
}

$whereClause = implode(" AND ", $whereConditions);

// Менежерийн заалны захиалгуудыг авах
$bookingsSql = "SELECT b.BookingID, u.Name as UserName, u.Email as UserEmail, 
                v.Name as VenueName, b.BookingDate, vts.StartTime, b.Duration, 
                v.HourlyPrice, b.Status, vts.SlotID, p.PaymentID, p.Status as PaymentStatus
                FROM booking b
                JOIN user u ON b.UserID = u.UserID
                JOIN venue v ON b.VenueID = v.VenueID
                JOIN venuetimeslot vts ON b.SlotID = vts.SlotID
                LEFT JOIN payment p ON b.BookingID = p.BookingID
                WHERE $whereClause
                ORDER BY b.BookingDate DESC, vts.StartTime ASC";

$stmt = $conn->prepare($bookingsSql);

// Prepare алдаа шалгах
if ($stmt === false) {
    die("Prepare алдаа: " . $conn->error);
}

$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$bookingsResult = $stmt->get_result();

// Менежерийн заалуудыг авах
$venuesSql = "SELECT VenueID, Name FROM venue WHERE ManagerID = ?";
$venuesStmt = $conn->prepare($venuesSql);
$venuesStmt->bind_param("i", $managerID);
$venuesStmt->execute();
$venuesResult = $venuesStmt->get_result();

// Захиалгын төлөв болон төлбөрийн төлөв шинэчлэх үйлдэл
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $bookingID = $_POST['booking_id'] ?? 0;
        $newStatus = $_POST['new_status'] ?? '';
        $paymentStatus = $_POST['payment_status'] ?? '';
        
        if ($bookingID && $newStatus) {
            // Холболтыг эхлүүлэх
            $conn->begin_transaction();
            
            try {
                // Захиалгын төлөв шинэчлэх
                $updateSql = "UPDATE booking b 
                              JOIN venue v ON b.VenueID = v.VenueID 
                              SET b.Status = ? 
                              WHERE b.BookingID = ? AND v.ManagerID = ?";
                
                $updateStmt = $conn->prepare($updateSql);
                
                if ($updateStmt === false) {
                    throw new Exception("Prepare алдаа (update): " . $conn->error);
                }
                
                $updateStmt->bind_param("sii", $newStatus, $bookingID, $managerID);
                $updateStmt->execute();
                
                // Төлбөрийн төлөв шинэчлэх (хэрэв төлбөрийн бичлэг байгаа бол)
                if (!empty($paymentStatus)) {
                    $paymentSql = "UPDATE payment p 
                                  JOIN booking b ON p.BookingID = b.BookingID
                                  JOIN venue v ON b.VenueID = v.VenueID 
                                  SET p.Status = ? 
                                  WHERE b.BookingID = ? AND v.ManagerID = ?";
                    
                    $paymentStmt = $conn->prepare($paymentSql);
                    
                    if ($paymentStmt === false) {
                        throw new Exception("Prepare алдаа (payment update): " . $conn->error);
                    }
                    
                    $paymentStmt->bind_param("sii", $paymentStatus, $bookingID, $managerID);
                    $paymentStmt->execute();
                }
                // Хэрэв төлбөр байхгүй бол үүсгэх
                else if ($newStatus == 'Confirmed') {
                    // Захиалгын дүнг тооцоолох
                    $amountSql = "SELECT b.Duration * v.HourlyPrice as Amount
                                 FROM booking b
                                 JOIN venue v ON b.VenueID = v.VenueID
                                 WHERE b.BookingID = ?";
                    
                    $amountStmt = $conn->prepare($amountSql);
                    $amountStmt->bind_param("i", $bookingID);
                    $amountStmt->execute();
                    $amountResult = $amountStmt->get_result();
                    $amountRow = $amountResult->fetch_assoc();
                    $amount = $amountRow['Amount'] ?? 0;
                    
                    // Төлбөрийн бичлэг үүсгэх
                    $createPaymentSql = "INSERT INTO payment (BookingID, Amount, Status, PaymentDate) 
                                        VALUES (?, ?, 'Paid', NOW())";
                    
                    $createPaymentStmt = $conn->prepare($createPaymentSql);
                    $createPaymentStmt->bind_param("id", $bookingID, $amount);
                    $createPaymentStmt->execute();
                }
                
                // Холболтыг баталгаажуулах
                $conn->commit();
                
                $_SESSION['success_message'] = "Захиалгын төлөв амжилттай шинэчлэгдлээ.";
            } catch (Exception $e) {
                // Алдаа гарвал буцаах
                $conn->rollback();
                $_SESSION['error_message'] = "Захиалгын төлөв шинэчлэх үед алдаа гарлаа: " . $e->getMessage();
            }
        }
    }
    
    // Хуудас шинэчлэх, формын дахин илгээлтээс зайлсхийх
    header("Location: manager_bookings.php" . (isset($_GET['status']) || isset($_GET['venue_id']) || isset($_GET['date']) ? '?' . http_build_query($_GET) : ''));
    exit();
}

// Дуусах цаг тооцоолох функц
function calculateEndTime($startTime, $durationHours) {
    $start = new DateTime($startTime);
    $end = clone $start;
    $end->add(new DateInterval('PT' . $durationHours . 'H'));
    return $end->format('H:i');
}

// Нийт үнэ тооцоолох функц
function calculateTotalPrice($pricePerHour, $duration) {
    return $pricePerHour * $duration;
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Захиалгын удирдлага</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
        }
        .status-pending {
            background-color: #ffeeba;
            color: #856404;
        }
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-canceled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .payment-status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            text-align: center;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'headers/header_manager.php'; ?>
    
    <div class="container mx-auto py-8 px-4">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Захиалгын удирдлага</h1>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Шүүлтүүрийн форм -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Захиалга шүүх</h2>
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Төлөв:</label>
                    <select name="status" id="status" class="w-full border border-gray-300 rounded-md p-2">
                        <option value="">Бүх төлөв</option>
                        <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Хүлээгдэж буй</option>
                        <option value="Confirmed" <?php echo $statusFilter === 'Confirmed' ? 'selected' : ''; ?>>Баталгаажсан</option>
                        <option value="Canceled" <?php echo $statusFilter === 'Canceled' ? 'selected' : ''; ?>>Цуцлагдсан</option>
                    </select>
                </div>
                
                <div>
                    <label for="venue_id" class="block text-sm font-medium text-gray-700 mb-1">Заал:</label>
                    <select name="venue_id" id="venue_id" class="w-full border border-gray-300 rounded-md p-2">
                        <option value="">Бүх заал</option>
                        <?php if ($venuesResult && $venuesResult->num_rows > 0): ?>
                            <?php while ($venue = $venuesResult->fetch_assoc()): ?>
                                <option value="<?php echo $venue['VenueID']; ?>" <?php echo $venueFilter == $venue['VenueID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($venue['Name']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Огноо:</label>
                    <input type="date" name="date" id="date" value="<?php echo $dateFilter; ?>" class="w-full border border-gray-300 rounded-md p-2">
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded">Шүүх</button>
                    <a href="manager_bookings.php" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">Цэвэрлэх</a>
                </div>
            </form>
        </div>
        
        <!-- Захиалгын хүснэгт -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Хэрэглэгч</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Заал</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Огноо</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Цаг</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үргэлжлэх</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үнэ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Төлөв</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($bookingsResult && $bookingsResult->num_rows > 0): ?>
                            <?php while ($booking = $bookingsResult->fetch_assoc()): 
                                $endTime = calculateEndTime($booking['StartTime'], $booking['Duration']);
                                $totalPrice = calculateTotalPrice($booking['HourlyPrice'], $booking['Duration']);
                                
                                // Захиалгын төлөвийг монгол руу хөрвүүлэх
                                $statusMongolian = $booking['Status'];
                                switch($booking['Status']) {
                                    case 'Pending': $statusMongolian = 'Хүлээгдэж буй'; break;
                                    case 'Confirmed': $statusMongolian = 'Баталгаажсан'; break;
                                    case 'Canceled': $statusMongolian = 'Цуцлагдсан'; break;
                                }
                                
                                // Төлбөрийн төлөвийг монгол руу хөрвүүлэх
                                $paymentStatusMongolian = $booking['PaymentStatus'] ?? '';
                                switch($booking['PaymentStatus']) {
                                    case 'Pending': $paymentStatusMongolian = 'Хүлээгдэж буй'; break;
                                    case 'Paid': $paymentStatusMongolian = 'Төлөгдсөн'; break;
                                    case 'Refunded': $paymentStatusMongolian = 'Буцаагдсан'; break;
                                    case 'Partially Refunded': $paymentStatusMongolian = 'Хэсэгчлэн буцаагдсан'; break;
                                    case 'Failed': $paymentStatusMongolian = 'Амжилтгүй'; break;
                                    case 'Canceled': $paymentStatusMongolian = 'Цуцлагдсан'; break;
                                }
                                
                                $statusClass = strtolower($booking['Status']);
                                $paymentStatusClass = strtolower(str_replace(' ', '-', $booking['PaymentStatus'] ?? ''));
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $booking['BookingID']; ?></td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['UserName']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($booking['UserEmail']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($booking['VenueName']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y/m/d', strtotime($booking['BookingDate'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('H:i', strtotime($booking['StartTime'])) . ' - ' . $endTime; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $booking['Duration']; ?> цаг</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($totalPrice, 0); ?> ₮</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="status-badge status-<?php echo $statusClass; ?>">
                                            <?php echo $statusMongolian; ?>
                                        </span>
                                        <?php if (!empty($booking['PaymentStatus'])): ?>
                                            <div class="mt-1">
                                                <span class="payment-status-badge status-<?php echo $paymentStatusClass; ?>">
                                                    Төлбөр: <?php echo $paymentStatusMongolian; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-1 px-3 rounded text-xs update-status-btn mb-1" 
                                                data-booking-id="<?php echo $booking['BookingID']; ?>"
                                                data-current-status="<?php echo $booking['Status']; ?>"
                                                data-payment-id="<?php echo $booking['PaymentID'] ?? ''; ?>"
                                                data-payment-status="<?php echo $booking['PaymentStatus'] ?? ''; ?>">
                                            Төлөв өөрчлөх
                                        </button>
                                        <a href="view_booking.php?id=<?php echo $booking['BookingID']; ?>" class="bg-green-500 hover:bg-green-600 text-white font-medium py-1 px-3 rounded text-xs ml-1">
                                            Дэлгэрэнгүй
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">Захиалга олдсонгүй</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Захиалгын төлөв шинэчлэх modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-bold mb-4">Захиалгын төлөв шинэчлэх</h2>
            <form action="" method="POST">
                <input type="hidden" name="booking_id" id="modal-booking-id">
                <input type="hidden" name="payment_id" id="modal-payment-id">
                
                <div class="mb-4">
                    <label for="new_status" class="block text-sm font-medium text-gray-700 mb-1">Захиалгын төлөв:</label>
                    <select name="new_status" id="new_status" required class="w-full border border-gray-300 rounded-md p-2">
                        <option value="Pending">Хүлээгдэж буй</option>
                        <option value="Confirmed">Баталгаажсан</option>
                        <option value="Canceled">Цуцлагдсан</option>
                    </select>
                </div>
                
                <div class="mb-4" id="payment-status-container">
                    <label for="payment_status" class="block text-sm font-medium text-gray-700 mb-1">Төлбөрийн төлөв:</label>
                    <select name="payment_status" id="payment_status" class="w-full border border-gray-300 rounded-md p-2">
                        <option value="">Төлбөр байхгүй</option>
                        <option value="Pending">Хүлээгдэж буй</option>
                        <option value="Paid">Төлөгдсөн</option>
                        <option value="Failed">Амжилтгүй</option>
                        <option value="Canceled">Цуцлагдсан</option>
                    </select>
                </div>
                
                <div class="text-sm text-gray-600 bg-gray-100 p-3 mb-4 rounded">
                    <p class="font-semibold">Захиалга/төлбөрийн холбоо:</p>
                    <ul class="list-disc pl-5 mt-1">
                        <li>"Баталгаажсан" захиалга төлбөр "Төлөгдсөн" төлөвтэй байх ёстой.</li>
                        <li>Төлбөр "Төлөгдсөн" үед захиалга автоматаар "Баталгаажсан" болно.</li>
                        <li>Захиалга "Цуцлагдсан" үед төлбөр автоматаар "Цуцлагдсан" болно.</li>
                    </ul>
                </div>
                
                <button type="submit" name="update_status" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded">Шинэчлэх</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Төлөв шинэчлэх modal
        var statusModal = document.getElementById('statusModal');
        var statusButtons = document.querySelectorAll('.update-status-btn');
        var statusCloseBtn = statusModal.querySelector('.close');
        var newStatusSelect = document.getElementById('new_status');
        var paymentStatusSelect = document.getElementById('payment_status');
        var paymentStatusContainer = document.getElementById('payment-status-container');
        
        statusButtons.forEach(function(btn) {
            btn.onclick = function() {
                var bookingId = this.getAttribute('data-booking-id');
                var currentStatus = this.getAttribute('data-current-status');
                var paymentId = this.getAttribute('data-payment-id');
                var paymentStatus = this.getAttribute('data-payment-status');
                
                document.getElementById('modal-booking-id').value = bookingId;
                newStatusSelect.value = currentStatus;
                
                // Төлбөрийн ID, төлөв
                document.getElementById('modal-payment-id').value = paymentId;
                
                if (paymentId && paymentStatus) {
                    paymentStatusSelect.value = paymentStatus;
                    paymentStatusContainer.style.display = 'block';
                } else {
                    paymentStatusSelect.value = '';
                    paymentStatusContainer.style.display = 'block';
                }
                
                statusModal.style.display = "block";
            }
        });
        
        // Холбоотой төлөв өөрчлөлт
        newStatusSelect.addEventListener('change', function() {
            if (this.value === 'Confirmed') {
                paymentStatusSelect.value = 'Paid';
            } else if (this.value === 'Canceled') {
                paymentStatusSelect.value = 'Canceled';
            }
        });
        
        paymentStatusSelect.addEventListener('change', function() {
            if (this.value === 'Paid') {
                newStatusSelect.value = 'Confirmed';
            } else if (this.value === 'Canceled') {
                newStatusSelect.value = 'Canceled';
            }
        });
        
        statusCloseBtn.onclick = function() {
            statusModal.style.display = "none";
        }
        
        // Modalыг гадна талаас нь дарахад хаах
        window.onclick = function(event) {
            if (event.target == statusModal) {
                statusModal.style.display = "none";
            }
        }
    });
    </script>
</body>
</html>