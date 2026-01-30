<?php
// dashboard.php - Add at the beginning
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$auth->requireRole('manager');

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Check if user has a house
$sql = "SELECT house_id FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);

// If no house, redirect to setup
if (!$user_data || !$user_data['house_id']) {
    $_SESSION['redirect_to'] = 'dashboard.php';
    header("Location: setup_house.php");
    exit();
}

// Set house_id
$house_id = $user_data['house_id'];
$_SESSION['house_id'] = $house_id;

// Rest of your dashboard.php code...
$page_title = "Dashboard";
require_once '../includes/header.php';
?>

<!-- Your existing dashboard HTML/PHP code -->
<div class="row">
    <!-- Stats Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Members</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_members']; ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-success mr-2"><i class="fas fa-check-circle"></i> Active</span>
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
                            <span class="text-info mr-2"><i class="fas fa-utensils"></i> Today: <?php echo $stats['today_meals']; ?></span>
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
                            <span class="text-warning mr-2"><i class="fas fa-money-bill-wave"></i> Current</span>
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
                            <span class="text-info mr-2"><i class="fas fa-calculator"></i> Per Meal</span>
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
                        <a href="add_meal.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Add Meals
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="add_expense.php" class="btn btn-success w-100">
                            <i class="fas fa-money-bill-wave me-2"></i>Add Expense
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="add_deposit.php" class="btn btn-warning w-100">
                            <i class="fas fa-wallet me-2"></i>Add Deposit
                        </a>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <a href="add_member.php" class="btn btn-info w-100">
                            <i class="fas fa-user-plus me-2"></i>Add Member
                        </a>
                    </div>
                </div>
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
    
    <!-- Recent Expenses -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-money-bill-wave me-2"></i>Recent Expenses</h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php if (empty($recent_expenses)): ?>
                    <div class="text-center text-muted py-3">No expenses found</div>
                    <?php else: ?>
                    <?php foreach ($recent_expenses as $expense): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($expense['category']); ?></h6>
                            <small class="text-muted"><?php echo $functions->formatDate($expense['expense_date']); ?></small>
                        </div>
                        <span class="badge bg-warning rounded-pill"><?php echo $functions->formatCurrency($expense['amount']); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Expense Summary -->
                <div class="mt-4">
                    <h6 class="text-muted mb-3">Expense Breakdown</h6>
                    <?php if (empty($expense_breakdown['breakdown'])): ?>
                    <div class="text-center text-muted py-3">No expenses this month</div>
                    <?php else: ?>
                    <?php foreach ($expense_breakdown['breakdown'] as $item): 
                        $percentage = $expense_breakdown['total'] > 0 ? ($item['total'] / $expense_breakdown['total'] * 100) : 0;
                    ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?php echo $item['category']; ?></span>
                            <span><?php echo $functions->formatCurrency($item['total']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo $percentage; ?>%;" 
                                 aria-valuenow="<?php echo $percentage; ?>" 
                                 aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong><?php echo $functions->formatCurrency($expense_breakdown['total']); ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Report Generator -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar me-2"></i>Generate Monthly Report</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="monthly_report.php" class="row g-3">
                    <div class="col-md-4">
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
                    <div class="col-md-4">
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
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-chart-bar me-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>