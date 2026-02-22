<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo "Error: Not logged in";
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Get house_id from database
$sql = "SELECT house_id FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);

if (!$user_data || !$user_data['house_id']) {
    echo "Error: No house assigned";
    exit();
}

$house_id = $user_data['house_id'];

// Get POST data
$house_name = isset($_POST['house_name']) ? trim($_POST['house_name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (empty($house_name)) {
    echo "Error: House name is required";
    exit();
}

// Get current house status before update
$current_house_sql = "SELECT is_active FROM houses WHERE house_id = ?";
$current_house_stmt = mysqli_prepare($conn, $current_house_sql);
mysqli_stmt_bind_param($current_house_stmt, "i", $house_id);
mysqli_stmt_execute($current_house_stmt);
$current_house_result = mysqli_stmt_get_result($current_house_stmt);
$current_house = mysqli_fetch_assoc($current_house_result);
$previous_is_active = $current_house['is_active'];
mysqli_stmt_close($current_house_stmt);

// Update the house
$sql = "UPDATE houses SET house_name = ?, description = ?, is_active = ? WHERE house_id = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "Error: Prepare failed - " . mysqli_error($conn);
    exit();
}

mysqli_stmt_bind_param($stmt, "ssii", $house_name, $description, $is_active, $house_id);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_affected_rows($conn) > 0) {
        // Check if house was deactivated (from active to inactive)
        if ($previous_is_active == 1 && $is_active == 0) {
            // Update all active members of this house to have house_inactive status
            // This notifies members that their house has been deactivated
            $update_members_sql = "UPDATE members 
                                   SET house_status = 'house_inactive' 
                                   WHERE house_id = ? 
                                   AND status = 'active' 
                                   AND house_status = 'active'";
            $update_members_stmt = mysqli_prepare($conn, $update_members_sql);
            mysqli_stmt_bind_param($update_members_stmt, "i", $house_id);
            mysqli_stmt_execute($update_members_stmt);
            mysqli_stmt_close($update_members_stmt);
            
            error_log("House $house_id deactivated. Members notified.");
        }
        
        // Check if house was reactivated (from inactive to active)
        if ($previous_is_active == 0 && $is_active == 1) {
            // Update all members who had house_inactive status back to active
            $update_members_sql = "UPDATE members 
                                   SET house_status = 'active' 
                                   WHERE house_id = ? 
                                   AND house_status = 'house_inactive'";
            $update_members_stmt = mysqli_prepare($conn, $update_members_sql);
            mysqli_stmt_bind_param($update_members_stmt, "i", $house_id);
            mysqli_stmt_execute($update_members_stmt);
            mysqli_stmt_close($update_members_stmt);
            
            error_log("House $house_id reactivated. Members status updated.");
        }
        
        // Update session if house_name exists
        if (isset($_SESSION['house_name'])) {
            $_SESSION['house_name'] = $house_name;
        }
        
        // Also update session house status
        $_SESSION['house_is_active'] = $is_active;
        
        echo "Success: House updated successfully";
    } else {
        echo "Info: No changes made or house not found";
    }
} else {
    echo "Error: Update failed - " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
