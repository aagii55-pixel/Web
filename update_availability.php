<?php
// Start session
session_start();

// Ensure only VenueStaff can access this page
function ensureVenueStaffAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'VenueStaff') {
        header('Location: ../login.php');
        exit();
    }
}

// Enforce role check
ensureVenueStaffAccess();



// Get the venue ID from URL (or form submission)
$venue_id = isset($_GET['venue_id']) ? $_GET['venue_id'] : '';

// Check if venue ID is valid
if ($venue_id == '') {
    echo "Invalid venue ID.";
    exit();
}

// Get available timeslots for the venue
$query = "SELECT * FROM VenueTimeSlot WHERE VenueID = ? ORDER BY DayOfWeek, StartTime";
$stmt = $pdo->prepare($query);
$stmt->execute([$venue_id]);
$timeslots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process form submission to update availability
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $availability = $_POST['availability']; // Array of time slot availability

    // Update availability for each time slot
    foreach ($availability as $slot_id => $status) {
        $update_query = "UPDATE VenueTimeSlot SET Status = ? WHERE SlotID = ?";
        $stmt = $pdo->prepare($update_query);
        $stmt->execute([$status, $slot_id]);
    }

    echo "Availability updated successfully!";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Venue Availability</title>
    <link rel="stylesheet" href="../assets/styles.css">
</head>
<body>
    <div class="container">
        <h1>Update Availability for Venue #<?php echo htmlspecialchars($venue_id); ?></h1>

        <form method="POST">
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timeslots as $slot): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($slot['DayOfWeek']); ?></td>
                            <td><?php echo htmlspecialchars($slot['StartTime']) . ' - ' . htmlspecialchars($slot['EndTime']); ?></td>
                            <td>
                                <select name="availability[<?php echo $slot['SlotID']; ?>]">
                                    <option value="Available" <?php echo ($slot['Status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="Unavailable" <?php echo ($slot['Status'] == 'Unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit">Update Availability</button>
        </form>
    </div>
</body>
</html>
