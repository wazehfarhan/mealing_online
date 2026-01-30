<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Reports";

$conn = getConnection();

// Get current user's house from session
$house_id = $_SESSION['house_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

// Validate house_id
if (!$house_id) {
    // Try to get house_id from database using Auth class
    $house_info = $auth->getUserHouseInfo($user_id);
    
    if ($house_info && !empty($house_info['house_id'])) {
        $house_id = $house_info['house_id'];
        // Update session
        $_SESSION['house_id'] = $house_id;
        $_SESSION['house_name'] = $house_info['house_name'] ?? null;
        $_SESSION['house_code'] = $house_info['house_code'] ?? null;
    } else {
        // No house found, redirect to setup
        echo '<div class="alert alert-warning">No house assigned. Please set up or join a house first.</div>';
        echo '<a href="setup_house.php" class="btn btn-primary">Set Up House</a>';
        exit();
    }
}

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Get available years for reports (filtered by house)
$years_sql = "SELECT DISTINCT YEAR(meal_date) as year FROM meals WHERE house_id = ?
              UNION SELECT DISTINCT YEAR(expense_date) as year FROM expenses WHERE house_id = ?
              UNION SELECT DISTINCT YEAR(deposit_date) as year FROM deposits WHERE house_id = ?
              ORDER BY year DESC";
$stmt = mysqli_prepare($conn, $years_sql);
mysqli_stmt_bind_param($stmt, 'iii', $house_id, $house_id, $house_id);
mysqli_stmt_execute($stmt);
$years_result = mysqli_stmt_get_result($stmt);
$available_years = mysqli_fetch_all($years_result, MYSQLI_ASSOC);

// If no years found, show empty array
if (!$available_years) {
    $available_years = [['year' => $current_year]];
}

// Calculate current month statistics for this house
$stats = $functions->getDashboardStats($current_month, $current_year, $house_id);

// Get expense breakdown for current month for this house
$expense_breakdown = $functions->getExpenseBreakdown($current_month, $current_year, $house_id);

// Get member-wise report for current month for this house
$monthly_report = $functions->calculateMonthlyReport($current_month, $current_year, $house_id);

// Calculate totals
$total_deposits = 0;
$total_cost = 0;
$total_balance = 0;
$credit_count = 0;
$due_count = 0;
$total_members = 0;

if ($monthly_report && is_array($monthly_report)) {
    foreach ($monthly_report as $member) {
        $total_deposits += $member['total_deposits'];
        $total_cost += $member['member_cost'];
        $total_balance += $member['balance'];
        
        if ($member['balance'] >= 0) {
            $credit_count++;
        } else {
            $due_count++;
        }
    }
    $total_members = count($monthly_report);
}

// Get house information
$house_sql = "SELECT house_name, house_code FROM houses WHERE house_id = ? AND is_active = 1";
$stmt = mysqli_prepare($conn, $house_sql);
mysqli_stmt_bind_param($stmt, 'i', $house_id);
mysqli_stmt_execute($stmt);
$house_result = mysqli_stmt_get_result($stmt);
$house_info = mysqli_fetch_assoc($house_result);

// If house info not found, show error
if (!$house_info) {
    die('<div class="alert alert-danger">Error: House information not found or inactive. Please contact administrator.</div>');
}
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">Reports & Analytics</h4>
                <p class="text-muted mb-0">House: <strong><?php echo htmlspecialchars($house_info['house_name']); ?></strong> (Code: <?php echo htmlspecialchars($house_info['house_code']); ?>) | Reports for <?php echo date('F Y'); ?></p>
            </div>
            <div class="text-end">
                <small class="text-muted">Total Members: <?php echo $total_members; ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Report Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Expenses</h6>
                <h3 class="text-primary mb-0"><?php echo $functions->formatCurrency($stats['total_expenses'] ?? 0); ?></h3>
                <small class="text-muted">For <?php echo date('F Y'); ?></small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Meals</h6>
                <h3 class="text-success mb-0"><?php echo number_format($stats['total_meals'] ?? 0, 2); ?></h3>
                <small class="text-muted">For <?php echo date('F Y'); ?></small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Meal Rate</h6>
                <h3 class="text-warning mb-0"><?php echo $functions->formatCurrency($stats['meal_rate'] ?? 0); ?></h3>
                <small class="text-muted">Per meal</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Net Balance</h6>
                <h3 class="text-info mb-0"><?php echo $functions->formatCurrency($total_balance); ?></h3>
                <small class="text-muted">For <?php echo date('F Y'); ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Report Types -->
<div class="row">
    <!-- Monthly Report Generator -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar me-2"></i>Monthly Financial Report</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="monthly_report.php" class="row g-3">
                    <input type="hidden" name="house_id" value="<?php echo $house_id; ?>">
                    
                    <div class="col-md-6">
                        <label for="month" class="form-label">Month</label>
                        <select name="month" id="month" class="form-select" required>
                            <option value="">Select Month</option>
                            <?php
                            $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                     'July', 'August', 'September', 'October', 'November', 'December'];
                            foreach ($months as $index => $month_name):
                                $month_num = $index + 1;
                            ?>
                            <option value="<?php echo $month_num; ?>" <?php echo $month_num == $current_month ? 'selected' : ''; ?>>
                                <?php echo $month_name; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="year" class="form-label">Year</label>
                        <select name="year" id="year" class="form-select" required>
                            <option value="">Select Year</option>
                            <?php foreach ($available_years as $year_data): ?>
                            <option value="<?php echo $year_data['year']; ?>" <?php echo $year_data['year'] == $current_year ? 'selected' : ''; ?>>
                                <?php echo $year_data['year']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-chart-bar me-2"></i>Generate Monthly Report
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="mt-4">
                    <h6>Report Includes:</h6>
                    <ul class="mb-0">
                        <li>Member-wise meal count and cost</li>
                        <li>Total expenses and meal rate</li>
                        <li>Deposit summary and balance calculation</li>
                        <li>Due/Return amounts for each member</li>
                        <li>Printable and exportable format</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Expense Breakdown -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-pie me-2"></i>Expense Breakdown (<?php echo date('F Y'); ?>)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($expense_breakdown['breakdown']) || !is_array($expense_breakdown['breakdown'])): ?>
                <div class="text-center text-muted py-3">No expenses recorded this month</div>
                <?php else: ?>
                <div class="row">
                    <div class="col-7">
                        <div class="list-group list-group-flush">
                            <?php foreach ($expense_breakdown['breakdown'] as $item): 
                                $percentage = ($expense_breakdown['total'] ?? 0) > 0 ? ($item['total'] / $expense_breakdown['total'] * 100) : 0;
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <span class="badge bg-<?php echo getCategoryColor($item['category']); ?> me-2">
                                        <?php echo $item['category']; ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo $functions->formatCurrency($item['total']); ?></div>
                                    <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-5">
                        <canvas id="expensePieChart" height="200"></canvas>
                    </div>
                </div>
                
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex justify-content-between">
                        <strong>Total Expenses:</strong>
                        <strong><?php echo $functions->formatCurrency($expense_breakdown['total'] ?? 0); ?></strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Summary -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-alt me-2"></i>Quick Summary (<?php echo date('F Y'); ?>)</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <h6 class="text-muted mb-2">Members Status</h6>
                            <div class="row">
                                <div class="col-6">
                                    <h3 class="text-success mb-0"><?php echo $credit_count; ?></h3>
                                    <small class="text-muted">In Credit</small>
                                </div>
                                <div class="col-6">
                                    <h3 class="text-danger mb-0"><?php echo $due_count; ?></h3>
                                    <small class="text-muted">With Due</small>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small>Total Active Members: <strong><?php echo $total_members; ?></strong></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <h6 class="text-muted mb-2">Financial Summary</h6>
                            <p class="mb-1"><strong>Total Deposits:</strong> <?php echo $functions->formatCurrency($total_deposits); ?></p>
                            <p class="mb-1"><strong>Total Cost:</strong> <?php echo $functions->formatCurrency($total_cost); ?></p>
                            <p class="mb-0"><strong>Net Balance:</strong> 
                                <span class="<?php echo $total_balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $functions->formatCurrency($total_balance); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <h6 class="text-muted mb-2">Meal Statistics</h6>
                            <p class="mb-1"><strong>Total Meals:</strong> <?php echo number_format($stats['total_meals'] ?? 0, 2); ?></p>
                            <p class="mb-1"><strong>Total Expenses:</strong> <?php echo $functions->formatCurrency($stats['total_expenses'] ?? 0); ?></p>
                            <p class="mb-0"><strong>Meal Rate:</strong> <?php echo $functions->formatCurrency($stats['meal_rate'] ?? 0); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Reports -->
