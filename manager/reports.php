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
    $house_info = $auth->getUserHouseInfo($user_id);
    if ($house_info && !empty($house_info['house_id'])) {
        $house_id = $house_info['house_id'];
        $_SESSION['house_id'] = $house_id;
        $_SESSION['house_name'] = $house_info['house_name'] ?? null;
        $_SESSION['house_code'] = $house_info['house_code'] ?? null;
    } else {
        echo '<div class="alert alert-warning">No house assigned. Please set up or join a house first.</div>';
        echo '<a href="setup_house.php" class="btn btn-primary">Set Up House</a>';
        exit();
    }
}

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Get available years for reports
$years_sql = "SELECT DISTINCT YEAR(meal_date) as year FROM meals WHERE house_id = ?
              UNION SELECT DISTINCT YEAR(expense_date) as year FROM expenses WHERE house_id = ?
              UNION SELECT DISTINCT YEAR(deposit_date) as year FROM deposits WHERE house_id = ?
              ORDER BY year DESC";
$stmt = mysqli_prepare($conn, $years_sql);
mysqli_stmt_bind_param($stmt, 'iii', $house_id, $house_id, $house_id);
mysqli_stmt_execute($stmt);
$years_result = mysqli_stmt_get_result($stmt);
$available_years = mysqli_fetch_all($years_result, MYSQLI_ASSOC);

if (!$available_years) {
    $available_years = [['year' => $current_year]];
}

// Calculate current month statistics
$stats = $functions->getDashboardStats($current_month, $current_year, $house_id);
$expense_breakdown = $functions->getExpenseBreakdown($current_month, $current_year, $house_id);
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

