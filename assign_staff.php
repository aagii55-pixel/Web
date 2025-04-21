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

$error = '';
$success = '';
$message_type = '';
$users = [];
$search_query = '';
$venue_id_param = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 0;

// Өгөгдлийн сангийн холболтыг шалгах
if ($conn->connect_error) {
    die("Холболт амжилтгүй: " . $conn->connect_error);
}

// Тухайн менежерийн удирдаж байгаа заалуудыг авах
$stmt = $conn->prepare("SELECT VenueID, Name FROM venue WHERE ManagerID = ?");
if ($stmt === false) {
    die('MySQL prepare алдаа: ' . $conn->error);
}
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$venues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Боломжтой үүрэг/роль-үүд
$roles = [
    'VenueStaff' => 'Заалны ажилтан',
    'Accountant' => 'Нягтлан бодогч'
];

// Хэрэглэгч хайх
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $search_param = "%{$search_query}%";
    
    $stmt = $conn->prepare("SELECT UserID, Name, Email, Phone FROM user 
                          WHERE (Name LIKE ? OR Email LIKE ? OR Phone LIKE ?) 
                          AND RoleID = 4 AND Status = 'Active' 
                          LIMIT 10");
    
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Тухайн заалуудад оноогдсон ажилтнуудыг авах
$venue_staff = [];
if (!empty($venues)) {
    $venue_ids = array_column($venues, 'VenueID');
    $placeholders = str_repeat('?,', count($venue_ids) - 1) . '?';
    
    $sql = "SELECT vsa.AssignmentID, vsa.UserID, u.Name as UserName, u.Email, 
           v.VenueID, v.Name as VenueName, vsa.Role 
           FROM VenueStaffAssignment vsa 
           JOIN user u ON vsa.UserID = u.UserID 
           JOIN venue v ON vsa.VenueID = v.VenueID 
           WHERE vsa.ManagerID = ? AND vsa.VenueID IN ($placeholders)
           ORDER BY v.Name, u.Name";
    
    $stmt = $conn->prepare($sql);
    
    // Параметрийн массив үүсгэх
    $params = array_merge([$manager_id], $venue_ids);
    
    // bind_param-д зориулсан төрлийн мөр үүсгэх
    $types = str_repeat('i', count($params));
    
    // Динамик параметрүүдийг зохицуулахын тулд call_user_func_array ашиглах
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (!isset($venue_staff[$row['VenueID']])) {
            $venue_staff[$row['VenueID']] = [
                'name' => $row['VenueName'],
                'staff' => []
            ];
        }
        
        $venue_staff[$row['VenueID']]['staff'][] = [
            'id' => $row['AssignmentID'],
            'user_id' => $row['UserID'],
            'name' => $row['UserName'],
            'email' => $row['Email'],
            'role' => $row['Role']
        ];
    }
    $stmt->close();
}

