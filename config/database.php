<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'meal_system');

// Site configuration
define('SITE_NAME', 'Meal Management System');
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__))) . '/');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection with error handling
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        // First, try to connect without selecting database
        $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
        
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
        
        // Create database if it doesn't exist
        $create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if (!mysqli_query($conn, $create_db)) {
            die("Error creating database: " . mysqli_error($conn));
        }
        
        // Select database
        if (!mysqli_select_db($conn, DB_NAME)) {
            die("Error selecting database: " . mysqli_error($conn));
        }
        
        // Set charset
        mysqli_set_charset($conn, "utf8mb4");
    }
    
    return $conn;
}

// Check if tables exist
function checkDatabaseTables() {
    $conn = getConnection();
    $required_tables = ['users', 'members', 'meals', 'expenses', 'deposits'];
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (!$result || mysqli_num_rows($result) == 0) {
            $missing_tables[] = $table;
        }
    }
    
    return $missing_tables;
}

// Redirect to setup if tables are missing
$missing_tables = checkDatabaseTables();
if (!empty($missing_tables) && basename($_SERVER['PHP_SELF']) !== 'setup.php' && basename($_SERVER['PHP_SELF']) !== 'index.php') {
    header("Location: ../setup.php");
    exit();
}
?>