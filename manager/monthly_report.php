<?php
// monthly_report.php - Main monthly report page

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../includes/functions.php';
date_default_timezone_set('Asia/Dhaka');
$auth = new Auth();
$functions = new Functions();

// Check authentication and manager role
$auth->requireRole('manager');

// Get parameters
$house_id = isset($_GET['house_id']) ? intval($_GET['house_id']) : null;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// For PDF format, redirect to the standalone PDF generator
if ($format == 'pdf') {
    if (!$house_id && isset($_SESSION['house_id'])) {
        $house_id = $_SESSION['house_id'];
    }
    
    if (!$house_id) {
        die('Error: No house specified.');
    }
    
    // Redirect to the standalone PDF generator
    $pdf_url = "../includes/generate_monthly_report.php?" . http_build_query([
        'house_id' => $house_id,
        'month' => $month,
        'year' => $year
    ]);
    
    if (!headers_sent()) {
        header("Location: $pdf_url");
        exit();
    } else {
        // JavaScript redirect if headers already sent
        echo '<script>window.location.href="' . $pdf_url . '";</script>';
        exit();
    }
}

// Continue with normal HTML display
require_once '../includes/header.php';

$page_title = "Monthly Report";

// Validate house_id for HTML version
if (!$house_id) {
    if (isset($_SESSION['house_id'])) {
        $house_id = $_SESSION['house_id'];
    } else {
        echo '<div class="alert alert-danger">Error: No house specified. Please go back and select a house.</div>';
        require_once '../includes/footer.php';
        exit();
    }
}

// Validate month/year
if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

$conn = getConnection();

// Get house information
$house_sql = "SELECT house_id, house_name, house_code FROM houses WHERE house_id = ? AND is_active = 1";
$stmt = mysqli_prepare($conn, $house_sql);
mysqli_stmt_bind_param($stmt, 'i', $house_id);
mysqli_stmt_execute($stmt);
$house_result = mysqli_stmt_get_result($stmt);
$house_info = mysqli_fetch_assoc($house_result);

if (!$house_info) {
    echo '<div class="alert alert-danger">Error: House not found or inactive.</div>';
    require_once '../includes/footer.php';
    exit();
}

// Calculate monthly report using the Functions class
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

// Get all members for the house
$all_members_sql = "SELECT COUNT(*) as total FROM members WHERE house_id = ? AND status = 'active'";
$stmt = mysqli_prepare($conn, $all_members_sql);
mysqli_stmt_bind_param($stmt, 'i', $house_id);
mysqli_stmt_execute($stmt);
$all_members_result = mysqli_stmt_get_result($stmt);
$all_members_data = mysqli_fetch_assoc($all_members_result);
$all_members_count = $all_members_data['total'] ?? 0;

// Helper function for category colors - MATCHING EXPENSES.PHP
function getCategoryColor($category) {
    $colors = [
        'Rice' => 'primary',        // Blue
        'Fish' => 'info',           // Cyan
        'Meat' => 'danger',         // Red
        'Vegetables' => 'success',  // Green
        'Spices' => 'warning',      // Yellow
        'Oil' => 'purple',          // Purple
        'Food' => 'orange',         // Orange
        'Others' => 'secondary'     // Gray
    ];
    return $colors[$category] ?? 'secondary';
}

// Helper function for text color based on badge color
function getBadgeTextColor($badge_color) {
    // For light/yellow badges, use dark text
    return ($badge_color == 'warning' || $badge_color == 'light') ? 'text-dark' : 'text-white';
}

