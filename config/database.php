<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config from .env (Task 1.1)
if (!file_exists(__DIR__ . '/../.env')) {
    die("Error: .env file not found. Please copy .env.example to .env and edit your database credentials.");
}

// Parse .env file properly
$env_file = file_get_contents(__DIR__ . '/../.env');
$env_array = [];
foreach (explode("\n", $env_file) as $line) {
    $line = trim($line);
    if (!$line || $line[0] === '#') continue;
    if (strpos($line, '=') === false) continue;
    list($key, $value) = explode('=', $line, 2);
    $env_array[trim($key)] = trim($value);
}

define('DB_HOST', $env_array['DB_HOST'] ?? 'localhost');
define('DB_USER', $env_array['DB_USER'] ?? 'root');
define('DB_PASS', $env_array['DB_PASS'] ?? '');
define('DB_NAME', $env_array['DB_NAME'] ?? 'mealing_online');
define('ENVIRONMENT', $env_array['ENVIRONMENT'] ?? 'development');

// Site configuration
define('SITE_NAME', 'Meal Management System');
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__))) . '/');

// Error reporting based on ENVIRONMENT (Task 1.2)
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Create connection with error handling
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        // Use TCP connection instead of socket for better compatibility
        $host = DB_HOST;
        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }
        
        // First, try to connect without selecting database
        $conn = mysqli_connect($host, DB_USER, DB_PASS, '', 3306);
        
        if (!$conn) {
            die("Connection failed: " . mysqli_connect_error() . "\nHost: " . $host . "\nUser: " . DB_USER);
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