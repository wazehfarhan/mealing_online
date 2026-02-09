<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();
$auth->requireRole('member');

$page_title = "Monthly Report";
$conn = getConnection();
$user_id = $_SESSION['user_id'];
$member_id = $_SESSION['member_id'];
$house_id = $_SESSION['house_id'];

// Get current month and year for filtering
$current_month = date('m');
$current_year = date('Y');
$selected_month = $_GET['month'] ?? $current_month;
$selected_year = $_GET['year'] ?? $current_year;
$view_type = $_GET['view'] ?? 'monthly';

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

// Initialize report arrays
$monthly_report = [];
$yearly_reports = [];
$category_totals = [];
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

// Initialize statement variables for proper cleanup
$total_meals_stmt = $expenses_stmt = $member_meals_stmt = $member_deposits_stmt = null;
$meals_stmt = $deposits_stmt = $expenses_list_stmt = $available_months_stmt = null;
$prev_balance_stmt = $yearly_cat_stmt = null;

if ($view_type === 'yearly') {
    // SIMPLIFIED YEARLY QUERY - Get monthly data separately
    $yearly_reports = [];
    
    // Get all months data for the year
    for ($month = 1; $month <= 12; $month++) {
        // Get member's total meals for the month
        $member_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as member_meals 
                             FROM meals 
                             WHERE member_id = ? 
                             AND house_id = ?
                             AND MONTH(meal_date) = ? 
                             AND YEAR(meal_date) = ?";
        $member_meals_stmt = mysqli_prepare($conn, $member_meals_sql);
        mysqli_stmt_bind_param($member_meals_stmt, "iiii", $member_id, $house_id, $month, $selected_year);
        mysqli_stmt_execute($member_meals_stmt);
        $member_meals_result = mysqli_stmt_get_result($member_meals_stmt);
        $member_meals_data = mysqli_fetch_assoc($member_meals_result);
        $member_meals = $member_meals_data['member_meals'] ?? 0;
        
        // Get total meals for all members in the house for the month
        $total_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as total_meals 
                            FROM meals 
                            WHERE house_id = ? 
                            AND MONTH(meal_date) = ? 
                            AND YEAR(meal_date) = ?";
        $total_meals_stmt = mysqli_prepare($conn, $total_meals_sql);
        mysqli_stmt_bind_param($total_meals_stmt, "iii", $house_id, $month, $selected_year);
        mysqli_stmt_execute($total_meals_stmt);
        $total_meals_result = mysqli_stmt_get_result($total_meals_stmt);
        $total_meals_data = mysqli_fetch_assoc($total_meals_result);
        $house_meals = $total_meals_data['total_meals'] ?? 0;
        
        // Get member's total deposits for the month
        $member_deposits_sql = "SELECT COALESCE(SUM(amount), 0) as member_deposits 
                                FROM deposits 
                                WHERE member_id = ? 
                                AND house_id = ?
                                AND MONTH(deposit_date) = ? 
                                AND YEAR(deposit_date) = ?";
        $member_deposits_stmt = mysqli_prepare($conn, $member_deposits_sql);
        mysqli_stmt_bind_param($member_deposits_stmt, "iiii", $member_id, $house_id, $month, $selected_year);
        mysqli_stmt_execute($member_deposits_stmt);
        $member_deposits_result = mysqli_stmt_get_result($member_deposits_stmt);
        $member_deposits_data = mysqli_fetch_assoc($member_deposits_result);
        $member_deposits = $member_deposits_data['member_deposits'] ?? 0;
        
        // Get total expenses for the house for the month
        $expenses_sql = "SELECT COALESCE(SUM(amount), 0) as total_expenses 
                         FROM expenses 
                         WHERE house_id = ? 
                         AND MONTH(expense_date) = ? 
                         AND YEAR(expense_date) = ?";
        $expenses_stmt = mysqli_prepare($conn, $expenses_sql);
        mysqli_stmt_bind_param($expenses_stmt, "iii", $house_id, $month, $selected_year);
        mysqli_stmt_execute($expenses_stmt);
        $expenses_result = mysqli_stmt_get_result($expenses_stmt);
        $expenses_data = mysqli_fetch_assoc($expenses_result);
        $house_expenses = $expenses_data['total_expenses'] ?? 0;
        
        // Close monthly statements
        if ($member_meals_stmt) mysqli_stmt_close($member_meals_stmt);
        if ($total_meals_stmt) mysqli_stmt_close($total_meals_stmt);
        if ($member_deposits_stmt) mysqli_stmt_close($member_deposits_stmt);
        if ($expenses_stmt) mysqli_stmt_close($expenses_stmt);
        
        // Calculate meal rate
        $meal_rate = 0;
        if ($house_meals > 0) {
            $meal_rate = $house_expenses / $house_meals;
        }
        
        // Calculate member's cost and balance
        $member_cost = $member_meals * $meal_rate;
        $balance = $member_deposits - $member_cost;
        
        $yearly_reports[$month] = [
            'month' => $month,
            'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
            'year' => $selected_year,
            'member_meals' => $member_meals,
            'house_meals' => $house_meals,
            'member_deposits' => $member_deposits,
            'house_expenses' => $house_expenses,
            'meal_rate' => $meal_rate,
            'member_cost' => $member_cost,
            'balance' => $balance
        ];
    }
    
    // Calculate yearly totals
    $yearly_totals = [
        'member_meals' => 0,
        'house_meals' => 0,
        'member_deposits' => 0,
        'house_expenses' => 0,
        'member_cost' => 0,
        'balance' => 0
    ];
    
    foreach ($yearly_reports as $report) {
        $yearly_totals['member_meals'] += $report['member_meals'];
        $yearly_totals['house_meals'] += $report['house_meals'];
        $yearly_totals['member_deposits'] += $report['member_deposits'];
        $yearly_totals['house_expenses'] += $report['house_expenses'];
        $yearly_totals['member_cost'] += $report['member_cost'];
        $yearly_totals['balance'] += $report['balance'];
    }
    
    // Get yearly expense categories
    $yearly_categories_sql = "
        SELECT category, SUM(amount) as total 
        FROM expenses 
        WHERE house_id = ? 
        AND YEAR(expense_date) = ?
        GROUP BY category 
        ORDER BY total DESC";
    
    $yearly_cat_stmt = mysqli_prepare($conn, $yearly_categories_sql);
    mysqli_stmt_bind_param($yearly_cat_stmt, "ii", $house_id, $selected_year);
    mysqli_stmt_execute($yearly_cat_stmt);
    $yearly_cat_result = mysqli_stmt_get_result($yearly_cat_stmt);
    
    while ($row = mysqli_fetch_assoc($yearly_cat_result)) {
        $category_totals[$row['category']] = $row['total'];
    }
} else {
    // MONTHLY VIEW CALCULATIONS
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

    $monthly_report = [
        'total_meals' => $member_total_meals,
        'total_deposits' => $member_total_deposits,
        'meal_rate' => $meal_rate,
        'member_cost' => $member_cost,
        'balance' => $balance,
        'house_total_meals' => $house_total_meals,
        'house_total_expenses' => $house_total_expenses
    ];

    // Get detailed meal history for the month
    $meals_sql = "SELECT * FROM meals 
                  WHERE member_id = ? 
                  AND house_id = ?
                  AND MONTH(meal_date) = ? 
                  AND YEAR(meal_date) = ? 
                  ORDER BY meal_date ASC";
    $meals_stmt = mysqli_prepare($conn, $meals_sql);
    mysqli_stmt_bind_param($meals_stmt, "iiii", $member_id, $house_id, $selected_month, $selected_year);
    mysqli_stmt_execute($meals_stmt);
    $meals_result = mysqli_stmt_get_result($meals_stmt);
    $meals = mysqli_fetch_all($meals_result, MYSQLI_ASSOC);

    // Get detailed deposit history for the month
    $deposits_sql = "SELECT * FROM deposits 
                     WHERE member_id = ? 
                     AND house_id = ?
                     AND MONTH(deposit_date) = ? 
                     AND YEAR(deposit_date) = ? 
                     ORDER BY deposit_date ASC";
    $deposits_stmt = mysqli_prepare($conn, $deposits_sql);
    mysqli_stmt_bind_param($deposits_stmt, "iiii", $member_id, $house_id, $selected_month, $selected_year);
    mysqli_stmt_execute($deposits_stmt);
    $deposits_result = mysqli_stmt_get_result($deposits_stmt);
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
    mysqli_stmt_bind_param($expenses_list_stmt, "iii", $house_id, $selected_month, $selected_year);
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

// Get all available months and years for dropdown
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

// Get previous month's balance
$prev_month = date('Y-m', strtotime($month_year . '-01 -1 month'));
$prev_month_with_day = $prev_month . '-01';
$prev_balance_sql = "SELECT mmd.balance 
                     FROM monthly_summary ms
                     JOIN monthly_member_details mmd ON ms.summary_id = mmd.summary_id
                     WHERE ms.house_id = ? 
                     AND ms.month_year = ? 
                     AND mmd.member_id = ?";
$prev_balance_stmt = mysqli_prepare($conn, $prev_balance_sql);
mysqli_stmt_bind_param($prev_balance_stmt, "isi", $house_id, $prev_month_with_day, $member_id);
mysqli_stmt_execute($prev_balance_stmt);
$prev_balance_result = mysqli_stmt_get_result($prev_balance_stmt);
$prev_balance_data = mysqli_fetch_assoc($prev_balance_result);
$previous_balance = $prev_balance_data['balance'] ?? 0;

// Calculate adjusted balance
if ($view_type === 'yearly') {
    $adjusted_balance = $previous_balance + $yearly_totals['balance'];
} else {
    $adjusted_balance = $previous_balance + $monthly_report['balance'];
}
?>

<div class="row">
    <div class="col-12">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-file-alt me-2"></i><?php echo $view_type === 'yearly' ? 'Yearly Report' : 'Monthly Report'; ?>
                        </h1>
                        <div class="text-muted">
                            <span class="badge bg-primary me-2"><?php echo htmlspecialchars($house['house_name']); ?></span>
                            <span class="me-2"><?php echo htmlspecialchars($member['name']); ?></span>
                            <span>- 
                                <?php if($view_type === 'yearly'): ?>
                                    <?php echo $selected_year; ?>
                                <?php else: ?>
                                    <?php echo $month_name . ' ' . $selected_year; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <!-- View Type Selector -->
                        <div class="btn-group me-2" role="group">
                            <a href="?view=monthly&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                               class="btn btn-sm btn-<?php echo $view_type === 'monthly' ? 'primary' : 'outline-primary'; ?>">
                                <i class="fas fa-calendar-alt me-1"></i> Monthly
                            </a>
                            <a href="?view=yearly&year=<?php echo $selected_year; ?>" 
                               class="btn btn-sm btn-<?php echo $view_type === 'yearly' ? 'primary' : 'outline-primary'; ?>">
                                <i class="fas fa-calendar me-1"></i> Yearly
                            </a>
                        </div>
                        
                        <!-- Month/Year Selector -->
                        <div class="btn-group me-2" role="group">
                            <form method="GET" class="d-flex" action="">
                                <input type="hidden" name="view" value="<?php echo $view_type; ?>">
                                <?php if($view_type === 'monthly'): ?>
                                    <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                <?php endif; ?>
                                <select name="year" class="form-select form-select-sm ms-2" onchange="this.form.submit()">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </form>
                        </div>
                        
                        <a href="dashboard.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                        <a href="../member/generate_member_pdf.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&view=<?php echo $view_type; ?>" 
                            class="btn btn-danger" id="downloadPdfBtn" target="_blank">
                            <i class="fas fa-file-pdf me-2"></i>PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Report Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Report Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="40%"><strong>Report For:</strong></td>
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Member ID:</strong></td>
                                        <td>M<?php echo str_pad($member_id, 4, '0', STR_PAD_LEFT); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>House:</strong></td>
                                        <td><?php echo htmlspecialchars($house['house_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>House Code:</strong></td>
                                        <td><?php echo $house['house_code']; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="40%"><strong>Report Period:</strong></td>
                                        <td>
                                            <?php if($view_type === 'yearly'): ?>
                                                <?php echo $selected_year; ?> (Yearly)
                                            <?php else: ?>
                                                <?php echo $month_name . ' ' . $selected_year; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Generated On:</strong></td>
                                        <td><?php echo date('F j, Y'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Generated At:</strong></td>
                                        <td><?php echo date('h:i A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $member['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($member['status']); ?> Member
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow border-<?php echo $adjusted_balance >= 0 ? 'success' : 'danger'; ?>">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-3">Overall Financial Status</h6>
                        <h1 class="<?php echo $adjusted_balance >= 0 ? 'text-success' : 'text-danger'; ?> mb-3">
                            <?php echo $functions->formatCurrency(abs($adjusted_balance)); ?>
                            <?php echo $adjusted_balance >= 0 ? '' : '-'; ?>
                        </h1>
                        <span class="badge bg-<?php echo $adjusted_balance >= 0 ? 'success' : 'danger'; ?>">
                            <?php echo $adjusted_balance >= 0 ? 'NET CREDIT' : 'NET DUE'; ?>
                        </span>
                        <div class="mt-3">
                            <small class="text-muted">Includes previous balances</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if($view_type === 'yearly'): ?>
        <!-- YEARLY VIEW CONTENT -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Yearly Summary - <?php echo $selected_year; ?></h5>
                    </div>
                    <div class="card-body">
                        <!-- Yearly Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-2 col-6 mb-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Total Meals</h6>
                                        <h3 class="text-success"><?php echo number_format($yearly_totals['member_meals'], 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Total Deposits</h6>
                                        <h3 class="text-primary"><?php echo $functions->formatCurrency($yearly_totals['member_deposits']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Total Cost</h6>
                                        <h3 class="text-warning"><?php echo $functions->formatCurrency($yearly_totals['member_cost']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">House Expenses</h6>
                                        <h3 class="text-info"><?php echo $functions->formatCurrency($yearly_totals['house_expenses']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="card border-secondary">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">House Meals</h6>
                                        <h3 class="text-secondary"><?php echo number_format($yearly_totals['house_meals'], 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-6 mb-3">
                                <div class="card border-<?php echo $yearly_totals['balance'] >= 0 ? 'success' : 'danger'; ?>">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Year Balance</h6>
                                        <h3 class="text-<?php echo $yearly_totals['balance'] >= 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $functions->formatCurrency($yearly_totals['balance']); ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monthly Breakdown Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month</th>
                                        <th class="text-end">My Meals</th>
                                        <th class="text-end">House Meals</th>
                                        <th class="text-end">My Deposits</th>
                                        <th class="text-end">House Expenses</th>
                                        <th class="text-end">Meal Rate</th>
                                        <th class="text-end">My Cost</th>
                                        <th class="text-end">Balance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($m = 1; $m <= 12; $m++): 
                                        $report = $yearly_reports[$m] ?? null;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></strong></td>
                                        <td class="text-end"><?php echo $report ? number_format($report['member_meals'], 2) : '0.00'; ?></td>
                                        <td class="text-end"><?php echo $report ? number_format($report['house_meals'], 2) : '0.00'; ?></td>
                                        <td class="text-end text-success"><?php echo $report ? $functions->formatCurrency($report['member_deposits']) : $functions->formatCurrency(0); ?></td>
                                        <td class="text-end text-danger"><?php echo $report ? $functions->formatCurrency($report['house_expenses']) : $functions->formatCurrency(0); ?></td>
                                        <td class="text-end text-info"><?php echo $report ? $functions->formatCurrency($report['meal_rate']) : $functions->formatCurrency(0); ?></td>
                                        <td class="text-end text-warning"><?php echo $report ? $functions->formatCurrency($report['member_cost']) : $functions->formatCurrency(0); ?></td>
                                        <td class="text-end text-<?php echo $report && $report['balance'] >= 0 ? 'success' : 'danger'; ?>"><?php echo $report ? $functions->formatCurrency($report['balance']) : $functions->formatCurrency(0); ?></td>
                                        <td>
                                            <a href="?view=monthly&month=<?php echo $m; ?>&year=<?php echo $selected_year; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th class="text-end">Yearly Totals:</th>
                                        <th class="text-end"><?php echo number_format($yearly_totals['member_meals'], 2); ?></th>
                                        <th class="text-end"><?php echo number_format($yearly_totals['house_meals'], 2); ?></th>
                                        <th class="text-end text-success"><?php echo $functions->formatCurrency($yearly_totals['member_deposits']); ?></th>
                                        <th class="text-end text-danger"><?php echo $functions->formatCurrency($yearly_totals['house_expenses']); ?></th>
                                        <th class="text-end">-</th>
                                        <th class="text-end text-warning"><?php echo $functions->formatCurrency($yearly_totals['member_cost']); ?></th>
                                        <th class="text-end text-<?php echo $yearly_totals['balance'] >= 0 ? 'success' : 'danger'; ?>"><?php echo $functions->formatCurrency($yearly_totals['balance']); ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Yearly Expense Categories -->
                        <?php if(!empty($category_totals)): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Yearly Expense Categories - <?php echo $selected_year; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Category</th>
                                                        <th class="text-end">Amount</th>
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
                                                            <span class="badge bg-<?php echo $category_color; ?>">
                                                                <?php echo $category; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end text-danger"><?php echo $functions->formatCurrency($total); ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="progress flex-grow-1" style="height: 20px;">
                                                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                                    </div>
                                                                </div>
                                                                <span class="ms-2"><?php echo number_format($percentage, 1); ?>%</span>
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
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- MONTHLY VIEW CONTENT -->
        <!-- Key Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">My Meals</h6>
                        <h2 class="text-success"><?php echo number_format($monthly_report['total_meals'], 2); ?></h2>
                        <small class="text-muted">
                            <?php 
                            $meal_days = 0;
                            foreach ($meals as $meal) {
                                if ($meal['meal_count'] > 0) $meal_days++;
                            }
                            echo $meal_days . ' days';
                            ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">My Deposits</h6>
                        <h2 class="text-primary"><?php echo $functions->formatCurrency($monthly_report['total_deposits']); ?></h2>
                        <small class="text-muted"><?php echo count($deposits); ?> deposit(s)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Meal Rate</h6>
                        <h2 class="text-info"><?php echo $functions->formatCurrency($monthly_report['meal_rate']); ?></h2>
                        <small class="text-muted">Per meal</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">This Month Balance</h6>
                        <h2 class="<?php echo $monthly_report['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $functions->formatCurrency($monthly_report['balance']); ?></h2>
                        <span class="badge bg-<?php echo $monthly_report['balance'] >= 0 ? 'success' : 'danger'; ?>"><?php echo $monthly_report['balance'] >= 0 ? 'CREDIT' : 'DUE'; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detailed Calculation -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Detailed Financial Calculation</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Description</th>
                                                <th class="text-end">Amount/Value</th>
                                                <th>Calculation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Total House Expenses</td>
                                                <td class="text-end text-warning"><?php echo $functions->formatCurrency($monthly_report['house_total_expenses']); ?></td>
                                                <td>Sum of all house expenses for <?php echo $month_name; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Total House Meals</td>
                                                <td class="text-end"><?php echo number_format($monthly_report['house_total_meals'], 2); ?></td>
                                                <td>Sum of all members' meals for <?php echo $month_name; ?></td>
                                            </tr>
                                            <tr class="table-info">
                                                <td><strong>Meal Rate Calculation</strong></td>
                                                <td class="text-end text-info"><?php echo $functions->formatCurrency($monthly_report['meal_rate']); ?></td>
                                                <td>Total Expenses ÷ Total House Meals</td>
                                            </tr>
                                            <tr>
                                                <td>My Total Meals</td>
                                                <td class="text-end"><?php echo number_format($monthly_report['total_meals'], 2); ?></td>
                                                <td>Sum of my meals for <?php echo $month_name; ?></td>
                                            </tr>
                                            <tr class="table-warning">
                                                <td><strong>My Total Meal Cost</strong></td>
                                                <td class="text-end text-warning"><?php echo $functions->formatCurrency($monthly_report['member_cost']); ?></td>
                                                <td>My Meals × Meal Rate</td>
                                            </tr>
                                            <tr>
                                                <td>My Total Deposits</td>
                                                <td class="text-end text-success"><?php echo $functions->formatCurrency($monthly_report['total_deposits']); ?></td>
                                                <td>Sum of my deposits for <?php echo $month_name; ?></td>
                                            </tr>
                                            <tr class="table-<?php echo $monthly_report['balance'] >= 0 ? 'success' : 'danger'; ?>">
                                                <td><strong>This Month's Balance</strong></td>
                                                <td class="text-end text-<?php echo $monthly_report['balance'] >= 0 ? 'success' : 'danger'; ?>"><?php echo $functions->formatCurrency($monthly_report['balance']); ?></td>
                                                <td>My Deposits - My Meal Cost</td>
                                            </tr>
                                            <?php if ($previous_balance != 0): ?>
                                            <tr>
                                                <td>Previous Balance</td>
                                                <td class="text-end <?php echo $previous_balance >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $functions->formatCurrency($previous_balance); ?></td>
                                                <td>Carried forward from previous month</td>
                                            </tr>
                                            <tr class="table-<?php echo $adjusted_balance >= 0 ? 'success' : 'danger'; ?>">
                                                <td><strong>Adjusted Balance</strong></td>
                                                <td class="text-end text-<?php echo $adjusted_balance >= 0 ? 'success' : 'danger'; ?>"><strong><?php echo $functions->formatCurrency($adjusted_balance); ?></strong></td>
                                                <td>This Month's Balance + Previous Balance</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fas fa-percentage me-2"></i>My Share Analysis</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $share_percentage = ($monthly_report['house_total_meals'] > 0) ? ($monthly_report['total_meals'] / $monthly_report['house_total_meals']) * 100 : 0;
                                        $expense_share = ($monthly_report['house_total_expenses'] > 0) ? ($monthly_report['member_cost'] / $monthly_report['house_total_expenses']) * 100 : 0;
                                        ?>
                                        <div class="text-center mb-4">
                                            <h3 class="text-info"><?php echo number_format($share_percentage, 1); ?>%</h3>
                                            <p class="text-muted">of total house meals</p>
                                        </div>
                                        <div class="progress mb-3" style="height: 25px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $share_percentage; ?>%" aria-valuenow="<?php echo $share_percentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo number_format($share_percentage, 1); ?>%</div>
                                        </div>
                                        <div class="text-center mt-4">
                                            <h4 class="text-warning"><?php echo number_format($expense_share, 1); ?>%</h4>
                                            <p class="text-muted">of total house expenses</p>
                                        </div>
                                        <div class="alert alert-info mt-3">
                                            <small><i class="fas fa-lightbulb me-2"></i>You consumed <strong><?php echo number_format($share_percentage, 1); ?>%</strong> of total meals, so you share <strong><?php echo number_format($expense_share, 1); ?>%</strong> of total expenses.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Deposit Records -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>My Deposits - <?php echo $month_name . ' ' . $selected_year; ?></h5>
                        <div>
                            <span class="badge bg-success"><?php echo count($deposits); ?> deposits</span>
                            <span class="badge bg-primary ms-1"><?php echo $functions->formatCurrency($monthly_report['total_deposits']); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($deposits)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Amount</th>
                                        <th>Description</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deposits as $deposit): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($deposit['deposit_date'])); ?></td>
                                        <td class="text-end text-success"><strong><?php echo $functions->formatCurrency($deposit['amount']); ?></strong></td>
                                        <td><?php echo !empty($deposit['description']) ? htmlspecialchars($deposit['description']) : 'Deposit'; ?></td>
                                        <td><span class="badge bg-info">Cash Deposit</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end">
                                            <h5 class="mb-0">Total Deposits: <span class="text-primary"><?php echo $functions->formatCurrency($monthly_report['total_deposits']); ?></span></h5>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <!-- Deposit Analysis -->
                        <div class="mt-4">
                            <h6><i class="fas fa-chart-line me-2"></i>Deposit Analysis</h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="p-3 border rounded">
                                        <h6 class="text-muted mb-2">Average Deposit</h6>
                                        <h4 class="text-success"><?php $average_deposit = count($deposits) > 0 ? $monthly_report['total_deposits'] / count($deposits) : 0; echo $functions->formatCurrency($average_deposit); ?></h4>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 border rounded">
                                        <h6 class="text-muted mb-2">Frequency</h6>
                                        <h4 class="text-info"><?php $deposit_days = count(array_unique(array_map(function($d) { return date('Y-m-d', strtotime($d['deposit_date'])); }, $deposits))); echo $deposit_days . ' days'; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Deposit Records</h5>
                            <p class="text-muted">No deposits recorded for <?php echo $month_name . ' ' . $selected_year; ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- House Expenses -->
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>House Expenses - <?php echo $month_name . ' ' . $selected_year; ?></h5>
                        <div>
                            <span class="badge bg-warning"><?php echo count($expenses_list); ?> expenses</span>
                            <span class="badge bg-danger ms-1"><?php echo $functions->formatCurrency($monthly_report['house_total_expenses']); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($expenses_list)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th class="text-end">Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses_list as $expense): 
                                        $category_color = $category_colors[$expense['category']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d', strtotime($expense['expense_date'])); ?></td>
                                        <td><span class="badge bg-<?php echo $category_color; ?>"><?php echo $expense['category']; ?></span></td>
                                        <td class="text-end text-danger"><?php echo $functions->formatCurrency($expense['amount']); ?></td>
                                        <td>
                                            <?php echo !empty($expense['description']) ? htmlspecialchars($expense['description']) : '-'; ?>
                                            <?php if (!empty($expense['added_by'])): ?>
                                            <br><small class="text-muted">by <?php echo htmlspecialchars($expense['added_by']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end">
                                            <h5 class="mb-0">Total Expenses: <span class="text-danger"><?php echo $functions->formatCurrency($monthly_report['house_total_expenses']); ?></span></h5>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Expense Categories -->
                        <div class="mt-4">
                            <h6><i class="fas fa-chart-pie me-2"></i>Expense Categories</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th class="text-end">Amount</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_totals as $category => $total): 
                                            $percentage = ($monthly_report['house_total_expenses'] > 0) ? ($total / $monthly_report['house_total_expenses']) * 100 : 0;
                                            $category_color = $category_colors[$category] ?? 'secondary';
                                        ?>
                                        <tr>
                                            <td><span class="badge bg-<?php echo $category_color; ?>"><?php echo $category; ?></span></td>
                                            <td class="text-end text-danger"><?php echo $functions->formatCurrency($total); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1" style="height: 20px;">
                                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <span class="ms-2"><?php echo number_format($percentage, 1); ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Expense Records</h5>
                            <p class="text-muted">No expenses recorded for <?php echo $month_name . ' ' . $selected_year; ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Daily Meal Records -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-utensils me-2"></i>Daily Meal Records - <?php echo $month_name . ' ' . $selected_year; ?></h5>
                        <div>
                            <span class="badge bg-success"><?php echo count($meals); ?> days</span>
                            <span class="badge bg-primary ms-1"><?php echo number_format($monthly_report['total_meals'], 2); ?> meals</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($meals)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th class="text-end">Meal Count</th>
                                        <th class="text-end">Daily Cost</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_daily_cost = 0;
                                    $days_with_meals = 0;
                                    $days_without_meals = 0;
                                    foreach ($meals as $meal): 
                                        $daily_cost = $meal['meal_count'] * $monthly_report['meal_rate'];
                                        $total_daily_cost += $daily_cost;
                                        if ($meal['meal_count'] > 0) { $days_with_meals++; } else { $days_without_meals++; }
                                    ?>
                                    <tr class="<?php echo $meal['meal_count'] == 0 ? 'table-secondary' : ''; ?>">
                                        <td><?php echo date('M d, Y', strtotime($meal['meal_date'])); ?></td>
                                        <td>
                                            <?php echo date('l', strtotime($meal['meal_date'])); ?>
                                            <?php if (date('N', strtotime($meal['meal_date'])) >= 6): ?><span class="badge bg-warning ms-1">Weekend</span><?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($meal['meal_count'] > 0): ?><span class="badge bg-success"><?php echo number_format($meal['meal_count'], 2); ?></span><?php else: ?><span class="badge bg-secondary">0</span><?php endif; ?>
                                        </td>
                                        <td class="text-end text-warning"><?php echo $functions->formatCurrency($daily_cost); ?></td>
                                        <td>
                                            <?php if ($meal['meal_count'] == 0): ?>
                                            <span class="badge bg-secondary">No Meal</span>
                                            <?php elseif ($meal['meal_count'] < 1): ?>
                                            <span class="badge bg-info">Partial Meal</span>
                                            <?php elseif ($meal['meal_count'] > 2): ?>
                                            <span class="badge bg-danger">Extra Meals</span>
                                            <?php else: ?>
                                            <span class="badge bg-success">Regular</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>Summary:</strong></td>
                                        <td class="text-end"><strong><?php echo number_format($monthly_report['total_meals'], 2); ?> total meals</strong></td>
                                        <td class="text-end text-warning"><strong><?php echo $functions->formatCurrency($total_daily_cost); ?> total</strong></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $days_with_meals; ?> days with meals</span>
                                            <?php if ($days_without_meals > 0): ?><span class="badge bg-secondary ms-1"><?php echo $days_without_meals; ?> days without</span><?php endif; ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Meal Statistics -->
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Average per Day</h6>
                                        <h3 class="text-success"><?php $average_daily = count($meals) > 0 ? $monthly_report['total_meals'] / count($meals) : 0; echo number_format($average_daily, 2); ?></h3>
                                        <small class="text-muted">Meals per day</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Highest Day</h6>
                                        <h3 class="text-primary">
                                            <?php 
                                            $max_meals = 0;
                                            $max_day = '';
                                            foreach ($meals as $meal) {
                                                if ($meal['meal_count'] > $max_meals) {
                                                    $max_meals = $meal['meal_count'];
                                                    $max_day = date('M d', strtotime($meal['meal_date']));
                                                }
                                            }
                                            echo number_format($max_meals, 2);
                                            ?>
                                        </h3>
                                        <small class="text-muted"><?php echo $max_day; ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Meal Days</h6>
                                        <h3 class="text-info"><?php echo $days_with_meals; ?></h3>
                                        <small class="text-muted">Days with meals</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted mb-2">Daily Cost Average</h6>
                                        <h3 class="text-warning"><?php $average_daily_cost = $days_with_meals > 0 ? $total_daily_cost / $days_with_meals : 0; echo $functions->formatCurrency($average_daily_cost); ?></h3>
                                        <small class="text-muted">Per day with meals</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-utensils fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">No Meal Records Found</h4>
                            <p class="text-muted">No meals recorded for <?php echo $month_name . ' ' . $selected_year; ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Available Reports -->
        <?php if (!empty($available_months)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>View Other Reports</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php 
                            $available_years = [];
                            foreach ($available_months as $available): 
                                list($year, $month) = explode('-', $available['month_year']);
                                $month_name_avail = date('F', mktime(0, 0, 0, $month, 1));
                                $available_years[$year] = true;
                            ?>
                            <div class="col-md-2 col-4 mb-2">
                                <a href="?view=monthly&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                                   class="btn btn-outline-primary w-100 text-start <?php echo ($view_type === 'monthly' && $month == $selected_month && $year == $selected_year) ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    <?php echo substr($month_name_avail, 0, 3) . ' ' . $year; ?>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <div class="row">
                            <?php foreach (array_keys($available_years) as $year): ?>
                            <div class="col-md-2 col-4 mb-2">
                                <a href="?view=yearly&year=<?php echo $year; ?>" 
                                   class="btn btn-outline-info w-100 text-start <?php echo ($view_type === 'yearly' && $year == $selected_year) ? 'active' : ''; ?>">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo $year; ?> (Yearly)
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Report Notes -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Report Notes</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-2"></i>Important Notes:</h6>
                                    <ul class="mb-0">
                                        <li>This report is generated for your personal reference</li>
                                        <li>All calculations are based on actual recorded data</li>
                                        <li>Balances are carried forward to next month</li>
                                        <li>Contact your house manager for any discrepancies</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-<?php echo $adjusted_balance < 0 ? 'danger' : 'success'; ?>">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Payment Status:</h6>
                                    <p class="mb-0">
                                        <?php if ($adjusted_balance < 0): ?>
                                        <strong class="text-danger">ACTION REQUIRED:</strong> You have a due amount of <?php echo $functions->formatCurrency(abs($adjusted_balance)); ?>. Please make payment.
                                        <?php else: ?>
                                        <strong class="text-success">CLEAR:</strong> You have no due amount.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-purple { background-color: #6f42c1 !important; }
.bg-orange { background-color: #fd7e14 !important; }
</style>

<script>
document.getElementById('downloadPdfBtn').addEventListener('click', function(e) {
    const originalHTML = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
    this.disabled = true;
    setTimeout(() => {
        this.innerHTML = originalHTML;
        this.disabled = false;
    }, 3000);
});
</script>

<?php
// Close statements - Only close if they exist and haven't been closed already
if ($view_type === 'monthly') {
    if (isset($total_meals_stmt) && $total_meals_stmt) mysqli_stmt_close($total_meals_stmt);
    if (isset($expenses_stmt) && $expenses_stmt) mysqli_stmt_close($expenses_stmt);
    if (isset($member_meals_stmt) && $member_meals_stmt) mysqli_stmt_close($member_meals_stmt);
    if (isset($member_deposits_stmt) && $member_deposits_stmt) mysqli_stmt_close($member_deposits_stmt);
    if (isset($meals_stmt) && $meals_stmt) mysqli_stmt_close($meals_stmt);
    if (isset($deposits_stmt) && $deposits_stmt) mysqli_stmt_close($deposits_stmt);
    if (isset($expenses_list_stmt) && $expenses_list_stmt) mysqli_stmt_close($expenses_list_stmt);
}

// Close common statements
if (isset($stmt) && $stmt) mysqli_stmt_close($stmt);
if (isset($house_stmt) && $house_stmt) mysqli_stmt_close($house_stmt);
if (isset($available_months_stmt) && $available_months_stmt) mysqli_stmt_close($available_months_stmt);
if (isset($prev_balance_stmt) && $prev_balance_stmt) mysqli_stmt_close($prev_balance_stmt);
if (isset($yearly_cat_stmt) && $yearly_cat_stmt) mysqli_stmt_close($yearly_cat_stmt);

mysqli_close($conn);
require_once '../includes/footer.php';
?>