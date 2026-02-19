<?php
/**
 * Fix Database Column Issue
 * Run this to fix the performed_at column
 */

$conn = mysqli_connect('localhost', 'root', '');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (!mysqli_select_db($conn, 'meal_system')) {
    die("Cannot select database: " . mysqli_error($conn));
}

echo "<h1>Fixing Database Schema</h1>";

// Fix house_transfers_log table
echo "<h3>Fixing house_transfers_log.performed_at</h3>";
$sql = "ALTER TABLE `house_transfers_log` 
        MODIFY COLUMN `performed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP";
        
if (mysqli_query($conn, $sql)) {
    echo "<p style='color: green;'>✓ Fixed performed_at column</p>";
} else {
    $error = mysqli_error($conn);
    if (strpos($error, 'already exists') !== false || strpos($error, 'NO DEFAULT') !== false) {
        echo "<p style='color: orange;'>⚠️ Column may already be fixed or different issue: $error</p>";
    } else {
        echo "<p style='color: red;'>✗ Error: $error</p>";
    }
}

// Check current column definition
echo "<h3>Current Column Definition</h3>";
$result = mysqli_query($conn, "DESCRIBE house_transfers_log performed_at");
if ($row = mysqli_fetch_assoc($result)) {
    echo "<p>Type: {$row['Type']}</p>";
    echo "<p>Null: {$row['Null']}</p>";
    echo "<p>Default: {$row['Default']}</p>";
    echo "<p>Extra: {$row['Extra']}</p>";
}

echo "<hr>";
echo "<h3>Testing Log Insert</h3>";
$test_sql = "INSERT INTO house_transfers_log (member_id, from_house_id, action, performed_by, notes) 
             VALUES (67, 29, 'leave_requested', 67, 'Test insert')";

if (mysqli_query($conn, $test_sql)) {
    echo "<p style='color: green;'>✓ Successfully inserted test log!</p>";
    
    // Delete the test
    mysqli_query($conn, "DELETE FROM house_transfers_log WHERE notes = 'Test insert'");
    echo "<p>Test record deleted.</p>";
} else {
    echo "<p style='color: red;'>✗ Still failing: " . mysqli_error($conn) . "</p>";
}

echo "<hr>";
echo "<h3>Next Steps</h3>";
echo "<ol>";
echo "<li>Go back to: <a href='http://localhost/mealing_online/member/test_leave.php'>Test Leave Page</a></li>";
echo "<li>Submit another test request</li>";
echo "<li>Should now work without errors!</li>";
echo "</ol>";
?>

