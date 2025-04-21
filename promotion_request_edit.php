<?php
session_start();
require 'config/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
function sendNotification($userID, $message)
{
    global $conn;
    $sql = "INSERT INTO Notifications (UserID, Message, IsRead, CreatedAt) VALUES (?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $userID, $message);
    $stmt->execute();
    $stmt->close();
}
// Fetch the request to be edited
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
    } else {
        $errorMessage = "Таны хүсэлт олдсонгүй эсвэл та энэ хүсэлтийг засах эрхгүй байна.";
    }
} else {
    header('Location: promotionrequestshow.php');
    exit();
}

// Handle form submission to update the promotion request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phoneNumber = $_POST['phone_number'];
    $phoneNumber2 = $_POST['phone_number_2'];
    $venueName = $_POST['venue_name'];
    $venueLocation = $_POST['venue_location'];
    $venuePrice = $_POST['venue_price'];
    $timeSlots = $_POST['time_slots'];
    $description = $_POST['description'];

    // Handle file upload (optional)
    $uploadedFiles = [];
    if (isset($_FILES['images']) && $_FILES['images']['error'][0] != UPLOAD_ERR_NO_FILE) {
        $uploadDir = 'uploads/';
        foreach ($_FILES['images']['name'] as $index => $fileName) {
            $fileTmpName = $_FILES['images']['tmp_name'][$index];
            $fileError = $_FILES['images']['error'][$index];

            // Check for upload errors
            if ($fileError === UPLOAD_ERR_OK) {
                $uniqueName = uniqid('img_', true) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
                $filePath = $uploadDir . $uniqueName;

                // Move the file to the uploads directory
                if (move_uploaded_file($fileTmpName, $filePath)) {
                    $uploadedFiles[] = $filePath; // Save the file path for database insertion
                }
            }
        }
    }

    // Convert array of file paths to a comma-separated string if there are any uploaded files
    $images = $uploadedFiles ? implode(',', $uploadedFiles) : $requestData['Images'];

    // Update the promotion request in the database
    $sql = "UPDATE PromotionRequest SET Name = ?, Email = ?, PhoneNumber = ?, PhoneNumber2 = ?, VenueName = ?, VenueLocation = ?, VenuePrice = ?, TimeSlots = ?, Description = ?, Images = ? WHERE RequestID = ? AND UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssssssssii', $name, $email, $phoneNumber, $phoneNumber2, $venueName, $venueLocation, $venuePrice, $timeSlots, $description, $images, $requestID, $userID);

    if ($stmt->execute()) {
        $successMessage = "Таны promotion хүсэлт амжилттай шинэчлэгдлээ.";
        // Send notification after deletion
        sendNotification($userID, "Таны promotion хүсэлт амжилттай шинэчлэгдлээ.");
    } else {
        $errorMessage = "Хүсэлт шинэчлэхэд алдаа гарлаа: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="mn">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Хүсэлт Засах</title>
    <!-- Add Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto mt-12 p-6 bg-white rounded-lg shadow-lg">
        <button class="btn bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600"
            onclick="window.location.href='promotionrequestshow.php'">Миний хүсэлтүүдэд буцах</button>
        <h2 class="text-2xl font-semibold text-center my-6">Promotion Хүсэлт Засах</h2>

        <?php if (isset($successMessage)): ?>
            <p class="text-green-500 text-center"><?php echo $successMessage; ?></p>
        <?php elseif (isset($errorMessage)): ?>
            <p class="text-red-500 text-center"><?php echo $errorMessage; ?></p>
        <?php endif; ?>

        <form action="promotion_request_edit.php?edit=<?php echo $requestID; ?>" method="POST"
            enctype="multipart/form-data">
            <div class="mb-4">
                <label for="name" class="block font-medium text-lg">Нэр:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($requestData['Name']); ?>"
                    required class="w-full p-2 border rounded-md mt-1">
            </div>
            <div class="mb-4">
                <label for="email" class="block font-medium text-lg">И-мэйл:</label>
                <input type="email" id="email" name="email"
                    value="<?php echo htmlspecialchars($requestData['Email']); ?>" required
                    class="w-full p-2 border rounded-md mt-1">
            </div>
            <div class="mb-4">
                <label for="phone_number" class="block font-medium text-lg">Утасны дугаар:</label>
                <input type="text" id="phone_number" name="phone_number"
                    value="<?php echo htmlspecialchars($requestData['PhoneNumber']); ?>" required
                    class="w-full p-2 border rounded-md mt-1">
            </div>
            <div class="mb-4">
                <label for="phone_number_2" class="block font-medium text-lg">Хоёр дахь утас (Сонголт):</label>
                <input type="text" id="phone_number_2" name="phone_number_2"
                    value="<?php echo htmlspecialchars($requestData['PhoneNumber2']); ?>"
                    class="w-full p-2 border rounded-md mt-1">
            </div>
            <div class="mb-4">
                <label for="venue_name" class="block font-medium text-lg">Талбайн нэр:</label>
                <input type="text" id="venue_name" name="venue_name"
                    value="<?php echo htmlspecialchars($requestData['VenueName']); ?>" required
                    class="w-full p-2 border rounded-md mt-1">
            </div>
            <div class="mb-4">
                <label for="venue_location" class="block font-medium text-lg">Талбайн байршил:</label>
                <input type="text" id="venue_location" name="venue_location"
                    value="<?php echo htmlspecialchars($requestData['VenueLocation']); ?>" required
                    class="w-full p-2 border rounded-md mt-1">
            </div>
            <div class="mb-4">
                <label for="venue_price" class="block font-medium text-lg">Талбайн үнэ:</label>
                <input type="number" step="0.01" id="venue_price" name="venue_price"
                    value="<?php echo htmlspecialchars($requestData['VenuePrice']); ?>" required
                    class="w-full p-2 border rounded-md mt-1">
            </div>
            <div class="mb-4">
                <label for="time_slots" class="block font-medium text-lg">Цагийн хуваарь:</label>
                <textarea id="time_slots" name="time_slots" required
                    class="w-full p-2 border rounded-md mt-1"><?php echo htmlspecialchars($requestData['TimeSlots']); ?></textarea>
            </div>
            <div class="mb-4">
                <label for="description" class="block font-medium text-lg">Тайлбар:</label>
                <textarea id="description" name="description"
                    class="w-full p-2 border rounded-md mt-1"><?php echo htmlspecialchars($requestData['Description']); ?></textarea>
            </div>
            <div class="mb-4">
                <label for="images" class="block font-medium text-lg">Зураг:</label>
                <input type="file" id="images" name="images[]" multiple accept="image/*"
                    class="w-full p-2 border rounded-md mt-1">
            </div>
            <div class="mb-4">
                <button type="submit"
                    class="btn bg-blue-500 text-white px-6 py-3 rounded-md hover:bg-blue-600">Өөрчлөлтүүдийг
                    хадгалах</button>
            </div>
        </form>
    </div>
</body>

</html>