<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireLogin();

if ($_SESSION['role'] !== 'member') {
    header("Location: ../index.php");
    exit();
}

$page_title = "Member Dashboard";

$conn = getConnection();
$member_id = $_SESSION['member_id'];

// Get member info
$sql = "SELECT * FROM members WHERE member_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $member_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);

if (!$member) {
    $_SESSION['error'] = "Member not found";
    header("Location: ../auth/logout.php");
    exit();
}

// Get current month stats
$current_month = date('m');
$current_year = date('Y');

$month_start = "$current_year-$current_month-01";
$month_end = date('Y-m-t', strtotime($month_start));

// Get member's meals for current month
$sql = "SELECT SUM(meal_count) as total_meals FROM meals 
        WHERE member_id = ? AND meal_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iss", $member_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$month_meals = mysqli_fetch_assoc($result)['total_meals'] ?: 0;

// Get member's deposits for current month
$sql = "SELECT SUM(amount) as total_deposits FROM deposits 
        WHERE member_id = ? AND deposit_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iss", $member_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$month_deposits = mysqli_fetch_assoc($result)['total_deposits'] ?: 0;

// Get total expenses for current month
$sql = "SELECT SUM(amount) as total_expenses FROM expenses 
        WHERE expense_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_expenses = mysqli_fetch_assoc($result)['total_expenses'] ?: 0;

// Get total meals for all members
$sql = "SELECT SUM(meal_count) as all_meals FROM meals 
        WHERE meal_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$all_meals = mysqli_fetch_assoc($result)['all_meals'] ?: 0;

// Calculate meal rate and cost
$meal_rate = 0;
$member_cost = 0;
$balance = 0;

if ($all_meals > 0) {
    $meal_rate = $total_expenses / $all_meals;
    $member_cost = $month_meals * $meal_rate;
    $balance = $month_deposits - $member_cost;
}

// Get recent meals (last 15 days)
$recent_start = date('Y-m-d', strtotime('-15 days'));
$sql = "SELECT * FROM meals 
        WHERE member_id = ? AND meal_date >= ? 
        ORDER BY meal_date DESC LIMIT 10";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "is", $member_id, $recent_start);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$recent_meals = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get recent deposits
$sql = "SELECT * FROM deposits 
        WHERE member_id = ? 
        ORDER BY deposit_date DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $member_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$recent_deposits = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get member's yearly summary
$current_year = date('Y');
$year_start = "$current_year-01-01";
$year_end = "$current_year-12-31";

$yearly_sql = "SELECT 
                DATE_FORMAT(meal_date, '%Y-%m') as month,
                SUM(meal_count) as monthly_meals
                FROM meals 
                WHERE member_id = ? AND meal_date BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(meal_date, '%Y-%m')
                ORDER BY month";
