<?php
session_start();
require 'config/db.php';

// Define the function to send notifications
function sendNotification($userID, $message) {
    global $conn;
    $sql = "INSERT INTO Notifications (UserID, Message, IsRead, CreatedAt) VALUES (?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $userID, $message);
    $stmt->execute();
    $stmt->close();
}

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle delete request
if (isset($_GET['delete'])) {
    $requestID = $_GET['delete'];
    $userID = $_SESSION['user_id'];

    // Delete the promotion request if it belongs to the logged-in user
    $sql = "DELETE FROM PromotionRequest WHERE RequestID = ? AND UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $requestID, $userID);
    if ($stmt->execute()) {
        // Send notification after deletion
        sendNotification($userID, "Таны хүсэлт амжилттай устгагдлаа.");
        $successMessage = "Хүсэлт амжилттай устгагдлаа.";
    } else {
        $errorMessage = "Хүсэлт устгахад алдаа гарлаа: " . $conn->error;
    }
}

// Handle edit request (fetch data)
if (isset($_GET['edit'])) {
    $requestID = $_GET['edit'];
    $userID = $_SESSION['user_id'];

    // Get the promotion request data
    $sql = "SELECT * FROM PromotionRequest WHERE RequestID = ? AND UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $requestID, $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $requestData = $result->fetch_assoc();
        // Send notification about editing the request
        sendNotification($userID, "Таны хүсэлт амжилттай засагдлаа.");
        // Display the edit form with current data (add your form code here)
    } else {
        $errorMessage = "Хүсэлт олдсонгүй.";
    }
}

// Fetch all requests for the logged-in user
$userID = $_SESSION['user_id'];
$sql = "SELECT * FROM PromotionRequest WHERE UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userID);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Миний Тамга Хүсэлтүүд</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-blue-500 to-teal-500">
    <div class="container mx-auto p-8 max-w-4xl bg-white rounded-lg shadow-xl">
        <button class="btn bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700" onclick="window.location.href='user_dashboard.php'">Тамга хүсэлтүүдэд буцах</button>
        <h2 class="text-3xl font-semibold text-center text-gray-800 my-4">Миний Тамга Хүсэлтүүд</h2>

        <!-- Success or error message -->
        <?php if (isset($successMessage)): ?>
            <p class="text-green-600 text-center font-semibold"><?php echo $successMessage; ?></p>
        <?php elseif (isset($errorMessage)): ?>
            <p class="text-red-600 text-center font-semibold"><?php echo $errorMessage; ?></p>
        <?php endif; ?>

        <table class="min-w-full table-auto mt-8 border-collapse">
            <thead>
                <tr class="bg-gray-100">
                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Нэр</th>
                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Талбай</th>
                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Үнэ</th>
                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Цагийн хуваарь</th>
                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Төсөл</th>
                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Үйлдлүүд</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="border-b">
                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($row['Name']); ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($row['VenueName']); ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($row['VenuePrice']); ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($row['TimeSlots']); ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($row['RequestStatus']); ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800">
                            <a href="promotion_request_edit.php?edit=<?php echo $row['RequestID']; ?>" class="text-blue-600 hover:text-blue-800">Засах</a> |
                            <a href="promotionrequestshow.php?delete=<?php echo $row['RequestID']; ?>" onclick="return confirm('Та энэ хүсэлтийг устгахдаа итгэлтэй байна уу?')" class="text-red-600 hover:text-red-800">Устгах</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