<div class="row">
    <!-- Member Reports -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-chart me-2"></i>Member Reports</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="member_report.php" class="row g-3 mb-4">
                    <input type="hidden" name="house_id" value="<?php echo $house_id; ?>">
                    
                    <div class="col-md-6">
                        <label for="member_id" class="form-label">Select Member</label>
                        <?php
                        $members_sql = "SELECT * FROM members WHERE house_id = ? AND status = 'active' ORDER BY name";
                        $stmt = mysqli_prepare($conn, $members_sql);
                        mysqli_stmt_bind_param($stmt, 'i', $house_id);
                        mysqli_stmt_execute($stmt);
                        $members_result = mysqli_stmt_get_result($stmt);

                        if ($members_result) {
                            $all_members = mysqli_fetch_all($members_result, MYSQLI_ASSOC);
                        } else {
                            $all_members = [];
                            echo '<div class="alert alert-warning">Error loading members: ' . mysqli_error($conn) . '</div>';
                        }
                        ?>
                        <select name="member_id" id="member_id" class="form-select" required>
                            <option value="">Select Member</option>
                            <?php if (!empty($all_members)): ?>
                                <?php foreach ($all_members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>">
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="report_month" class="form-label">Month</label>
                        <select name="month" id="report_month" class="form-select">
                            <option value="<?php echo $current_month; ?>">Current</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>"><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="report_year" class="form-label">Year</label>
                        <select name="year" id="report_year" class="form-select">
                            <?php foreach ($available_years as $year_data): ?>
                            <option value="<?php echo $year_data['year']; ?>" <?php echo $year_data['year'] == $current_year ? 'selected' : ''; ?>>
                                <?php echo $year_data['year']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-user-chart me-2"></i>Generate Member Report
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Member Report Includes:</h6>
                    <ul class="mb-0">
                        <li>Individual meal history</li>
                        <li>Deposit records</li>
                        <li>Monthly cost calculation</li>
                        <li>Balance status</li>
                        <li>Printable format</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Export Reports -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-download me-2"></i>Export Reports</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card border-success h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-csv fa-2x text-success mb-3"></i>
                                <h6>CSV Exports</h6>
                                <div class="d-grid gap-2">
                                    <a href="export_meals.php?format=csv&house_id=<?php echo $house_id; ?>" class="btn btn-outline-success btn-sm">Export Meals</a>
                                    <a href="export_expenses.php?format=csv&house_id=<?php echo $house_id; ?>" class="btn btn-outline-success btn-sm mt-1">Export Expenses</a>
                                    <a href="export_deposits.php?format=csv&house_id=<?php echo $house_id; ?>" class="btn btn-outline-success btn-sm mt-1">Export Deposits</a>
                                    <a href="export_members.php?format=csv&house_id=<?php echo $house_id; ?>" class="btn btn-outline-success btn-sm mt-1">Export Members</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-danger h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-pdf fa-2x text-danger mb-3"></i>
                                <h6>PDF Reports</h6>
                                <div class="d-grid gap-2">
                                    <a href="monthly_report.php?month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>&house_id=<?php echo $house_id; ?>&format=pdf" 
                                       class="btn btn-outline-danger btn-sm">Monthly Report</a>
                                    <a href="export_members.php?format=pdf&house_id=<?php echo $house_id; ?>" class="btn btn-outline-danger btn-sm mt-1">Members List</a>
                                    <a href="export_financial.php?format=pdf&house_id=<?php echo $house_id; ?>" class="btn btn-outline-danger btn-sm mt-1">Financial Summary</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-lightbulb me-2"></i>Tips for Better Reporting:</h6>
                        <ul class="mb-0">
                            <li>Generate monthly reports at the end of each month</li>
                            <li>Use CSV exports for data analysis in Excel</li>
                            <li>PDF reports are best for printing and sharing</li>
                            <li>Check member reports regularly for due amounts</li>
                            <li>Compare monthly trends using the available years</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history me-2"></i>Recent Activity</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">Recent Meals (Last 7 Days)</h6>
                        <?php
                        $recent_meals_sql = "SELECT m.name, ml.meal_date, ml.meal_count 
                                            FROM meals ml 
                                            JOIN members m ON ml.member_id = m.member_id 
                                            WHERE ml.house_id = ? AND ml.meal_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                                            ORDER BY ml.meal_date DESC, ml.created_at DESC 
                                            LIMIT 10";
                        $stmt = mysqli_prepare($conn, $recent_meals_sql);
                        mysqli_stmt_bind_param($stmt, 'i', $house_id);
                        mysqli_stmt_execute($stmt);
                        $recent_meals = mysqli_stmt_get_result($stmt);
                        
                        if (mysqli_num_rows($recent_meals) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($meal = mysqli_fetch_assoc($recent_meals)): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <small><?php echo date('M d', strtotime($meal['meal_date'])); ?></small>
                                        <div class="fw-bold"><?php echo htmlspecialchars($meal['name']); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success"><?php echo $meal['meal_count']; ?> meals</span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No meals recorded in the last 7 days</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="mb-3">Recent Deposits (Last 7 Days)</h6>
                        <?php
                        $recent_deposits_sql = "SELECT m.name, d.amount, d.deposit_date 
                                               FROM deposits d 
                                               JOIN members m ON d.member_id = m.member_id 
                                               WHERE d.house_id = ? AND d.deposit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                                               ORDER BY d.deposit_date DESC, d.created_at DESC 
                                               LIMIT 10";
                        $stmt = mysqli_prepare($conn, $recent_deposits_sql);
                        mysqli_stmt_bind_param($stmt, 'i', $house_id);
                        mysqli_stmt_execute($stmt);
                        $recent_deposits = mysqli_stmt_get_result($stmt);
                        
                        if (mysqli_num_rows($recent_deposits) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($deposit = mysqli_fetch_assoc($recent_deposits)): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <small><?php echo date('M d', strtotime($deposit['deposit_date'])); ?></small>
                                        <div class="fw-bold"><?php echo htmlspecialchars($deposit['name']); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-info"><?php echo $functions->formatCurrency($deposit['amount']); ?></span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No deposits recorded in the last 7 days</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function for category colors
function getCategoryColor($category) {
    $colors = [
        'Rice' => 'primary',
        'Fish' => 'info',
        'Meat' => 'danger',
        'Vegetables' => 'success',
        'Gas' => 'warning',
        'Internet' => 'secondary',
        'Utility' => 'dark',
        'Others' => 'light'
    ];
    return $colors[$category] ?? 'light';
}
?>

<script>
// Expense Pie Chart
<?php if (!empty($expense_breakdown['breakdown']) && is_array($expense_breakdown['breakdown'])): ?>
$(document).ready(function() {
    const ctx = document.getElementById('expensePieChart').getContext('2d');
    const expensePieChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: [
                <?php foreach ($expense_breakdown['breakdown'] as $item): ?>
                '<?php echo $item['category']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($expense_breakdown['breakdown'] as $item): ?>
                    <?php echo $item['total']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    '#3498db', // Rice - blue
                    '#17a2b8', // Fish - cyan
                    '#e74c3c', // Meat - red
                    '#27ae60', // Vegetables - green
                    '#f39c12', // Gas - orange
                    '#6c757d', // Internet - gray
                    '#2c3e50', // Utility - dark
                    '#95a5a6'  // Others - light gray
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.raw || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = Math.round((value / total) * 100);
                            return label + ': ৳' + value.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
});
<?php endif; ?>

// Auto-select current month/year in forms
document.getElementById('month').value = '<?php echo $current_month; ?>';
document.getElementById('year').value = '<?php echo $current_year; ?>';
</script>

<?php 
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>