<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Dashboard";

$conn = getConnection();

// Get current user's house
$user_id = $_SESSION['user_id'] ?? null;
$house_id = $_SESSION['house_id'] ?? null;

// Validate house_id
if (!$house_id) {
    // Try to get house_id from database
    $house_info = $auth->getUserHouseInfo($user_id);
    
    if ($house_info && !empty($house_info['house_id'])) {
        $house_id = $house_info['house_id'];
        // Update session
        $_SESSION['house_id'] = $house_id;
        $_SESSION['house_name'] = $house_info['house_name'] ?? null;
        $_SESSION['house_code'] = $house_info['house_code'] ?? null;
    } else {
        // No house found, redirect to setup
        header('Location: setup_house.php');
        exit();
    }
}

// Get dashboard statistics for this house
$stats = $functions->getDashboardStats(date('m'), date('Y'), $house_id);

// Get house information
$house_sql = "SELECT house_name, house_code FROM houses WHERE house_id = ? AND is_active = 1";
$stmt = mysqli_prepare($conn, $house_sql);
mysqli_stmt_bind_param($stmt, 'i', $house_id);
mysqli_stmt_execute($stmt);
$house_result = mysqli_stmt_get_result($stmt);
$house_info = mysqli_fetch_assoc($house_result);

// Get recent meals for this house (last 7 days)
$recent_meals_sql = "SELECT m.meal_id, m.meal_date, m.meal_count, mb.name 
                    FROM meals m 
                    JOIN members mb ON m.member_id = mb.member_id 
                    WHERE m.house_id = ? 
                    ORDER BY m.meal_date DESC, m.created_at DESC 
                    LIMIT 10";
$stmt = mysqli_prepare($conn, $recent_meals_sql);
mysqli_stmt_bind_param($stmt, 'i', $house_id);
mysqli_stmt_execute($stmt);
$recent_meals_result = mysqli_stmt_get_result($stmt);
$recent_meals = mysqli_fetch_all($recent_meals_result, MYSQLI_ASSOC);

// Get recent expenses for this house (last 7 days)
$recent_expenses_sql = "SELECT expense_id, expense_date, category, amount, description 
                       FROM expenses 
                       WHERE house_id = ? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                       ORDER BY expense_date DESC, created_at DESC 
                       LIMIT 10";
$stmt = mysqli_prepare($conn, $recent_expenses_sql);
mysqli_stmt_bind_param($stmt, 'i', $house_id);
mysqli_stmt_execute($stmt);
$recent_expenses_result = mysqli_stmt_get_result($stmt);
$recent_expenses = mysqli_fetch_all($recent_expenses_result, MYSQLI_ASSOC);

// Get expense breakdown for current month for this house
$expense_breakdown = $functions->getExpenseBreakdown(date('m'), date('Y'), $house_id);

// Get recent deposits for this house (last 7 days)
$recent_deposits_sql = "SELECT d.deposit_id, d.deposit_date, d.amount, d.description, mb.name 
                       FROM deposits d 
                       JOIN members mb ON d.member_id = mb.member_id 
                       WHERE d.house_id = ? AND d.deposit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                       ORDER BY d.deposit_date DESC, d.created_at DESC 
                       LIMIT 5";
$stmt = mysqli_prepare($conn, $recent_deposits_sql);
mysqli_stmt_bind_param($stmt, 'i', $house_id);
mysqli_stmt_execute($stmt);
$recent_deposits_result = mysqli_stmt_get_result($stmt);
$recent_deposits = mysqli_fetch_all($recent_deposits_result, MYSQLI_ASSOC);

// Get members without accounts for this house (for quick view)
$members_no_account_sql = "SELECT m.* 
                          FROM members m 
                          LEFT JOIN users u ON m.member_id = u.member_id 
                          WHERE m.house_id = ? AND m.status = 'active' AND u.user_id IS NULL 
                          ORDER BY m.name ASC 
                          LIMIT 5";
