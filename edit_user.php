<?php
session_start();
require 'config/db.php';

// Ensure only admin users can access this page
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'Admin') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['user_id'])) {
    die("User ID not specified.");
}

$user_id = $_GET['user_id'];
$message = "";

// Fetch user details
$user_stmt = $conn->prepare("SELECT u.UserID, u.Name, u.Email, u.RoleID FROM User u WHERE u.UserID = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user) {
    die("User not found.");
}

// Handle form submission for updating user information
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role_id = $_POST['role_id']; // 1 for Admin, 2 for User

    $update_stmt = $conn->prepare("UPDATE User SET Name = ?, Email = ?, RoleID = ? WHERE UserID = ?");
    if (!$update_stmt) {
        die("Error preparing update statement: " . $conn->error);
    }
    $update_stmt->bind_param("ssii", $name, $email, $role_id, $user_id);
    
    if ($update_stmt->execute()) {
        // Store success message in session and redirect to avoid form resubmission
        $_SESSION['message'] = "User information updated successfully!";
        header("Location: edit_user.php?user_id=" . $user_id); // Redirect to avoid resubmission
        exit();
    } else {
        $message = "Error updating user information: " . $update_stmt->error;
    }
    $update_stmt->close();
}

// Fetch roles for the dropdown
$roles_stmt = $conn->prepare("SELECT RoleID, RoleName FROM Role");
$roles_stmt->execute();
$roles_result = $roles_stmt->get_result();
$roles_stmt->close();

// Display success message stored in session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="form-container">
        <h2>Edit User</h2>
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="text" name="name" placeholder="Name" value="<?php echo htmlspecialchars($user['Name']); ?>" required>
            <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($user['Email']); ?>" required>
            
            <label for="role_id">Role</label>
            <select name="role_id" id="role_id" required>
                <?php while ($role = $roles_result->fetch_assoc()): ?>
                    <option value="<?php echo $role['RoleID']; ?>" <?php echo $role['RoleID'] == $user['RoleID'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($role['RoleName']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" class="btn">Update User</button>
        </form>
        
        <a href="manage_users.php" class="btn">Back to Manage Users</a>
    </div>
</body>
</html>
