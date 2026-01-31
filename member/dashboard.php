<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('member');

$page_title = "Member Dashboard";

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$member_id = $_SESSION['member_id'];
$house_id = $_SESSION['house_id'];

// Get current month and year for filtering
$current_month = date('m');
$current_year = date('Y');
$selected_month = $_GET['month'] ?? $current_month;
$selected_year = $_GET['year'] ?? $current_year;

// Validate inputs
$selected_month = intval($selected_month);
$selected_year = intval($selected_year);

if ($selected_month < 1 || $selected_month > 12) $selected_month = $current_month;
if ($selected_year < 2000 || $selected_year > 2100) $selected_year = $current_year;

$month_name = date('F', mktime(0, 0, 0, $selected_month, 1));
$month_year = date('Y-m', mktime(0, 0, 0, $selected_month, 1, $selected_year));

// Get member information
$sql = "SELECT m.* FROM members m WHERE m.member_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $member_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);

// Get house information
$house_sql = "SELECT house_name, house_code, description FROM houses WHERE house_id = ?";
$house_stmt = mysqli_prepare($conn, $house_sql);
mysqli_stmt_bind_param($house_stmt, "i", $house_id);
mysqli_stmt_execute($house_stmt);
$house_result = mysqli_stmt_get_result($house_stmt);
$house = mysqli_fetch_assoc($house_result);

// Get detailed report data for selected month
$member_report = null;
$meals = [];
$deposits = [];
$total_expenses = 0;
$monthly_total_meals = 0;

