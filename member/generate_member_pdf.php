<?php
/**
 * Member Report PDF Generator - Member Version
 * Standalone file for members to download their own PDF reports
 * Now supports both Monthly and Yearly reports
 */

// Start session
session_start();

date_default_timezone_set('Asia/Dhaka');
// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('<div style="padding: 20px; text-align: center; font-family: Arial; color: #dc3545;">
        <h2>Access Denied</h2>
        <p>Please log in to access this report.</p>
    </div>');
}

// Only members can generate their own PDF reports
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    die('<div style="padding: 20px; text-align: center; font-family: Arial; color: #dc3545;">
        <h2>Access Denied</h2>
        <p>You must be logged in as a member to access this report.</p>
    </div>');
}

// Include database and functions
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Get parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$view_type = isset($_GET['view']) ? $_GET['view'] : 'monthly'; // 'monthly' or 'yearly'
$member_id = $_SESSION['member_id'];
$house_id = $_SESSION['house_id'];

if (!$member_id || !$house_id) {
    die('<div style="padding: 20px; text-align: center; font-family: Arial; color: #dc3545;">
        <h2>Error</h2>
        <p>Invalid session parameters.</p>
    </div>');
}

// Validate parameters
if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}
if (!in_array($view_type, ['monthly', 'yearly'])) {
    $view_type = 'monthly';
}

$month_name = date('F', mktime(0, 0, 0, $month, 1));

// Database connection
$conn = getConnection();

// Get member and house information
$member_sql = "SELECT m.*, h.house_name, h.house_code 
               FROM members m 
               LEFT JOIN houses h ON m.house_id = h.house_id 
               WHERE m.member_id = ? AND m.house_id = ?";
$stmt = mysqli_prepare($conn, $member_sql);
mysqli_stmt_bind_param($stmt, "ii", $member_id, $house_id);
mysqli_stmt_execute($stmt);
$member_result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($member_result);

