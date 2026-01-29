<?php
require_once 'config/database.php';

// Check if database needs setup
$missing_tables = checkDatabaseTables();
if (!empty($missing_tables) && basename($_SERVER['PHP_SELF']) !== 'setup.php') {
    header("Location: setup.php");
    exit();
}

// Check if user is logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'manager') {
        header("Location: /manager/dashboard.php");
    } else {
        header("Location: member/dashboard.php");
    }
    exit();
}

// If not logged in, redirect to login
header("Location: auth/login.php");
exit();
?>