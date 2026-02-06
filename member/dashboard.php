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
$house_sql = "SELECT house_name, house_code FROM houses WHERE house_id = ?";
$house_stmt = mysqli_prepare($conn, $house_sql);
mysqli_stmt_bind_param($house_stmt, "i", $house_id);
mysqli_stmt_execute($house_stmt);
$house_result = mysqli_stmt_get_result($house_stmt);
$house = mysqli_fetch_assoc($house_result);

// Get detailed report data for selected month
$member_report = [
    'total_meals' => 0,
    'total_deposits' => 0,
    'meal_rate' => 0,
    'member_cost' => 0,
    'balance' => 0,
    'house_total_meals' => 0,
    'house_total_expenses' => 0
];

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
$house_total_meals = $total_meals_data['total_meals'] ?? 0;

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
$house_total_expenses = $expenses_data['total_expenses'] ?? 0;

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

// Calculate meal rate
$meal_rate = 0;
if ($house_total_meals > 0) {
    $meal_rate = $house_total_expenses / $house_total_meals;
}

// Calculate member's cost and balance
$member_cost = $member_total_meals * $meal_rate;
$balance = $member_total_deposits - $member_cost;

$member_report = [
    'total_meals' => $member_total_meals,
    'total_deposits' => $member_total_deposits,
    'meal_rate' => $meal_rate,
    'member_cost' => $member_cost,
    'balance' => $balance,
    'house_total_meals' => $house_total_meals,
    'house_total_expenses' => $house_total_expenses
];

// Get ALL deposits for the member
$all_deposits = [];
$total_all_deposits = 0;
$deposits_sql = "SELECT * FROM deposits 
                 WHERE member_id = ? 
                 AND house_id = ?
                 ORDER BY deposit_date DESC";
$deposits_stmt = mysqli_prepare($conn, $deposits_sql);
mysqli_stmt_bind_param($deposits_stmt, "ii", $member_id, $house_id);
mysqli_stmt_execute($deposits_stmt);
$deposits_result = mysqli_stmt_get_result($deposits_stmt);
$all_deposits = mysqli_fetch_all($deposits_result, MYSQLI_ASSOC);

// Calculate total of all deposits
foreach ($all_deposits as $deposit) {
    $total_all_deposits += $deposit['amount'];
}

// Get ALL expenses for the house (complete expense list)
$all_expenses = [];
$total_all_expenses = 0;
$expenses_list_sql = "SELECT e.*, u.username as added_by 
                      FROM expenses e 
                      LEFT JOIN users u ON e.created_by = u.user_id
                      WHERE e.house_id = ?
                      ORDER BY e.expense_date DESC";
$expenses_list_stmt = mysqli_prepare($conn, $expenses_list_sql);
mysqli_stmt_bind_param($expenses_list_stmt, "i", $house_id);
mysqli_stmt_execute($expenses_list_stmt);
$expenses_list_result = mysqli_stmt_get_result($expenses_list_stmt);
$all_expenses = mysqli_fetch_all($expenses_list_result, MYSQLI_ASSOC);

// Calculate total of all expenses
foreach ($all_expenses as $expense) {
    $total_all_expenses += $expense['amount'];
}

// Get expenses for current month only (for summary)
$month_expenses = array_filter($all_expenses, function($expense) use ($selected_month, $selected_year) {
    $expense_month = date('m', strtotime($expense['expense_date']));
    $expense_year = date('Y', strtotime($expense['expense_date']));
    return ($expense_month == $selected_month && $expense_year == $selected_year);
});

// Get category totals for all expenses
$category_totals = [];
$category_names = [
    'Rice' => 'Rice',
    'Fish' => 'Fish',
    'Meat' => 'Meat',
    'Vegetables' => 'Vegetables',
    'Spices' => 'Spices',
    'Oil' => 'Oil',
    'Food' => 'Food',
    'Others' => 'Others'
];

foreach ($all_expenses as $expense) {
    $category = $expense['category'];
    if (!isset($category_totals[$category])) {
        $category_totals[$category] = 0;
    }
    $category_totals[$category] += $expense['amount'];
}

// Get all available months for dropdown
$available_months_sql = "SELECT DISTINCT DATE_FORMAT(meal_date, '%Y-%m') as month_year 
                         FROM meals WHERE member_id = ? 
                         UNION 
                         SELECT DISTINCT DATE_FORMAT(deposit_date, '%Y-%m') as month_year 
                         FROM deposits WHERE member_id = ?
                         UNION
                         SELECT DISTINCT DATE_FORMAT(expense_date, '%Y-%m') as month_year 
                         FROM expenses WHERE house_id = ?
                         ORDER BY month_year DESC";
