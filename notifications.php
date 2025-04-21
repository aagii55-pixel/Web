<?php
session_start();
require 'config/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please log in to view notifications.";
    exit();
}

$userID = $_SESSION['user_id'];

// Fetch notifications
$sql = "SELECT NotificationID, Title, Message, Date, IsRead FROM Notifications WHERE UserID = ? ORDER BY Date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userID);
$stmt->execute();
$result = $stmt->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .notification {
            border-bottom: 1px solid #ccc;
            padding: 10px;
            background-color: #f9f9f9;
            cursor: pointer;
        }
        .notification.read {
            background-color: #e0e0e0;
        }
        .notification h4 {
            margin: 0;
            font-size: 16px;
        }
        .notification p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }
        .notification small {
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h3>Мэдэгдлүүд</h3>
    <div id="notifications">
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="notification <?php echo $row['IsRead'] ? 'read' : ''; ?>" data-id="<?php echo $row['NotificationID']; ?>">
                <h4><?php echo htmlspecialchars($row['Title']); ?></h4>
                <p><?php echo htmlspecialchars($row['Message']); ?></p>
                <small><?php echo date('Y-m-d H:i', strtotime($row['Date'])); ?></small>
            </div>
        <?php endwhile; ?>
    </div>

    <script>
        document.querySelectorAll('.notification').forEach(function (notification) {
            notification.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                if (!this.classList.contains('read')) {
                    // Mark as read via AJAX
                    fetch('mark_as_read.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'notificationID=' + id
                    }).then(response => response.text()).then(data => {
                        if (data === 'Success') {
                            this.classList.add('read');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