$stmt = mysqli_prepare($conn, $members_no_account_sql);
mysqli_stmt_bind_param($stmt, 'i', $house_id);
mysqli_stmt_execute($stmt);
$members_no_account_result = mysqli_stmt_get_result($stmt);
$members_no_account = mysqli_fetch_all($members_no_account_result, MYSQLI_ASSOC);
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">Dashboard</h4>
                <p class="text-muted mb-0">
                    <?php if ($house_info): ?>
                    House: <strong><?php echo htmlspecialchars($house_info['house_name']); ?></strong> 
                    (Code: <?php echo htmlspecialchars($house_info['house_code']); ?>)
                    <?php endif; ?>
                </p>
            </div>
            <div class="text-end">
                <small class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</small>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Members</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_members']; ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <?php if (count($members_no_account) > 0): ?>
                            <span class="text-warning mr-2">
                                <i class="fas fa-exclamation-circle"></i> <?php echo count($members_no_account); ?> need accounts
                            </span>
                            <?php else: ?>
                            <span class="text-success mr-2"><i class="fas fa-check-circle"></i> All have accounts</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
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
                            Total Meals (This Month)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_meals'], 2); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-info mr-2">
                                <i class="fas fa-utensils"></i> Today: <?php echo $stats['today_meals']; ?>
                            </span>
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
        <div class="card stat-card border-left-warning">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Expenses (This Month)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $functions->formatCurrency($stats['total_expenses']); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-warning mr-2">
                                <i class="fas fa-money-bill-wave"></i> Current Month
                            </span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                            Current Meal Rate</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $functions->formatCurrency($stats['meal_rate']); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-info mr-2">
                                <i class="fas fa-calculator"></i> Per Meal
                            </span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calculator fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-6 mb-3">
                        <a href="meals.php?action=add" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Add Meals
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="expenses.php?action=add" class="btn btn-success w-100">
                            <i class="fas fa-money-bill-wave me-2"></i>Add Expense
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="deposits.php?action=add" class="btn btn-warning w-100">
                            <i class="fas fa-wallet me-2"></i>Add Deposit
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="members.php?action=add" class="btn btn-info w-100">
                            <i class="fas fa-user-plus me-2"></i>Add Member
                        </a>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3 col-6 mb-3">
                        <a href="reports.php" class="btn btn-secondary w-100">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="generate_link.php" class="btn btn-dark w-100">
                            <i class="fas fa-link me-2"></i>Generate Links
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="settings.php" class="btn btn-light w-100">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="../auth/logout.php" class="btn btn-outline-danger w-100">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Meals -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-utensils me-2"></i>Recent Meal Entries</h6>
                <a href="meals.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Meals</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_meals)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No meal entries found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recent_meals as $meal): ?>
                            <tr>
                                <td><?php echo $functions->formatDate($meal['meal_date']); ?></td>
                                <td><?php echo htmlspecialchars($meal['name']); ?></td>
                                <td><span class="badge bg-primary"><?php echo $meal['meal_count']; ?></span></td>
                                <td>
                                    <a href="edit_meal.php?id=<?php echo $meal['meal_id']; ?>" 
                                       class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-history me-2"></i>Recent Activity</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Recent Expenses -->
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-3"><i class="fas fa-money-bill-wave me-2"></i>Recent Expenses</h6>
                        <div class="list-group list-group-flush">
                            <?php if (empty($recent_expenses)): ?>
                            <div class="text-center text-muted py-3">No recent expenses</div>
                            <?php else: ?>
                            <?php foreach ($recent_expenses as $expense): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <div>
                                    <small class="text-muted"><?php echo date('M d', strtotime($expense['expense_date'])); ?></small>
                                    <div class="fw-bold small"><?php echo $expense['category']; ?></div>
                                </div>
                                <span class="badge bg-warning"><?php echo $functions->formatCurrency($expense['amount']); ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Deposits -->
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-3"><i class="fas fa-wallet me-2"></i>Recent Deposits</h6>
                        <div class="list-group list-group-flush">
                            <?php if (empty($recent_deposits)): ?>
                            <div class="text-center text-muted py-3">No recent deposits</div>
                            <?php else: ?>
                            <?php foreach ($recent_deposits as $deposit): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                <div>
                                    <small class="text-muted"><?php echo date('M d', strtotime($deposit['deposit_date'])); ?></small>
                                    <div class="fw-bold small"><?php echo htmlspecialchars($deposit['name']); ?></div>
                                </div>
                                <span class="badge bg-info"><?php echo $functions->formatCurrency($deposit['amount']); ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Members without accounts -->
                <?php if (count($members_no_account) > 0): ?>
                <div class="mt-4 pt-3 border-top">
                    <h6 class="text-muted mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Members Need Accounts</h6>
                    <div class="row">
                        <?php foreach ($members_no_account as $member): ?>
                        <div class="col-12 mb-2">
                            <div class="card border-warning">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['name']); ?></small>
                                            <?php if ($member['phone']): ?>
                                            <div><small class="text-muted"><?php echo $member['phone']; ?></small></div>
                                            <?php endif; ?>
                                        </div>
                                        <a href="generate_link.php?member=<?php echo $member['member_id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            Generate Link
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($members_no_account) >= 5): ?>
                    <div class="text-center mt-2">
                        <a href="generate_link.php" class="btn btn-sm btn-outline-warning">
                            View All <?php echo count($members_no_account); ?> members
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Expense Breakdown & Monthly Report -->
<div class="row">
    <!-- Expense Breakdown -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-pie me-2"></i>Expense Breakdown (<?php echo date('F Y'); ?>)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($expense_breakdown['breakdown'])): ?>
                <div class="text-center text-muted py-3">No expenses this month</div>
                <?php else: ?>
                <?php foreach ($expense_breakdown['breakdown'] as $item): 
                    $percentage = ($expense_breakdown['total'] ?? 0) > 0 ? ($item['total'] / $expense_breakdown['total'] * 100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-bold"><?php echo $item['category']; ?></span>
                        <span><?php echo $functions->formatCurrency($item['total']); ?></span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped" role="progressbar" 
                             style="width: <?php echo $percentage; ?>%; background-color: <?php echo getCategoryColorHex($item['category']); ?>" 
                             aria-valuenow="<?php echo $percentage; ?>" 
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted"><?php echo number_format($percentage, 1); ?>% of total</small>
                </div>
                <?php endforeach; ?>
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between">
                        <strong>Total Expenses:</strong>
                        <strong><?php echo $functions->formatCurrency($expense_breakdown['total'] ?? 0); ?></strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Monthly Report Generator -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar me-2"></i>Generate Monthly Report</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="monthly_report.php" class="row g-3">
                    <input type="hidden" name="house_id" value="<?php echo $house_id; ?>">
                    
                    <div class="col-md-6">
                        <label for="month" class="form-label">Month</label>
                        <select name="month" class="form-select" required>
                            <option value="">Select Month</option>
                            <?php
                            $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                     'July', 'August', 'September', 'October', 'November', 'December'];
                            foreach ($months as $index => $month):
                                $month_num = $index + 1;
                            ?>
                            <option value="<?php echo $month_num; ?>" <?php echo $month_num == date('n') ? 'selected' : ''; ?>>
                                <?php echo $month; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="year" class="form-label">Year</label>
                        <select name="year" class="form-select" required>
                            <option value="">Select Year</option>
                            <?php
                            $current_year = date('Y');
                            for ($y = $current_year; $y >= $current_year - 5; $y--):
                            ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-chart-bar me-2"></i>Generate Report
                        </button>
                    </div>
                    
                    <div class="col-12">
                        <div class="alert alert-info mt-3">
                            <h6><i class="fas fa-info-circle me-2"></i>Report Includes:</h6>
                            <ul class="mb-0">
                                <li>Member-wise meal count and cost</li>
                                <li>Total expenses and meal rate</li>
                                <li>Deposit summary and balance calculation</li>
                                <li>Due/Return amounts for each member</li>
                                <li>Printable format</li>
                            </ul>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- House Information -->
