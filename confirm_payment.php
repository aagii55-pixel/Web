<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_POST['confirm_payment'])) {
    $booking_id = $_POST['booking_id'];
    $amount = $_POST['amount'];
    $payment_date = date('Y-m-d');
    $status = 'Paid';

    $stmt = $conn->prepare("INSERT INTO Payment (BookingID, Amount, PaymentDate, Status) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("idss", $booking_id, $amount, $payment_date, $status);

    if ($stmt->execute()) {
        $stmt_booking = $conn->prepare("UPDATE Booking SET Status = 'Confirmed' WHERE BookingID = ?");
        $stmt_booking->bind_param("i", $booking_id);
        $stmt_booking->execute();
        $stmt_booking->close();

        $message = "Payment confirmed successfully!";
    } else {
        $message = "Error confirming payment: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Payment</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="confirm-payment">
        <h2>Confirm Payment</h2>
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="booking_id" value="<?php echo $_GET['booking_id']; ?>">
            <input type="number" step="0.01" name="amount" placeholder="Amount" required>
            <input type="submit" name="confirm_payment" value="Confirm Payment" class="btn">
        </form>

        <a href="user_dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>
