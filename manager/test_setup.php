<?php
// test_setup.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

echo "<h1>Testing House Setup</h1>";

// Test database connection
$conn = getConnection();
if ($conn) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test if houses table exists
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'houses'");
    if (mysqli_num_rows($result) > 0) {
        echo "<p style='color: green;'>✓ Houses table exists</p>";
        
        // Show houses table structure
        $result = mysqli_query($conn, "DESCRIBE houses");
        echo "<h3>Houses Table Structure:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>✗ Houses table doesn't exist</p>";
    }
    
    // Test if users table exists
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
    if (mysqli_num_rows($result) > 0) {
        echo "<p style='color: green;'>✓ Users table exists</p>";
        
        // Check if current user exists
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $sql = "SELECT * FROM users WHERE user_id = $user_id";
            $result = mysqli_query($conn, $sql);
            if (mysqli_num_rows($result) > 0) {
                $user = mysqli_fetch_assoc($result);
                echo "<p style='color: green;'>✓ Current user found: " . $user['username'] . "</p>";
                echo "<p>User house_id: " . ($user['house_id'] ? $user['house_id'] : 'NULL') . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Current user not found in database</p>";
            }
        } else {
            echo "<p style='color: orange;'>ℹ No user logged in</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Users table doesn't exist</p>";
    }
    
    // Test if members table exists
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'members'");
    if (mysqli_num_rows($result) > 0) {
        echo "<p style='color: green;'>✓ Members table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Members table doesn't exist</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
}

// Test Auth class
echo "<h2>Testing Auth Class</h2>";
$auth = new Auth();
echo "<p>Auth class instantiated</p>";

// Test createHouse method
echo "<h3>Testing createHouse method manually:</h3>";
if (isset($_SESSION['user_id'])) {
    $test_result = $auth->createHouse("Test House " . time(), "Test description");
    echo "<pre>";
    print_r($test_result);
    echo "</pre>";
    
    if ($test_result['success']) {
        echo "<p style='color: green;'>✓ House creation successful</p>";
        
        // Clean up - delete test house
        $house_id = $test_result['house_id'];
        mysqli_query($conn, "DELETE FROM members WHERE house_id = $house_id");
        mysqli_query($conn, "DELETE FROM houses WHERE house_id = $house_id");
        mysqli_query($conn, "UPDATE users SET house_id = NULL, member_id = NULL WHERE user_id = {$_SESSION['user_id']}");
        echo "<p>Test house cleaned up</p>";
    } else {
        echo "<p style='color: red;'>✗ House creation failed: " . ($test_result['error'] ?? 'Unknown error') . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='setup_house.php'>Go to actual setup page</a></p>";
?>