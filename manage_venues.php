<?php
session_start();
require 'config/db.php';

// Ensure that only managers can access this page
function ensureManagerAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Manager') {
        header('Location: ../login.php');
        exit();
    }
}

// Enforce role check
ensureManagerAccess();

$managerID = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Start transaction
    $conn->begin_transaction();
    try {
        // Insert into Venue table
        $stmtVenue = $conn->prepare("INSERT INTO Venue (ManagerID, Name, Location, SportType, HourlyPrice, Description, MapLocation) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtVenue->bind_param("isssdss", $managerID, $_POST['name'], $_POST['location'], $_POST['sport_type'], $_POST['hourly_price'], $_POST['description'], $_POST['map_location']);
        
        if (!$stmtVenue->execute()) {
            throw new Exception("Error adding venue: " . $stmtVenue->error);
        }
        
        // Get the last inserted VenueID
        $venueID = $conn->insert_id;
        $stmtVenue->close();

        // Insert into VenueTimeSlot table for each time slot
        if (!empty($_POST['time_slots'])) {
            $stmtTimeSlot = $conn->prepare("INSERT INTO VenueTimeSlot (VenueID, Week, DayOfWeek, StartTime, EndTime, Status, Price) VALUES (?, ?, ?, ?, ?, 'Available', ?)");
            
            foreach ($_POST['time_slots'] as $week => $days) {
                foreach ($days as $dayOfWeek => $hours) {
                    foreach ($hours as $hour => $slot) {
                        $startTime = sprintf('%02d:00:00', $hour);
                        $endTime = sprintf('%02d:00:00', ($hour + 1) % 24);
                        $price = $slot['price'];
                        
                        $stmtTimeSlot->bind_param("iisssd", $venueID, $week, $dayOfWeek, $startTime, $endTime, $price);
                        if (!$stmtTimeSlot->execute()) {
                            throw new Exception("Error adding time slot: " . $stmtTimeSlot->error);
                        }
                    }
                }
            }
            $stmtTimeSlot->close();
        }

        // Commit transaction
        $conn->commit();
        echo "<script>alert('Venue and time slots added successfully!');</script>";
    } catch (Exception $e) {
        // Rollback transaction in case of an error
        $conn->rollback();
        echo "<script>alert('" . $e->getMessage() . "');</script>";
    }

    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Venues</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .venues-container {
            width: 80%;
            margin: 0 auto;
            margin-top: 30px;
        }
        .venues-container table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .venues-container table, .venues-container th, .venues-container td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .venues-container th {
            background-color: #007bff;
            color: white;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .message {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #d6e9c6;
            border-radius: 4px;
        }
        .delete {
            background-color: #d9534f;
        }
        .delete:hover {
            background-color: #c9302c;
        }
        .edit {
            background-color: #5bc0de;
        }
        .edit:hover {
            background-color: #31b0d5;
        }
    </style>
</head>
<body>
    <div class="venues-container">
        <h2>Manage Venues</h2>
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Sport Type</th>
                    <th>Hourly Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($venues->num_rows > 0): ?>
                    <?php while ($venue = $venues->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($venue['Name']); ?></td>
                            <td><?php echo htmlspecialchars($venue['Location']); ?></td>
                            <td><?php echo htmlspecialchars($venue['SportType']); ?></td>
                            <td>$<?php echo htmlspecialchars($venue['HourlyPrice']); ?></td>
                            <td>
                                <form action="" method="POST" style="display:inline;">
                                    <input type="hidden" name="delete_venue_id" value="<?php echo $venue['VenueID']; ?>">
                                    <input type="submit" value="Delete" class="btn delete" onclick="return confirm('Are you sure you want to delete this venue?');">
                                </form>
                                <a href="edit_venue.php?venue_id=<?php echo $venue['VenueID']; ?>" class="btn edit">Edit</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No venues found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="add_venue.php" class="btn">Add New Venue</a>
        <a href="admin_dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>
