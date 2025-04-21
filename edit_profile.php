<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

        // Validate phone number
        if (!preg_match('/^\d{8}$/', $phone)) {
            $message = "Утасны дугаар яг 8 оронтой байх ёстой.";
        } 
        // Validate email
        else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Имэйл хаяг буруу байна.";
        }
        else {
            $stmt = $conn->prepare("UPDATE User SET Name = ?, Phone = ?, Email = ? WHERE UserID = ?");
            $stmt->bind_param("sssi", $name, $phone, $email, $user_id);

            if ($stmt->execute()) {
                $message = "Профайл амжилттай шинэчлэгдлээ!";
            } else {
                $message = "Шинэчлэхэд алдаа гарлаа: " . $stmt->error;
            }

            $stmt->close();
        }
    }

    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Fetch the current hashed password
        $stmt = $conn->prepare("SELECT Password FROM User WHERE UserID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        // Verify the current password
        if (!password_verify($current_password, $hashed_password)) {
            $message = "Одоогийн нууц үг буруу байна.";
        }
        // Validate new password length
        else if (strlen($new_password) < 8) {
            $message = "Шинэ нууц үг дор хаяж 8 тэмдэгт байх ёстой.";
        }
        // Validate new password special character
        else if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
            $message = "Шинэ нууц үг заавал тусгай тэмдэгт агуулсан байх ёстой.";
        }
        // Validate password confirmation
        else if ($new_password !== $confirm_password) {
            $message = "Шинэ нууц үг таарахгүй байна.";
        }
        else {
            // Hash new password
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE User SET Password = ? WHERE UserID = ?");
            $stmt->bind_param("si", $new_hashed_password, $user_id);

            if ($stmt->execute()) {
                $message = "Нууц үг амжилттай шинэчлэгдлээ!";
            } else {
                $message = "Нууц үг шинэчлэхэд алдаа гарлаа: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

// Fetch the user's current data
$stmt = $conn->prepare("SELECT Name, Phone, Email FROM User WHERE UserID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($name, $phone, $email);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профайл засах</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold text-center mb-6">Профайл засах</h2>
        <?php if ($message): ?>
            <div class="text-red-500 mb-4"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Profile Update Form -->
        <form action="" method="POST" class="space-y-4">
            <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Нэр" 
                   class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Утасны дугаар" 
                   class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Имэйл" 
                   class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
            <button type="submit" name="update_profile" 
                    class="w-full bg-blue-500 text-white py-2 rounded-md hover:bg-blue-600 transition">
                Шинэчлэх
            </button>
        </form>

        <!-- Password Update Form -->
        <h3 class="text-xl font-semibold text-center mt-8">Нууц үг шинэчлэх</h3>
        <form action="" method="POST" class="space-y-4 mt-4">
            <input type="password" name="current_password" placeholder="Одоогийн нууц үг" 
                   class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
            <input type="password" name="new_password" placeholder="Шинэ нууц үг" 
                   class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
            <input type="password" name="confirm_password" placeholder="Шинэ нууц үг давтах" 
                   class="w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-blue-500 focus:outline-none" required>
            <button type="submit" name="update_password" 
                    class="w-full bg-green-500 text-white py-2 rounded-md hover:bg-green-600 transition">
                Шинэчлэх
            </button>
        </form>

        <a href="user_dashboard.php" 
           class="block text-center text-blue-500 mt-6 hover:underline">
            Буцах
        </a>
    </div>
</body>
</html>