$available_months_stmt = mysqli_prepare($conn, $available_months_sql);
mysqli_stmt_bind_param($available_months_stmt, "iii", $member_id, $member_id, $house_id);
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
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card border-success shadow">
                    <div class="card-body text-center">
                        <div class="card-icon text-success mb-3">
                            <i class="fas fa-utensils fa-2x"></i>
                        </div>
                        <h3 class="card-title"><?php echo number_format($member_report['total_meals'], 2); ?></h3>
                        <p class="text-muted mb-0">My Meals</p>
                        <small class="text-muted"><?php echo $month_name . ' ' . $selected_year; ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card border-primary shadow">
                    <div class="card-body text-center">
                        <div class="card-icon text-primary mb-3">
                            <i class="fas fa-wallet fa-2x"></i>
                        </div>
                        <h3 class="card-title"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></h3>
                        <p class="text-muted mb-0">My Deposits</p>
                        <small class="text-muted"><?php echo $month_name . ' ' . $selected_year; ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card border-warning shadow">
                    <div class="card-body text-center">
                        <div class="card-icon text-warning mb-3">
                            <i class="fas fa-money-bill-wave fa-2x"></i>
                        </div>
                        <h3 class="card-title"><?php echo $functions->formatCurrency($member_report['house_total_expenses']); ?></h3>
                        <p class="text-muted mb-0">House Expenses</p>
                        <small class="text-muted"><?php echo $month_name . ' ' . $selected_year; ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card stat-card border-<?php echo $member_report['balance'] >= 0 ? 'success' : 'danger'; ?> shadow">
                    <div class="card-body text-center">
                        <div class="card-icon text-<?php echo $member_report['balance'] >= 0 ? 'success' : 'danger'; ?> mb-3">
                            <i class="fas fa-balance-scale fa-2x"></i>
                        </div>
                        <h3 class="card-title text-<?php echo $member_report['balance'] >= 0 ? 'success' : 'danger'; ?>">
                            <?php echo $functions->formatCurrency(abs($member_report['balance'])); ?>
                            <?php echo $member_report['balance'] >= 0 ? '' : '-'; ?>
                        </h3>
                        <p class="text-muted mb-0">My Balance</p>
                        <span class="badge bg-<?php echo $member_report['balance'] >= 0 ? 'success' : 'danger'; ?>">
                            <?php echo $member_report['balance'] >= 0 ? 'CREDIT' : 'DUE'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Member & Financial Info -->
        <div class="row mb-4">
            <div class="col-md-6">
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
                        <hr>
                        <div class="row">
                            <div class="col-12">
                                <p><strong>House:</strong> <?php echo htmlspecialchars($house['house_name']); ?></p>
                                <p><strong>House Code:</strong> <?php echo $house['house_code']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Financial Summary</h5>
                        <small class="text-muted"><?php echo $month_name . ' ' . $selected_year; ?></small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td width="60%">My Total Meals:</td>
                                    <td class="text-end">
                                        <span class="badge bg-secondary"><?php echo number_format($member_report['total_meals'], 2); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>House Total Expenses:</td>
                                    <td class="text-end text-warning"><?php echo $functions->formatCurrency($member_report['house_total_expenses']); ?></td>
                                </tr>
                                <tr>
                                    <td>Total House Meals:</td>
                                    <td class="text-end"><?php echo number_format($member_report['house_total_meals'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Calculated Meal Rate:</td>
                                    <td class="text-end text-info"><?php echo $functions->formatCurrency($member_report['meal_rate']); ?></td>
                                </tr>
                                <tr>
                                    <td>My Meal Cost:</td>
                                    <td class="text-end text-danger"><?php echo $functions->formatCurrency($member_report['member_cost']); ?></td>
                                </tr>
                                <tr>
                                    <td>My Deposits This Month:</td>
                                    <td class="text-end text-success"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></td>
                                </tr>
                                <tr class="table-light">
                                    <td><strong>My Balance:</strong></td>
                                    <td class="text-end">
                                        <strong class="<?php echo $member_report['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $functions->formatCurrency($member_report['balance']); ?>
                                        </strong>
                                        <span class="badge bg-<?php echo $member_report['balance'] >= 0 ? 'success' : 'danger'; ?> ms-2">
                                            <?php echo $member_report['balance'] >= 0 ? 'CREDIT' : 'DUE'; ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="alert alert-info mt-3 p-2">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Formula:</strong> Balance = Deposits - (My Meals × Meal Rate)<br>
                                <strong>Meal Rate:</strong> Total Expenses ÷ Total House Meals
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Complete Expense List -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>House Expense List</h5>
                        <div>
                            <span class="badge bg-warning"><?php echo count($all_expenses); ?> expenses</span>
                            <span class="badge bg-danger ms-1"><?php echo $functions->formatCurrency($total_all_expenses); ?> total</span>
                            <span class="badge bg-info ms-1"><?php echo count($month_expenses); ?> this month</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($all_expenses)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th class="text-end">Amount</th>
                                        <th>Description</th>
                                        <th>Added By</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $yearly_expense_totals = [];
                                    $current_year = date('Y');
                                    foreach ($all_expenses as $expense): 
                                        $expense_date = strtotime($expense['expense_date']);
                                        $expense_year = date('Y', $expense_date);
                                        $expense_month = date('F Y', $expense_date);
                                        
                                        // Track yearly totals
                                        if (!isset($yearly_expense_totals[$expense_year])) {
                                            $yearly_expense_totals[$expense_year] = 0;
                                        }
                                        $yearly_expense_totals[$expense_year] += $expense['amount'];
                                        
                                        $is_current_month = (date('Y-m', $expense_date) == $month_year);
                                        $category_bg = [
                                            'Rice' => 'bg-primary',
                                            'Fish' => 'bg-info',
                                            'Meat' => 'bg-danger',
                                            'Vegetables' => 'bg-success',
                                            'Spices' => 'bg-warning',
                                            'Oil' => 'bg-purple',
                                            'Food' => 'bg-orange',
                                            'Others' => 'bg-secondary'
                                        ];
                                        $category_color = $category_bg[$expense['category']] ?? 'bg-secondary';
                                    ?>
                                    <tr class="<?php echo $is_current_month ? 'table-active' : ''; ?>">
                                        <td>
                                            <?php echo date('M d, Y', $expense_date); ?>
                                            <br>
                                            <small class="text-muted"><?php echo date('D', $expense_date); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $category_color; ?>">
                                                <?php echo $expense['category']; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <h6 class="mb-0 text-danger">
                                                <?php echo $functions->formatCurrency($expense['amount']); ?>
                                            </h6>
                                        </td>
                                        <td>
                                            <?php echo !empty($expense['description']) ? htmlspecialchars($expense['description']) : '<em class="text-muted">No description</em>'; ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($expense['added_by']) ? htmlspecialchars($expense['added_by']) : '<em class="text-muted">System</em>'; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_current_month): ?>
                                            <span class="badge bg-success">Current</span>
                                            <?php elseif ($expense_year == $current_year): ?>
                                            <span class="badge bg-primary">This Year</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo $expense_year; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Monthly Total (<?php echo $month_name; ?>):</strong></td>
                                        <td class="text-end">
                                            <strong class="text-danger"><?php echo $functions->formatCurrency($member_report['house_total_expenses']); ?></strong>
                                        </td>
                                        <td colspan="3"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>All Time Total:</strong></td>
                                        <td class="text-end">
                                            <strong class="text-danger"><?php echo $functions->formatCurrency($total_all_expenses); ?></strong>
                                        </td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Expense Summary & Categories -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Expense Categories</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Category</th>
                                                        <th class="text-end">Total Amount</th>
                                                        <th>Percentage</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($category_totals as $category => $total): 
                                                        $percentage = ($total_all_expenses > 0) ? ($total / $total_all_expenses) * 100 : 0;
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge <?php echo $category_bg[$category] ?? 'bg-secondary'; ?>">
                                                                <?php echo $category; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end text-danger"><?php echo $functions->formatCurrency($total); ?></td>
                                                        <td>
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar bg-danger" role="progressbar" 
                                                                     style="width: <?php echo $percentage; ?>%" 
                                                                     aria-valuenow="<?php echo $percentage; ?>" 
                                                                     aria-valuemin="0" aria-valuemax="100">
                                                                    <?php echo number_format($percentage, 1); ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Expense Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6 mb-3">
                                                <div class="p-3 border rounded">
                                                    <h6 class="text-muted mb-2">This Month</h6>
                                                    <h3 class="text-danger"><?php echo $functions->formatCurrency($member_report['house_total_expenses']); ?></h3>
                                                    <small class="text-muted"><?php echo $month_name . ' ' . $selected_year; ?></small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="p-3 border rounded">
                                                    <h6 class="text-muted mb-2">All Time</h6>
                                                    <h3 class="text-danger"><?php echo $functions->formatCurrency($total_all_expenses); ?></h3>
                                                    <small class="text-muted">Total expenses</small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="p-3 border rounded">
                                                    <h6 class="text-muted mb-2">Expense Count</h6>
                                                    <h3 class="text-info"><?php echo count($all_expenses); ?></h3>
                                                    <small class="text-muted">Total records</small>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="p-3 border rounded">
                                                    <h6 class="text-muted mb-2">Average per Month</h6>
                                                    <h3 class="text-warning">
                                                        <?php 
                                                        $months_count = count(array_unique(array_map(function($e) {
                                                            return date('Y-m', strtotime($e['expense_date']));
                                                        }, $all_expenses)));
                                                        $average_monthly = $months_count > 0 ? $total_all_expenses / $months_count : 0;
                                                        echo $functions->formatCurrency($average_monthly);
                                                        ?>
                                                    </h3>
                                                    <small class="text-muted">Based on <?php echo $months_count; ?> months</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-receipt fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">No Expense Records Found</h4>
                            <p class="text-muted mb-4">No expenses have been recorded for your house yet.</p>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                House expenses will appear here once they are recorded by your house manager.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Complete Deposit List -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>My Complete Deposit History</h5>
                        <div>
                            <span class="badge bg-secondary"><?php echo count($all_deposits); ?> total deposits</span>
                            <span class="badge bg-primary ms-1"><?php echo $functions->formatCurrency($total_all_deposits); ?> total</span>
                            <span class="badge bg-success ms-1"><?php echo count(array_filter($all_deposits, function($d) use ($selected_month, $selected_year) {
                                return date('m', strtotime($d['deposit_date'])) == $selected_month && 
                                       date('Y', strtotime($d['deposit_date'])) == $selected_year;
                            })); ?> this month</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($all_deposits)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Month/Year</th>
                                        <th class="text-end">Amount</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $yearly_deposit_totals = [];
                                    $current_year = date('Y');
                                    foreach ($all_deposits as $deposit): 
                                        $deposit_date = strtotime($deposit['deposit_date']);
                                        $deposit_year = date('Y', $deposit_date);
                                        $deposit_month = date('F Y', $deposit_date);
                                        
                                        // Track yearly totals
                                        if (!isset($yearly_deposit_totals[$deposit_year])) {
                                            $yearly_deposit_totals[$deposit_year] = 0;
                                        }
                                        $yearly_deposit_totals[$deposit_year] += $deposit['amount'];
                                        
                                        $is_current_month = (date('Y-m', $deposit_date) == $month_year);
                                    ?>
                                    <tr class="<?php echo $is_current_month ? 'table-active' : ''; ?>">
                                        <td>
                                            <?php echo date('M d, Y', $deposit_date); ?>
                                            <br>
                                            <small class="text-muted"><?php echo date('D', $deposit_date); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $deposit_month; ?></span>
                                        </td>
                                        <td class="text-end">
                                            <h6 class="mb-0 text-success">
                                                <?php echo $functions->formatCurrency($deposit['amount']); ?>
                                            </h6>
                                        </td>
                                        <td>
                                            <?php echo !empty($deposit['description']) ? htmlspecialchars($deposit['description']) : '<em class="text-muted">No description</em>'; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_current_month): ?>
                                            <span class="badge bg-success">Current</span>
                                            <?php elseif ($deposit_year == $current_year): ?>
                                            <span class="badge bg-primary">This Year</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo $deposit_year; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Monthly Total (<?php echo $month_name; ?>):</strong></td>
                                        <td class="text-end">
                                            <strong class="text-primary"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></strong>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>All Time Total:</strong></td>
                                        <td class="text-end">
                                            <strong class="text-success"><?php echo $functions->formatCurrency($total_all_deposits); ?></strong>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Deposit Summary -->
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">This Month</h6>
                                        <h3 class="text-primary"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></h3>
                                        <small class="text-muted"><?php echo $month_name . ' ' . $selected_year; ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Total Deposits</h6>
                                        <h3 class="text-success"><?php echo $functions->formatCurrency($total_all_deposits); ?></h3>
                                        <small class="text-muted">All time deposits</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Deposit Count</h6>
                                        <h3 class="text-info"><?php echo count($all_deposits); ?></h3>
                                        <small class="text-muted">Total transactions</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Yearly Deposit Breakdown -->
                        <?php if (!empty($yearly_deposit_totals)): ?>
                        <div class="mt-4">
                            <h6><i class="fas fa-chart-bar me-2"></i>Yearly Deposit Summary</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Year</th>
                                            <th>Total Deposits</th>
                                            <th>Number of Deposits</th>
                                            <th>Average per Deposit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        krsort($yearly_deposit_totals); // Sort years descending
                                        foreach ($yearly_deposit_totals as $year => $total): 
                                            $year_deposits = array_filter($all_deposits, function($d) use ($year) {
                                                return date('Y', strtotime($d['deposit_date'])) == $year;
                                            });
                                            $deposit_count = count($year_deposits);
                                            $average = $deposit_count > 0 ? $total / $deposit_count : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo $year; ?></strong></td>
                                            <td class="text-success"><?php echo $functions->formatCurrency($total); ?></td>
                                            <td><span class="badge bg-info"><?php echo $deposit_count; ?> deposits</span></td>
                                            <td class="text-primary"><?php echo $functions->formatCurrency($average); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-money-bill-wave fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">No Deposit Records Found</h4>
                            <p class="text-muted mb-4">You haven't made any deposits yet.</p>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Your deposits will appear here once they are recorded by your house manager.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- House Summary & Quick Actions -->
        <div class="row">
            <div class="col-md-8">
                <?php if ($member_report['house_total_meals'] > 0): ?>
                <div class="card shadow mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-house-user me-2"></i>House Summary - <?php echo $month_name . ' ' . $selected_year; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2">Total House Meals</h6>
                                    <h3 class="text-success"><?php echo number_format($member_report['house_total_meals'], 2); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2">Total House Expenses</h6>
                                    <h3 class="text-warning"><?php echo $functions->formatCurrency($member_report['house_total_expenses']); ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2">Average Meal Rate</h6>
                                    <h3 class="text-info"><?php echo $functions->formatCurrency($member_report['meal_rate']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 25px;">
                            <?php 
                            $share_percentage = ($member_report['house_total_meals'] > 0) ? 
                                ($member_report['total_meals'] / $member_report['house_total_meals']) * 100 : 0;
                            ?>
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $share_percentage; ?>%" 
                                 aria-valuenow="<?php echo $share_percentage; ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                                Your meals: <?php echo number_format($share_percentage, 1); ?>% of total
                            </div>
                        </div>
                        <small class="text-muted d-block">
                            You consumed <?php echo number_format($member_report['total_meals'], 2); ?> out of <?php echo number_format($member_report['house_total_meals'], 2); ?> total house meals
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($available_months)): ?>
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-archive me-2"></i>View Other Months</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($available_months as $available): 
                                list($year, $month) = explode('-', $available['month_year']);
                            ?>
                            <div class="col-md-3 mb-2">
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
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="profile.php" class="btn btn-outline-primary text-start">
                                <i class="fas fa-user-edit me-2"></i>Update Profile
                            </a>
                            <a href="report.php" class="btn btn-outline-info text-start">
                                <i class="fas fa-file-alt me-2"></i>View Full Report
                            </a>
                            <a href="../auth/change_password.php" class="btn btn-outline-warning text-start">
                                <i class="fas fa-key me-2"></i>Change Password
                            </a>
                            <a href="../auth/logout.php" class="btn btn-outline-danger text-start">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Category badge colors */
.bg-purple { background-color: #6f42c1 !important; }
.bg-orange { background-color: #fd7e14 !important; }

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
    .col-md-3, .col-md-4, .col-md-6, .col-md-8, .col-md-12 {
        width: 100% !important;
        margin-bottom: 20px !important;
    }
    .btn-group {
        display: none !important;
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
if (isset($deposits_stmt)) mysqli_stmt_close($deposits_stmt);
if (isset($expenses_list_stmt)) mysqli_stmt_close($expenses_list_stmt);
if (isset($available_months_stmt)) mysqli_stmt_close($available_months_stmt);
mysqli_close($conn);

require_once '../includes/footer.php';
?>