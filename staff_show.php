<?php
session_start();
require 'config/db.php';

// Зөвхөн менежер эрхтэй хэрэглэгчид нэвтрэх боломжтой
function ensureManagerAccess() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'Manager') {
        header('Location: login.php');
        exit();
    }
}

ensureManagerAccess();
$manager_id = $_SESSION['user_id'];
$venue_id_param = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 0;

$error = '';
$success = '';
$message_type = '';

// Өгөгдлийн сантай холбогдох
if ($conn->connect_error) {
    die("Холболт амжилтгүй: " . $conn->connect_error);
}

// Тухайн менежерийн бүх заалуудыг авах
$venuesSql = "SELECT VenueID, Name FROM venue WHERE ManagerID = ?";
$stmt = $conn->prepare($venuesSql);
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$venues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Хаариулагдсан ажилтнуудыг авах
$sql = "SELECT vsa.AssignmentID, vsa.UserID, u.Name, u.Email, u.Phone, v.VenueID, v.Name AS VenueName, vsa.Role 
        FROM VenueStaffAssignment vsa 
        JOIN user u ON vsa.UserID = u.UserID 
        JOIN venue v ON vsa.VenueID = v.VenueID 
        WHERE vsa.ManagerID = ?";

$params = [$manager_id];
$types = "i";

// Хэрэв тодорхой заал сонгогдсон бол түүнийг шүүж харуулах
if ($venue_id_param > 0) {
    $sql .= " AND vsa.VenueID = ?";
    $params[] = $venue_id_param;
    $types .= "i";
}

$sql .= " ORDER BY v.Name, u.Name";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$assignedStaff = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ажилтныг хасах үйлдэл
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_assignment'])) {
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
    
    if (!empty($assignment_id)) {
        $stmt = $conn->prepare("DELETE FROM VenueStaffAssignment 
                              WHERE AssignmentID = ? AND ManagerID = ?");
        $stmt->bind_param("ii", $assignment_id, $manager_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $success = "Ажилтан амжилттай хасагдлаа.";
            $message_type = 'success';
            
            // Хуудсыг шинэчлэх
            $redirect_url = 'staff_show.php';
            if ($venue_id_param > 0) {
                $redirect_url .= "?venue_id=" . $venue_id_param;
            }
            header("Location: " . $redirect_url);
            exit();
        } else {
            $error = "Ажилтан хасах үед алдаа гарлаа. Та зөвхөн өөрийн оноосон ажилтныг хасах боломжтой.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Үүргийн Монгол нэрийг авах
function getRoleName($roleKey) {
    $roles = [
        'VenueStaff' => 'Заалны ажилтан',
        'Accountant' => 'Нягтлан бодогч'
    ];
    
    return isset($roles[$roleKey]) ? $roles[$roleKey] : $roleKey;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ажилтнуудын жагсаалт</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'headers/header_manager.php'; ?>
    
    <div class="container mx-auto py-6 px-4">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <h1 class="text-2xl font-bold text-blue-700">
                    <i class="fas fa-users mr-2"></i>Ажилтнуудын жагсаалт
                </h1>
                
                <div class="flex flex-col sm:flex-row gap-3">
                    <!-- Заал сонгох -->
                    <form method="GET" class="flex gap-2">
                        <select name="venue_id" class="border border-gray-300 rounded-md p-2">
                            <option value="">Бүх заал</option>
                            <?php foreach ($venues as $venue): ?>
                                <option value="<?php echo $venue['VenueID']; ?>" 
                                    <?php echo ($venue_id_param == $venue['VenueID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($venue['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                            <i class="fas fa-filter mr-1"></i> Шүүх
                        </button>
                    </form>
                    
                    <!-- Ажилтан нэмэх товч -->
                    <a href="assign_staff.php<?php echo $venue_id_param > 0 ? '?venue_id='.$venue_id_param : ''; ?>" 
                       class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md flex items-center whitespace-nowrap">
                        <i class="fas fa-user-plus mr-2"></i> Ажилтан нэмэх
                    </a>
                </div>
            </div>

            <?php if (!empty($error) && $message_type == 'error'): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success) && $message_type == 'success'): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <p><?php echo htmlspecialchars($success); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ажилтны жагсаалт (Хүснэгт хэлбэрээр) -->
            <?php if (!empty($assignedStaff)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full border divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ажилтан
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Заал
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Үүрэг
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Үйлдэл
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($assignedStaff as $staff): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                <span class="text-blue-600 font-bold">
                                                    <?php echo strtoupper(substr($staff['Name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($staff['Name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($staff['Email']); ?>
                                                </div>
                                                <?php if(!empty($staff['Phone'])): ?>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($staff['Phone']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($staff['VenueName']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            ID: <?php echo $staff['VenueID']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                              <?php echo $staff['Role'] == 'VenueStaff' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800'; ?>">
                                            <?php echo htmlspecialchars(getRoleName($staff['Role'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <form method="POST" onsubmit="return confirm('Та энэ ажилтныг хасахдаа итгэлтэй байна уу?');">
                                            <input type="hidden" name="assignment_id" value="<?php echo $staff['AssignmentID']; ?>">
                                            <button type="submit" name="remove_assignment" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash mr-1"></i> Хасах
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <?php if ($venue_id_param > 0): ?>
                                    Сонгосон заалд ажилтан оноогдоогүй байна.
                                <?php else: ?>
                                    Танд одоогоор хаариулагдсан ажилтан байхгүй байна.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Жагсаалт (Карт хэлбэрээр) - Гар утсанд илүү сайн харагдах -->
            <?php if (!empty($assignedStaff)): ?>
                <div class="md:hidden mt-6">
                    <h2 class="text-lg font-medium mb-4">Ажилтнуудын жагсаалт</h2>
                    <div class="space-y-4">
                        <?php foreach ($assignedStaff as $staff): ?>
                            <div class="bg-white rounded-lg shadow-sm border p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-medium text-blue-700">
                                            <?php echo htmlspecialchars($staff['Name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-600 mt-1">
                                            <?php echo htmlspecialchars($staff['Email']); ?>
                                        </div>
                                        <?php if(!empty($staff['Phone'])): ?>
                                        <div class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($staff['Phone']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                          <?php echo $staff['Role'] == 'VenueStaff' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800'; ?>">
                                        <?php echo htmlspecialchars(getRoleName($staff['Role'])); ?>
                                    </span>
                                </div>
                                
                                <div class="mt-3 bg-gray-50 p-2 rounded text-sm">
                                    <strong>Заал:</strong> <?php echo htmlspecialchars($staff['VenueName']); ?>
                                </div>
                                
                                <div class="mt-3 flex justify-end">
                                    <form method="POST" onsubmit="return confirm('Та энэ ажилтныг хасахдаа итгэлтэй байна уу?');">
                                        <input type="hidden" name="assignment_id" value="<?php echo $staff['AssignmentID']; ?>">
                                        <button type="submit" name="remove_assignment" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                                            <i class="fas fa-trash mr-1"></i> Хасах
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-6">
                <a href="manager_dashboard.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i> Хянах самбар руу буцах
                </a>
            </div>
        </div>
    </div>
</body>
</html>