// Ажилтан оноох маягт илгээх үед боловсруулах
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_staff'])) {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $venue_ids = isset($_POST['venue_ids']) ? $_POST['venue_ids'] : []; 
    $selected_roles = isset($_POST['roles']) ? $_POST['roles'] : [];

    if (empty($user_id) || empty($venue_ids) || empty($selected_roles)) {
        $error = "Хэрэглэгчийн ID, дор хаяж нэг заал, болон нэг үүрэг сонгоно уу.";
        $message_type = 'error';
    } else {
        // Хэрэглэгч байгаа эсэх болон энгийн хэрэглэгч эсэхийг шалгах
        $stmt = $conn->prepare("SELECT UserID, Name FROM user WHERE UserID = ? AND RoleID = 4 AND Status = 'Active'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $userResult = $stmt->get_result();
        $userData = $userResult->fetch_assoc();
        $stmt->close();

        if ($userResult->num_rows > 0) {
            $userName = $userData['Name'];
            $assigned_count = 0;
            $already_assigned = 0;
            
            // Хэрэглэгчийг сонгосон заал болон үүрэгт оноох, давхардлаас зайлсхийх
            foreach ($venue_ids as $venue_id) {
                foreach ($selected_roles as $role) {
                    // Аль хэдийн оноогдсон эсэхийг шалгах
                    $checkStmt = $conn->prepare("SELECT 1 FROM VenueStaffAssignment 
                                               WHERE UserID = ? AND VenueID = ? AND Role = ? AND ManagerID = ?");
                    $checkStmt->bind_param("iisi", $user_id, $venue_id, $role, $manager_id);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $checkStmt->close();

                    if ($checkResult->num_rows > 0) {
                        $already_assigned++;
                    } else {
                        $stmt = $conn->prepare("INSERT INTO VenueStaffAssignment 
                                             (UserID, VenueID, ManagerID, Role) 
                                             VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iiis", $user_id, $venue_id, $manager_id, $role);
                        $stmt->execute();
                        $stmt->close();
                        $assigned_count++;
                    }
                }
            }
            
            if ($assigned_count > 0) {
                $success = "$userName амжилттай {$assigned_count} үүрэгт оноогдлоо.";
                $message_type = 'success';
                
                if ($already_assigned > 0) {
                    $success .= " ({$already_assigned} үүрэг аль хэдийн оноогдсон байсан.)";
                }
                
                // Амжилттай оноогдсон бол хайлтын үр дүнг цэвэрлэх
                $users = [];
                $search_query = '';
            } else if ($already_assigned > 0) {
                $error = "$userName аль хэдийн бүх сонгосон заал/үүрэгт оноогдсон байна.";
                $message_type = 'error';
            } else {
                $error = "Ажилтан оноох үед алдаа гарлаа. Дахин оролдоно уу.";
                $message_type = 'error';
            }
        } else {
            $error = "Идэвхтэй хэрэглэгч олдсонгүй. ID-ээ шалгана уу эсвэл хэрэглэгч хайх функцийг ашиглана уу.";
            $message_type = 'error';
        }
    }
}

// Ажилтан хасах үйлдэл
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
        } else {
            $error = "Ажилтан хасах үед алдаа гарлаа. Та зөвхөн өөрийн оноосон ажилтныг хасах боломжтой.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ажилтан оноох</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 text-gray-800">
    <?php include 'headers/header_manager.php'; ?>

    <div class="container mx-auto p-4 md:p-8">
        <div class="flex flex-col md:flex-row gap-6">
            <!-- Зүүн талын ажилтан оноох хэсэг -->
            <div class="bg-white shadow-lg rounded-lg p-6 md:w-1/2">
                <h1 class="text-2xl font-bold mb-6 text-blue-700">
                    <i class="fas fa-user-plus mr-2"></i>Ажилтан оноох
                </h1>

                <?php if (!empty($error) && $message_type == 'error'): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success) && $message_type == 'success'): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <p><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Хэрэглэгч хайх -->
                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-3 text-gray-700">Хэрэглэгч хайх</h2>
                    <form method="GET" class="mb-4">
                        <div class="flex gap-2">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" 
                                class="border-2 border-gray-300 rounded-md p-2 flex-grow"
                                placeholder="Нэр, Имэйл, Утас">
                            
                            <?php if (!empty($venue_id_param)): ?>
                                <input type="hidden" name="venue_id" value="<?php echo $venue_id_param; ?>">
                            <?php endif; ?>
                            
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                                <i class="fas fa-search mr-1"></i> Хайх
                            </button>
                        </div>
                    </form>
                    
                    <?php if (!empty($users)): ?>
                        <div class="bg-gray-50 p-4 rounded-md border border-gray-200 mb-4 h-64 overflow-y-auto">
                            <h3 class="font-medium mb-2">Хайлтын үр дүн (<?php echo count($users); ?>):</h3>
                            <div class="divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <div class="py-2">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <p class="font-medium"><?php echo htmlspecialchars($user['Name']); ?></p>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo htmlspecialchars($user['Email']); ?> | 
                                                    <?php echo htmlspecialchars($user['Phone']); ?>
                                                </p>
                                            </div>
                                            <button type="button" 
                                                class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600"
                                                onclick="selectUser(<?php echo $user['UserID']; ?>, '<?php echo htmlspecialchars($user['Name']); ?>')">
                                                Сонгох
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif (!empty($search_query)): ?>
                        <div class="bg-yellow-50 p-4 rounded-md border border-yellow-200 mb-4">
                            <p class="text-yellow-700">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                "<?php echo htmlspecialchars($search_query); ?>" хайлтаар хэрэглэгч олдсонгүй.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ажилтан оноох форм -->
                <form method="POST" id="assignForm">
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <label for="user_id" class="block text-sm font-semibold">Сонгосон хэрэглэгч:</label>
                            <span id="selectedUserName" class="text-sm text-gray-600 italic">Хэрэглэгч сонгоогүй</span>
                        </div>
                        <input type="hidden" id="user_id" name="user_id" value="">
                        <div id="userCard" class="bg-gray-50 p-4 rounded-md border border-gray-200 hidden">
                            <p class="text-lg font-medium" id="displayUserName"></p>
                            <p class="text-sm text-blue-500">ID: <span id="displayUserID"></span></p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Заал сонгох:</label>
                        <?php if (!empty($venues)): ?>
                            <div class="bg-gray-50 p-4 rounded-md border border-gray-200 max-h-48 overflow-y-auto">
                                <?php foreach ($venues as $venue): ?>
                                    <div class="flex items-center my-2">
                                        <input type="checkbox" name="venue_ids[]" value="<?php echo $venue['VenueID']; ?>" 
                                            id="venue_<?php echo $venue['VenueID']; ?>"
                                            <?php echo ($venue_id_param == $venue['VenueID']) ? 'checked' : ''; ?>
                                            class="mr-2 h-4 w-4">
                                        <label for="venue_<?php echo $venue['VenueID']; ?>" class="cursor-pointer">
                                            <?php echo htmlspecialchars($venue['Name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-50 p-4 rounded-md border border-yellow-200">
                                <p class="text-yellow-700">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Танд оноох заал байхгүй байна. Эхлээд заал нэмнэ үү.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold mb-2">Үүрэг сонгох:</label>
                        <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                            <?php foreach ($roles as $role_key => $role_label): ?>
                                <div class="flex items-center my-2">
                                    <input type="checkbox" name="roles[]" value="<?php echo $role_key; ?>" 
                                        id="role_<?php echo $role_key; ?>"
                                        class="mr-2 h-4 w-4">
                                    <label for="role_<?php echo $role_key; ?>" class="cursor-pointer">
                                        <?php echo htmlspecialchars($role_label); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" name="assign_staff" 
                        class="w-full bg-blue-500 text-white px-6 py-3 rounded-md hover:bg-blue-600 font-medium flex items-center justify-center">
                        <i class="fas fa-user-plus mr-2"></i> Ажилтан оноох
                    </button>
                </form>
            </div>

            <!-- Баруун талын одоогийн ажилтнууд -->
            <div class="bg-white shadow-lg rounded-lg p-6 md:w-1/2">
                <h2 class="text-2xl font-bold mb-6 text-blue-700">
                    <i class="fas fa-users mr-2"></i>Одоогийн ажилтнууд
                </h2>

                <?php if (!empty($venue_staff)): ?>
                    <?php foreach ($venue_staff as $venue_id => $venue_data): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-2 bg-blue-50 p-2 rounded">
                                <?php echo htmlspecialchars($venue_data['name']); ?>
                            </h3>
                            
                            <?php if (!empty($venue_data['staff'])): ?>
                                <div class="divide-y divide-gray-200">
                                    <?php foreach ($venue_data['staff'] as $staff): ?>
                                        <div class="py-3 px-2 flex justify-between items-center">
                                            <div>
                                                <p class="font-medium"><?php echo htmlspecialchars($staff['name']); ?></p>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($staff['email']); ?></p>
                                                <span class="text-xs inline-block bg-blue-100 text-blue-600 px-2 py-1 rounded-full mt-1">
                                                    <?php echo ($staff['role'] == 'VenueStaff') ? 'Заалны ажилтан' : 'Нягтлан бодогч'; ?>
                                                </span>
                                            </div>
                                            <form method="POST" onsubmit="return confirm('Та энэ ажилтныг хасахдаа итгэлтэй байна уу?');">
                                                <input type="hidden" name="assignment_id" value="<?php echo $staff['id']; ?>">
                                                <button type="submit" name="remove_assignment" 
                                                    class="bg-red-500 text-white p-2 rounded-full hover:bg-red-600">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-600 italic">Энэ заалд ажилтан оноогдоогүй байна.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-yellow-50 p-4 rounded-md border border-yellow-200">
                        <p class="text-yellow-700">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Одоогоор ямар ч ажилтан оноогдоогүй байна.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Хэрэглэгч сонгох функц
        function selectUser(userId, userName) {
            document.getElementById('user_id').value = userId;
            document.getElementById('selectedUserName').textContent = 'Сонгогдсон';
            document.getElementById('displayUserName').textContent = userName;
            document.getElementById('displayUserID').textContent = userId;
            document.getElementById('userCard').classList.remove('hidden');
        }
        
        // Форм илгээхээс өмнө шалгах
        document.getElementById('assignForm').addEventListener('submit', function(e) {
            if (!document.getElementById('user_id').value) {
                e.preventDefault();
                alert('Хэрэглэгч сонгоно уу!');
                return false;
            }
            
            // Заал сонгогдсон эсэхийг шалгах
            const venueCheckboxes = document.querySelectorAll('input[name="venue_ids[]"]:checked');
            if (venueCheckboxes.length === 0) {
                e.preventDefault();
                alert('Дор хаяж нэг заал сонгоно уу!');
                return false;
            }
            
            // Үүрэг сонгогдсон эсэхийг шалгах
            const roleCheckboxes = document.querySelectorAll('input[name="roles[]"]:checked');
            if (roleCheckboxes.length === 0) {
                e.preventDefault();
                alert('Дор хаяж нэг үүрэг сонгоно уу!');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>