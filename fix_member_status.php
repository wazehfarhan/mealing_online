<?php
/**
 * Fix Member Status Script
 * 
 * This script fixes the member status when a manager has accidentally 
 * set the member's status to 'inactive' instead of just changing 
 * the house_status to 'house_inactive'.
 * 
 * Usage: Run this file in your browser or via command line
 * http://127.0.0.1/mealing_online/fix_member_status.php
 */

session_start();
require_once 'config/database.php';

echo "<h1>Member Status Fix Tool</h1>";

$conn = getConnection();

// Get all members with their status
$sql = "SELECT m.member_id, m.name, m.status, m.house_status, h.house_name, h.is_active as house_is_active
        FROM members m
        LEFT JOIN houses h ON m.house_id = h.house_id
        ORDER BY m.member_id";
$result = mysqli_query($conn, $sql);

echo "<h2>Current Member Status</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>House Status</th><th>House Name</th><th>House Active</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    $style = '';
    if ($row['status'] == 'inactive') {
        $style = 'background-color: #ffcccc;';
    } elseif ($row['house_status'] == 'house_inactive') {
        $style = 'background-color: #fff3cd;';
    }
    echo "<tr style='{$style}'>";
    echo "<td>" . $row['member_id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['house_status'] . "</td>";
    echo "<td>" . htmlspecialchars($row['house_name'] ?? 'N/A') . "</td>";
    echo "<td>" . ($row['house_is_active'] ? 'Yes' : 'No') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Fix members whose status is inactive but should be active (for active houses)
$fix_sql = "UPDATE members m
            JOIN houses h ON m.house_id = h.house_id
            SET m.status = 'active'
            WHERE h.is_active = 1 
            AND m.status = 'inactive'";

if (mysqli_query($conn, $fix_sql)) {
    $affected = mysqli_affected_rows($conn);
    if ($affected > 0) {
        echo "<p style='color: green; font-weight: bold;'>Fixed {$affected} members whose status was incorrectly set to inactive!</p>";
    } else {
        echo "<p>No members needed fixing.</p>";
    }
} else {
    echo "<p style='color: red;'>Error: " . mysqli_error($conn) . "</p>";
}

echo "<h2>Instructions</h2>";
echo "<ul>";
echo "<li>If a member's <strong>status</strong> is 'inactive' but the house is still active, the member cannot login.</li>";
echo "<li>To properly deactivate a house (without deleting members), use the house settings to set 'is_active' to 0.</li>";
echo "<li>When a house is deactivated, the system should automatically set members' <strong>house_status</strong> to 'house_inactive' (not their personal status to 'inactive').</li>";
echo "<li>Members with <strong>house_status = 'house_inactive'</strong> can still login and join a new house.</li>";
echo "<li>Members with <strong>status = 'inactive'</strong> CANNOT login - this is a hard deactivation.</li>";
echo "</ul>";

echo "<h2>Actions</h2>";
echo "<form method='post'>";
echo "<button type='submit' name='fix_all' class='btn btn-primary'>Fix All Inactive Members in Active Houses</button>";
echo "</form>";

if (isset($_POST['fix_all'])) {
    $conn2 = getConnection();
    $update_sql = "UPDATE members m
                   JOIN houses h ON m.house_id = h.house_id
                   SET m.status = 'active'
                   WHERE h.is_active = 1 
                   AND m.status = 'inactive'";
    
    if (mysqli_query($conn2, $update_sql)) {
        $count = mysqli_affected_rows($conn2);
        echo "<p style='color: green; font-weight: bold;'>Successfully updated {$count} members!</p>";
        echo "<p><a href='fix_member_status.php'>Refresh page</a> to see changes.</p>";
    }
}

mysqli_close($conn);
?>

