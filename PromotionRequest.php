<?php
session_start();
require 'config/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userID = $_SESSION['user_id'];
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list'; // Default to list view
$requestData = null;

// Function to send notifications
function sendNotification($userID, $message) {
    global $conn;
    $sql = "INSERT INTO Notifications (UserID, Message, IsRead, CreatedAt) VALUES (?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $userID, $message);
    $stmt->execute();
    $stmt->close();
}

// Handle delete request
if (isset($_GET['delete'])) {
    $requestID = $_GET['delete'];

    // Delete the promotion request if it belongs to the logged-in user
    $sql = "DELETE FROM PromotionRequest WHERE RequestID = ? AND UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $requestID, $userID);
    if ($stmt->execute()) {
        sendNotification($userID, "Таны хүсэлт амжилттай устгагдлаа.");
        $successMessage = "Хүсэлт амжилттай устгагдлаа.";
    } else {
        $errorMessage = "Хүсэлт устгахад алдаа гарлаа: " . $conn->error;
    }
    $mode = 'list'; // Show list after delete
}

// Fetch specific request data for editing
if ($mode == 'edit' && isset($_GET['edit'])) {
    $requestID = $_GET['edit'];
    $sql = "SELECT * FROM PromotionRequest WHERE RequestID = ? AND UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $requestID, $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $requestData = $result->fetch_assoc();
    } else {
        $errorMessage = "Таны хүсэлт олдсонгүй эсвэл та энэ хүсэлтийг засах эрхгүй байна.";
        $mode = 'list'; // Switch back to list on error
    }
}

// Handle form submission for new request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $mode == 'create') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phoneNumber = $_POST['phone_number'];
    $phoneNumber2 = $_POST['phone_number_2'];
    $venueName = $_POST['venue_name'];
    $venueLocation = $_POST['venue_location'];
    $venuePrice = $_POST['venue_price'];
    $timeSlots = $_POST['time_slots'];
    $description = $_POST['description'];

    $uploadDir = 'uploads/';
    $uploadedFiles = []; // To store paths of uploaded files

    // Handle each uploaded file
    if (isset($_FILES['images']) && $_FILES['images']['error'][0] != UPLOAD_ERR_NO_FILE) {
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

    // Convert array of file paths to a comma-separated string
    $images = !empty($uploadedFiles) ? implode(',', $uploadedFiles) : '';

    // Insert the promotion request into the database
    $sql = "INSERT INTO PromotionRequest (UserID, Name, Email, PhoneNumber, PhoneNumber2, VenueName, VenueLocation, VenuePrice, TimeSlots, Description, Images)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('issssssssss', $userID, $name, $email, $phoneNumber, $phoneNumber2, $venueName, $venueLocation, $venuePrice, $timeSlots, $description, $images);

    if ($stmt->execute()) {
        sendNotification($userID, "Таны хүсэлт амжилттай илгээгдсэн.");
        $successMessage = "Таны хүсэлт амжилттай илгээгдсэн.";
        $mode = 'list'; // Show list after submission
    } else {
        $errorMessage = "Хүсэлт илгээхэд алдаа гарлаа: " . $conn->error;
    }
}

// Handle form submission for edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $mode == 'edit') {
    $requestID = $_GET['edit'];
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
    $images = !empty($uploadedFiles) ? implode(',', $uploadedFiles) : $requestData['Images'];

    // Update the promotion request in the database
    $sql = "UPDATE PromotionRequest SET Name = ?, Email = ?, PhoneNumber = ?, PhoneNumber2 = ?, VenueName = ?, VenueLocation = ?, VenuePrice = ?, TimeSlots = ?, Description = ?, Images = ? WHERE RequestID = ? AND UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssssssssii', $name, $email, $phoneNumber, $phoneNumber2, $venueName, $venueLocation, $venuePrice, $timeSlots, $description, $images, $requestID, $userID);

    if ($stmt->execute()) {
        sendNotification($userID, "Таны promotion хүсэлт амжилттай шинэчлэгдлээ.");
        $successMessage = "Таны promotion хүсэлт амжилттай шинэчлэгдлээ.";
        $mode = 'list'; // Show list after edit
    } else {
        $errorMessage = "Хүсэлт шинэчлэхэд алдаа гарлаа: " . $conn->error;
    }
}