try {
    // Get total meals for all members in the house for the month
    $total_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as total_meals 
                        FROM meals 
                        WHERE house_id = ? 
                        AND MONTH(meal_date) = ? 
                        AND YEAR(meal_date) = ?";
    $total_meals_stmt = mysqli_prepare($conn, $total_meals_sql);
    mysqli_stmt_bind_param($total_meals_stmt, "iii", $house_id, $selected_month, $selected_year);
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
    mysqli_stmt_bind_param($expenses_stmt, "iii", $house_id, $selected_month, $selected_year);
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
    mysqli_stmt_bind_param($member_meals_stmt, "iiii", $member_id, $house_id, $selected_month, $selected_year);
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
    mysqli_stmt_bind_param($member_deposits_stmt, "iiii", $member_id, $house_id, $selected_month, $selected_year);
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
    mysqli_stmt_bind_param($meals_stmt, "iiii", $member_id, $house_id, $selected_month, $selected_year);
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
    mysqli_stmt_bind_param($deposits_stmt, "iiii", $member_id, $house_id, $selected_month, $selected_year);
    mysqli_stmt_execute($deposits_stmt);
    $deposits_result = mysqli_stmt_get_result($deposits_stmt);
    $deposits = mysqli_fetch_all($deposits_result, MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Get recent meals (last 7 days)
$recent_meals_sql = "SELECT meal_date, meal_count FROM meals 
                     WHERE member_id = ? AND meal_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     ORDER BY meal_date DESC";
$recent_meals_stmt = mysqli_prepare($conn, $recent_meals_sql);
mysqli_stmt_bind_param($recent_meals_stmt, "i", $member_id);
mysqli_stmt_execute($recent_meals_stmt);
$recent_meals_result = mysqli_stmt_get_result($recent_meals_stmt);
$recent_meals = mysqli_fetch_all($recent_meals_result, MYSQLI_ASSOC);

// Get recent deposits (last 5)
$recent_deposits_sql = "SELECT amount, deposit_date, description FROM deposits 
                        WHERE member_id = ? 
                        ORDER BY deposit_date DESC LIMIT 5";
$recent_deposits_stmt = mysqli_prepare($conn, $recent_deposits_sql);
mysqli_stmt_bind_param($recent_deposits_stmt, "i", $member_id);
mysqli_stmt_execute($recent_deposits_stmt);
$recent_deposits_result = mysqli_stmt_get_result($recent_deposits_stmt);
$recent_deposits = mysqli_fetch_all($recent_deposits_result, MYSQLI_ASSOC);

// Get all available months for dropdown
$available_months_sql = "SELECT DISTINCT DATE_FORMAT(meal_date, '%Y-%m') as month_year 
                         FROM meals WHERE member_id = ? 
                         UNION 
                         SELECT DISTINCT DATE_FORMAT(deposit_date, '%Y-%m') as month_year 
                         FROM deposits WHERE member_id = ?
                         ORDER BY month_year DESC";
$available_months_stmt = mysqli_prepare($conn, $available_months_sql);
mysqli_stmt_bind_param($available_months_stmt, "ii", $member_id, $member_id);
mysqli_stmt_execute($available_months_stmt);
$available_months_result = mysqli_stmt_get_result($available_months_stmt);
$available_months = mysqli_fetch_all($available_months_result, MYSQLI_ASSOC);
?>
<div class="row">
    <div class="col-12">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-tachometer-alt me-2"></i>Member Dashboard
                        </h1>
                        <div class="text-muted">
                            <span class="badge bg-primary me-2"><?php echo htmlspecialchars($house['house_name']); ?></span>
                            <span class="me-2"><?php echo htmlspecialchars($member['name']); ?></span>
                            <span>- <?php echo $month_name . ' ' . $selected_year; ?></span>
                        </div>
                    </div>
                    <div>
                        <!-- Month/Year Selector -->
                        <div class="btn-group me-2" role="group">
                            <form method="GET" class="d-flex" action="">
                                <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="year" class="form-select form-select-sm ms-2" onchange="this.form.submit()">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </form>
                        </div>
                        <button onclick="window.print()" class="btn btn-primary ms-2">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Member Info -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Member ID:</strong> #<?php echo $member['member_id']; ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($member['name']); ?></p>
                                <p><strong>Phone:</strong> <?php echo !empty($member['phone']) ? htmlspecialchars($member['phone']) : 'Not provided'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?php echo !empty($member['email']) ? htmlspecialchars($member['email']) : 'Not provided'; ?></p>
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
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Quick Summary</h5>
                        <small class="text-muted"><?php echo $month_name . ' ' . $selected_year; ?></small>
                    </div>
                    <div class="card-body text-center">
                        <?php if ($member_report): ?>
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">My Meals</h6>
                            <h2 class="text-success"><?php echo number_format($member_report['total_meals'], 2); ?></h2>
                        </div>
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">My Deposits</h6>
                            <h2 class="text-primary"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></h2>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">My Balance</h6>
                            <h2 class="<?php echo $member_report['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $functions->formatCurrency($member_report['balance']); ?>
                            </h2>
                            <span class="badge bg-<?php echo $member_report['balance'] >= 0 ? 'success' : 'danger'; ?>">
                                <?php echo $member_report['balance'] >= 0 ? 'CREDIT' : 'DUE'; ?>
                            </span>
                        </div>
                        <?php else: ?>
                        <p class="text-muted py-3">No data available for <?php echo $month_name . ' ' . $selected_year; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($member_report && ($member_report['total_meals'] > 0 || $member_report['total_deposits'] > 0)): ?>
        <!-- Financial Breakdown -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Financial Breakdown</h5>
                        <small class="text-muted"><?php echo $month_name . ' ' . $selected_year; ?></small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="alert alert-info">
                                    <h6>Meal Rate</h6>
                                    <h3 class="text-info"><?php echo $functions->formatCurrency($member_report['meal_rate']); ?></h3>
                                    <small class="text-muted">
                                        Based on total house expenses ÷ total house meals
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-warning">
                                    <h6>My Meals Cost</h6>
                                    <h3 class="text-warning"><?php echo $functions->formatCurrency($member_report['member_cost']); ?></h3>
                                    <small class="text-muted">
                                        <?php echo number_format($member_report['total_meals'], 2); ?> meals × <?php echo $functions->formatCurrency($member_report['meal_rate']); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-success">
                                    <h6>My Deposits</h6>
                                    <h3 class="text-success"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></h3>
                                    <small class="text-muted">
                                        Total deposited this month
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="alert alert-<?php echo $member_report['balance'] >= 0 ? 'primary' : 'danger'; ?>">
                                    <h6>My Balance</h6>
                                    <h3><?php echo $functions->formatCurrency($member_report['balance']); ?></h3>
                                    <small>
                                        <?php echo $member_report['balance'] >= 0 ? 'Credit balance' : 'Due amount'; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Calculation Formula -->
                        <div class="mt-4 pt-3 border-top">
                            <h6><i class="fas fa-calculator me-2"></i>Calculation:</h6>
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
                        <h5 class="mb-0"><i class="fas fa-utensils me-2"></i>My Meals</h5>
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
                                        <td><?php echo date('M d', strtotime($meal['meal_date'])); ?></td>
                                        <td><?php echo date('D', strtotime($meal['meal_date'])); ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-secondary"><?php echo number_format($meal['meal_count'], 2); ?></span>
                                        </td>
                                        <td class="text-end text-warning">
                                            <?php echo $functions->formatCurrency($meal['meal_count'] * $member_report['meal_rate']); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total Cost:</strong></td>
                                        <td class="text-end text-warning">
                                            <strong><?php echo $functions->formatCurrency($member_report['member_cost']); ?></strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-utensils fa-2x mb-3"></i><br>
                            No meals recorded for <?php echo $month_name . ' ' . $selected_year; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>My Deposits</h5>
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
                                        <td><?php echo date('M d', strtotime($deposit['deposit_date'])); ?></td>
                                        <td class="text-end text-success">
                                            <?php echo $functions->formatCurrency($deposit['amount']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($deposit['description'] ?? 'No description'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td class="text-end"><strong>Total:</strong></td>
                                        <td class="text-end text-success">
                                            <strong><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></strong>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-money-bill-wave fa-2x mb-3"></i><br>
                            No deposits recorded for <?php echo $month_name . ' ' . $selected_year; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Activity -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Meals (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_meals)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th class="text-end">Meals</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_meals as $meal): ?>
                                    <tr>
                                        <td><?php echo date('M d', strtotime($meal['meal_date'])); ?></td>
                                        <td><?php echo date('D', strtotime($meal['meal_date'])); ?></td>
                                        <td class="text-end">
                                            <span class="badge bg-<?php echo $meal['meal_count'] > 0 ? 'success' : 'secondary'; ?>">
                                                <?php echo number_format($meal['meal_count'], 2); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-utensils fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No meal records found for the last 7 days</p>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Recent Deposits</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_deposits)): ?>
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
                                    <?php foreach ($recent_deposits as $deposit): ?>
                                    <tr>
                                        <td><?php echo date('M d', strtotime($deposit['deposit_date'])); ?></td>
                                        <td class="text-end text-success">
                                            <?php echo $functions->formatCurrency($deposit['amount']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($deposit['description'] ?? 'Deposit'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-wallet fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No recent deposit records</p>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- House Summary -->
        <?php if ($member_report && $member_report['house_total_meals'] > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-house-user me-2"></i>House Summary - <?php echo $month_name . ' ' . $selected_year; ?></h5>
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
                                You: <?php echo number_format($member_percentage, 1); ?>% of total meals
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            You consumed <?php echo number_format($member_report['total_meals'], 2); ?> out of <?php echo number_format($member_report['house_total_meals'], 2); ?> total house meals
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Available Reports -->
        <?php if (!empty($available_months)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-archive me-2"></i>View Other Months</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($available_months as $available): 
                                list($year, $month) = explode('-', $available['month_year']);
                            ?>
                            <div class="col-md-2 mb-2">
                                <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                                   class="btn btn-outline-primary w-100 text-start <?php echo ($month == $selected_month && $year == $selected_year) ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <?php echo date('M Y', strtotime($available['month_year'] . '-01')); ?>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="profile.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                    <i class="fas fa-user-edit fa-2x mb-2"></i>
                                    <span>Update Profile</span>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="report.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                    <i class="fas fa-file-alt fa-2x mb-2"></i>
                                    <span>View Full Report</span>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="../auth/change_password.php" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                    <i class="fas fa-key fa-2x mb-2"></i>
                                    <span>Change Password</span>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="../auth/logout.php" class="btn btn-outline-danger w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                    <i class="fas fa-sign-out-alt fa-2x mb-2"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .navbar-top, .btn, .form-select, form, .no-print {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .content-wrapper {
        padding: 0 !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    .card-header {
        background: white !important;
        color: black !important;
        border-bottom: 2px solid #dee2e6 !important;
    }
    .row {
        display: block !important;
    }
    .col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-md-2 {
        width: 100% !important;
        margin-bottom: 20px !important;
    }
    .btn-group {
        display: none !important;
    }
    .d-flex {
        display: block !important;
    }
    .text-end {
        text-align: right !important;
    }
}
</style>

<script>
// Print function
function printReport() {
    window.print();
}

// Update form submission for month/year selectors
document.querySelectorAll('select[name="month"], select[name="year"]').forEach(select => {
    select.addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php
// Close statements
if (isset($stmt)) mysqli_stmt_close($stmt);
if (isset($house_stmt)) mysqli_stmt_close($house_stmt);
if (isset($total_meals_stmt)) mysqli_stmt_close($total_meals_stmt);
if (isset($expenses_stmt)) mysqli_stmt_close($expenses_stmt);
if (isset($member_meals_stmt)) mysqli_stmt_close($member_meals_stmt);
if (isset($member_deposits_stmt)) mysqli_stmt_close($member_deposits_stmt);
if (isset($meals_stmt)) mysqli_stmt_close($meals_stmt);
if (isset($deposits_stmt)) mysqli_stmt_close($deposits_stmt);
if (isset($recent_meals_stmt)) mysqli_stmt_close($recent_meals_stmt);
if (isset($recent_deposits_stmt)) mysqli_stmt_close($recent_deposits_stmt);
if (isset($available_months_stmt)) mysqli_stmt_close($available_months_stmt);
mysqli_close($conn);

require_once '../includes/footer.php';
?>