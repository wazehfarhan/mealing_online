<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole('member');

$member_id = $_SESSION['member_id'] ?? 0;
$house_id = $_GET['house_id'] ?? 0;

if ($member_id && $house_id) {
    $conn = getConnection();
    
    // Update member to view history for the specified house
    $sql = "UPDATE members SET is_viewing_history = 1, history_house_id = ? WHERE member_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $house_id, $member_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['viewing_history'] = true;
        $_SESSION['history_house_id'] = $house_id;
    }
    mysqli_stmt_close($stmt);
}

// Redirect to dashboard
header("Location: dashboard.php");
exit();

