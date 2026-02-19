<?php
// test_leave.php - place in your member directory
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/transfer_functions.php';

$auth = new Auth();
$auth->requireRole('member');

$conn = getConnection();
$member_id = $_SESSION['member_id'];

echo "Testing leave request for member_id: " . $member_id . "<br>";

// Check current status
$sql = "SELECT house_status, leave_request_date FROM members WHERE member_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $member_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);
echo "Current status: " . $member['house_status'] . "<br>";

// Try to update
$update_sql = "UPDATE members SET house_status = 'pending_leave', leave_request_date = NOW() WHERE member_id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "i", $member_id);

if (mysqli_stmt_execute($update_stmt)) {
    $affected = mysqli_stmt_affected_rows($update_stmt);
    echo "Update executed. Affected rows: " . $affected . "<br>";
} else {
    echo "Update failed: " . mysqli_error($conn) . "<br>";
}

// Check new status
$sql = "SELECT house_status, leave_request_date FROM members WHERE member_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $member_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);
echo "New status: " . $member['house_status'] . "<br>";
?>