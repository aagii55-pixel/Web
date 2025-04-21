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

// Шүүлтүүрийн нөхцөлүүдийг бүрдүүлэх
$whereConditions = ["v.ManagerID = ?"];
$params = [$managerID];
$paramTypes = "i";

if (!empty($statusFilter)) {
    $whereConditions[] = "p.Status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

if (!empty($venueFilter)) {
    $whereConditions[] = "b.VenueID = ?";
    $params[] = $venueFilter;
    $paramTypes .= "i";
}

if (!empty($dateFilter)) {
    $whereConditions[] = "DATE(p.PaymentDate) = ?";
    $params[] = $dateFilter;
    $paramTypes .= "s";
}

$whereClause = implode(" AND ", $whereConditions);

// Менежерийн заалны төлбөрүүдийг авах
$paymentsSql = "SELECT p.PaymentID, p.BookingID, p.Amount, p.Status, p.PaymentDate, 
                p.BankName, p.AccountNumber, p.TransactionID, p.RefundAmount, 
                b.BookingDate, b.Duration, b.Status as BookingStatus, u.Name as UserName, u.Email as UserEmail, 
                v.Name as VenueName, v.HourlyPrice
                FROM payment p
                JOIN booking b ON p.BookingID = b.BookingID
                JOIN user u ON b.UserID = u.UserID
                JOIN venue v ON b.VenueID = v.VenueID
                WHERE $whereClause
                ORDER BY p.PaymentDate DESC";

$stmt = $conn->prepare($paymentsSql);

// Prepare алдаа шалгах
if ($stmt === false) {
    die("Prepare алдаа: " . $conn->error);
}

$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$paymentsResult = $stmt->get_result();

// Менежерийн заалуудыг авах
$venuesSql = "SELECT VenueID, Name FROM venue WHERE ManagerID = ?";
$venuesStmt = $conn->prepare($venuesSql);
$venuesStmt->bind_param("i", $managerID);
$venuesStmt->execute();
$venuesResult = $venuesStmt->get_result();

// Төлбөрийн төлөв шинэчлэх үйлдэл
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $paymentID = $_POST['payment_id'] ?? 0;
        $newStatus = $_POST['new_status'] ?? '';
        $adminNotes = $_POST['admin_notes'] ?? '';
        
        if ($paymentID && $newStatus) {
            // Холболтыг эхлүүлэх
            $conn->begin_transaction();
            
            try {
                // Төлбөрийн төлөв шинэчлэх
                $updateSql = "UPDATE payment p 
                              JOIN booking b ON p.BookingID = b.BookingID
                              JOIN venue v ON b.VenueID = v.VenueID 
                              SET p.Status = ?, p.AdminNotes = ?
                              WHERE p.PaymentID = ? AND v.ManagerID = ?";
                
                $updateStmt = $conn->prepare($updateSql);
                
                if ($updateStmt === false) {
                    throw new Exception("Prepare алдаа (update): " . $conn->error);
                }
                
                $updateStmt->bind_param("ssii", $newStatus, $adminNotes, $paymentID, $managerID);
                $updateStmt->execute();
                
                // Хэрэв төлбөр төлөгдсөн бол захиалгын төлөвийг Баталгаажсан болгох
                if ($newStatus === 'Paid') {
                    $updateBookingSql = "UPDATE booking b
                                        JOIN payment p ON b.BookingID = p.BookingID
                                        JOIN venue v ON b.VenueID = v.VenueID
                                        SET b.Status = 'Confirmed'
                                        WHERE p.PaymentID = ? AND v.ManagerID = ?";
                    
                    $bookingStmt = $conn->prepare($updateBookingSql);
                    if ($bookingStmt === false) {
                        throw new Exception("Prepare алдаа (booking update): " . $conn->error);
                    }
                    
                    $bookingStmt->bind_param("ii", $paymentID, $managerID);
                    $bookingStmt->execute();
                }
                
                // Холболтыг баталгаажуулах
                $conn->commit();
                
                $_SESSION['success_message'] = "Төлбөрийн төлөв амжилттай шинэчлэгдлээ.";
            } catch (Exception $e) {
                // Алдаа гарвал буцаах
                $conn->rollback();
                $_SESSION['error_message'] = "Төлбөрийн төлөв шинэчлэх үед алдаа гарлаа: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['process_refund'])) {
        $paymentID = $_POST['payment_id'] ?? 0;
        $refundAmount = $_POST['refund_amount'] ?? 0;
        $refundReason = $_POST['refund_reason'] ?? '';
        
        if ($paymentID && $refundAmount > 0) {
            // Одоогийн төлбөрийн мэдээллийг авах
            $paymentInfoSql = "SELECT p.Amount, p.RefundAmount, p.BookingID
                              FROM payment p
                              JOIN booking b ON p.BookingID = b.BookingID
                              JOIN venue v ON b.VenueID = v.VenueID
                              WHERE p.PaymentID = ? AND v.ManagerID = ?";
            
            $infoStmt = $conn->prepare($paymentInfoSql);
            $infoStmt->bind_param("ii", $paymentID, $managerID);
            $infoStmt->execute();
            $paymentInfo = $infoStmt->get_result()->fetch_assoc();
            
            if ($paymentInfo) {
                $bookingID = $paymentInfo['BookingID'];
                $totalAmount = $paymentInfo['Amount'];
                $currentRefund = $paymentInfo['RefundAmount'];
                $newTotalRefund = $currentRefund + $refundAmount;
                
                // Буцаан олголт нь анхны төлбөрөөс хэтрэхгүй байх
                if ($newTotalRefund <= $totalAmount) {
                    // Холболтыг эхлүүлэх
                    $conn->begin_transaction();
                    
                    try {
                        $refundStatus = ($newTotalRefund == $totalAmount) ? 'Refunded' : 'Partially Refunded';
                        
                        // Төлбөрийн төлөв шинэчлэх
                        $updatePaymentSql = "UPDATE payment p 
                                          JOIN booking b ON p.BookingID = b.BookingID
                                          JOIN venue v ON b.VenueID = v.VenueID 
                                          SET p.Status = ?, p.RefundAmount = ?, p.RefundReason = ?, p.RefundDate = NOW()
                                          WHERE p.PaymentID = ? AND v.ManagerID = ?";
                        
                        $updatePaymentStmt = $conn->prepare($updatePaymentSql);
                        $updatePaymentStmt->bind_param("sdsii", $refundStatus, $newTotalRefund, $refundReason, $paymentID, $managerID);
                        $updatePaymentStmt->execute();
                        
                        // Хэрэв бүрэн буцаалт хийсэн бол захиалгын төлөвийг Цуцлагдсан болгох
                        if ($refundStatus === 'Refunded') {
                            $updateBookingSql = "UPDATE booking b
                                              JOIN venue v ON b.VenueID = v.VenueID
                                              SET b.Status = 'Canceled'
                                              WHERE b.BookingID = ? AND v.ManagerID = ?";
                            
                            $bookingStmt = $conn->prepare($updateBookingSql);
                            $bookingStmt->bind_param("ii", $bookingID, $managerID);
                            $bookingStmt->execute();
                        }
                        
                        // Холболтыг баталгаажуулах
                        $conn->commit();
                        
                        $_SESSION['success_message'] = "Буцаан олголт амжилттай хийгдлээ.";
                    } catch (Exception $e) {
                        // Алдаа гарвал буцаах
                        $conn->rollback();
                        $_SESSION['error_message'] = "Буцаан олголт хийх үед алдаа гарлаа: " . $e->getMessage();
                    }
                } else {
                    $_SESSION['error_message'] = "Буцаан олголтын дүн анхны төлбөрөөс хэтэрч болохгүй.";
                }
            } else {
                $_SESSION['error_message'] = "Төлбөрийн мэдээлэл олдсонгүй эсвэл танд энэ төлбөрийг удирдах эрх байхгүй байна.";
            }
        } else {
            $_SESSION['error_message'] = "Төлбөрийн ID эсвэл буцаан олголтын дүн буруу байна.";
        }
    }
    
    // Хуудас шинэчлэх, формын дахин илгээлтээс зайлсхийх
    header("Location: manager_payments.php" . (isset($_GET['status']) || isset($_GET['venue_id']) || isset($_GET['date']) ? '?' . http_build_query($_GET) : ''));
    exit();
}

// Буцаан олголтын хураамж тооцоолох (10%)
function calculateRefundFee($amount) {
    return $amount * 0.1; // 10% хураамж
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Төлбөрийн удирдлага</title>
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
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        .status-refunded {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-partially-refunded {
            background-color: #ffe8d9;
            color: #d35400;
        }
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-canceled {
            background-color: #e2e3e5;
            color: #383d41;
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
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Төлбөрийн удирдлага</h1>
        
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
            <h2 class="text-xl font-semibold mb-4">Төлбөр шүүх</h2>
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Төлөв:</label>
                    <select name="status" id="status" class="w-full border border-gray-300 rounded-md p-2">
                        <option value="">Бүх төлөв</option>
                        <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Хүлээгдэж буй</option>
                        <option value="Paid" <?php echo $statusFilter === 'Paid' ? 'selected' : ''; ?>>Төлөгдсөн</option>
                        <option value="Refunded" <?php echo $statusFilter === 'Refunded' ? 'selected' : ''; ?>>Буцаагдсан</option>
                        <option value="Partially Refunded" <?php echo $statusFilter === 'Partially Refunded' ? 'selected' : ''; ?>>Хэсэгчлэн буцаагдсан</option>
                        <option value="Failed" <?php echo $statusFilter === 'Failed' ? 'selected' : ''; ?>>Амжилтгүй</option>
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
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Төлбөр хийсэн огноо:</label>
                    <input type="date" name="date" id="date" value="<?php echo $dateFilter; ?>" class="w-full border border-gray-300 rounded-md p-2">
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded">Шүүх</button>
                    <a href="manager_payments.php" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">Цэвэрлэх</a>
                </div>
            </form>
        </div>
        
        <!-- Төлбөрийн хүснэгт -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Төлбөр ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Хэрэглэгч</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Заал</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дүн</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Банк</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Гүйлгээ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Огноо</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Төлөв</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Үйлдэл</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($paymentsResult && $paymentsResult->num_rows > 0): ?>
                            <?php while ($payment = $paymentsResult->fetch_assoc()): 
                                $statusClass = strtolower(str_replace(' ', '-', $payment['Status']));
                                
                                // Төлбөрийн төлөвийг монгол руу хөрвүүлэх
                                $statusMongolian = $payment['Status'];
                                switch($payment['Status']) {
                                    case 'Pending': $statusMongolian = 'Хүлээгдэж буй'; break;
                                    case 'Paid': $statusMongolian = 'Төлөгдсөн'; break;
                                    case 'Refunded': $statusMongolian = 'Буцаагдсан'; break;
                                    case 'Partially Refunded': $statusMongolian = 'Хэсэгчлэн буцаагдсан'; break;
                                    case 'Failed': $statusMongolian = 'Амжилтгүй'; break;
                                    case 'Canceled': $statusMongolian = 'Цуцлагдсан'; break;
                                }
                                
                                // Захиалгын төлөвийг монгол руу хөрвүүлэх
                                $bookingStatusMongolian = $payment['BookingStatus'];
                                switch($payment['BookingStatus']) {
                                    case 'Pending': $bookingStatusMongolian = 'Хүлээгдэж буй'; break;
                                    case 'Confirmed': $bookingStatusMongolian = 'Баталгаажсан'; break;
                                    case 'Canceled': $bookingStatusMongolian = 'Цуцлагдсан'; break;
                                }
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $payment['PaymentID']; ?></td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($payment['UserName']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($payment['UserEmail']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($payment['VenueName']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo number_format($payment['Amount'], 2); ?> ₮</div>
                                        <?php if ($payment['RefundAmount'] > 0): ?>
                                            <div class="text-sm text-red-500">Буцаагдсан: <?php echo number_format($payment['RefundAmount'], 2); ?> ₮</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($payment['BankName']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($payment['TransactionID']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('Y/m/d H:i', strtotime($payment['PaymentDate'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="status-badge status-<?php echo $statusClass; ?>">
                                            <?php echo $statusMongolian; ?>
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Захиалга: <?php echo $bookingStatusMongolian; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-1 px-3 rounded text-xs update-status-btn mb-1" 
                                                data-payment-id="<?php echo $payment['PaymentID']; ?>"
                                                data-current-status="<?php echo $payment['Status']; ?>">
                                            Төлөв өөрчлөх
                                        </button>
                                        
                                        <?php if ($payment['Status'] === 'Paid'): ?>
                                            <button class="bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-1 px-3 rounded text-xs refund-btn"
                                                    data-payment-id="<?php echo $payment['PaymentID']; ?>"
                                                    data-amount="<?php echo $payment['Amount']; ?>"
                                                    data-refunded="<?php echo $payment['RefundAmount']; ?>">
                                                Буцаан олголт
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="view_payment.php?id=<?php echo $payment['PaymentID']; ?>" class="bg-green-500 hover:bg-green-600 text-white font-medium py-1 px-3 rounded text-xs ml-1">
                                            Дэлгэрэнгүй
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-6 py-4 text-center text-sm text-gray-500">Төлбөр олдсонгүй</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Төлбөрийн төлөв шинэчлэх modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-bold mb-4">Төлбөрийн төлөв шинэчлэх</h2>
            <form action="" method="POST">
                <input type="hidden" name="payment_id" id="modal-payment-id">
                <div class="mb-4">
                    <label for="new_status" class="block text-sm font-medium text-gray-700 mb-1">Шинэ төлөв:</label>
                    <select name="new_status" id="new_status" required class="w-full border border-gray-300 rounded-md p-2">
                        <option value="Pending">Хүлээгдэж буй</option>
                        <option value="Paid">Төлөгдсөн</option>
                        <option value="Failed">Амжилтгүй</option>
                        <option value="Canceled">Цуцлагдсан</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="admin_notes" class="block text-sm font-medium text-gray-700 mb-1">Тэмдэглэл:</label>
                    <textarea name="admin_notes" id="admin_notes" rows="3" class="w-full border border-gray-300 rounded-md p-2"></textarea>
                </div>
                <button type="submit" name="update_status" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded">Шинэчлэх</button>
            </form>
        </div>
    </div>

    <!-- Буцаан олголт хийх modal -->
    <div id="refundModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-xl font-bold mb-4">Буцаан олголт хийх</h2>
            <form action="" method="POST">
                <input type="hidden" name="payment_id" id="refund-payment-id">
                
                <div id="refund-info" class="bg-gray-100 p-4 rounded-md mb-4">
                    <p>Анхны дүн: <span id="original-amount">0</span> ₮</p>
                    <p>Буцаагдсан: <span id="already-refunded">0</span> ₮</p>
                    <p>Буцаах боломжтой: <span id="available-refund">0</span> ₮</p>
                </div>
                
                <div class="mb-4">
                    <label for="refund_amount" class="block text-sm font-medium text-gray-700 mb-1">Буцаах дүн:</label>
                    <input type="number" name="refund_amount" id="refund_amount" step="0.01" min="0
                    <div class="mb-4">
                    <label for="refund_amount" class="block text-sm font-medium text-gray-700 mb-1">Буцаах дүн:</label>
                    <input type="number" name="refund_amount" id="refund_amount" step="0.01" min="0" required class="w-full border border-gray-300 rounded-md p-2">
                    <p class="text-sm text-gray-500 mt-1">Хураамж (10%): <span id="refund-fee">0</span> ₮</p>
                </div>
                
                <div class="mb-4">
                    <label for="refund_reason" class="block text-sm font-medium text-gray-700 mb-1">Буцаах шалтгаан:</label>
                    <textarea name="refund_reason" id="refund_reason" rows="3" required class="w-full border border-gray-300 rounded-md p-2"></textarea>
                </div>
                
                <button type="submit" name="process_refund" class="w-full bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-4 rounded">Буцаан олголт хийх</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Төлөв шинэчлэх modal
        var statusModal = document.getElementById('statusModal');
        var statusButtons = document.querySelectorAll('.update-status-btn');
        var statusCloseBtn = statusModal.querySelector('.close');
        
        statusButtons.forEach(function(btn) {
            btn.onclick = function() {
                var paymentId = this.getAttribute('data-payment-id');
                var currentStatus = this.getAttribute('data-current-status');
                
                document.getElementById('modal-payment-id').value = paymentId;
                document.getElementById('new_status').value = currentStatus;
                
                statusModal.style.display = "block";
            }
        });
        
        statusCloseBtn.onclick = function() {
            statusModal.style.display = "none";
        }
        
        // Буцаан олголт modal
        var refundModal = document.getElementById('refundModal');
        var refundButtons = document.querySelectorAll('.refund-btn');
        var refundCloseBtn = refundModal.querySelector('.close');
        var refundAmountInput = document.getElementById('refund_amount');
        
        refundButtons.forEach(function(btn) {
            btn.onclick = function() {
                var paymentId = this.getAttribute('data-payment-id');
                var amount = parseFloat(this.getAttribute('data-amount'));
                var alreadyRefunded = parseFloat(this.getAttribute('data-refunded'));
                var availableForRefund = amount - alreadyRefunded;
                
                document.getElementById('refund-payment-id').value = paymentId;
                document.getElementById('original-amount').textContent = amount.toFixed(2);
                document.getElementById('already-refunded').textContent = alreadyRefunded.toFixed(2);
                document.getElementById('available-refund').textContent = availableForRefund.toFixed(2);
                
                refundAmountInput.setAttribute('max', availableForRefund);
                refundAmountInput.value = availableForRefund.toFixed(2);
                
                // Хураамжийг тооцоолох
                var fee = availableForRefund * 0.1;
                document.getElementById('refund-fee').textContent = fee.toFixed(2);
                
                refundModal.style.display = "block";
            }
        });
        
        refundCloseBtn.onclick = function() {
            refundModal.style.display = "none";
        }
        
        // Буцаан олголтын хураамжийг тооцоолох
        refundAmountInput.addEventListener('input', function() {
            var amount = parseFloat(this.value) || 0;
            var fee = amount * 0.1; // 10% хураамж
            document.getElementById('refund-fee').textContent = fee.toFixed(2);
        });
        
        // Modalыг гадна талаас нь дарахад хаах
        window.onclick = function(event) {
            if (event.target == statusModal) {
                statusModal.style.display = "none";
            }
            if (event.target == refundModal) {
                refundModal.style.display = "none";
            }
        }
    });
    </script>
</body>
</html>