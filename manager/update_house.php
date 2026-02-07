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
        // Update session if house_name exists
        if (isset($_SESSION['house_name'])) {
            $_SESSION['house_name'] = $house_name;
        }
        echo "Success: House updated successfully";
    } else {
        echo "Info: No changes made or house not found";
    }
} else {
    echo "Error: Update failed - " . mysqli_error($conn);
}
?>