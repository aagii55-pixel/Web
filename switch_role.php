<?php
session_start();
require 'config/db.php';

// Case 1: Manager/User role switch (from URL parameter)
if (isset($_GET['role'])) {
    $requested_role = $_GET['role'];

    // Store original role if switching for the first time
    if (!isset($_SESSION['original_role']) && isset($_SESSION['user_role'])) {
        $_SESSION['original_role'] = $_SESSION['user_role'];
        $_SESSION['original_role_id'] = $_SESSION['role_id'] ?? $_SESSION['user_role_id'] ?? 3; // Default to Manager (3)
    }

    // Handle the role switch
    if ($requested_role === 'user' && $_SESSION['user_role'] === 'Manager') {
        // Switch to User role but maintain manager's original ID for permissions
        $_SESSION['user_role'] = 'User';
        $_SESSION['role_id'] = 4; // User role ID
        $_SESSION['temp_is_manager'] = true; // Flag to indicate this is a manager in user mode
        
        // Redirect to user dashboard
        header('Location: user_dashboard.php');
        exit();
    } 
    elseif ($requested_role === 'manager' && isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'Manager') {
        // Switch back to Manager role
        $_SESSION['user_role'] = 'Manager';
        $_SESSION['role_id'] = $_SESSION['original_role_id'];
        unset($_SESSION['temp_is_manager']); // Remove the temporary flag
        
        // Redirect to manager dashboard
        header('Location: manager_dashboard.php');
        exit();
    }
}

// Case 2: Original functionality for staff role switching (from POST)
if (isset($_SESSION['user_id']) && isset($_POST['new_role'])) {
    $userID = $_SESSION['user_id'];
    $newRole = $_POST['new_role']; // 'VenueStaff' or 'Accountant'

    // Verify if the user has this role in VenueStaffAssignment
    $stmt = $conn->prepare("SELECT Role FROM VenueStaffAssignment WHERE UserID = ? AND Role = ?");
    $stmt->bind_param("is", $userID, $newRole);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['user_role'] = $newRole;
    }

    $stmt->close();
    header('Location: user_dashboard.php'); // Redirect to user dashboard
    exit();
}

// If we reach here, it's likely an invalid request
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'home.php'));
exit();
?>