if (!$house_info) {
    die('<div class="alert alert-danger">Error: House information not found or inactive. Please contact administrator.</div>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Meal Management System</title>
    <?php require_once '../includes/header.php'; ?>
</head>
<body>
<div class="container-fluid">
    

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

    <!-- Report Sections - New Layout -->
    <div class="row">
        <!-- LEFT SIDE: Monthly Financial Report & Member Report -->
        <div class="col-lg-6 mb-4">
            <!-- Monthly Financial Report -->
            <div class="card shadow mb-4">
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
                                $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                                foreach ($months as $index => $month_name):
                                    $month_num = $index + 1;
                                ?>
                                <option value="<?php echo $month_num; ?>" <?php echo $month_num == $current_month ? 'selected' : ''; ?>><?php echo $month_name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="year" class="form-label">Year</label>
                            <select name="year" id="year" class="form-select" required>
                                <option value="">Select Year</option>
                                <?php foreach ($available_years as $year_data): ?>
                                <option value="<?php echo $year_data['year']; ?>" <?php echo $year_data['year'] == $current_year ? 'selected' : ''; ?>><?php echo $year_data['year']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-chart-bar me-2"></i>Generate Monthly Report</button>
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

            <!-- Individual Member Report -->
            <div class="card shadow">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-chart me-2"></i>Individual Member Report</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="member_report.php" class="row g-3">
                        <input type="hidden" name="house_id" value="<?php echo $house_id; ?>">
                        <div class="col-md-6">
                            <label for="member_id" class="form-label">Select Member</label>
                            <?php
                            $members_sql = "SELECT * FROM members WHERE house_id = ? AND status = 'active' ORDER BY name";
                            $stmt = mysqli_prepare($conn, $members_sql);
                            mysqli_stmt_bind_param($stmt, 'i', $house_id);
                            mysqli_stmt_execute($stmt);
                            $members_result = mysqli_stmt_get_result($stmt);
                            $all_members = $members_result ? mysqli_fetch_all($members_result, MYSQLI_ASSOC) : [];
                            ?>
                            <select name="member_id" id="member_id" class="form-select" required>
                                <option value="">Select Member</option>
                                <?php if (!empty($all_members)): foreach ($all_members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>"><?php echo htmlspecialchars($member['name']); ?></option>
                                <?php endforeach; endif; ?>
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
                                <option value="<?php echo $year_data['year']; ?>" <?php echo $year_data['year'] == $current_year ? 'selected' : ''; ?>><?php echo $year_data['year']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-info"><i class="fas fa-user-chart me-2"></i>Generate Member Report</button>
                            </div>
                        </div>
                    </form>
                    <div class="mt-4">
                        <h6>Member Report Includes:</h6>
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

        <!-- RIGHT SIDE: Expense Breakdown -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-pie me-2"></i>Expense Breakdown (<?php echo date('F Y'); ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($expense_breakdown['breakdown']) || !is_array($expense_breakdown['breakdown'])): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-pie fa-3x mb-3"></i>
                        <h5>No Expenses Recorded</h5>
                        <p class="mb-0">No expenses have been recorded for <?php echo date('F Y'); ?></p>
                    </div>
                    <?php else: 
                    $count_sql = "SELECT category, COUNT(*) as count FROM expenses WHERE house_id = ? AND MONTH(expense_date) = ? AND YEAR(expense_date) = ? GROUP BY category";
                    $count_stmt = mysqli_prepare($conn, $count_sql);
                    mysqli_stmt_bind_param($count_stmt, 'iii', $house_id, $current_month, $current_year);
                    mysqli_stmt_execute($count_stmt);
                    $count_result = mysqli_stmt_get_result($count_stmt);
                    $count_data = [];
                    while ($row = mysqli_fetch_assoc($count_result)) {
                        $count_data[$row['category']] = $row['count'];
                    }
                    mysqli_stmt_close($count_stmt);
                    ?>
                    
                    <!-- Breakdown List -->
                    <div class="row">
                        <div class="col-12">
                            <h6 class="mb-3">Category-wise Details</h6>
                            <div class="list-group list-group-flush">
                                <?php foreach ($expense_breakdown['breakdown'] as $item): 
                                $percentage = ($expense_breakdown['total'] ?? 0) > 0 ? ($item['total'] / $expense_breakdown['total'] * 100) : 0;
                                $badge_color = getCategoryColor($item['category']);
                                $badge_text_color = ($badge_color == 'warning' || $badge_color == 'light') ? 'text-dark' : 'text-white';
                                $count = isset($count_data[$item['category']]) ? $count_data[$item['category']] : '';
                                ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-<?php echo $badge_color; ?> <?php echo $badge_text_color; ?> me-3" style="width: 80px;"><?php echo $item['category']; ?></span>
                                        <div>
                                            <div class="fw-bold"><?php echo $functions->formatCurrency($item['total']); ?></div>
                                            <?php if ($count !== ''): ?>
                                            <small class="text-muted"><?php echo $count; ?> entries</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="progress" style="width: 100px; height: 8px;">
                                            <div class="progress-bar bg-<?php echo $badge_color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Summary -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Total Expenses for <?php echo date('F Y'); ?></h6>
                                <small class="text-muted">All categories combined</small>
                            </div>
                            <div class="text-end">
                                <h4 class="text-primary mb-0"><?php echo $functions->formatCurrency($expense_breakdown['total'] ?? 0); ?></h4>
                            </div>
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
                                <p class="mb-0"><strong>Net Balance:</strong> <span class="<?php echo $total_balance >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $functions->formatCurrency($total_balance); ?></span></p>
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
                            $recent_meals_sql = "SELECT m.name, ml.meal_date, ml.meal_count FROM meals ml JOIN members m ON ml.member_id = m.member_id WHERE ml.house_id = ? AND ml.meal_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY ml.meal_date DESC, ml.created_at DESC LIMIT 10";
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
                            $recent_deposits_sql = "SELECT m.name, d.amount, d.deposit_date FROM deposits d JOIN members m ON d.member_id = m.member_id WHERE d.house_id = ? AND d.deposit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY d.deposit_date DESC, d.created_at DESC LIMIT 10";
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
</div>

<?php
// Helper function for category colors
function getCategoryColor($category) {
    $colors = [
        'Rice' => 'primary',
        'Fish' => 'info',
        'Meat' => 'danger',
        'Vegetables' => 'success',
        'Spices' => 'warning',
        'Oil' => 'purple',
        'Food' => 'orange',
        'Others' => 'secondary'
    ];
    return $colors[$category] ?? 'secondary';
}
?>

<script>
<?php if (!empty($expense_breakdown['breakdown']) && is_array($expense_breakdown['breakdown'])): ?>
$(document).ready(function() {
    const ctx = document.getElementById('expensePieChart').getContext('2d');
    
    // Prepare data arrays without extra whitespace
    const labels = [<?php 
        $label_array = [];
        foreach ($expense_breakdown['breakdown'] as $item) {
            $label_array[] = "'" . $item['category'] . "'";
        }
        echo implode(',', $label_array);
    ?>];
    
    const data = [<?php 
        $data_array = [];
        foreach ($expense_breakdown['breakdown'] as $item) {
            $data_array[] = $item['total'];
        }
        echo implode(',', $data_array);
    ?>];
    
    const expensePieChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: ['#007bff','#17a2b8','#dc3545','#28a745','#ffc107','#6f42c1','#fd7e14','#6c757d'],
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        padding: 15,
                        font: {
                            size: 11
                        }
                    }
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

document.addEventListener('DOMContentLoaded', function() {
    const monthSelect = document.getElementById('month');
    const yearSelect = document.getElementById('year');
    const reportMonthSelect = document.getElementById('report_month');
    const reportYearSelect = document.getElementById('report_year');
    if (monthSelect) monthSelect.value = '<?php echo $current_month; ?>';
    if (yearSelect) yearSelect.value = '<?php echo $current_year; ?>';
    if (reportMonthSelect) reportMonthSelect.value = '<?php echo $current_month; ?>';
    if (reportYearSelect) reportYearSelect.value = '<?php echo $current_year; ?>';
});
</script>

<?php 
if (isset($stmt)) mysqli_stmt_close($stmt);
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>
</body>
</html>