if (!$member) {
    die('<div style="padding: 20px; text-align: center; font-family: Arial; color: #dc3545;">
        <h2>Error</h2>
        <p>Member not found.</p>
    </div>');
}

// Create Functions instance
$functions = new Functions();

// Initialize arrays
$monthly_report = [];
$yearly_reports = [];
$yearly_totals = [];
$category_totals = [];
$expenses_list = [];
$meals = [];
$deposits = [];

$category_colors = [
    'Rice' => 'primary',
    'Fish' => 'info',
    'Meat' => 'danger',
    'Vegetables' => 'success',
    'Spices' => 'warning',
    'Oil' => 'purple',
    'food' => 'orange',
    'Others' => 'secondary'
];

if ($view_type === 'yearly') {
    // YEARLY REPORT CALCULATIONS
    $yearly_reports = [];
    $yearly_totals = [
        'member_meals' => 0,
        'house_meals' => 0,
        'member_deposits' => 0,
        'house_expenses' => 0,
        'member_cost' => 0,
        'balance' => 0
    ];
    
    // Get data for all months in the year
    for ($current_month = 1; $current_month <= 12; $current_month++) {
        // Get member's total meals for the month
        $member_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as member_meals 
                             FROM meals 
                             WHERE member_id = ? 
                             AND house_id = ?
                             AND MONTH(meal_date) = ? 
                             AND YEAR(meal_date) = ?";
        $stmt = mysqli_prepare($conn, $member_meals_sql);
        mysqli_stmt_bind_param($stmt, "iiii", $member_id, $house_id, $current_month, $year);
        mysqli_stmt_execute($stmt);
        $member_meals_result = mysqli_stmt_get_result($stmt);
        $member_meals_data = mysqli_fetch_assoc($member_meals_result);
        $member_meals = $member_meals_data['member_meals'] ?? 0;
        
        // Get total meals for all members in the house for the month
        $total_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as total_meals 
                            FROM meals 
                            WHERE house_id = ? 
                            AND MONTH(meal_date) = ? 
                            AND YEAR(meal_date) = ?";
        $stmt = mysqli_prepare($conn, $total_meals_sql);
        mysqli_stmt_bind_param($stmt, "iii", $house_id, $current_month, $year);
        mysqli_stmt_execute($stmt);
        $total_meals_result = mysqli_stmt_get_result($stmt);
        $total_meals_data = mysqli_fetch_assoc($total_meals_result);
        $house_meals = $total_meals_data['total_meals'] ?? 0;
        
        // Get member's total deposits for the month
        $member_deposits_sql = "SELECT COALESCE(SUM(amount), 0) as member_deposits 
                                FROM deposits 
                                WHERE member_id = ? 
                                AND house_id = ?
                                AND MONTH(deposit_date) = ? 
                                AND YEAR(deposit_date) = ?";
        $stmt = mysqli_prepare($conn, $member_deposits_sql);
        mysqli_stmt_bind_param($stmt, "iiii", $member_id, $house_id, $current_month, $year);
        mysqli_stmt_execute($stmt);
        $member_deposits_result = mysqli_stmt_get_result($stmt);
        $member_deposits_data = mysqli_fetch_assoc($member_deposits_result);
        $member_deposits = $member_deposits_data['member_deposits'] ?? 0;
        
        // Get total expenses for the house for the month
        $expenses_sql = "SELECT COALESCE(SUM(amount), 0) as total_expenses 
                         FROM expenses 
                         WHERE house_id = ? 
                         AND MONTH(expense_date) = ? 
                         AND YEAR(expense_date) = ?";
        $stmt = mysqli_prepare($conn, $expenses_sql);
        mysqli_stmt_bind_param($stmt, "iii", $house_id, $current_month, $year);
        mysqli_stmt_execute($stmt);
        $expenses_result = mysqli_stmt_get_result($stmt);
        $expenses_data = mysqli_fetch_assoc($expenses_result);
        $house_expenses = $expenses_data['total_expenses'] ?? 0;
        
        // Calculate meal rate
        $meal_rate = 0;
        if ($house_meals > 0) {
            $meal_rate = $house_expenses / $house_meals;
        }
        
        // Calculate member's cost and balance
        $member_cost = $member_meals * $meal_rate;
        $balance = $member_deposits - $member_cost;
        
        $yearly_reports[$current_month] = [
            'month' => $current_month,
            'month_name' => date('F', mktime(0, 0, 0, $current_month, 1)),
            'year' => $year,
            'member_meals' => $member_meals,
            'house_meals' => $house_meals,
            'member_deposits' => $member_deposits,
            'house_expenses' => $house_expenses,
            'meal_rate' => $meal_rate,
            'member_cost' => $member_cost,
            'balance' => $balance
        ];
        
        // Add to yearly totals
        $yearly_totals['member_meals'] += $member_meals;
        $yearly_totals['house_meals'] += $house_meals;
        $yearly_totals['member_deposits'] += $member_deposits;
        $yearly_totals['house_expenses'] += $house_expenses;
        $yearly_totals['member_cost'] += $member_cost;
        $yearly_totals['balance'] += $balance;
    }
    
    // Get yearly expense categories
    $yearly_categories_sql = "
        SELECT category, SUM(amount) as total 
        FROM expenses 
        WHERE house_id = ? 
        AND YEAR(expense_date) = ?
        GROUP BY category 
        ORDER BY total DESC";
    
    $stmt = mysqli_prepare($conn, $yearly_categories_sql);
    mysqli_stmt_bind_param($stmt, "ii", $house_id, $year);
    mysqli_stmt_execute($stmt);
    $yearly_cat_result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($yearly_cat_result)) {
        $category_totals[$row['category']] = $row['total'];
    }
    
    // Get previous year's ending balance
    $prev_year = $year - 1;
    $prev_year_end_date = $prev_year . '-12-01';
    $prev_balance_sql = "SELECT mmd.balance 
                         FROM monthly_summary ms
                         JOIN monthly_member_details mmd ON ms.summary_id = mmd.summary_id
                         WHERE ms.house_id = ? 
                         AND ms.month_year = ? 
                         AND mmd.member_id = ?";
    $prev_balance_stmt = mysqli_prepare($conn, $prev_balance_sql);
    mysqli_stmt_bind_param($prev_balance_stmt, "isi", $house_id, $prev_year_end_date, $member_id);
    mysqli_stmt_execute($prev_balance_stmt);
    $prev_balance_result = mysqli_stmt_get_result($prev_balance_stmt);
    $prev_balance_data = mysqli_fetch_assoc($prev_balance_result);
    $previous_balance = $prev_balance_data['balance'] ?? 0;
    
    // Calculate adjusted balance
    $adjusted_balance = $previous_balance + $yearly_totals['balance'];
    
} else {
    // MONTHLY REPORT CALCULATIONS (existing code)
    // Get total meals for all members in the house for the month
    $total_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as total_meals 
                        FROM meals 
                        WHERE house_id = ? 
                        AND MONTH(meal_date) = ? 
                        AND YEAR(meal_date) = ?";
    $stmt = mysqli_prepare($conn, $total_meals_sql);
    mysqli_stmt_bind_param($stmt, "iii", $house_id, $month, $year);
    mysqli_stmt_execute($stmt);
    $total_meals_result = mysqli_stmt_get_result($stmt);
    $total_meals_data = mysqli_fetch_assoc($total_meals_result);
    $house_total_meals = $total_meals_data['total_meals'] ?? 0;

    // Get total expenses for the house for the month
    $expenses_sql = "SELECT COALESCE(SUM(amount), 0) as total_expenses 
                     FROM expenses 
                     WHERE house_id = ? 
                     AND MONTH(expense_date) = ? 
                     AND YEAR(expense_date) = ?";
    $stmt = mysqli_prepare($conn, $expenses_sql);
    mysqli_stmt_bind_param($stmt, "iii", $house_id, $month, $year);
    mysqli_stmt_execute($stmt);
    $expenses_result = mysqli_stmt_get_result($stmt);
    $expenses_data = mysqli_fetch_assoc($expenses_result);
    $house_total_expenses = $expenses_data['total_expenses'] ?? 0;

    // Get member's total meals for the month
    $member_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as member_meals 
                         FROM meals 
                         WHERE member_id = ? 
                         AND house_id = ?
                         AND MONTH(meal_date) = ? 
                         AND YEAR(meal_date) = ?";
    $stmt = mysqli_prepare($conn, $member_meals_sql);
    mysqli_stmt_bind_param($stmt, "iiii", $member_id, $house_id, $month, $year);
    mysqli_stmt_execute($stmt);
    $member_meals_result = mysqli_stmt_get_result($stmt);
    $member_meals_data = mysqli_fetch_assoc($member_meals_result);
    $member_total_meals = $member_meals_data['member_meals'] ?? 0;

    // Get member's total deposits for the month
    $member_deposits_sql = "SELECT COALESCE(SUM(amount), 0) as member_deposits 
                            FROM deposits 
                            WHERE member_id = ? 
                            AND house_id = ?
                            AND MONTH(deposit_date) = ? 
                            AND YEAR(deposit_date) = ?";
    $stmt = mysqli_prepare($conn, $member_deposits_sql);
    mysqli_stmt_bind_param($stmt, "iiii", $member_id, $house_id, $month, $year);
    mysqli_stmt_execute($stmt);
    $member_deposits_result = mysqli_stmt_get_result($stmt);
    $member_deposits_data = mysqli_fetch_assoc($member_deposits_result);
    $member_total_deposits = $member_deposits_data['member_deposits'] ?? 0;

    // Calculate meal rate
    $meal_rate = 0;
    if ($house_total_meals > 0) {
        $meal_rate = $house_total_expenses / $house_total_meals;
    }

    // Calculate member's cost
    $member_cost = $member_total_meals * $meal_rate;

    // Calculate balance
    $balance = $member_total_deposits - $member_cost;

    // Get previous month's balance
    $prev_year_month = date('Y-m', strtotime($year . '-' . sprintf('%02d', $month) . '-01 -1 month'));
    $prev_year_month_with_day = $prev_year_month . '-01';

    $prev_balance_sql = "SELECT mmd.balance 
                         FROM monthly_summary ms
                         JOIN monthly_member_details mmd ON ms.summary_id = mmd.summary_id
                         WHERE ms.house_id = ? 
                         AND ms.month_year = ? 
                         AND mmd.member_id = ?";
    $prev_balance_stmt = mysqli_prepare($conn, $prev_balance_sql);
    mysqli_stmt_bind_param($prev_balance_stmt, "isi", $house_id, $prev_year_month_with_day, $member_id);
    mysqli_stmt_execute($prev_balance_stmt);
    $prev_balance_result = mysqli_stmt_get_result($prev_balance_stmt);
    $prev_balance_data = mysqli_fetch_assoc($prev_balance_result);
    $previous_balance = $prev_balance_data['balance'] ?? 0;

    // Calculate adjusted balance
    $adjusted_balance = $previous_balance + $balance;

    // Build member report array
    $monthly_report = [
        'total_meals' => $member_total_meals,
        'total_deposits' => $member_total_deposits,
        'meal_rate' => $meal_rate,
        'member_cost' => $member_cost,
        'balance' => $balance,
        'adjusted_balance' => $adjusted_balance,
        'previous_balance' => $previous_balance,
        'house_total_meals' => $house_total_meals,
        'house_total_expenses' => $house_total_expenses
    ];

    // Get detailed meal history
    $meals_sql = "SELECT * FROM meals 
                  WHERE member_id = ? 
                  AND house_id = ?
                  AND MONTH(meal_date) = ? 
                  AND YEAR(meal_date) = ? 
                  ORDER BY meal_date DESC";
    $stmt = mysqli_prepare($conn, $meals_sql);
    mysqli_stmt_bind_param($stmt, "iiii", $member_id, $house_id, $month, $year);
    mysqli_stmt_execute($stmt);
    $meals_result = mysqli_stmt_get_result($stmt);
    $meals = mysqli_fetch_all($meals_result, MYSQLI_ASSOC);

    // Get detailed deposit history
    $deposits_sql = "SELECT * FROM deposits 
                     WHERE member_id = ? 
                     AND house_id = ?
                     AND MONTH(deposit_date) = ? 
                     AND YEAR(deposit_date) = ? 
                     ORDER BY deposit_date DESC";
    $stmt = mysqli_prepare($conn, $deposits_sql);
    mysqli_stmt_bind_param($stmt, "iiii", $member_id, $house_id, $month, $year);
    mysqli_stmt_execute($stmt);
    $deposits_result = mysqli_stmt_get_result($stmt);
    $deposits = mysqli_fetch_all($deposits_result, MYSQLI_ASSOC);

    // Get all expenses for the house for the month
    $expenses_list_sql = "SELECT e.*, u.username as added_by 
                          FROM expenses e 
                          LEFT JOIN users u ON e.created_by = u.user_id
                          WHERE e.house_id = ?
                          AND MONTH(e.expense_date) = ? 
                          AND YEAR(e.expense_date) = ?
                          ORDER BY e.expense_date ASC";
    $expenses_list_stmt = mysqli_prepare($conn, $expenses_list_sql);
    mysqli_stmt_bind_param($expenses_list_stmt, "iii", $house_id, $month, $year);
    mysqli_stmt_execute($expenses_list_stmt);
    $expenses_list_result = mysqli_stmt_get_result($expenses_list_stmt);
    $expenses_list = mysqli_fetch_all($expenses_list_result, MYSQLI_ASSOC);

    // Get category totals for expenses
    foreach ($expenses_list as $expense) {
        $category = $expense['category'];
        if (!isset($category_totals[$category])) {
            $category_totals[$category] = 0;
        }
        $category_totals[$category] += $expense['amount'];
    }
}

