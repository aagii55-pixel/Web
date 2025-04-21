<?php
session_start();
require 'config/db.php';

$message = "";

// 🟩 Role name -> ID хөрвүүлэх туслах функц
function getRoleIdByName($roleName)
{
    $roles = [
        'SuperAdmin' => 1,
        'Admin' => 2,
        'Manager' => 3,
        'User' => 4,
        'VenueStaff' => 5,
        'Accountant' => 6
    ];
    return $roles[$roleName] ?? 0;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Имэйл хаяг буруу байна.";
    } else {
        $stmt = $conn->prepare("
            SELECT User.UserID, User.Password, Role.RoleName, User.Status 
            FROM User 
            INNER JOIN Role ON User.RoleID = Role.RoleID 
            WHERE User.Email = ? 
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id, $hashed_password, $role_name, $status);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                if ($status === 'Active') {
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_role'] = $role_name;
                    $_SESSION['role_id'] = getRoleIdByName($role_name);

                    // Хэрвээ олон үүрэгтэй хэрэглэгч байвал session-д нэмнэ
                    $stmt2 = $conn->prepare("SELECT DISTINCT Role FROM VenueStaffAssignment WHERE UserID = ?");
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();

                    $_SESSION['available_roles'] = [$role_name];
                    while ($row = $result2->fetch_assoc()) {
                        $_SESSION['available_roles'][] = $row['Role'];
                    }
                    $stmt2->close();

                    if (count($_SESSION['available_roles']) > 1) {
                        header("Location: role_selection.php");
                    } else {
                        // Нэг үүрэгтэй хэрэглэгчийг шууд чиглүүлэх
                        switch ($role_name) {
                            case 'SuperAdmin':
                            case 'Admin':
                                header("Location: admin_dashboard.php");
                                break;
                            case 'Manager':
                                header("Location: manager_dashboard.php");
                                break;
                            case 'User':
                                header("Location: user_dashboard.php");
                                break;
                            case 'VenueStaff':
                                header("Location: venue_staff_dashboard.php");
                                break;
                            case 'Accountant':
                                header("Location: accountant_dashboard.php");
                                break;
                            default:
                                header("Location: home.php");
                                break;
                        }
                    }
                    exit();
                } else {
                    $message = "Таны бүртгэл идэвхгүй байна. Админтай холбогдож, статусыг шалгаарай.";
                }
            } else {
                $message = "Нууц үг буруу байна.";
            }
        } else {
            $message = "Идэвхтэй бүртгэл олдсонгүй.";
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
    <title>Нэвтрэх</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-r from-blue-400 to-teal-300 font-sans flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Нэвтрэх</h2>

        <?php if ($message): ?>
            <div class="text-red-500 mb-4"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <input type="email" name="email" placeholder="Имэйл хаяг"
                class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
            <input type="password" name="password" placeholder="Нууц үг"
                class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
            <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition">Нэвтрэх</button>
        </form>

        <a href="register.php" class="block text-center text-blue-500 mt-4 hover:underline">
            Бүртгэлгүй юу? Бүртгүүлэх
        </a>
    </div>
</body>

</html>