<?php
session_start();
require 'config/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userID = $_SESSION['user_id'];

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


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View My Bookings</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto my-8 p-6 bg-white rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">My Bookings</h2>

        <?php if ($result->num_rows > 0): ?>
            <table class="min-w-full table-auto border-collapse border border-gray-300">
                <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="px-4 py-2 border border-gray-300">#</th>
                        <th class="px-4 py-2 border border-gray-300">Venue Name</th>
                        <th class="px-4 py-2 border border-gray-300">Location</th>
                        <th class="px-4 py-2 border border-gray-300">Start Time</th>
                        <th class="px-4 py-2 border border-gray-300">End Time</th>
                        <th class="px-4 py-2 border border-gray-300">Booking Date</th>
                        <th class="px-4 py-2 border border-gray-300">Duration (Hours)</th>
                        <th class="px-4 py-2 border border-gray-300">Amount</th>
                        <th class="px-4 py-2 border border-gray-300">Payment Status</th>
                        <th class="px-4 py-2 border border-gray-300">Booking Status</th>
                        <th class="px-4 py-2 border border-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                        <tr class="odd:bg-white even:bg-gray-100">
                            <td class="px-4 py-2 border border-gray-300"><?php echo $i++; ?></td>
                            <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['VenueName']); ?></td>
                            <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['VenueLocation']); ?></td>
                            <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['StartTime']); ?></td>
                            <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['EndTime']); ?></td>
                            <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['BookingDate']); ?></td>
                            <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['Duration']); ?></td>
                            <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars(number_format($row['Amount'], 2)); ?></td>
                            <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['PaymentStatus']); ?></td>
                            <td class="px-4 py-2 border border-gray-300"><?php echo htmlspecialchars($row['BookingStatus']); ?></td>
                            <td class="px-4 py-2 border border-gray-300 text-center">
                                <?php if ($row['BookingStatus'] !== 'Canceled'): ?>
                                    <a href="view_bookings.php?cancel_booking_id=<?php echo $row['BookingID']; ?>" 
                                       class="inline-block px-3 py-1 text-sm font-semibold text-white bg-red-500 rounded hover:bg-red-600"
                                       onclick="return confirm('Are you sure you want to cancel this booking?')">Cancel</a>
                                <?php else: ?>
                                    <span class="text-red-500 font-semibold">Canceled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-center text-gray-600 mt-6">You have no bookings yet.</p>
        <?php endif; ?>

        <a href="user_dashboard.php" class="block mt-4 text-center text-white bg-blue-500 px-4 py-2 rounded hover:bg-blue-600">
            Back to Dashboard
        </a>
    </div>
</body>
</html>
