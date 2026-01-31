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

// Get current month stats
$current_month = date('Y-m-01');
$next_month = date('Y-m-01', strtotime('+1 month'));

// Total meals for current month
$meals_sql = "SELECT SUM(meal_count) as total_meals FROM meals 
              WHERE member_id = ? AND meal_date >= ? AND meal_date < ?";
$meals_stmt = mysqli_prepare($conn, $meals_sql);
mysqli_stmt_bind_param($meals_stmt, "iss", $member_id, $current_month, $next_month);
mysqli_stmt_execute($meals_stmt);
$meals_result = mysqli_stmt_get_result($meals_stmt);
$meals_data = mysqli_fetch_assoc($meals_result);
$total_meals = $meals_data['total_meals'] ?? 0;

// Total deposits for current month
$deposits_sql = "SELECT SUM(amount) as total_deposits FROM deposits 
                 WHERE member_id = ? AND deposit_date >= ? AND deposit_date < ?";
$deposits_stmt = mysqli_prepare($conn, $deposits_sql);
mysqli_stmt_bind_param($deposits_stmt, "iss", $member_id, $current_month, $next_month);
mysqli_stmt_execute($deposits_stmt);
$deposits_result = mysqli_stmt_get_result($deposits_stmt);
$deposits_data = mysqli_fetch_assoc($deposits_result);
$total_deposits = $deposits_data['total_deposits'] ?? 0;

// Get recent meals (last 7 days)
$recent_meals_sql = "SELECT meal_date, meal_count FROM meals 
                     WHERE member_id = ? AND meal_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     ORDER BY meal_date DESC LIMIT 7";
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

// Get monthly summary if available
$summary_sql = "SELECT ms.*, mmd.total_meals as member_meals, mmd.total_deposits as member_deposits, 
                       mmd.total_cost, mmd.balance
                FROM monthly_summary ms
                LEFT JOIN monthly_member_details mmd ON ms.summary_id = mmd.summary_id
                WHERE ms.house_id = ? AND mmd.member_id = ? 
                ORDER BY ms.month_year DESC LIMIT 1";
$summary_stmt = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($summary_stmt, "ii", $house_id, $member_id);
mysqli_stmt_execute($summary_stmt);
$summary_result = mysqli_stmt_get_result($summary_stmt);
$monthly_summary = mysqli_fetch_assoc($summary_result);
?>

<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-home me-2"></i>Welcome, <?php echo htmlspecialchars($member['name']); ?>!</h5>
                <span class="badge bg-primary">
                    <i class="fas fa-house-user me-1"></i>
                    <?php echo htmlspecialchars($house['house_name']); ?> (<?php echo $house['house_code']; ?>)
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stat-card border-success">
                            <div class="card-body text-center">
                                <div class="card-icon text-success mb-3">
                                    <i class="fas fa-utensil-spoon"></i>
                                </div>
                                <h3 class="card-title"><?php echo number_format($total_meals, 2); ?></h3>
                                <p class="text-muted mb-0">This Month's Meals</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card border-primary">
                            <div class="card-body text-center">
                                <div class="card-icon text-primary mb-3">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <h3 class="card-title">৳<?php echo number_format($total_deposits, 2); ?></h3>
                                <p class="text-muted mb-0">This Month's Deposits</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($monthly_summary): ?>
                    <div class="col-md-3">
                        <div class="card stat-card border-info">
                            <div class="card-body text-center">
                                <div class="card-icon text-info mb-3">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <h3 class="card-title">৳<?php echo number_format($monthly_summary['meal_rate'], 2); ?></h3>
                                <p class="text-muted mb-0">Meal Rate</p>
                                <small class="text-muted"><?php echo date('F Y', strtotime($monthly_summary['month_year'])); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card <?php echo $monthly_summary['balance'] >= 0 ? 'border-success' : 'border-danger'; ?>">
                            <div class="card-body text-center">
                                <div class="card-icon <?php echo $monthly_summary['balance'] >= 0 ? 'text-success' : 'text-danger'; ?> mb-3">
                                    <i class="fas fa-balance-scale"></i>
                                </div>
                                <h3 class="card-title <?php echo $monthly_summary['balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                    ৳<?php echo number_format(abs($monthly_summary['balance']), 2); ?>
                                    <?php echo $monthly_summary['balance'] >= 0 ? '' : '-'; ?>
                                </h3>
                                <p class="text-muted mb-0">Balance</p>
                                <small class="text-muted"><?php echo date('F Y', strtotime($monthly_summary['month_year'])); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Meals -->
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-utensil-spoon me-2"></i>Recent Meals (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_meals)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Meal Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_meals as $meal): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($meal['meal_date'])); ?></td>
                                <td><?php echo date('l', strtotime($meal['meal_date'])); ?></td>
                                <td><strong><?php echo $meal['meal_count']; ?></strong> meals</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-utensil-spoon fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No meal records found for the last 7 days</p>
                    <a href="meals.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Meal
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Deposits -->
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Recent Deposits</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_deposits)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_deposits as $deposit): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($deposit['deposit_date'])); ?></td>
                                <td><strong class="text-success">৳<?php echo number_format($deposit['amount'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($deposit['description'] ?? 'Deposit'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-wallet fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No deposit records found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Member Information -->
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card border-light mb-3">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Member Details</h6>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($member['name']); ?></p>
                                <?php if ($member['email']): ?>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($member['email']); ?></p>
                                <?php endif; ?>
                                <?php if ($member['phone']): ?>
                                <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($member['phone']); ?></p>
                                <?php endif; ?>
                                <p class="mb-0"><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($member['join_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-light mb-3">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">House Information</h6>
                                <p class="mb-1"><strong>House:</strong> <?php echo htmlspecialchars($house['house_name']); ?></p>
                                <p class="mb-1"><strong>House Code:</strong> <?php echo $house['house_code']; ?></p>
                                <p class="mb-0"><strong>Member ID:</strong> M<?php echo str_pad($member_id, 4, '0', STR_PAD_LEFT); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-light mb-3">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Quick Actions</h6>
                                <div class="d-grid gap-2">
                                    <a href="profile.php" class="btn btn-outline-primary">
                                        <i class="fas fa-user-edit me-2"></i>Update Profile
                                    </a>
                                    <a href="deposit.php" class="btn btn-outline-success">
                                        <i class="fas fa-money-bill-wave me-2"></i>Make Deposit
                                    </a>
                                    <a href="report.php" class="btn btn-outline-info">
                                        <i class="fas fa-file-alt me-2"></i>View Full Report
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close statements
if (isset($stmt)) mysqli_stmt_close($stmt);
if (isset($house_stmt)) mysqli_stmt_close($house_stmt);
if (isset($meals_stmt)) mysqli_stmt_close($meals_stmt);
if (isset($deposits_stmt)) mysqli_stmt_close($deposits_stmt);
if (isset($recent_meals_stmt)) mysqli_stmt_close($recent_meals_stmt);
if (isset($recent_deposits_stmt)) mysqli_stmt_close($recent_deposits_stmt);
if (isset($summary_stmt)) mysqli_stmt_close($summary_stmt);
mysqli_close($conn);

require_once '../includes/footer.php';
?>