// Close statement
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Meal Management System</title>
    <?php require_once '../includes/header.php'; ?>
    <style>
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        .summary-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }
        .balance-positive {
            color: #28a745;
            font-weight: bold;
        }
        .balance-negative {
            color: #dc3545;
            font-weight: bold;
        }
        .print-only {
            display: none;
        }
        
        /* New styles for colored percentages - MATCHING EXPENSES.PHP */
        .percentage-high {
            color: #dc3545 !important; /* Red (matches danger) */
            font-weight: bold !important;
        }
        .percentage-medium-high {
            color: #fd7e14 !important; /* Orange (matches orange) */
            font-weight: bold !important;
        }
        .percentage-medium {
            color: #0d6efd !important; /* Blue (matches primary) */
            font-weight: bold !important;
        }
        .percentage-low {
            color: #198754 !important; /* Green (matches success) */
            font-weight: bold !important;
        }
        
        /* Progress bar colors based on percentage */
        .progress-bar-high {
            background-color: #dc3545 !important; /* Red */
        }
        .progress-bar-medium-high {
            background-color: #fd7e14 !important; /* Orange */
        }
        .progress-bar-medium {
            background-color: #0d6efd !important; /* Blue */
        }
        .progress-bar-low {
            background-color: #198754 !important; /* Green */
        }
        
        /* Special badge colors for Oil (purple) */
        .bg-purple {
            background-color: #6f42c1 !important;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block;
            }
            .report-header {
                background: #333 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .bg-purple {
                background-color: #6f42c1 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Report Header -->
        <div class="report-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="h2 mb-1">Monthly Meal Report</h1>
                    <p class="mb-0 opacity-75"><?php echo htmlspecialchars($house_info['house_name']); ?> 
                        (Code: <?php echo htmlspecialchars($house_info['house_code']); ?>)</p>
                </div>
                <div class="text-end">
                    <h3 class="mb-1"><?php echo date('F Y', strtotime("$year-$month-01")); ?></h3>
                    <small class="opacity-75">Generated on: <?php echo date('d M Y, h:i A'); ?></small>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4 no-print">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="reports.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Reports
                                </a>
                            </div>
                            <div>
                                <!-- UPDATED PDF BUTTON - Opens in new tab -->
                                <a href="../includes/generate_monthly_report.php?house_id=<?php echo $house_id; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                                   class="btn btn-outline-danger" target="_blank">
                                    <i class="fas fa-file-pdf me-2"></i>Download PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-primary h-100">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Expenses</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $functions->formatCurrency($total_expenses); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill-wave fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-success h-100">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Meals</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($total_meals, 2); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-utensils fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-warning h-100">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Meal Rate</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $functions->formatCurrency($meal_rate); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calculator fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-info h-100">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Net Balance</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <span class="<?php echo $grand_total_balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $functions->formatCurrency($grand_total_balance); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-balance-scale fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Breakdown -->
        <?php if (!empty($expense_categories)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-pie me-2"></i>Expense Breakdown by Category
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expense_categories as $category): 
                                        $percentage = $total_expenses > 0 ? ($category['total'] / $total_expenses * 100) : 0;
                                        
                                        // Get category badge color
                                        $category_color = getCategoryColor($category['category']);
                                        $badge_text_color = getBadgeTextColor($category_color);
                                        
                                        // Special case for Oil (purple)
                                        if ($category_color == 'purple') {
                                            $badge_class = 'bg-purple text-white';
                                        } else {
                                            $badge_class = 'bg-' . $category_color . ' ' . $badge_text_color;
                                        }
                                        
                                        // Determine color class based on percentage value
                                        $percentage_color_class = '';
                                        $progress_bar_class = '';
                                        
                                        if ($percentage > 30) {
                                            $percentage_color_class = 'percentage-high';
                                            $progress_bar_class = 'progress-bar-high';
                                        } elseif ($percentage > 15) {
                                            $percentage_color_class = 'percentage-medium-high';
                                            $progress_bar_class = 'progress-bar-medium-high';
                                        } elseif ($percentage > 5) {
                                            $percentage_color_class = 'percentage-medium';
                                            $progress_bar_class = 'progress-bar-medium';
                                        } else {
                                            $percentage_color_class = 'percentage-low';
                                            $progress_bar_class = 'progress-bar-low';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($category['category']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $functions->formatCurrency($category['total']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                    <div class="progress-bar <?php echo $progress_bar_class; ?>" role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%;" 
                                                         aria-valuenow="<?php echo $percentage; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </div>
                                                </div>
                                                <span class="<?php echo $percentage_color_class; ?>">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo $functions->formatCurrency($total_expenses); ?></strong></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" 
                                                         style="width: 100%;" 
                                                         aria-valuenow="100" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        100%
                                                    </div>
                                                </div>
                                                <span class="text-primary fw-bold">100%</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Member-wise Report -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-users me-2"></i>Member-wise Monthly Report
                        </h6>
                        <div class="text-muted">
                            Total Members: <?php echo $total_members; ?> | 
                            In Credit: <span class="text-success"><?php echo $members_in_credit; ?></span> | 
                            With Due: <span class="text-danger"><?php echo $members_with_due; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($monthly_report) || !is_array($monthly_report)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                <h5>No data available for <?php echo date('F Y', strtotime("$year-$month-01")); ?></h5>
                                <p>No meals or expenses recorded for this month.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Member Name</th>
                                            <th class="text-center">Total Meals</th>
                                            <th class="text-center">Meal Cost</th>
                                            <th class="text-center">Total Deposits</th>
                                            <th class="text-center">Balance</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        foreach ($monthly_report as $member): 
                                            $balance_class = ($member['balance'] ?? 0) >= 0 ? 'balance-positive' : 'balance-negative';
                                            $status = ($member['balance'] ?? 0) >= 0 ? 'In Credit' : 'Due';
                                            $status_class = ($member['balance'] ?? 0) >= 0 ? 'badge bg-success' : 'badge bg-danger';
                                        ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($member['name'] ?? 'N/A'); ?></td>
                                            <td class="text-center"><?php echo number_format($member['total_meals'] ?? 0, 2); ?></td>
                                            <td class="text-center"><?php echo $functions->formatCurrency($member['member_cost'] ?? 0); ?></td>
                                            <td class="text-center"><?php echo $functions->formatCurrency($member['total_deposits'] ?? 0); ?></td>
                                            <td class="text-center <?php echo $balance_class; ?>">
                                                <?php echo $functions->formatCurrency($member['balance'] ?? 0); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="<?php echo $status_class; ?>"><?php echo $status; ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-light">
                                            <td colspan="2"><strong>Grand Total</strong></td>
                                            <td class="text-center"><strong><?php echo number_format($total_meals, 2); ?></strong></td>
                                            <td class="text-center"><strong><?php echo $functions->formatCurrency($grand_total_cost); ?></strong></td>
                                            <td class="text-center"><strong><?php echo $functions->formatCurrency($grand_total_deposits); ?></strong></td>
                                            <td class="text-center">
                                                <strong class="<?php echo $grand_total_balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $functions->formatCurrency($grand_total_balance); ?>
                                                </strong>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($grand_total_balance >= 0): ?>
                                                    <span class="badge bg-success">House in Credit</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">House has Due</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calculations & Notes -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>Calculations</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td>Total Expenses:</td>
                                <td class="text-end"><?php echo $functions->formatCurrency($total_expenses); ?></td>
                            </tr>
                            <tr>
                                <td>Total Meals:</td>
                                <td class="text-end"><?php echo number_format($total_meals, 2); ?></td>
                            </tr>
                            <tr>
                                <td>Meal Rate:</td>
                                <td class="text-end"><?php echo $functions->formatCurrency($meal_rate); ?></td>
                            </tr>
                            <tr>
                                <td>Total Deposits:</td>
                                <td class="text-end"><?php echo $functions->formatCurrency($grand_total_deposits); ?></td>
                            </tr>
                            <tr>
                                <td>Total Member Cost:</td>
                                <td class="text-end"><?php echo $functions->formatCurrency($grand_total_cost); ?></td>
                            </tr>
                            <tr class="table-light">
                                <td><strong>Net Balance:</strong></td>
                                <td class="text-end">
                                    <strong class="<?php echo $grand_total_balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $functions->formatCurrency($grand_total_balance); ?>
                                    </strong>
                                </td>
                            </tr>
                        </table>
                        <p class="small text-muted mb-0 mt-2">
                            <strong>Formula:</strong> Meal Rate = Total Expenses ÷ Total Meals<br>
                            Member Cost = Member Meals × Meal Rate<br>
                            Balance = Total Deposits - Member Cost
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes & Remarks</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Add Notes (Optional)</label>
                            <textarea class="form-control" id="remarks" rows="4" placeholder="Add any notes or remarks about this monthly report..."></textarea>
                        </div>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Report Status</h6>
                            <ul class="mb-0 small">
                                <li>Report generated on: <?php echo date('d M Y, h:i A'); ?></li>
                                <li>House: <?php echo htmlspecialchars($house_info['house_name']); ?></li>
                                <li>Month: <?php echo date('F Y', strtotime("$year-$month-01")); ?></li>
                                <li>Total active members in house: <?php echo $all_members_count; ?></li>
                                <li>Members with data this month: <?php echo $total_members; ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Print Footer -->
        <div class="print-only mt-5 pt-5">
            <hr>
            <div class="text-center text-muted small">
                <p>Report generated by mealsa</p>
                <p>House: <?php echo htmlspecialchars($house_info['house_name']); ?> | 
                   Month: <?php echo date('F Y', strtotime("$year-$month-01")); ?> | 
                   Generated on: <?php echo date('d M Y, h:i A'); ?></p>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row mb-4 no-print">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <?php if ($month > 1): ?>
                                <a href="?house_id=<?php echo $house_id; ?>&month=<?php echo $month-1; ?>&year=<?php echo $year; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-chevron-left me-2"></i>Previous Month
                                </a>
                                <?php endif; ?>
                                
                                <a href="?house_id=<?php echo $house_id; ?>&month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>" 
                                   class="btn btn-outline-info mx-2">
                                    <i class="fas fa-calendar-alt me-2"></i>Current Month
                                </a>
                                
                                <?php if ($month < 12): ?>
                                <a href="?house_id=<?php echo $house_id; ?>&month=<?php echo $month+1; ?>&year=<?php echo $year; ?>" 
                                   class="btn btn-outline-primary">
                                    Next Month<i class="fas fa-chevron-right ms-2"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <select id="monthSelector" class="form-select d-inline-block w-auto">
                                    <?php
                                    $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                             'July', 'August', 'September', 'October', 'November', 'December'];
                                    for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                        <?php echo $months[$m-1]; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <select id="yearSelector" class="form-select d-inline-block w-auto ms-2">
                                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 2; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <button onclick="goToMonth()" class="btn btn-primary ms-2">
                                    <i class="fas fa-search me-2"></i>Go
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function goToMonth() {
        const month = document.getElementById('monthSelector').value;
        const year = document.getElementById('yearSelector').value;
        const house_id = <?php echo $house_id; ?>;
        window.location.href = `?house_id=${house_id}&month=${month}&year=${year}`;
    }

    // Print optimization
    window.onbeforeprint = function() {
        document.querySelectorAll('.no-print').forEach(el => {
            el.style.display = 'none';
        });
        document.querySelectorAll('.print-only').forEach(el => {
            el.style.display = 'block';
        });
    };

    window.onafterprint = function() {
        document.querySelectorAll('.no-print').forEach(el => {
            el.style.display = '';
        });
        document.querySelectorAll('.print-only').forEach(el => {
            el.style.display = 'none';
        });
    };

    // Save remarks to localStorage
    document.getElementById('remarks').addEventListener('input', function() {
        localStorage.setItem('monthly_report_remarks_<?php echo $house_id . '_' . $month . '_' . $year; ?>', this.value);
    });

    // Load saved remarks
    window.onload = function() {
        const savedRemarks = localStorage.getItem('monthly_report_remarks_<?php echo $house_id . '_' . $month . '_' . $year; ?>');
        if (savedRemarks) {
            document.getElementById('remarks').value = savedRemarks;
        }
    };
    </script>

<?php 
// Close database connection
mysqli_close($conn);

require_once '../includes/footer.php'; 
?>
</body>
</html>