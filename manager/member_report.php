<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Dhaka');

// Handle PDF request BEFORE any output
if (isset($_GET['format']) && $_GET['format'] == 'pdf') {
    // Get parameters for PDF
    $member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $house_id = $_SESSION['house_id'] ?? null;
    
    if ($member_id > 0 && $house_id) {
        // Redirect to PDF generator
        $pdf_url = "../includes/generate_member_report.php?" . http_build_query([
            'member_id' => $member_id,
            'month' => $month,
            'year' => $year,
            'house_id' => $house_id
        ]);
        
        // Use meta refresh for PDF redirect
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Redirecting to PDF...</title>
            <meta http-equiv="refresh" content="0; url=' . htmlspecialchars($pdf_url) . '">
        </head>
        <body>
            <p style="text-align: center; padding: 50px; font-family: Arial;">
                Generating PDF report...<br>
                If not redirected, <a href="' . htmlspecialchars($pdf_url) . '">click here</a>.
            </p>
        </body>
        </html>';
        exit();
    }
}

require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

// Get current user's house_id
$user_id = $_SESSION['user_id'];
$house_id = $_SESSION['house_id'] ?? null;

// Check if user has a house
if (!$house_id) {
    $_SESSION['error'] = "You need to set up a house first";
    header("Location: setup_house.php");
    exit();
}

$page_title = "Member Report";

$conn = getConnection();

// Get parameters with validation
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate inputs
if ($member_id <= 0) {
    $_SESSION['error'] = "Invalid member ID. Please select a valid member.";
    header("Location: members.php");
    exit();
}

if ($month < 1 || $month > 12) $month = date('m');
if ($year < 2000 || $year > 2100) $year = date('Y');

$month_name = date('F', mktime(0, 0, 0, $month, 1));

// Check if member belongs to current house
$member_check_sql = "SELECT m.*, h.house_name 
                     FROM members m 
                     LEFT JOIN houses h ON m.house_id = h.house_id 
                     WHERE m.member_id = ? AND m.house_id = ?";
$member_check_stmt = mysqli_prepare($conn, $member_check_sql);
mysqli_stmt_bind_param($member_check_stmt, "ii", $member_id, $house_id);
mysqli_stmt_execute($member_check_stmt);
$member_result = mysqli_stmt_get_result($member_check_stmt);
$member = mysqli_fetch_assoc($member_result);

if (!$member) {
    $_SESSION['error'] = "Member not found or you don't have permission to view this report";
    header("Location: members.php");
    exit();
}

// Get report data
$member_report = null;
$meals = [];
$deposits = [];
$monthly_total_meals = 0;
$total_expenses = 0;

