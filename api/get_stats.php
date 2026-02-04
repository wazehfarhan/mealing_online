<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Add CORS headers
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    // Create Functions instance
    $functions = new Functions();
    
    // Get current statistics from database
    $stats = $functions->getSystemStats();
    
    // Format money for display
    $total_money = $stats['total_money_managed'] ?? 0;
    if ($total_money >= 1000000) {
        $money_formatted = number_format($total_money / 1000000, 1) . 'M';
    } elseif ($total_money >= 1000) {
        $money_formatted = number_format($total_money / 1000, 1) . 'K';
    } else {
        $money_formatted = number_format($total_money, 2);
    }
    
    // Format response
    $response = [
        'success' => true,
        'houses' => (int)($stats['total_houses'] ?? 0),
        'members' => (int)($stats['total_members'] ?? 0),
        'meals' => (float)($stats['today_meals'] ?? 0),
        'money' => (float)($total_money),
        'money_formatted' => $money_formatted,
        'new_houses' => (int)($stats['new_houses_30_days'] ?? 0),
        'active_houses' => (int)($stats['active_houses_today'] ?? 0),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Error in get_stats.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch statistics',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Close connection
if (isset($functions)) {
    $functions->close();
}
?>