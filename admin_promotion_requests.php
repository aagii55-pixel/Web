<?php
session_start();
require_once 'config/db.php';


// Enhanced admin access control
function ensureAdminAccess() {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
        error_log("Unauthorized admin access attempt from IP: " . $_SERVER['REMOTE_ADDR']);
        header('Location: ../login.php?error=unauthorized');
        exit();
    }
}

// CSRF Protection Function
function validateCSRFToken($token) {
    return !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// Enhanced error handling
function handleDatabaseError($conn) {
    error_log("Database Error: " . $conn->error);
    die("A database error occurred. Please contact support.");
}

// Enforce role check
ensureAdminAccess();

// CSRF Token Initialization with more secure generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sanitize and validate input function
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Fetch pending requests count
$pendingCountStmt = $conn->prepare("SELECT COUNT(*) as count FROM PromotionRequest WHERE RequestStatus = 'Pending'");
$pendingCountStmt->execute();
$pendingCountResult = $pendingCountStmt->get_result();
$pendingCount = $pendingCountResult->fetch_assoc()['count'];
$pendingCountStmt->close();

// Fetch promotion requests with improved query
$promotionRequestsSql = "SELECT 
    pr.RequestID, 
    pr.UserID, 
    pr.Name, 
    pr.Email, 
    pr.PhoneNumber, 
    pr.PhoneNumber2,
    pr.VenueName, 
    pr.VenueLocation, 
    pr.VenuePrice, 
    pr.TimeSlots, 
    pr.Description, 
    pr.Images, 
    pr.RequestStatus, 
    pr.RequestDate,
    u.Name AS UserFullName,
    u.Email AS UserEmail
FROM PromotionRequest pr
JOIN User u ON pr.UserID = u.UserID
ORDER BY pr.RequestDate DESC";

$promotionRequestsStmt = $conn->prepare($promotionRequestsSql);
if (!$promotionRequestsStmt) {
    handleDatabaseError($conn);
}
$promotionRequestsStmt->execute();
$promotionRequests = $promotionRequestsStmt->get_result();

// Handle Request Actions with Prepared Statements
if (isset($_GET['action'], $_GET['requestId'], $_GET['csrf_token'])) {
    if (!validateCSRFToken($_GET['csrf_token'])) {
        die("CSRF token validation failed");
    }

    $requestId = filter_input(INPUT_GET, 'requestId', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);

    if ($requestId && in_array($action, ['approve', 'reject'])) {
        // Fetch promotion request and user details securely
        $requestStmt = $conn->prepare("SELECT UserID, Name, Email, VenueName, VenueLocation, VenuePrice, Description FROM PromotionRequest WHERE RequestID = ?");
        $requestStmt->bind_param("i", $requestId);
        $requestStmt->execute();
        $requestResult = $requestStmt->get_result();
        $requestData = $requestResult->fetch_assoc();
        $requestStmt->close();

        if ($requestData) {
            $userId = $requestData['UserID'];
            
            // Use transactions for data integrity
            $conn->begin_transaction();

            try {
                if ($action === 'approve') {
                    // 1. Get the Manager role ID
                    $roleStmt = $conn->prepare("SELECT RoleID FROM Role WHERE RoleName = 'Manager'");
                    $roleStmt->execute();
                    $roleResult = $roleStmt->get_result();
                    $roleData = $roleResult->fetch_assoc();
                    $roleStmt->close();
                    
                    if (!$roleData) {
                        // If Manager role doesn't exist, create it
                        $createRoleStmt = $conn->prepare("INSERT INTO Role (RoleName) VALUES ('Manager')");
                        $createRoleStmt->execute();
                        $managerRoleId = $conn->insert_id;
                        $createRoleStmt->close();
                    } else {
                        $managerRoleId = $roleData['RoleID'];
                    }

                    // 2. Update user role to Manager
                    $updateUserStmt = $conn->prepare("UPDATE User SET RoleID = ? WHERE UserID = ?");
                    $updateUserStmt->bind_param("ii", $managerRoleId, $userId);
                    $updateUserStmt->execute();
                    $updateUserStmt->close();

                    // 3. Approve request with prepared statement
                    $approveStmt = $conn->prepare("UPDATE PromotionRequest SET RequestStatus = 'Approved' WHERE RequestID = ?");
                    $approveStmt->bind_param("i", $requestId);
                    $approveStmt->execute();
                    $approveStmt->close();

                    // 4. Get default sport type (first one from enum list, for example)
                    $defaultSportType = 'Хөлбөмбөг'; // Default sport type

                    // 5. Insert venue with prepared statement - FIXED to match venue table structure
                    $venueStmt = $conn->prepare("INSERT INTO Venue (ManagerID, Name, Location, SportType, HourlyPrice, Description) 
                        VALUES (?, ?, ?, ?, ?, ?)");
                    $venueStmt->bind_param("isssds", $userId, $requestData['VenueName'], $requestData['VenueLocation'], $defaultSportType, $requestData['VenuePrice'], $requestData['Description']);
                    $venueStmt->execute();
                    $venueId = $conn->insert_id;
                    $venueStmt->close();
                    
                    // 6. Update the user's VenueID field to associate them with the venue
                    $linkUserToVenueStmt = $conn->prepare("UPDATE User SET VenueID = ? WHERE UserID = ?");
                    $linkUserToVenueStmt->bind_param("ii", $venueId, $userId);
                    $linkUserToVenueStmt->execute();
                    $linkUserToVenueStmt->close();

                    // 7. Notify user
                    $notifyStmt = $conn->prepare("INSERT INTO Notifications (UserID, Title, Message) VALUES (?, 'Танхим нэмэх хүсэлт батлагдсан', 'Таны танхим нэмэх хүсэлт зөвшөөрөгдлөө. Та одоо менежер болсон байна.')");
                    $notifyStmt->bind_param("i", $userId);
                    $notifyStmt->execute();
                    $notifyStmt->close();

                    $message = "Хүсэлт амжилттай зөвшөөрөгдсөн!";
                } elseif ($action === 'reject') {
                    // Reject request with prepared statement
                    $rejectStmt = $conn->prepare("UPDATE PromotionRequest SET RequestStatus = 'Rejected' WHERE RequestID = ?");
                    $rejectStmt->bind_param("i", $requestId);
                    $rejectStmt->execute();
                    $rejectStmt->close();

                    // Notify user
                    $notifyStmt = $conn->prepare("INSERT INTO Notifications (UserID, Title, Message) VALUES (?, 'Танхим нэмэх хүсэлт татгалзсан', 'Таны танхим нэмэх хүсэлт татгалзагдлаа.')");
                    $notifyStmt->bind_param("i", $userId);
                    $notifyStmt->execute();
                    $notifyStmt->close();

                    $message = "Хүсэлт амжилттай татгалзсан!";
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Transaction failed: " . $e->getMessage());
                $message = "Алдаа гарлаа. Дахин оролдоно уу.";
            }
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../a/logout.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Танхим Нэмэх Хүсэлтүүд</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 80%;
            max-height: 80%;
            overflow-y: auto;
        }
        .image-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .image-thumbnail {
            width: 100px;
            height: 100px;
            object-fit: cover;
            cursor: pointer;
        }
    </style>
</head>
<nav class="bg-gray-800 text-white px-6 py-4 shadow-lg flex justify-between items-center">
    <div class="flex space-x-6">
        <!-- Dashboard Link -->
        <a href="admin_dashboard.php" class="relative <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">Дашбоард</a>
        <a href="admin_search_reports.php" class="relative <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">Хайлт & Тайлан</a>

        <!-- Promotion Requests Link with Pending Count -->
        <a href="admin_promotion_requests.php" class="relative flex items-center <?php echo basename($_SERVER['PHP_SELF']) == 'admin_promotion_requests.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">
            Хамтрах Хүсэлтүүд
            <?php if ($pendingCount > 0): ?>
                <span class="absolute -top-2 -right-3 bg-red-600 text-white text-xs font-semibold rounded-full px-2 py-1"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Manage Users Link -->
        <a href="manage_users.php" class="relative <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'font-semibold text-yellow-300' : 'hover:text-yellow-400'; ?> transition-colors duration-200">Хэрэглэгчийг удирдах</a>
    </div>
    
    <!-- Logout Button -->
    <a href="?logout=true" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-full transition-all duration-300 ease-in-out hover:scale-105">Гарах</a>
</nav>
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <h2 class="text-3xl font-bold text-center mb-6">Танхим Нэмэх Хүсэлтүүд</h2>

        <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 bg-green-200 text-green-800 rounded">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white shadow-md rounded-lg overflow-hidden">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-left">Дугаар</th>
                        <th class="py-3 px-4 text-left">Танхимын Нэр</th>
                        <th class="py-3 px-4 text-left">Байршил</th>
                        <th class="py-3 px-4 text-left">Үнэ</th>
                        <th class="py-3 px-4 text-left">Төлөв</th>
                        <th class="py-3 px-4 text-left">Хүсэлт Огноо</th>
                        <th class="py-3 px-4 text-left">Үйлдэл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $promotionRequests->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-50" onclick="showRequestDetails(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                            <td class="py-3 px-4"><?php echo $row['RequestID']; ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['VenueName']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($row['VenueLocation']); ?></td>
                            <td class="py-3 px-4"><?php echo number_format($row['VenuePrice'], 2); ?> ₮</td>
                            <td class="py-3 px-4">
                                <?php 
                                $statusClass = [
                                    'Pending' => 'bg-yellow-200 text-yellow-800',
                                    'Approved' => 'bg-green-200 text-green-800',
                                    'Rejected' => 'bg-red-200 text-red-800'
                                ];
                                echo "<span class='px-2 py-1 rounded " . $statusClass[$row['RequestStatus']] . "'>" . 
                                     htmlspecialchars($row['RequestStatus']) . 
                                     "</span>"; 
                                ?>
                            </td>
                            <td class="py-3 px-4"><?php echo date('Y-m-d H:i', strtotime($row['RequestDate'])); ?></td>
                            <td class="py-3 px-4">
                                <?php if ($row['RequestStatus'] == 'Pending'): ?>
                                    <a href="?action=approve&requestId=<?php echo $row['RequestID']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                       class="text-green-600 hover:text-green-800 mr-2">Зөвшөөрөх</a>

                                    <a href="?action=reject&requestId=<?php echo $row['RequestID']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                       class="text-red-600 hover:text-red-800">Татгалзах</a>
                                <?php else: ?>
                                    <span class="text-gray-500">Шийдвэрлэгдсэн</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for Request Details -->
    <div id="requestDetailsModal" class="modal">
        <div class="modal-content bg-white p-8 rounded-lg shadow-xl w-3/4 max-h-3/4 overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modalTitle" class="text-2xl font-bold">Хүсэлтийн Дэлгэрэнгүй</h2>
                <button onclick="closeModal()" class="text-red-500 hover:text-red-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <h3 class="font-semibold text-lg mb-2">Хүсэлт Гаргагчийн Мэдээлэл</h3>
                    <p><strong>Нэр:</strong> <span id="modalUserName"></span></p>
                    <p><strong>Имэйл:</strong> <span id="modalUserEmail"></span></p>
                    <p><strong>Утас 1:</strong> <span id="modalPhone1"></span></p>
                    <p><strong>Утас 2:</strong> <span id="modalPhone2"></span></p>
                </div>

                <div>
                    <h3 class="font-semibold text-lg mb-2">Танхимын Мэдээлэл</h3>
                    <p><strong>Танхимын Нэр:</strong> <span id="modalVenueName"></span></p>
                    <p><strong>Байршил:</strong> <span id="modalVenueLocation"></span></p>
                    <p><strong>Үнэ:</strong> <span id="modalVenuePrice"></span></p>
                    <p><strong>Цагийн Мэдээлэл:</strong> <span id="modalTimeSlots"></span></p>
                </div>
            </div>

            <div class="mt-4">
                <h3 class="font-semibold text-lg mb-2">Нэмэлт Тайлбар</h3>
                <p id="modalDescription"></p>
            </div>

            <div class="mt-4">
                <h3 class="font-semibold text-lg mb-2">Зураг</h3>
                <div id="modalImageGallery" class="image-gallery"></div>
            </div>
        </div>
    </div>

    <script>
        function showRequestDetails(request) {
            // Populate modal with request details
            document.getElementById('modalUserName').textContent = request.Name;
            document.getElementById('modalUserEmail').textContent = request.Email;
            document.getElementById('modalPhone1').textContent = request.PhoneNumber;
            document.getElementById('modalPhone2').textContent = request.PhoneNumber2 || 'Өгөөгүй';
            document.getElementById('modalVenueName').textContent = request.VenueName;
            document.getElementById('modalVenueLocation').textContent = request.VenueLocation;
            document.getElementById('modalVenuePrice').textContent = request.VenuePrice.toLocaleString() + ' ₮';
            document.getElementById('modalTimeSlots').textContent = request.TimeSlots;
            document.getElementById('modalDescription').textContent = request.Description || 'Тайлбар байхгүй';

            // Handle images
            const imageGallery = document.getElementById('modalImageGallery');
            imageGallery.innerHTML = ''; // Clear previous images
            
            if (request.Images) {
                const images = request.Images.split(',');
                images.forEach(imagePath => {
                    const img = document.createElement('img');
                    img.src = imagePath.trim();
                    img.classList.add('image-thumbnail');
                    img.onclick = () => openFullImage(imagePath.trim());
                    imageGallery.appendChild(img);
                });
            } else {
                imageGallery.textContent = 'Зураг байхгүй';
            }

            // Show modal
            document.getElementById('requestDetailsModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('requestDetailsModal').style.display = 'none';
        }

        function openFullImage(imagePath) {
            const fullImageModal = document.createElement('div');
            fullImageModal.style.position = 'fixed';
            fullImageModal.style.top = '0';
            fullImageModal.style.left = '0';
            fullImageModal.style.width = '100%';
            fullImageModal.style.height = '100%';
            fullImageModal.style.background = 'rgba(0,0,0,0.8)';
            fullImageModal.style.display = 'flex';
            fullImageModal.style.justifyContent = 'center';
            fullImageModal.style.alignItems = 'center';
            fullImageModal.style.zIndex = '1000';

            const fullImage = document.createElement('img');
            fullImage.src = imagePath;
            fullImage.style.maxWidth = '90%';
            fullImage.style.maxHeight = '90%';
            fullImage.style.objectFit = 'contain';

            fullImageModal.appendChild(fullImage);
            document.body.appendChild(fullImageModal);

            fullImageModal.onclick = () => {
                document.body.removeChild(fullImageModal);
            };
        }

        // Close modal if clicked outside
        document.getElementById('requestDetailsModal').onclick = function(event) {
            if (event.target === this) {
                closeModal();
            }
        };
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>