// Calculate member percentage for display
$member_percentage = 0;
if ($view_type === 'yearly') {
    if ($yearly_totals['house_meals'] > 0) {
        $member_percentage = ($yearly_totals['member_meals'] / $yearly_totals['house_meals']) * 100;
    }
} else {
    if ($monthly_report['house_total_meals'] > 0) {
        $member_percentage = ($monthly_report['total_meals'] / $monthly_report['house_total_meals']) * 100;
    }
}

// Close statement if still open
if (isset($stmt) && $stmt) mysqli_stmt_close($stmt);
if (isset($expenses_list_stmt) && $expenses_list_stmt) mysqli_stmt_close($expenses_list_stmt);
if (isset($prev_balance_stmt) && $prev_balance_stmt) mysqli_stmt_close($prev_balance_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../image/icon.png">
    <title><?php echo $view_type === 'yearly' ? 'Yearly' : 'Monthly'; ?> Report - <?php echo htmlspecialchars($member['name']); ?> - <?php echo $view_type === 'yearly' ? $year : $month_name . ' ' . $year; ?></title>
    <style>
        /* PDF Styles - Exactly like your existing PDF */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            padding: 15px;
            background: #fff;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px double #333;
        }
        
        .header h1 {
            font-size: 22px;
            color: #333;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .header .member-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .header .report-period {
            font-size: 13px;
            font-weight: bold;
            color: #2c3e50;
            margin-top: 5px;
        }
        
        /* Summary Section */
        .summary-section {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            text-align: center;
        }
        
        .summary-item {
            padding: 10px;
        }
        
        .summary-label {
            font-size: 10px;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        
        .balance-positive { color: #27ae60; }
        .balance-negative { color: #e74c3c; }
        
        /* Tables */
        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #2c3e50;
            margin: 20px 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #3498db;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        .data-table th {
            background: #2c3e50;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1a252f;
        }
        
        .data-table td {
            padding: 8px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .data-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .data-table .total-row {
            background: #e8e8e8;
            font-weight: bold;
        }
        
        /* Alignment */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        
        /* Status Colors */
        .text-success { color: #27ae60; font-weight: bold; }
        .text-warning { color: #f39c12; font-weight: bold; }
        .text-danger { color: #e74c3c; font-weight: bold; }
        .text-info { color: #3498db; font-weight: bold; }
        
        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #6c757d;
        }
        
        /* Print Controls */
        .no-print {
            text-align: center;
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .print-btn {
            display: inline-block;
            padding: 12px 30px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        /* Member Info */
        .member-info-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .info-row {
            margin-bottom: 8px;
            display: flex;
        }
        
        .info-label {
            font-weight: bold;
            width: 150px;
            color: #2c3e50;
        }
        
        /* Financial Cards */
        .financial-card {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }
        
        .card-info {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
        }
        
        .card-warning {
            background: #fef5e7;
            border-left: 4px solid #f39c12;
        }
        
        .card-success {
            background: #eafaf1;
            border-left: 4px solid #27ae60;
        }
        
        .card-primary {
            background: #f0f8ff;
            border-left: 4px solid #2980b9;
        }
        
        .card-danger {
            background: #fdedec;
            border-left: 4px solid #e74c3c;
        }
        
        /* Progress Bar */
        .progress {
            height: 20px;
            background: #ecf0f1;
            border-radius: 3px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-bar {
            height: 100%;
            background: #2ecc71;
            text-align: center;
            color: white;
            font-size: 10px;
            line-height: 20px;
            font-weight: bold;
        }
        
        /* Print Styles */
        @media print {
            body {
                padding: 0;
                font-size: 10px;
            }
            
            .no-print {
                display: none;
            }
            
            .header {
                border-bottom: 2px solid #333;
            }
            
            .summary-section {
                border: 1px solid #ccc;
                background: #fff !important;
            }
            
            .data-table {
                font-size: 9px;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
        
        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Category badge colors - FIXED */
        .badge-primary { background-color: #007bff !important; color: white !important; }
        .badge-info { background-color: #17a2b8 !important; color: white !important; }
        .badge-danger { background-color: #dc3545 !important; color: white !important; }
        .badge-success { background-color: #28a745 !important; color: white !important; }
        .badge-warning { background-color: #ffc107 !important; color: black !important; }
        .badge-purple { background-color: #6f42c1 !important; color: white !important; }
        .badge-orange { background-color: #fd7e14 !important; color: white !important; }
        .badge-secondary { background-color: #6c757d !important; color: white !important; }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 9px;
            font-weight: bold;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 3px;
        }
        
        /* Monthly breakdown table */
        .monthly-breakdown {
            margin: 20px 0;
        }
        
        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .breakdown-item {
            padding: 10px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .breakdown-label {
            font-size: 9px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .breakdown-value {
            font-size: 13px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1><?php echo $view_type === 'yearly' ? 'Yearly Report' : 'Monthly Report'; ?></h1>
        <div class="member-info">
            <?php echo htmlspecialchars($member['name']); ?> - <?php echo htmlspecialchars($member['house_name']); ?>
        </div>
        <div class="report-period">
            <?php echo $view_type === 'yearly' ? $year : $month_name . ' ' . $year; ?>
        </div>
        <div style="font-size: 11px; color: #7f8c8d; margin-top: 5px;">
            Generated on: <?php echo date('d/m/Y h:i A'); ?>
        </div>
    </div>

    <!-- Member Information -->
    <div class="member-info-section">
        <div class="info-row">
            <div class="info-label">Member ID:</div>
            <div>#<?php echo $member['member_id']; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Name:</div>
            <div><?php echo htmlspecialchars($member['name']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Phone:</div>
            <div><?php echo !empty($member['phone']) ? htmlspecialchars($member['phone']) : 'Not provided'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div><?php echo !empty($member['email']) ? htmlspecialchars($member['email']) : 'Not provided'; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Joined Date:</div>
            <div><?php echo date('M d, Y', strtotime($member['join_date'])); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div>
                <span style="background: <?php echo $member['status'] == 'active' ? '#27ae60' : '#95a5a6'; ?>; 
                      color: white; padding: 2px 8px; border-radius: 3px; font-size: 10px;">
                    <?php echo ucfirst($member['status']); ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($view_type === 'yearly'): ?>
    <!-- YEARLY REPORT CONTENT -->
    
    <!-- Yearly Summary -->
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Meals</div>
                <div class="summary-value"><?php echo number_format($yearly_totals['member_meals'], 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Deposits</div>
                <div class="summary-value">৳ <?php echo number_format($yearly_totals['member_deposits'], 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Cost</div>
                <div class="summary-value">৳ <?php echo number_format($yearly_totals['member_cost'], 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Year Balance</div>
                <div class="summary-value <?php echo $yearly_totals['balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                    ৳ <?php echo number_format($yearly_totals['balance'], 2); ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($previous_balance != 0): ?>
    <div style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h6 style="margin: 0; color: #2c3e50;">Previous Year Carry Forward</h6>
                <small style="color: #7f8c8d;">From <?php echo $prev_year; ?></small>
            </div>
            <div style="text-align: right;">
                <h4 style="margin: 0; color: <?php echo $previous_balance >= 0 ? '#27ae60' : '#e74c3c'; ?>;">
                    ৳ <?php echo number_format($previous_balance, 2); ?>
                </h4>
                <small style="color: #7f8c8d;">
                    <?php echo $previous_balance >= 0 ? 'Credit' : 'Due'; ?> from previous year
                </small>
            </div>
        </div>
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6; text-align: center;">
            <strong>Overall Balance:</strong>
            <span style="color: <?php echo $adjusted_balance >= 0 ? '#27ae60' : '#e74c3c'; ?>; font-weight: bold; font-size: 16px;">
                ৳ <?php echo number_format($adjusted_balance, 2); ?>
            </span>
            (<?php echo $adjusted_balance >= 0 ? 'IN CREDIT' : 'DUE'; ?>)
        </div>
    </div>
    <?php endif; ?>
    
    <!-- House Summary -->
    <div class="section-title">House Summary for <?php echo $year; ?></div>
    <div class="breakdown-grid">
        <div class="breakdown-item">
            <div class="breakdown-label">Total House Meals</div>
            <div class="breakdown-value"><?php echo number_format($yearly_totals['house_meals'], 2); ?></div>
        </div>
        <div class="breakdown-item">
            <div class="breakdown-label">Total House Expenses</div>
            <div class="breakdown-value">৳ <?php echo number_format($yearly_totals['house_expenses'], 2); ?></div>
        </div>
        <div class="breakdown-item">
            <div class="breakdown-label">Average Meal Rate</div>
            <div class="breakdown-value">৳ <?php echo number_format($yearly_totals['house_meals'] > 0 ? $yearly_totals['house_expenses'] / $yearly_totals['house_meals'] : 0, 2); ?></div>
        </div>
        <div class="breakdown-item">
            <div class="breakdown-label">Your Share</div>
            <div class="breakdown-value"><?php echo number_format($member_percentage, 1); ?>%</div>
        </div>
    </div>
    
    <!-- Monthly Breakdown Table -->
    <div class="section-title">Monthly Breakdown for <?php echo $year; ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Month</th>
                <th class="text-right">My Meals</th>
                <th class="text-right">My Deposits</th>
                <th class="text-right">Meal Rate</th>
                <th class="text-right">My Cost</th>
                <th class="text-right">Monthly Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($m = 1; $m <= 12; $m++): 
                $report = $yearly_reports[$m] ?? null;
            ?>
            <tr>
                <td><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></td>
                <td class="text-right"><?php echo $report ? number_format($report['member_meals'], 2) : '0.00'; ?></td>
                <td class="text-right"><?php echo $report ? number_format($report['member_deposits'], 2) : '0.00'; ?></td>
                <td class="text-right">৳ <?php echo $report ? number_format($report['meal_rate'], 2) : '0.00'; ?></td>
                <td class="text-right">৳ <?php echo $report ? number_format($report['member_cost'], 2) : '0.00'; ?></td>
                <td class="text-right <?php echo $report && $report['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    ৳ <?php echo $report ? number_format($report['balance'], 2) : '0.00'; ?>
                </td>
            </tr>
            <?php endfor; ?>
            <tr class="total-row">
                <td><strong>Yearly Totals:</strong></td>
                <td class="text-right"><strong><?php echo number_format($yearly_totals['member_meals'], 2); ?></strong></td>
                <td class="text-right"><strong>৳ <?php echo number_format($yearly_totals['member_deposits'], 2); ?></strong></td>
                <td class="text-right">-</td>
                <td class="text-right"><strong>৳ <?php echo number_format($yearly_totals['member_cost'], 2); ?></strong></td>
                <td class="text-right <?php echo $yearly_totals['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <strong>৳ <?php echo number_format($yearly_totals['balance'], 2); ?></strong>
                </td>
            </tr>
        </tbody>
    </table>
    
    <!-- Yearly Expense Categories -->
    <?php if (!empty($category_totals)): ?>
    <div class="section-title">Yearly Expense Categories - <?php echo $year; ?></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-right">Amount</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($category_totals as $category => $total): 
                $percentage = ($yearly_totals['house_expenses'] > 0) ? ($total / $yearly_totals['house_expenses']) * 100 : 0;
                $category_color = $category_colors[$category] ?? 'secondary';
            ?>
            <tr>
                <td>
                    <span class="badge badge-<?php echo $category_color; ?>">
                        <?php echo $category; ?>
                    </span>
                </td>
                <td class="text-right">৳ <?php echo number_format($total, 2); ?></td>
                <td>
                    <div style="display: flex; align-items: center;">
                        <div style="flex-grow: 1; height: 15px; background: #ecf0f1; border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $percentage; ?>%; background: #<?php 
                                switch($category_color) {
                                    case 'primary': echo '007bff'; break;
                                    case 'info': echo '17a2b8'; break;
                                    case 'danger': echo 'dc3545'; break;
                                    case 'success': echo '28a745'; break;
                                    case 'warning': echo 'ffc107'; break;
                                    case 'purple': echo '6f42c1'; break;
                                    case 'orange': echo 'fd7e14'; break;
                                    default: echo '6c757d'; break;
                                }
                            ?>;"></div>
                        </div>
                        <span style="margin-left: 8px; min-width: 40px; text-align: right;"><?php echo number_format($percentage, 1); ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td><strong>Total Expenses</strong></td>
                <td class="text-right"><strong>৳ <?php echo number_format($yearly_totals['house_expenses'], 2); ?></strong></td>
                <td><strong>100%</strong></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>
    
    <!-- Progress Bar -->
    <div style="margin: 20px 0;">
        <div class="progress">
            <div class="progress-bar" style="width: <?php echo $member_percentage; ?>%;">
                <?php echo htmlspecialchars($member['name']); ?>: <?php echo number_format($member_percentage, 1); ?>%
            </div>
        </div>
        <small style="color: #7f8c8d;">
            <?php echo htmlspecialchars($member['name']); ?> consumed <?php echo number_format($yearly_totals['member_meals'], 2); ?> out of <?php echo number_format($yearly_totals['house_meals'], 2); ?> total house meals
        </small>
    </div>
    
    <?php else: ?>
    <!-- MONTHLY REPORT CONTENT (existing code) -->
    
    <!-- Summary Section -->
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Meals</div>
                <div class="summary-value"><?php echo number_format($monthly_report['total_meals'], 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Deposits</div>
                <div class="summary-value">৳ <?php echo number_format($monthly_report['total_deposits'], 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">This Month Balance</div>
                <div class="summary-value <?php echo $monthly_report['balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                    ৳ <?php echo number_format($monthly_report['balance'], 2); ?>
                </div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Overall Balance</div>
                <div class="summary-value <?php echo $monthly_report['adjusted_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                    ৳ <?php echo number_format($monthly_report['adjusted_balance'], 2); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Breakdown -->
    <div class="section-title">Financial Breakdown</div>
    <div style="display: flex; flex-wrap: wrap; margin: 0 -10px;">
        <div style="flex: 1; min-width: 200px; padding: 0 10px;">
            <div class="financial-card card-info">
                <h6>Meal Rate</h6>
                <h3 class="text-info">৳ <?php echo number_format($monthly_report['meal_rate'], 2); ?></h3>
                <small>
                    Based on ৳<?php echo number_format($monthly_report['house_total_expenses'], 2); ?> expenses ÷ <?php echo number_format($monthly_report['house_total_meals'], 2); ?> total house meals
                </small>
            </div>
        </div>
        <div style="flex: 1; min-width: 200px; padding: 0 10px;">
            <div class="financial-card card-warning">
                <h6>Your Meals Cost</h6>
                <h3 class="text-warning">৳ <?php echo number_format($monthly_report['member_cost'], 2); ?></h3>
                <small>
                    <?php echo number_format($monthly_report['total_meals'], 2); ?> meals × ৳<?php echo number_format($monthly_report['meal_rate'], 2); ?> per meal
                </small>
            </div>
        </div>
        <div style="flex: 1; min-width: 200px; padding: 0 10px;">
            <div class="financial-card card-success">
                <h6>Your Deposits</h6>
                <h3 class="text-success">৳ <?php echo number_format($monthly_report['total_deposits'], 2); ?></h3>
                <small>
                    Total deposited this month
                </small>
            </div>
        </div>
        <div style="flex: 1; min-width: 200px; padding: 0 10px;">
            <div class="financial-card <?php echo $monthly_report['balance'] >= 0 ? 'card-primary' : 'card-danger'; ?>">
                <h6>Monthly Balance</h6>
                <h3 class="<?php echo $monthly_report['balance'] >= 0 ? 'text-info' : 'text-danger'; ?>">
                    ৳ <?php echo number_format($monthly_report['balance'], 2); ?>
                </h3>
                <small>
                    <?php echo $monthly_report['balance'] >= 0 ? 'Deposits exceed costs' : 'Costs exceed deposits'; ?>
                </small>
            </div>
        </div>
    </div>

    <?php if ($monthly_report['previous_balance'] != 0): ?>
    <div style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; border: 1px solid #dee2e6;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h6 style="margin: 0; color: #2c3e50;">Previous Balance Carry Forward</h6>
                <small style="color: #7f8c8d;">From previous month</small>
            </div>
            <div style="text-align: right;">
                <h4 style="margin: 0; color: <?php echo $monthly_report['previous_balance'] >= 0 ? '#27ae60' : '#e74c3c'; ?>;">
                    ৳ <?php echo number_format($monthly_report['previous_balance'], 2); ?>
                </h4>
                <small style="color: #7f8c8d;">
                    <?php echo $monthly_report['previous_balance'] >= 0 ? 'Credit' : 'Due'; ?> from previous month
                </small>
            </div>
        </div>
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6; text-align: center;">
            <strong>Overall Balance:</strong>
            <span style="color: <?php echo $monthly_report['adjusted_balance'] >= 0 ? '#27ae60' : '#e74c3c'; ?>; font-weight: bold; font-size: 16px;">
                ৳ <?php echo number_format($monthly_report['adjusted_balance'], 2); ?>
            </span>
            (<?php echo $monthly_report['adjusted_balance'] >= 0 ? 'IN CREDIT' : 'DUE'; ?>)
        </div>
    </div>
    <?php endif; ?>

    <!-- Calculation Formula -->
    <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
        <h6 style="margin-bottom: 10px;">Calculation Formula:</h6>
        <div style="font-family: monospace; padding: 10px; background: white; border-radius: 3px; border: 1px solid #dee2e6;">
            (<span style="color: #27ae60; font-weight: bold;">৳ <?php echo number_format($monthly_report['total_deposits'], 2); ?></span> Deposits) 
            - 
            (<span style="color: #f39c12; font-weight: bold;"><?php echo number_format($monthly_report['total_meals'], 2); ?></span> Meals × 
            <span style="color: #3498db; font-weight: bold;">৳ <?php echo number_format($monthly_report['meal_rate'], 2); ?></span> Rate) 
            = 
            <span style="color: <?php echo $monthly_report['balance'] >= 0 ? '#3498db' : '#e74c3c'; ?>; font-weight: bold;">
                ৳ <?php echo number_format($monthly_report['balance'], 2); ?>
            </span>
        </div>
    </div>

    <!-- History Tables -->
    <div style="display: flex; flex-wrap: wrap; margin: 0 -10px;">
        <!-- Meal History -->
        <div style="flex: 1; min-width: 300px; padding: 0 10px;">
            <div class="section-title">Meal History</div>
            <?php if (!empty($meals)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th class="text-right">Meals</th>
                        <th class="text-right">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_daily_cost = 0;
                    foreach ($meals as $meal): 
                        $daily_cost = $meal['meal_count'] * $monthly_report['meal_rate'];
                        $total_daily_cost += $daily_cost;
                    ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($meal['meal_date'])); ?></td>
                        <td><?php echo date('D', strtotime($meal['meal_date'])); ?></td>
                        <td class="text-right"><?php echo number_format($meal['meal_count'], 2); ?></td>
                        <td class="text-right">৳ <?php echo number_format($daily_cost, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="3" class="text-right"><strong>Total:</strong></td>
                        <td class="text-right"><strong>৳ <?php echo number_format($total_daily_cost, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            <div style="text-align: center; font-size: 10px; color: #7f8c8d; margin-top: 5px;">
                <?php echo count($meals); ?> days recorded | Average per day: ৳ <?php echo count($meals) > 0 ? number_format($total_daily_cost / count($meals), 2) : '0.00'; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 20px; color: #7f8c8d; font-style: italic;">
                No meals recorded for <?php echo $month_name . ' ' . $year; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Deposit History -->
        <div style="flex: 1; min-width: 300px; padding: 0 10px;">
            <div class="section-title">Deposit History</div>
            <?php if (!empty($deposits)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deposits as $deposit): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($deposit['deposit_date'])); ?></td>
                        <td class="text-right">৳ <?php echo number_format($deposit['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($deposit['description'] ?? 'No description'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="2" class="text-right"><strong>Total:</strong></td>
                        <td><strong>৳ <?php echo number_format($monthly_report['total_deposits'], 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            <div style="text-align: center; font-size: 10px; color: #7f8c8d; margin-top: 5px;">
                <?php echo count($deposits); ?> deposits | Average per deposit: ৳ <?php echo count($deposits) > 0 ? number_format($monthly_report['total_deposits'] / count($deposits), 2) : '0.00'; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 20px; color: #7f8c8d; font-style: italic;">
                No deposits recorded for <?php echo $month_name . ' ' . $year; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- House Summary -->
    <div class="section-title">House Summary for <?php echo $month_name . ' ' . $year; ?></div>
    <div style="display: flex; flex-wrap: wrap; margin: 0 -10px; text-align: center;">
        <div style="flex: 1; min-width: 200px; padding: 0 10px;">
            <h6>Total House Meals</h6>
            <h3 style="color: #27ae60;"><?php echo number_format($monthly_report['house_total_meals'], 2); ?></h3>
        </div>
        <div style="flex: 1; min-width: 200px; padding: 0 10px;">
            <h6>Total House Expenses</h6>
            <h3 style="color: #f39c12;">৳ <?php echo number_format($monthly_report['house_total_expenses'], 2); ?></h3>
        </div>
        <div style="flex: 1; min-width: 200px; padding: 0 10px;">
            <h6>Average Meal Rate</h6>
            <h3 style="color: #3498db;">৳ <?php echo number_format($monthly_report['meal_rate'], 2); ?></h3>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div style="margin: 20px 0;">
        <div class="progress">
            <div class="progress-bar" style="width: <?php echo $member_percentage; ?>%;">
                <?php echo htmlspecialchars($member['name']); ?>: <?php echo number_format($member_percentage, 1); ?>%
            </div>
        </div>
        <small style="color: #7f8c8d;">
            <?php echo htmlspecialchars($member['name']); ?> consumed <?php echo number_format($monthly_report['total_meals'], 2); ?> out of <?php echo number_format($monthly_report['house_total_meals'], 2); ?> total house meals
        </small>
    </div>

    <!-- House Expenses Breakdown -->
    <?php if (!empty($category_totals)): ?>
    <div class="section-title">House Expenses Breakdown</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-right">Amount</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($category_totals as $category => $total): 
                $percentage = ($monthly_report['house_total_expenses'] > 0) ? ($total / $monthly_report['house_total_expenses']) * 100 : 0;
                $category_color = $category_colors[$category] ?? 'secondary';
            ?>
            <tr>
                <td>
                    <span class="badge badge-<?php echo $category_color; ?>">
                        <?php echo $category; ?>
                    </span>
                </td>
                <td class="text-right">৳ <?php echo number_format($total, 2); ?></td>
                <td>
                    <div style="display: flex; align-items: center;">
                        <div style="flex-grow: 1; height: 15px; background: #ecf0f1; border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $percentage; ?>%; background: #<?php 
                                switch($category_color) {
                                    case 'primary': echo '007bff'; break;
                                    case 'info': echo '17a2b8'; break;
                                    case 'danger': echo 'dc3545'; break;
                                    case 'success': echo '28a745'; break;
                                    case 'warning': echo 'ffc107'; break;
                                    case 'purple': echo '6f42c1'; break;
                                    case 'orange': echo 'fd7e14'; break;
                                    default: echo '6c757d'; break;
                                }
                            ?>;"></div>
                        </div>
                        <span style="margin-left: 8px; min-width: 40px; text-align: right;"><?php echo number_format($percentage, 1); ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td><strong>Total Expenses</strong></td>
                <td class="text-right"><strong>৳ <?php echo number_format($monthly_report['house_total_expenses'], 2); ?></strong></td>
                <td><strong>100%</strong></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>
    
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <p>Report generated by mealsa</p>
        <p>Member: <?php echo htmlspecialchars($member['name']); ?> | 
           House: <?php echo htmlspecialchars($member['house_name']); ?> | 
           <?php echo $view_type === 'yearly' ? 'Year: ' . $year : 'Month: ' . $month_name . ' ' . $year; ?></p>
        <p>Generated on: <?php echo date('d M Y, h:i A'); ?></p>
        <p>This is a computer-generated report. No signature required.</p>
    </div>

    <!-- Print Controls -->
    <div class="no-print">
        <button onclick="window.print()" class="print-btn">
            Print / Save as PDF
        </button>
        <p style="margin-top: 15px; color: #666; font-size: 12px;">
            Click the button above to print this report or save it as PDF
        </p>
        <p style="margin-top: 5px; color: #888; font-size: 11px;">
            Tip: In the print dialog, select "Save as PDF" to download this report
        </p>
        <div style="margin-top: 15px;">
            <a href="report.php?view=<?php echo $view_type; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
               style="padding: 8px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;">
                Back to Report View
            </a>
            <button onclick="window.close()" style="padding: 8px 20px; background: #95a5a6; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Close Window
            </button>
        </div>
    </div>

    <script>
        // Auto-focus on print button
        document.addEventListener('DOMContentLoaded', function() {
            const printBtn = document.querySelector('.print-btn');
            if (printBtn) {
                printBtn.focus();
            }
            
            // Auto-print if specified
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint') === '1') {
                window.print();
            }
        });
        
        // Auto-close after printing
        window.onafterprint = function() {
            // Optional: Auto-close window after printing
            // window.close();
        };
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>