$yearly_stmt = mysqli_prepare($conn, $yearly_sql);
mysqli_stmt_bind_param($yearly_stmt, "iss", $member_id, $year_start, $year_end);
mysqli_stmt_execute($yearly_stmt);
$yearly_result = mysqli_stmt_get_result($yearly_stmt);
$yearly_data = mysqli_fetch_all($yearly_result, MYSQLI_ASSOC);
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">Welcome, <?php echo htmlspecialchars($member['name']); ?>!</h4>
                <p class="text-muted mb-0">Member Dashboard - <?php echo date('F Y'); ?></p>
            </div>
            <div>
                <a href="report.php" class="btn btn-primary">
                    <i class="fas fa-file-alt me-2"></i>View Detailed Report
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Welcome Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-white">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-2">Your Monthly Summary</h3>
                        <p class="mb-0">Track your meals, expenses, and balance in real-time. Everything you need to know about your meal account.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <i class="fas fa-user-circle fa-5x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Meals (This Month)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($month_meals, 2); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-primary mr-2"><i class="fas fa-utensils"></i> Current</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-utensils fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-success">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Deposits (This Month)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $functions->formatCurrency($month_deposits); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-success mr-2"><i class="fas fa-wallet"></i> Deposited</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-wallet fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-warning">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Your Cost (This Month)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $functions->formatCurrency($member_cost); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-warning mr-2"><i class="fas fa-calculator"></i> Calculated</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calculator fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-info">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Current Balance</div>
                        <div class="h5 mb-0 font-weight-bold <?php echo $balance >= 0 ? 'text-info' : 'text-danger'; ?>">
                            <?php echo $functions->formatCurrency($balance); ?>
                        </div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <?php if ($balance >= 0): ?>
                            <span class="text-success mr-2"><i class="fas fa-arrow-up"></i> Credit</span>
                            <?php else: ?>
                            <span class="text-danger mr-2"><i class="fas fa-arrow-down"></i> Due</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Info Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Current Meal Rate</h6>
                <h3 class="text-primary mb-0"><?php echo $functions->formatCurrency($meal_rate); ?></h3>
                <small class="text-muted">Per meal</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">System Meals</h6>
                <h3 class="text-success mb-0"><?php echo number_format($all_meals, 2); ?></h3>
                <small class="text-muted">Total this month</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">System Expenses</h6>
                <h3 class="text-warning mb-0"><?php echo $functions->formatCurrency($total_expenses); ?></h3>
                <small class="text-muted">Total this month</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Meals -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-utensils me-2"></i>Recent Meal Entries</h6>
                <a href="report.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_meals)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-utensils fa-2x mb-3"></i>
                    <p>No meal entries found for the last 15 days</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th class="text-center">Meal Count</th>
                                <th>Daily Cost</th>
                                <th>Cumulative Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $cumulative_cost = 0;
                            foreach ($recent_meals as $meal): 
                                $daily_cost = $meal['meal_count'] * $meal_rate;
                                $cumulative_cost += $daily_cost;
                            ?>
                            <tr>
                                <td>
                                    <?php echo $functions->formatDate($meal['meal_date']); ?>
                                </td>
                                <td>
                                    <?php echo date('l', strtotime($meal['meal_date'])); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill" style="font-size: 1em;">
                                        <?php echo $meal['meal_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-warning"><?php echo $functions->formatCurrency($daily_cost); ?></span>
                                </td>
                                <td>
                                    <span class="text-info"><?php echo $functions->formatCurrency($cumulative_cost); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Deposits & Quick Stats -->
    <div class="col-lg-4 mb-4">
        <!-- Recent Deposits -->
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-wallet me-2"></i>Recent Deposits</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recent_deposits)): ?>
                <div class="text-center text-muted py-3">No recent deposits</div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_deposits as $deposit): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <h6 class="mb-1"><?php echo $functions->formatDate($deposit['deposit_date']); ?></h6>
                            <?php if ($deposit['description']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($deposit['description']); ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-success rounded-pill"><?php echo $functions->formatCurrency($deposit['amount']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar me-2"></i>Quick Stats</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Your Status</span>
                        <span class="badge bg-<?php echo $balance >= 0 ? 'success' : 'danger'; ?>">
                            <?php echo $balance >= 0 ? 'In Credit' : 'Has Due'; ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Average Daily Meals</span>
                        <span class="badge bg-primary">
                            <?php 
                            $days_passed = date('j');
                            $avg_daily = $days_passed > 0 ? $month_meals / $days_passed : 0;
                            echo number_format($avg_daily, 2);
                            ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Projected Monthly Cost</span>
                        <span class="badge bg-warning">
                            <?php 
                            $days_in_month = date('t');
                            $projected_meals = $avg_daily * $days_in_month;
                            $projected_cost = $projected_meals * $meal_rate;
                            echo $functions->formatCurrency($projected_cost);
                            ?>
                        </span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Remaining Days</span>
                        <span class="badge bg-info">
                            <?php echo $days_in_month - date('j'); ?> days
                        </span>
                    </div>
                </div>
                
                <!-- Balance Status -->
                <div class="mt-4 p-3 rounded" style="background-color: <?php echo $balance >= 0 ? '#d4edda' : '#f8d7da'; ?>">
                    <h6><i class="fas fa-<?php echo $balance >= 0 ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        Balance Status
                    </h6>
                    <p class="mb-2">
                        <?php if ($balance >= 0): ?>
                        You have a credit of <strong class="text-success"><?php echo $functions->formatCurrency($balance); ?></strong>.
                        This amount will be returned to you at month end.
                        <?php else: ?>
                        You have a due of <strong class="text-danger"><?php echo $functions->formatCurrency(abs($balance)); ?></strong>.
                        Please deposit this amount to clear your balance.
                        <?php endif; ?>
                    </p>
                    <?php if ($balance < 0): ?>
                    <div class="alert alert-warning mb-0">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Contact your manager to make a deposit and clear your due amount.
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Meal Chart -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-line me-2"></i>Monthly Meal Trend (<?php echo date('Y'); ?>)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($yearly_data)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-chart-line fa-2x mb-3"></i>
                    <p>No meal data available for this year</p>
                </div>
                <?php else: ?>
                <canvas id="monthlyChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Important Notes -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-info">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Important Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <i class="fas fa-calculator fa-2x text-primary mb-3"></i>
                            <h6>How Costs Are Calculated</h6>
                            <p class="small mb-0">Your cost = Your meals × (Total expenses ÷ Total system meals)</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                            <h6>Reporting Schedule</h6>
                            <p class="small mb-0">Monthly reports are generated at month end. Check back regularly for updates.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center p-3">
                            <i class="fas fa-question-circle fa-2x text-success mb-3"></i>
                            <h6>Need Help?</h6>
                            <p class="small mb-0">Contact your manager for any questions about your account or calculations.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Monthly Meal Chart
<?php if (!empty($yearly_data)): ?>
$(document).ready(function() {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    const monthlyChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [
                <?php 
                $month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                foreach ($month_names as $index => $month_name):
                    $month_num = $index + 1;
                    $found = false;
                    foreach ($yearly_data as $data) {
                        if (date('m', strtotime($data['month'])) == sprintf('%02d', $month_num)) {
                            $found = true;
                            break;
                        }
                    }
                    echo "'" . $month_name . "',";
                endforeach;
                ?>
            ],
            datasets: [{
                label: 'Monthly Meals',
                data: [
                    <?php 
                    foreach ($month_names as $index => $month_name):
                        $month_num = $index + 1;
                        $found = false;
                        foreach ($yearly_data as $data) {
                            if (date('m', strtotime($data['month'])) == sprintf('%02d', $month_num)) {
                                echo $data['monthly_meals'] . ',';
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) echo '0,';
                    endforeach;
                    ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
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
                            return 'Meals: ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Meals'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Months'
                    }
                }
            }
        }
    });
});
<?php endif; ?>
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>