<?php if ($house_info): ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-home me-2"></i>House Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>House Name:</strong> <?php echo htmlspecialchars($house_info['house_name']); ?></p>
                        <p><strong>House Code:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($house_info['house_code']); ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Active Members:</strong> <?php echo $stats['total_members']; ?></p>
                        <p><strong>Current Month:</strong> <?php echo date('F Y'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Helper function for category colors (hex format for progress bars)
function getCategoryColorHex($category) {
    $colors = [
        'Rice' => '#3498db',      // Blue
        'Fish' => '#17a2b8',      // Cyan
        'Meat' => '#e74c3c',      // Red
        'Vegetables' => '#27ae60', // Green
        'Spices' => '#f39c12',       // Orange
        'Oil' => '#6c757d',   // Gray
        'Food' => '#2c3e50',    // Dark
        'Others' => '#95a5a6'     // Light gray
    ];
    return $colors[$category] ?? '#95a5a6';
}
?>

<script>
// Auto-refresh dashboard every 5 minutes (optional)
setTimeout(function() {
    window.location.reload();
}, 300000); // 5 minutes

// Show welcome notification
window.onload = function() {
    const welcomeMessage = "Welcome to your dashboard! Manage your house activities here.";
    console.log(welcomeMessage);
};
</script>

<?php 
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>