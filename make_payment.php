<?php
session_start();
require 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Validate the booking exists and belongs to the current user
if ($bookingId > 0) {
    $bookingQuery = "SELECT b.*, v.Name as VenueName, v.HourlyPrice, vts.StartTime, vts.EndTime 
                    FROM booking b
                    JOIN venue v ON b.VenueID = v.VenueID
                    JOIN venuetimeslot vts ON b.SlotID = vts.SlotID
                    WHERE b.BookingID = ? AND b.UserID = ?";
    
    $stmt = $conn->prepare($bookingQuery);
    $stmt->bind_param("ii", $bookingId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Invalid booking or you don't have permission to access this booking.";
        header('Location: my_bookings.php');
        exit();
    }
    
    $booking = $result->fetch_assoc();
    
    // Calculate total amount
    $totalAmount = $booking['Duration'] * $booking['HourlyPrice'];
    
    // Check if payment already exists
    $paymentQuery = "SELECT * FROM payment WHERE BookingID = ?";
    $stmt = $conn->prepare($paymentQuery);
    $stmt->bind_param("i", $bookingId);
    $stmt->execute();
    $paymentResult = $stmt->get_result();
    $paymentExists = $paymentResult->num_rows > 0;
    
    if ($paymentExists) {
        $payment = $paymentResult->fetch_assoc();
    }
} else {
    $_SESSION['error_message'] = "Invalid booking ID.";
    header('Location: my_bookings.php');
    exit();
}

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $bankName = $_POST['bank_name'];
    $accountNumber = $_POST['account_number'];
    $transactionId = $_POST['transaction_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        if ($paymentExists) {
            // Update existing payment
            $updatePaymentQuery = "UPDATE payment 
                                  SET BankName = ?, 
                                      AccountNumber = ?, 
                                      TransactionID = ?, 
                                      Status = 'Pending',
                                      PaymentDate = NOW() 
                                  WHERE BookingID = ?";
            
            $stmt = $conn->prepare($updatePaymentQuery);
            $stmt->bind_param("sssi", $bankName, $accountNumber, $transactionId, $bookingId);
            $stmt->execute();
        } else {
            // Insert new payment
            $insertPaymentQuery = "INSERT INTO payment (BookingID, Amount, BankName, AccountNumber, TransactionID, Status, PaymentDate) 
                                  VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
            
            $stmt = $conn->prepare($insertPaymentQuery);
            $stmt->bind_param("idsss", $bookingId, $totalAmount, $bankName, $accountNumber, $transactionId);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Payment information submitted successfully. Your payment is pending verification.";
        header('Location: my_bookings.php');
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Error processing payment: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include 'header.php'; ?>
    
    <div class="container mx-auto py-8 px-4">
        <h1 class="text-3xl font-bold text-center mb-8">Make Payment</h1>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden max-w-3xl mx-auto">
            <div class="bg-blue-600 text-white py-4 px-6">
                <h2 class="text-xl font-semibold">Booking Details</h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <p class="text-gray-600 text-sm">Venue</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($booking['VenueName']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600 text-sm">Date</p>
                        <p class="font-semibold"><?php echo date('d-m-Y', strtotime($booking['BookingDate'])); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600 text-sm">Time</p>
                        <p class="font-semibold">
                            <?php echo date('H:i', strtotime($booking['StartTime'])); ?> - 
                            <?php 
                                $endTime = new DateTime($booking['StartTime']);
                                $endTime->add(new DateInterval('PT' . $booking['Duration'] . 'H'));
                                echo $endTime->format('H:i');
                            ?>
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600 text-sm">Duration</p>
                        <p class="font-semibold"><?php echo $booking['Duration']; ?> hour(s)</p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600 text-sm">Price per Hour</p>
                        <p class="font-semibold"><?php echo number_format($booking['HourlyPrice'], 2); ?> ₮</p>
                    </div>
                    
                    <div>
                        <p class="text-gray-600 text-sm">Total Amount</p>
                        <p class="font-bold text-lg text-blue-600"><?php echo number_format($totalAmount, 2); ?> ₮</p>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold mb-4">Payment Information</h3>
                    
                    <?php if ($paymentExists && $payment['Status'] === 'Paid'): ?>
                        <div class="bg-green-100 text-green-700 p-4 rounded mb-4">
                            Payment has been confirmed. Thank you!
                        </div>
                    <?php elseif ($paymentExists && $payment['Status'] === 'Pending'): ?>
                        <div class="bg-yellow-100 text-yellow-700 p-4 rounded mb-4">
                            Your payment is being verified. We'll update the status soon.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$paymentExists || ($paymentExists && $payment['Status'] !== 'Paid')): ?>
                        <form action="" method="POST" class="space-y-4">
                            <div>
                                <label for="bank_name" class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                                <select name="bank_name" id="bank_name" required class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select a Bank</option>
                                    <option value="Khan Bank" <?php echo (isset($payment) && $payment['BankName'] === 'Khan Bank') ? 'selected' : ''; ?>>Khan Bank</option>
                                    <option value="State Bank" <?php echo (isset($payment) && $payment['BankName'] === 'State Bank') ? 'selected' : ''; ?>>State Bank</option>
                                    <option value="Golomt Bank" <?php echo (isset($payment) && $payment['BankName'] === 'Golomt Bank') ? 'selected' : ''; ?>>Golomt Bank</option>
                                    <option value="TDB Bank" <?php echo (isset($payment) && $payment['BankName'] === 'TDB Bank') ? 'selected' : ''; ?>>TDB Bank</option>
                                    <option value="Xac Bank" <?php echo (isset($payment) && $payment['BankName'] === 'Xac Bank') ? 'selected' : ''; ?>>Xac Bank</option>
                                    <option value="Capitron Bank" <?php echo (isset($payment) && $payment['BankName'] === 'Capitron Bank') ? 'selected' : ''; ?>>Capitron Bank</option>
                                    <option value="National Investment Bank" <?php echo (isset($payment) && $payment['BankName'] === 'National Investment Bank') ? 'selected' : ''; ?>>National Investment Bank</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="account_number" class="block text-sm font-medium text-gray-700 mb-1">Account Number or Phone Number</label>
                                <input type="text" name="account_number" id="account_number" required 
                                    value="<?php echo isset($payment) ? htmlspecialchars($payment['AccountNumber']) : ''; ?>"
                                    class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Enter your account number or phone number">
                            </div>
                            
                            <div>
                                <label for="transaction_id" class="block text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
                                <input type="text" name="transaction_id" id="transaction_id" required 
                                    value="<?php echo isset($payment) ? htmlspecialchars($payment['TransactionID']) : ''; ?>"
                                    class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Enter transaction ID from your bank receipt">
                            </div>
                            
                            <div class="bg-gray-100 p-4 rounded-md">
                                <h4 class="font-medium text-gray-800 mb-2">Payment Instructions:</h4>
                                <ol class="list-decimal pl-5 text-sm text-gray-700 space-y-1">
                                    <li>Transfer the exact amount of <?php echo number_format($totalAmount, 2); ?> ₮ to our account.</li>
                                    <li>Use your booking ID (<?php echo $bookingId; ?>) as the reference number.</li>
                                    <li>Fill in the bank details and transaction ID from your receipt/confirmation.</li>
                                    <li>Submit the form to notify us about your payment.</li>
                                    <li>Your booking will be confirmed once the payment is verified.</li>
                                </ol>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <a href="my_bookings.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded">
                                    Cancel
                                </a>
                                <button type="submit" name="submit_payment" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">
                                    Submit Payment
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>