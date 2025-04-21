<?php
session_start();
require 'config/db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $dob = $_POST['dob'];
    $role_name = 'User';

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Имэйл хаяг буруу байна.";
    } 
    // Validate phone number
    else if (!preg_match('/^\d{8}$/', $phone)) {
        $message = "Утасны дугаар 8 оронтой байх ёстой.";
    }
    // Validate password length
    else if (strlen($password) < 8) {
        $message = "Нууц үг дор хаяж 8 тэмдэгт байх ёстой.";
    }
    // Validate password special character
    else if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $message = "Нууц үг заавал тусгай тэмдэгт агуулсан байх ёстой.";
    }
    // Validate password confirmation
    else if ($password !== $confirm_password) {
        $message = "Нууц үг таарахгүй байна.";
    }
    else {
        // Check for existing email
        $stmt = $conn->prepare("SELECT * FROM User WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "Имэйл хаяг бүртгэлтэй байна.";
        } else {
            // Get role ID
            $role_stmt = $conn->prepare("SELECT RoleID FROM Role WHERE RoleName = ?");
            $role_stmt->bind_param("s", $role_name);
            $role_stmt->execute();
            $role_stmt->bind_result($role_id);
            $role_stmt->fetch();
            $role_stmt->close();

            if ($role_id) {
                // Calculate age
                $birthDate = new DateTime($dob);
                $today = new DateTime();
                $age = $today->diff($birthDate)->y;

                if ($age < 16) {
                    $message = "Та бүртгүүлэхийн тулд 16 нас хүрсэн байх ёстой.";
                } else {
                    // Hash password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Insert user
                    $stmt = $conn->prepare("INSERT INTO User (Name, Phone, Email, Password, DateOfBirth, RoleID) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssi", $name, $phone, $email, $hashedPassword, $dob, $role_id);

                    if ($stmt->execute()) {
                        $_SESSION['message'] = "Бүртгэл амжилттай! Та одоо нэвтрэх боломжтой.";
                        header("Location: login.php");
                        exit();
                    } else {
                        $message = "Бүртгүүлэхэд алдаа гарлаа: " . $stmt->error;
                    }

                    $stmt->close();
                }
            } else {
                $message = "Үүрэг олдсонгүй.";
            }
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
    <title>Бүртгүүлэх</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold text-center mb-6">Бүртгүүлэх</h2>
        <?php if ($message): ?>
            <div class="text-red-500 mb-4"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form action="" method="POST" class="space-y-4">
            <input type="text" name="name" placeholder="Бүтэн нэр" class="w-full px-4 py-2 border rounded-md" required>
            <input type="text" name="phone" placeholder="Утасны дугаар" class="w-full px-4 py-2 border rounded-md" required>
            <input type="email" name="email" placeholder="Имэйл хаяг" class="w-full px-4 py-2 border rounded-md" required>
            <label for="dob" class="block text-sm font-medium">Төрсөн огноо:</label>
            <input type="date" name="dob" id="dob" class="w-full px-4 py-2 border rounded-md" required>
            <input type="password" name="password" placeholder="Нууц үг" class="w-full px-4 py-2 border rounded-md" required>
            <input type="password" name="confirm_password" placeholder="Нууц үг давтах" class="w-full px-4 py-2 border rounded-md" required>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-md hover:bg-blue-600 transition">Бүртгүүлэх</button>
        </form>
        <a href="login.php" class="block text-center text-blue-500 mt-4 hover:underline">Та бүртгэлтэй юу? Нэвтрэх</a>
    </div>
</body>
</html>