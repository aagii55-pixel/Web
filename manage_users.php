<?php
session_start();
require 'config/db.php';

// Function to ensure that only admins can access this page
function ensureAdminAccess() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Admin') {
        header('Location: ../login.php');
        exit();
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../a/logout.php');
    exit();
}
$pendingCountQuery = "SELECT COUNT(*) as pendingCount FROM PromotionRequest WHERE RequestStatus = 'Pending'";
$pendingCountResult = $conn->query($pendingCountQuery);
$pendingCountRow = $pendingCountResult->fetch_assoc();
$pendingCount = $pendingCountRow['pendingCount'];
// Enforce role check
ensureAdminAccess();

// Handle Promotion, Demotion, Deletion, Ban, and Activation
if (isset($_GET['action'])) {
    $userId = $_GET['userId'];
    $action = $_GET['action'];

    // Fetch the user role before performing any action
    $roleCheckQuery = "SELECT r.RoleName FROM User u JOIN Role r ON u.RoleID = r.RoleID WHERE u.UserID = $userId";
    $roleResult = $conn->query($roleCheckQuery);
    $userRole = $roleResult->fetch_assoc()['RoleName'];

    // Prevent the admin from banning, deleting, or performing any action on themselves
    if ($_SESSION['user_id'] == $userId) {
        echo "Та өөр дээрээ энэ үйлдлийг гүйцэтгэж чадахгүй.";
        exit();  // Stop further processing
    }

    // Prevent admins from deleting or modifying other admins or superadmins
    if (in_array($userRole, ['Admin', 'SuperAdmin'])) {
        echo "Таныг админ эсвэл супер админ хэрэглэгчийг устгах боломжгүй.";
        exit();
    }

    // If the action is 'ban', ask for admin password confirmation
    if ($action == 'ban' && !isset($_POST['admin_password'])) {
        header("Location: confirm_action.php?action=ban&userId=$userId");
        exit();
    }

    // If the action is 'delete', ask for admin password
    if ($action == 'delete' && !isset($_POST['admin_password'])) {
        header("Location: confirm_action.php?action=delete&userId=$userId");
        exit();
    }

    // If the action is 'promote' or 'demote', confirm action
    if (($action == 'promote' || $action == 'demote') && !isset($_POST['admin_password'])) {
        header("Location: confirm_action.php?action=$action&userId=$userId");
        exit();
    }

    // If the action is 'activate', ask for admin password confirmation
    if ($action == 'activate' && !isset($_POST['admin_password'])) {
        header("Location: confirm_action.php?action=activate&userId=$userId");
        exit();
    }

    // Handle Admin Password Validation and Action Execution
    if (isset($_POST['admin_password'])) {
        $adminPassword = $_POST['admin_password'];
        // Fetch the current admin's password from the database
        $adminId = $_SESSION['user_id'];
        $query = "SELECT Password FROM User WHERE UserID = $adminId";
        $result = $conn->query($query);
        $admin = $result->fetch_assoc();

        // Verify the password
        if (password_verify($adminPassword, $admin['Password'])) {
            // Perform the action (Promote, Demote, Delete, Ban, Activate)
            if ($action == 'promote') {
                $updateUserRole = "UPDATE User SET RoleID = (SELECT RoleID FROM Role WHERE RoleName = 'Manager') WHERE UserID = $userId";
                $conn->query($updateUserRole);
            } elseif ($action == 'demote') {
                $updateUserRole = "UPDATE User SET RoleID = (SELECT RoleID FROM Role WHERE RoleName = 'User') WHERE UserID = $userId";
                $conn->query($updateUserRole);
            } elseif ($action == 'delete') {
                // Delete user
                $deleteUser = "DELETE FROM User WHERE UserID = $userId";
                $conn->query($deleteUser);
            } elseif ($action == 'ban') {
                // Ban user
                $banUser = "UPDATE User SET Status = 'Banned' WHERE UserID = $userId";
                $conn->query($banUser);
            } elseif ($action == 'activate') {
                // Activate user
                $updateUserStatus = "UPDATE User SET Status = 'Active' WHERE UserID = $userId";
                $conn->query($updateUserStatus);
            }
            // Redirect back to the manage users page after the action is complete
            header("Location: manage_users.php");
            exit();
        } else {
            echo "Нууц үг буруу байна. Үйлдлийг гүйцэтгэж чадаагүй.";
        }
    }
}

// Fetch all users and their roles
$usersSql = "SELECT u.UserID, u.Name, u.Email, r.RoleName, u.Status FROM User u JOIN Role r ON u.RoleID = r.RoleID";
$users = $conn->query($usersSql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хэрэглэгчдийг удирдах</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">

    <!-- Navigation Menu -->
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

    <div class="container mx-auto p-6">
        <h2 class="text-2xl font-bold mb-4">Хэрэглэгчдийг удирдах</h2>

        <table class="min-w-full bg-white shadow-md rounded-lg">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b">Нэр</th>
                    <th class="py-2 px-4 border-b">Имэйл</th>
                    <th class="py-2 px-4 border-b">Үүрэг</th>
                    <th class="py-2 px-4 border-b">Төлөв</th>
                    <th class="py-2 px-4 border-b">Үйлдэл</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td class="py-2 px-4 border-b"><?php echo $row['Name']; ?></td>
                        <td class="py-2 px-4 border-b"><?php echo $row['Email']; ?></td>
                        <td class="py-2 px-4 border-b"><?php echo $row['RoleName']; ?></td>
                        <td class="py-2 px-4 border-b">
                            <?php echo $row['Status'] == 'Banned' ? 'Түр Хаагдсан' : 'Идэвхитэй'; ?>
                        </td>
                        <td class="py-2 px-4 border-b">
                            <?php if ($row['RoleName'] == 'User' && $row['Status'] != 'Banned'): ?>
                                <a href="?action=promote&userId=<?php echo $row['UserID']; ?>" class="text-blue-500 hover:text-blue-700">Менежер болгох</a>
                            <?php elseif ($row['RoleName'] == 'Manager'): ?>
                                <a href="?action=demote&userId=<?php echo $row['UserID']; ?>" class="text-blue-500 hover:text-blue-700">Хэрэглэгч болгох</a>
                            <?php endif; ?>
                            <?php if ($row['RoleName'] != 'Admin' && $row['RoleName'] != 'SuperAdmin' && $row['UserID'] != $_SESSION['user_id']): ?>
                                <a href="?action=delete&userId=<?php echo $row['UserID']; ?>" class="text-red-500 hover:text-red-700">Устгах</a> |
                                <?php if ($row['Status'] == 'Banned'): ?>
                                    <a href="?action=activate&userId=<?php echo $row['UserID']; ?>" class="text-green-500 hover:text-green-700">Идэвхжүүлэх</a>
                                <?php else: ?>
                                    <a href="?action=ban&userId=<?php echo $row['UserID']; ?>" class="text-red-500 hover:text-red-700">Түр хаах</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span>Админ эсвэл супер админыг устгах боломжгүй</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
