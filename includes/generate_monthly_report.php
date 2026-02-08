<?php
/**
 * Monthly Report PDF Generator
 * Standalone file - NO HEADER.PHP INCLUSION
 */

// Start session
session_start();

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die('<div style="padding: 20px; text-align: center; font-family: Arial; color: #dc3545;">
        <h2>Access Denied</h2>
        <p>Please log in to access this report.</p>
        <p><a href="../auth/login.php">Go to Login</a></p>
    </div>');
}

// Only managers can generate PDF reports
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    die('<div style="padding: 20px; text-align: center; font-family: Arial; color: #dc3545;">
        <h2>Access Denied</h2>
        <p>Manager access required to generate reports.</p>
        <p><a href="../dashboard/">Go to Dashboard</a></p>
    </div>');
}

// Include database and functions
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Get parameters
$house_id = isset($_GET['house_id']) ? intval($_GET['house_id']) : null;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate house_id
if (!$house_id && isset($_SESSION['house_id'])) {
    $house_id = $_SESSION['house_id'];
}

if (!$house_id) {
    die('<div style="padding: 20px; text-align: center; font-family: Arial; color: #dc3545;">
        <h2>Error</h2>
        <p>No house specified. Please go back and select a house.</p>
    </div>');
}

// Validate month/year
if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

// Database connection
$conn = getConnection();

// Get house information
$house_sql = "SELECT house_id, house_name, house_code FROM houses WHERE house_id = ? AND is_active = 1";
$stmt = mysqli_prepare($conn, $house_sql);
mysqli_stmt_bind_param($stmt, 'i', $house_id);
mysqli_stmt_execute($stmt);
$house_result = mysqli_stmt_get_result($stmt);
$house_info = mysqli_fetch_assoc($house_result);

if (!$house_info) {
    die('<div style="padding: 20px; text-align: center; font-family: Arial; color: #dc3545;">
        <h2>Error</h2>
        <p>House not found or inactive.</p>
    </div>');
}

// Create Functions instance
$functions = new Functions();

// Calculate monthly report
$monthly_report = $functions->calculateMonthlyReport($month, $year, $house_id);

// Get total expenses for the month
$expenses_sql = "SELECT SUM(amount) as total_expenses FROM expenses 
                WHERE house_id = ? AND MONTH(expense_date) = ? AND YEAR(expense_date) = ?";
$stmt = mysqli_prepare($conn, $expenses_sql);
mysqli_stmt_bind_param($stmt, 'iii', $house_id, $month, $year);
mysqli_stmt_execute($stmt);
$expenses_result = mysqli_stmt_get_result($stmt);
$expenses_data = mysqli_fetch_assoc($expenses_result);
$total_expenses = $expenses_data['total_expenses'] ?? 0;

// Get total meals for the month
$meals_sql = "SELECT SUM(meal_count) as total_meals FROM meals 
             WHERE house_id = ? AND MONTH(meal_date) = ? AND YEAR(meal_date) = ?";
$stmt = mysqli_prepare($conn, $meals_sql);
mysqli_stmt_bind_param($stmt, 'iii', $house_id, $month, $year);
mysqli_stmt_execute($stmt);
$meals_result = mysqli_stmt_get_result($stmt);
$meals_data = mysqli_fetch_assoc($meals_result);
$total_meals = $meals_data['total_meals'] ?? 0;

// Calculate meal rate
$meal_rate = ($total_meals > 0 && $total_expenses > 0) ? $total_expenses / $total_meals : 0;

// Get expense breakdown by category
$category_sql = "SELECT category, SUM(amount) as total 
                FROM expenses 
                WHERE house_id = ? AND MONTH(expense_date) = ? AND YEAR(expense_date) = ? 
                GROUP BY category 
                ORDER BY total DESC";
$stmt = mysqli_prepare($conn, $category_sql);
mysqli_stmt_bind_param($stmt, 'iii', $house_id, $month, $year);
mysqli_stmt_execute($stmt);
$category_result = mysqli_stmt_get_result($stmt);
$expense_categories = mysqli_fetch_all($category_result, MYSQLI_ASSOC);

// Calculate totals from monthly report
$grand_total_deposits = 0;
$grand_total_cost = 0;
$grand_total_balance = 0;
$total_members = 0;
$members_in_credit = 0;
$members_with_due = 0;

if ($monthly_report && is_array($monthly_report)) {
    foreach ($monthly_report as $member) {
        $grand_total_deposits += $member['total_deposits'] ?? 0;
        $grand_total_cost += $member['member_cost'] ?? 0;
        $grand_total_balance += $member['balance'] ?? 0;
        
        if (($member['balance'] ?? 0) >= 0) {
            $members_in_credit++;
        } else {
            $members_with_due++;
        }
    }
    $total_members = count($monthly_report);
}

// Close statement
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}

// Generate PDF HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report - <?php echo htmlspecialchars($house_info['house_name']); ?></title>
    <style>
        /* PDF Styles */
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
        
        .header .house-info {
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
        .balance-positive { color: #27ae60; font-weight: bold; }
        .balance-negative { color: #e74c3c; font-weight: bold; }
        
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
        
        .print-btn:hover {
            background: #2980b9;
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
        }
        
        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Monthly Meal Report</h1>
        <div class="house-info">
            <?php echo htmlspecialchars($house_info['house_name']); ?> 
            (Code: <?php echo htmlspecialchars($house_info['house_code']); ?>)
        </div>
        <div class="report-period">
            <?php echo date('F Y', strtotime("$year-$month-01")); ?>
        </div>
        <div style="font-size: 11px; color: #7f8c8d; margin-top: 5px;">
            Generated on: <?php echo date('d/m/Y h:i A'); ?>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Expenses</div>
                <div class="summary-value">৳ <?php echo number_format($total_expenses, 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Meals</div>
                <div class="summary-value"><?php echo number_format($total_meals, 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Meal Rate</div>
                <div class="summary-value">৳ <?php echo number_format($meal_rate, 2); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Net Balance</div>
                <div class="summary-value balance-<?php echo $grand_total_balance >= 0 ? 'positive' : 'negative'; ?>">
                    ৳ <?php echo number_format($grand_total_balance, 2); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Categories -->
    <?php if (!empty($expense_categories)): ?>
    <div class="section-title">Expense Breakdown</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-left">Category</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expense_categories as $category):
                $percentage = $total_expenses > 0 ? ($category['total'] / $total_expenses * 100) : 0;
            ?>
            <tr>
                <td class="text-left"><?php echo htmlspecialchars($category['category']); ?></td>
                <td class="text-right">৳ <?php echo number_format($category['total'], 2); ?></td>
                <td class="text-right"><?php echo number_format($percentage, 1); ?>%</td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td class="text-left"><strong>TOTAL</strong></td>
                <td class="text-right"><strong>৳ <?php echo number_format($total_expenses, 2); ?></strong></td>
                <td class="text-right"><strong>100%</strong></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Member-wise Report -->
    <?php if (!empty($monthly_report)): ?>
    <div class="section-title">Member-wise Report</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th class="text-left">Member Name</th>
                <th class="text-center">Total Meals</th>
                <th class="text-right">Meal Cost</th>
                <th class="text-right">Total Deposits</th>
                <th class="text-right">Balance</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1;
            $total_meals_calc = 0;
            $total_cost = 0;
            $total_deposits = 0;
            $final_balance = 0;
            
            foreach ($monthly_report as $member):
                $total_meals_calc += $member['total_meals'] ?? 0;
                $total_cost += $member['member_cost'] ?? 0;
                $total_deposits += $member['total_deposits'] ?? 0;
                $final_balance += $member['balance'] ?? 0;
                $balance_class = ($member['balance'] ?? 0) >= 0 ? 'balance-positive' : 'balance-negative';
                $status = ($member['balance'] ?? 0) >= 0 ? 'In Credit' : 'Due';
                $status_color = ($member['balance'] ?? 0) >= 0 ? '#27ae60' : '#e74c3c';
            ?>
            <tr>
                <td class="text-center"><?php echo $counter++; ?></td>
                <td class="text-left"><?php echo htmlspecialchars($member['name'] ?? 'N/A'); ?></td>
                <td class="text-center"><?php echo number_format($member['total_meals'] ?? 0, 2); ?></td>
                <td class="text-right">৳ <?php echo number_format($member['member_cost'] ?? 0, 2); ?></td>
                <td class="text-right">৳ <?php echo number_format($member['total_deposits'] ?? 0, 2); ?></td>
                <td class="text-right <?php echo $balance_class; ?>">
                    ৳ <?php echo number_format($member['balance'] ?? 0, 2); ?>
                </td>
                <td class="text-center">
                    <span style="background: <?php echo $status_color; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 10px;">
                        <?php echo $status; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="2" class="text-left"><strong>GRAND TOTAL</strong></td>
                <td class="text-center"><strong><?php echo number_format($total_meals_calc, 2); ?></strong></td>
                <td class="text-right"><strong>৳ <?php echo number_format($total_cost, 2); ?></strong></td>
                <td class="text-right"><strong>৳ <?php echo number_format($total_deposits, 2); ?></strong></td>
                <td class="text-right <?php echo $final_balance >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                    <strong>৳ <?php echo number_format($final_balance, 2); ?></strong>
                </td>
                <td class="text-center">
                    <strong>
                        <?php if ($final_balance >= 0): ?>
                        HOUSE IN CREDIT
                        <?php else: ?>
                        HOUSE HAS DUE
                        <?php endif; ?>
                    </strong>
                </td>
            </tr>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 30px; color: #7f8c8d; font-style: italic;">
        No data available for <?php echo date('F Y', strtotime("$year-$month-01")); ?>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <p>Report generated by mealsa</p>
        <p>House: <?php echo htmlspecialchars($house_info['house_name']); ?> | 
           Month: <?php echo date('F Y', strtotime("$year-$month-01")); ?> | 
           Generated on: <?php echo date('d M Y, h:i A'); ?></p>
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
        });
        
        // Optional: Auto-print after 1 second (uncomment if desired)
        // setTimeout(function() {
        //     window.print();
        // }, 1000);
        
        // Handle after print
        window.onafterprint = function() {
            // Optionally close window after printing
            // setTimeout(function() {
            //     window.close();
            // }, 1000);
        };
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>