// Fetch all requests for the user (for list view)
$sql = "SELECT * FROM PromotionRequest WHERE UserID = ? ORDER BY RequestID DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userID);
$stmt->execute();
$requestsResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хамтрах хүсэлт</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .image-preview div {
            position: relative;
        }
        .image-preview img {
            transition: transform 0.3s ease;
        }
        .image-preview img:hover {
            transform: scale(1.05);
        }
        .remove-btn {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            background-color: rgba(0, 0, 0, 0.6);
            color: #fff;
            padding: 0.2rem 0.4rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            transition: background-color 0.2s ease;
        }
        .remove-btn:hover {
            background-color: rgba(0, 0, 0, 0.8);
        }
        .btn-transition {
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .btn-transition:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gradient-to-r from-blue-500 to-teal-500 min-h-screen">
    <div class="container mx-auto py-8 px-4">
        <div class="max-w-4xl mx-auto bg-white shadow-2xl rounded-lg p-8">
            <!-- Navigation Back Button -->
            <div class="mb-6">
                <button onclick="window.location.href='user_dashboard.php'"
                    class="bg-blue-600 text-white py-2 px-4 rounded-full hover:bg-blue-700 btn-transition">
                    Буцах
                </button>
            </div>

            <!-- Tab Navigation -->
            <div class="mb-8 flex justify-center">
                <div class="bg-gray-100 rounded-lg flex p-1 w-full max-w-md">
                    <a href="?mode=list" class="<?php echo $mode == 'list' ? 'bg-blue-600 text-white' : 'text-gray-600'; ?> flex-1 text-center py-3 px-4 rounded-lg transition-all duration-300 font-medium">Миний хүсэлтүүд</a>
                    <a href="?mode=create" class="<?php echo $mode == 'create' ? 'bg-blue-600 text-white' : 'text-gray-600'; ?> flex-1 text-center py-3 px-4 rounded-lg transition-all duration-300 font-medium">Шинэ хүсэлт үүсгэх</a>
                </div>
            </div>

            <!-- Success/Error Message -->
            <?php if (isset($successMessage)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                    <p><?php echo $successMessage; ?></p>
                </div>
            <?php elseif (isset($errorMessage)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                    <p><?php echo $errorMessage; ?></p>
                </div>
            <?php endif; ?>

            <!-- Content based on mode -->
            <?php if ($mode == 'list'): ?>
                <!-- List View -->
                <h2 class="text-3xl font-semibold text-center text-gray-800 mb-6">Миний Хүсэлтүүд</h2>
                
                <?php if ($requestsResult->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto border-collapse">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Нэр</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Талбай</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Үнэ</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Цагийн хуваарь</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Төлөв</th>
                                    <th class="py-3 px-4 text-left text-sm font-medium text-gray-600">Үйлдлүүд</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $requestsResult->fetch_assoc()): ?>
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($row['Name']); ?></td>
                                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($row['VenueName']); ?></td>
                                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($row['VenuePrice']); ?></td>
                                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($row['TimeSlots']); ?></td>
                                        <td class="py-3 px-4 text-sm text-gray-800"><?php echo isset($row['RequestStatus']) ? htmlspecialchars($row['RequestStatus']) : 'Хүлээгдэж байна'; ?></td>
                                        <td class="py-3 px-4 text-sm text-gray-800">
                                            <a href="?mode=edit&edit=<?php echo $row['RequestID']; ?>" class="text-blue-600 hover:text-blue-800 mr-2">Засах</a>
                                            <a href="?delete=<?php echo $row['RequestID']; ?>" onclick="return confirm('Та энэ хүсэлтийг устгахдаа итгэлтэй байна уу?')" class="text-red-600 hover:text-red-800">Устгах</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-gray-600">Танд одоогоор хүсэлт байхгүй байна. Шинэ хүсэлт үүсгэхийн тулд дээрх "Шинэ хүсэлт үүсгэх" товчийг дарна уу.</p>
                <?php endif; ?>

                <!-- Quick Create Button -->
                <div class="mt-8 text-center">
                    <a href="?mode=create" class="inline-block bg-blue-600 text-white py-3 px-6 rounded-lg hover:bg-blue-700 btn-transition">Шинэ хүсэлт үүсгэх</a>
                </div>

            <?php elseif ($mode == 'create'): ?>
                <!-- Create Form -->
                <h2 class="text-3xl font-semibold text-center text-gray-800 mb-6">
                    Менежер болон хамтарж ажиллах хүсэлт илгээх
                </h2>

                <form action="?mode=create" method="POST" enctype="multipart/form-data"
                    class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label for="name" class="block text-lg font-medium text-gray-700">Нэр:</label>
                        <input type="text" id="name" name="name" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="email" class="block text-lg font-medium text-gray-700">Имэйл:</label>
                        <input type="email" id="email" name="email" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="phone_number" class="block text-lg font-medium text-gray-700">Утасны дугаар:</label>
                        <input type="text" id="phone_number" name="phone_number" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="phone_number_2" class="block text-lg font-medium text-gray-700">Хоёрдугаар утасны дугаар
                            (Сонголт):</label>
                        <input type="text" id="phone_number_2" name="phone_number_2"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="venue_name" class="block text-lg font-medium text-gray-700">Заалны нэр:</label>
                        <input type="text" id="venue_name" name="venue_name" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="venue_location" class="block text-lg font-medium text-gray-700">Заалны байршил:</label>
                        <input type="text" id="venue_location" name="venue_location" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="venue_price" class="block text-lg font-medium text-gray-700">Заалны үнэ:</label>
                        <input type="number" step="0.01" id="venue_price" name="venue_price" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="time_slots" class="block text-lg font-medium text-gray-700">Цагийн хуваарь:</label>
                        <textarea id="time_slots" name="time_slots" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <label for="description" class="block text-lg font-medium text-gray-700">Тодорхойлолт:</label>
                        <textarea id="description" name="description"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <label for="images" class="block text-lg font-medium text-gray-700">Зураг байршуулна уу:</label>
                        <input type="file" id="images" name="images[]" multiple accept="image/*" onchange="previewImages()"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <div class="image-preview flex flex-wrap gap-4 mt-4" id="imagePreview"></div>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit"
                            class="w-full py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 btn-transition">
                            Хүсэлт илгээх
                        </button>
                    </div>
                </form>

            <?php elseif ($mode == 'edit' && $requestData): ?>
                <!-- Edit Form -->
                <h2 class="text-3xl font-semibold text-center text-gray-800 mb-6">
                    Хүсэлт засах
                </h2>

                <form action="?mode=edit&edit=<?php echo $_GET['edit']; ?>" method="POST" enctype="multipart/form-data"
                    class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label for="name" class="block text-lg font-medium text-gray-700">Нэр:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($requestData['Name']); ?>" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="email" class="block text-lg font-medium text-gray-700">Имэйл:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($requestData['Email']); ?>" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="phone_number" class="block text-lg font-medium text-gray-700">Утасны дугаар:</label>
                        <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($requestData['PhoneNumber']); ?>" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="phone_number_2" class="block text-lg font-medium text-gray-700">Хоёрдугаар утасны дугаар (Сонголт):</label>
                        <input type="text" id="phone_number_2" name="phone_number_2" value="<?php echo htmlspecialchars($requestData['PhoneNumber2']); ?>"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="venue_name" class="block text-lg font-medium text-gray-700">Заалны нэр:</label>
                        <input type="text" id="venue_name" name="venue_name" value="<?php echo htmlspecialchars($requestData['VenueName']); ?>" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="venue_location" class="block text-lg font-medium text-gray-700">Заалны байршил:</label>
                        <input type="text" id="venue_location" name="venue_location" value="<?php echo htmlspecialchars($requestData['VenueLocation']); ?>" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="venue_price" class="block text-lg font-medium text-gray-700">Заалны үнэ:</label>
                        <input type="number" step="0.01" id="venue_price" name="venue_price" value="<?php echo htmlspecialchars($requestData['VenuePrice']); ?>" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="space-y-2">
                        <label for="time_slots" class="block text-lg font-medium text-gray-700">Цагийн хуваарь:</label>
                        <textarea id="time_slots" name="time_slots" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($requestData['TimeSlots']); ?></textarea>
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <label for="description" class="block text-lg font-medium text-gray-700">Тодорхойлолт:</label>
                        <textarea id="description" name="description"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($requestData['Description']); ?></textarea>
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <label for="images" class="block text-lg font-medium text-gray-700">Зураг:</label>
                        <?php if (!empty($requestData['Images'])): ?>
                            <p class="text-sm text-gray-500 mb-2">Одоогийн зураг: <?php echo count(explode(',', $requestData['Images'])); ?> зураг байна. Шинэ зураг нэмснээр одоогийн зургуудыг сольж болно.</p>
                        <?php endif; ?>
                        <input type="file" id="images" name="images[]" multiple accept="image/*" onchange="previewImages()"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <div class="image-preview flex flex-wrap gap-4 mt-4" id="imagePreview"></div>
                    </div>
                    <div class="sm:col-span-2 flex space-x-4">
                        <a href="?mode=list" class="w-1/3 py-3 bg-gray-500 text-white text-center font-semibold rounded-lg hover:bg-gray-600 focus:ring-2 focus:ring-gray-500 btn-transition">
                            Буцах
                        </a>
                        <button type="submit" class="w-2/3 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 btn-transition">
                            Өөрчлөлтүүдийг хадгалах
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function previewImages() {
            const imagePreview = document.getElementById('imagePreview');
            imagePreview.innerHTML = ""; // Clear previous previews
            const files = document.getElementById('images').files;

            // Loop through selected files and display previews
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();

                reader.onload = function (e) {
                    const imageWrapper = document.createElement('div');
                    imageWrapper.classList.add('relative');

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.classList.add('w-24', 'h-24', 'object-cover', 'rounded-lg');
                    imageWrapper.appendChild(img);

                    const removeButton = document.createElement('button');
                    removeButton.classList.add('remove-btn');
                    removeButton.innerHTML = 'X';
                    removeButton.onclick = function () {
                        imageWrapper.remove();
                    };

                    imageWrapper.appendChild(removeButton);
                    imagePreview.appendChild(imageWrapper);
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>