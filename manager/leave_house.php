<?php
// leave_house.php - Place in manager folder
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$house_id = $_POST['house_id'] ?? $_GET['house_id'] ?? null;

if (!$house_id) {
    $_SESSION['error'] = "No house specified";
    header("Location: settings.php");
    exit();
}

$conn = getConnection();

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Remove house_id and member_id from user
    $sql = "UPDATE users SET house_id = NULL, member_id = NULL WHERE user_id = ? AND house_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $house_id);
    mysqli_stmt_execute($stmt);
    
    // If user is a manager and leaving, find if there are other managers
    if ($_SESSION['role'] === 'manager') {
        // Check if there are other managers in the house
        $sql = "SELECT COUNT(*) as manager_count FROM users 
                WHERE house_id = ? AND role = 'manager' AND user_id != ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $house_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        
        if ($data['manager_count'] == 0) {
            // No other managers, assign the oldest active member as manager
            $sql = "SELECT user_id FROM users 
                    WHERE house_id = ? AND role = 'member' AND is_active = 1 
                    ORDER BY created_at ASC LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $house_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($new_manager = mysqli_fetch_assoc($result)) {
                // Promote this member to manager
                $sql = "UPDATE users SET role = 'manager' WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $new_manager['user_id']);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    
    // Update member status to inactive
    $sql = "UPDATE members m 
            INNER JOIN users u ON m.member_id = u.member_id 
            SET m.status = 'inactive' 
            WHERE u.user_id = ? AND m.house_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $house_id);
    mysqli_stmt_execute($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Clear session house data
    unset($_SESSION['house_id']);
    unset($_SESSION['member_id']);
    
    $_SESSION['success'] = "You have successfully left the house.";
    header("Location: setup_house.php");
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Error leaving house: " . $e->getMessage();
    header("Location: settings.php");
}
exit();