try {
    // Get total meals for all members in the house for the month
    $total_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as total_meals 
                        FROM meals 
                        WHERE house_id = ? 
                        AND MONTH(meal_date) = ? 
                        AND YEAR(meal_date) = ?";
    $total_meals_stmt = mysqli_prepare($conn, $total_meals_sql);
    mysqli_stmt_bind_param($total_meals_stmt, "iii", $house_id, $month, $year);
    mysqli_stmt_execute($total_meals_stmt);
    $total_meals_result = mysqli_stmt_get_result($total_meals_stmt);
    $total_meals_data = mysqli_fetch_assoc($total_meals_result);
    $monthly_total_meals = $total_meals_data['total_meals'] ?? 0;

    // Get total expenses for the house for the month
    $expenses_sql = "SELECT COALESCE(SUM(amount), 0) as total_expenses 
                     FROM expenses 
                     WHERE house_id = ? 
                     AND MONTH(expense_date) = ? 
                     AND YEAR(expense_date) = ?";
    $expenses_stmt = mysqli_prepare($conn, $expenses_sql);
    mysqli_stmt_bind_param($expenses_stmt, "iii", $house_id, $month, $year);
    mysqli_stmt_execute($expenses_stmt);
    $expenses_result = mysqli_stmt_get_result($expenses_stmt);
    $expenses_data = mysqli_fetch_assoc($expenses_result);
    $total_expenses = $expenses_data['total_expenses'] ?? 0;

    // Get member's total meals for the month
    $member_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as member_meals 
                         FROM meals 
                         WHERE member_id = ? 
                         AND house_id = ?
                         AND MONTH(meal_date) = ? 
                         AND YEAR(meal_date) = ?";
    $member_meals_stmt = mysqli_prepare($conn, $member_meals_sql);
    mysqli_stmt_bind_param($member_meals_stmt, "iiii", $member_id, $house_id, $month, $year);
    mysqli_stmt_execute($member_meals_stmt);
    $member_meals_result = mysqli_stmt_get_result($member_meals_stmt);
    $member_meals_data = mysqli_fetch_assoc($member_meals_result);
    $member_total_meals = $member_meals_data['member_meals'] ?? 0;

    // Get member's total deposits for the month
    $member_deposits_sql = "SELECT COALESCE(SUM(amount), 0) as member_deposits 
                            FROM deposits 
                            WHERE member_id = ? 
                            AND house_id = ?
                            AND MONTH(deposit_date) = ? 
                            AND YEAR(deposit_date) = ?";
    $member_deposits_stmt = mysqli_prepare($conn, $member_deposits_sql);
    mysqli_stmt_bind_param($member_deposits_stmt, "iiii", $member_id, $house_id, $month, $year);
    mysqli_stmt_execute($member_deposits_stmt);
    $member_deposits_result = mysqli_stmt_get_result($member_deposits_stmt);
    $member_deposits_data = mysqli_fetch_assoc($member_deposits_result);
    $member_total_deposits = $member_deposits_data['member_deposits'] ?? 0;

    // Calculate meal rate (avoid division by zero)
    $meal_rate = 0;
    if ($monthly_total_meals > 0) {
        $meal_rate = $total_expenses / $monthly_total_meals;
    }

    // Calculate member's cost
    $member_cost = $member_total_meals * $meal_rate;

    // Calculate balance
    $balance = $member_total_deposits - $member_cost;

    // Build member report array
    $member_report = [
        'total_meals' => $member_total_meals,
        'total_deposits' => $member_total_deposits,
        'meal_rate' => $meal_rate,
        'member_cost' => $member_cost,
        'balance' => $balance,
        'house_total_meals' => $monthly_total_meals,
        'house_total_expenses' => $total_expenses
    ];

    // Get detailed meal history
    $meals_sql = "SELECT * FROM meals 
                  WHERE member_id = ? 
                  AND house_id = ?
                  AND MONTH(meal_date) = ? 
                  AND YEAR(meal_date) = ? 
                  ORDER BY meal_date DESC";
    $meals_stmt = mysqli_prepare($conn, $meals_sql);
    mysqli_stmt_bind_param($meals_stmt, "iiii", $member_id, $house_id, $month, $year);
    mysqli_stmt_execute($meals_stmt);
    $meals_result = mysqli_stmt_get_result($meals_stmt);
    $meals = mysqli_fetch_all($meals_result, MYSQLI_ASSOC);

    // Get detailed deposit history
    $deposits_sql = "SELECT * FROM deposits 
                     WHERE member_id = ? 
                     AND house_id = ?
                     AND MONTH(deposit_date) = ? 
                     AND YEAR(deposit_date) = ? 
                     ORDER BY deposit_date DESC";
    $deposits_stmt = mysqli_prepare($conn, $deposits_sql);
    mysqli_stmt_bind_param($deposits_stmt, "iiii", $member_id, $house_id, $month, $year);
    mysqli_stmt_execute($deposits_stmt);
    $deposits_result = mysqli_stmt_get_result($deposits_stmt);
    $deposits = mysqli_fetch_all($deposits_result, MYSQLI_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = "Error generating report: " . $e->getMessage();
}

// Now include the header AFTER all processing
require_once '../includes/header.php';
?>
<div class="row">
    <div class="col-12">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Member Report
                        </h1>
                        <div class="text-muted">
                            <span class="badge bg-primary me-2">House: <?php echo htmlspecialchars($member['house_name']); ?></span>
                            <span class="me-2"><?php echo htmlspecialchars($member['name']); ?></span>
                            <span>- <?php echo $month_name . ' ' . $year; ?></span>
                        </div>
                    </div>
                    <div>
                        <!-- Month/Year Selector -->
                        <div class="btn-group me-2" role="group">
                            <form method="GET" class="d-flex" action="">
                                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                                <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="year" class="form-select form-select-sm ms-2" onchange="this.form.submit()">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </form>
                        </div>
                        
                        <a href="members.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                        <a href="?member_id=<?php echo $member_id; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>&format=pdf" 
                           class="btn btn-danger ms-2" target="_blank">
                            <i class="fas fa-file-pdf me-2"></i>Download PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Member Info -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Member Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Member ID:</strong> #<?php echo $member['member_id']; ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($member['name']); ?></p>
                                <p><strong>Phone:</strong> <?php echo !empty($member['phone']) ? $member['phone'] : 'Not provided'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?php echo !empty($member['email']) ? $member['email'] : 'Not provided'; ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $member['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($member['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($member['join_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Monthly Summary</h5>
                    </div>
                    <div class="card-body text-center">
                        <?php if ($member_report): ?>
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Total Meals</h6>
                            <h2 class="text-success"><?php echo number_format($member_report['total_meals'], 2); ?></h2>
                        </div>
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Total Deposits</h6>
                            <h2 class="text-primary"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></h2>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Balance</h6>
                            <h2 class="<?php echo $member_report['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $functions->formatCurrency($member_report['balance']); ?>
                            </h2>
                            <span class="badge bg-<?php echo $member_report['balance'] >= 0 ? 'success' : 'danger'; ?>">
                                <?php echo $member_report['balance'] >= 0 ? 'CREDIT' : 'DUE'; ?>
                            </span>
                        </div>
                        <?php else: ?>
                        <p class="text-muted py-3">No data available for this month</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($member_report): ?>
        <!-- Financial Breakdown -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Financial Breakdown</h5>
                        <small class="text-muted"><?php echo $month_name . ' ' . $year; ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="alert alert-info">
                                    <h6>Meal Rate</h6>
                                    <h3 class="text-info"><?php echo $functions->formatCurrency($member_report['meal_rate']); ?></h3>
                                    <small class="text-muted">
                                        Based on <?php echo $functions->formatCurrency($member_report['house_total_expenses']); ?> expenses ÷ <?php echo number_format($member_report['house_total_meals'], 2); ?> total house meals
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-warning">
                                    <h6>Your Meals Cost</h6>
                                    <h3 class="text-warning"><?php echo $functions->formatCurrency($member_report['member_cost']); ?></h3>
                                    <small class="text-muted">
                                        <?php echo number_format($member_report['total_meals'], 2); ?> meals × <?php echo $functions->formatCurrency($member_report['meal_rate']); ?> per meal
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-success">
                                    <h6>Your Deposits</h6>
                                    <h3 class="text-success"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></h3>
                                    <small class="text-muted">
                                        Total deposited this month
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-<?php echo $member_report['balance'] >= 0 ? 'primary' : 'danger'; ?>">
                                    <h6>Net Balance</h6>
                                    <h3><?php echo $functions->formatCurrency($member_report['balance']); ?></h3>
                                    <small>
                                        <?php echo $member_report['balance'] >= 0 ? 'Deposits exceed costs' : 'Costs exceed deposits'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Calculation Formula -->
                        <div class="mt-4 pt-3 border-top">
                            <h6><i class="fas fa-calculator me-2"></i>Calculation Formula:</h6>
                            <code class="d-block p-3 bg-light rounded">
                                (<span class="text-success"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></span> Deposits) 
                                - 
                                (<span class="text-warning"><?php echo number_format($member_report['total_meals'], 2); ?></span> Meals × 
                                <span class="text-info"><?php echo $functions->formatCurrency($member_report['meal_rate']); ?></span> Rate) 
                                = 
                                <span class="<?php echo $member_report['balance'] >= 0 ? 'text-primary' : 'text-danger'; ?>">
                                    <?php echo $functions->formatCurrency($member_report['balance']); ?>
                                </span>
                            </code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- History Tables -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-utensils me-2"></i>Meal History</h5>
                        <div>
                            <span class="badge bg-secondary"><?php echo count($meals); ?> days</span>
                            <span class="badge bg-success ms-1"><?php echo number_format($member_report['total_meals'], 2); ?> meals</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($meals)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th class="text-end">Meals</th>
                                        <th class="text-end">Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($meals as $meal): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($meal['meal_date'])); ?></td>
                                        <td><?php echo date('D', strtotime($meal['meal_date'])); ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-secondary"><?php echo number_format($meal['meal_count'], 2); ?></span>
                                        </td>
                                        <td class="text-end text-warning">
                                            <?php echo $functions->formatCurrency($meal['meal_count'] * $member_report['meal_rate']); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                        <td class="text-end text-warning">
                                            <strong><?php echo $functions->formatCurrency($member_report['member_cost']); ?></strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-utensils fa-2x mb-3"></i><br>
                            No meals recorded for <?php echo $month_name . ' ' . $year; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Deposit History</h5>
                        <div>
                            <span class="badge bg-success"><?php echo count($deposits); ?> deposits</span>
                            <span class="badge bg-primary ms-1"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($deposits)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deposits as $deposit): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($deposit['deposit_date'])); ?></td>
                                        <td class="text-end text-success">
                                            <?php echo $functions->formatCurrency($deposit['amount']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($deposit['description'] ?? 'No description'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light">
                                        <td class="text-end" colspan="2"><strong>Total:</strong></td>
                                        <td class="text-success">
                                            <strong><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-money-bill-wave fa-2x mb-3"></i><br>
                            No deposits recorded for <?php echo $month_name . ' ' . $year; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- House Summary -->
        <?php if ($member_report): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-house-user me-2"></i>House Summary for <?php echo $month_name . ' ' . $year; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <h6 class="text-muted">Total House Meals</h6>
                                    <h3 class="text-success"><?php echo number_format($member_report['house_total_meals'], 2); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <h6 class="text-muted">Total House Expenses</h6>
                                    <h3 class="text-warning"><?php echo $functions->formatCurrency($member_report['house_total_expenses']); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <h6 class="text-muted">Average Meal Rate</h6>
                                    <h3 class="text-info"><?php echo $functions->formatCurrency($member_report['meal_rate']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <?php 
                            $member_percentage = 0;
                            if ($member_report['house_total_meals'] > 0) {
                                $member_percentage = ($member_report['total_meals'] / $member_report['house_total_meals']) * 100;
                            }
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $member_percentage; ?>%" 
                                 aria-valuenow="<?php echo $member_percentage; ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                                <?php echo htmlspecialchars($member['name']); ?>: <?php echo number_format($member_percentage, 1); ?>% of total meals
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <?php echo htmlspecialchars($member['name']); ?> consumed <?php echo number_format($member_report['total_meals'], 2); ?> out of <?php echo number_format($member_report['house_total_meals'], 2); ?> total house meals
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <p class="text-muted mb-2">
                            <i class="fas fa-file-alt me-2"></i>
                            Report generated on <?php echo date('F j, Y'); ?> at <?php echo date('h:i A'); ?>
                        </p>
                        <p class="text-muted mb-0">
                            <small>© <?php echo date('Y'); ?> Meal Management System | House: <?php echo htmlspecialchars($member['house_name']); ?></small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>


// Update form submission for month/year selectors
document.querySelectorAll('select[name="month"], select[name="year"]').forEach(select => {
    select.addEventListener('change', function() {
        this.form.submit();
    });
});

// Show loading indicator during report generation
document.addEventListener('DOMContentLoaded', function() {
    // Remove any existing loading indicators
    const loadingElements = document.querySelectorAll('.report-loading');
    loadingElements.forEach(el => el.remove());
    
    // DataTables initialization has been REMOVED to fix the disappearing content issue
    // The tables work fine without DataTables and it was causing conflicts
});
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>