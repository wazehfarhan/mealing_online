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

$page_title = "My Profile";

$conn = getConnection();
$member_id = $_SESSION['member_id'];

// Get member info
$sql = "SELECT m.*, u.username, u.email as user_email, u.last_login 
        FROM members m 
        JOIN users u ON m.member_id = u.member_id 
        WHERE m.member_id = ?";
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

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Validate email
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Update member info
        $update_sql = "UPDATE members SET phone = ?, email = ? WHERE member_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssi", $phone, $email, $member_id);
        
        // Update user email if changed
        if ($email != $member['user_email']) {
            $user_sql = "UPDATE users SET email = ? WHERE member_id = ?";
            $user_stmt = mysqli_prepare($conn, $user_sql);
            mysqli_stmt_bind_param($user_stmt, "si", $email, $member_id);
            mysqli_stmt_execute($user_stmt);
        }
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Profile updated successfully!";
            
            // Refresh member data
            $sql = "SELECT m.*, u.username, u.email as user_email, u.last_login 
                    FROM members m 
                    JOIN users u ON m.member_id = u.member_id 
                    WHERE m.member_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $member_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $member = mysqli_fetch_assoc($result);
        } else {
            $error = "Error updating profile: " . mysqli_error($conn);
        }
    }
}

// Get member statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM meals WHERE member_id = ?) as total_meals,
                (SELECT COUNT(DISTINCT DATE_FORMAT(meal_date, '%Y-%m')) FROM meals WHERE member_id = ?) as active_months,
                (SELECT SUM(amount) FROM deposits WHERE member_id = ?) as total_deposits,
                (SELECT COUNT(*) FROM deposits WHERE member_id = ?) as deposit_count";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "iiii", $member_id, $member_id, $member_id, $member_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get current month stats
$current_month = date('m');
$current_year = date('Y');
$month_start = "$current_year-$current_month-01";
$month_end = date('Y-m-t', strtotime($month_start));

$month_sql = "SELECT 
                (SELECT SUM(meal_count) FROM meals WHERE member_id = ? AND meal_date BETWEEN ? AND ?) as month_meals,
                (SELECT SUM(amount) FROM deposits WHERE member_id = ? AND deposit_date BETWEEN ? AND ?) as month_deposits";
$month_stmt = mysqli_prepare($conn, $month_sql);
mysqli_stmt_bind_param($month_stmt, "ississ", $member_id, $month_start, $month_end, $member_id, $month_start, $month_end);
mysqli_stmt_execute($month_stmt);
$month_result = mysqli_stmt_get_result($month_stmt);
$month_stats = mysqli_fetch_assoc($month_result);
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">My Profile</h4>
                <p class="text-muted mb-0">Manage your account information</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Information</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['name']); ?>" readonly>
                            <div class="form-text">Your name (cannot be changed)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Join Date</label>
                            <input type="text" class="form-control" value="<?php echo $functions->formatDate($member['join_date']); ?>" readonly>
                            <div class="form-text">Date you joined the meal system</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($member['phone']); ?>">
                            <div class="form-text">Your contact number</div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($member['email'] ?: $member['user_email']); ?>">
                            <div class="form-text">Your email address for notifications</div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['username']); ?>" readonly>
                            <div class="form-text">Your login username (cannot be changed)</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Login</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo $member['last_login'] ? date('M d, Y h:i A', strtotime($member['last_login'])) : 'Never'; ?>" 
                                   readonly>
                            <div class="form-text">Last time you logged in</div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                        <a href="../auth/change_password.php" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Change Password
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Statistics -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Account Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-6 mb-3">
                        <div class="card border-primary text-center">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Total Meals</h6>
                                <h3 class="text-primary mb-0"><?php echo $stats['total_meals'] ?: 0; ?></h3>
                                <small class="text-muted">All time</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="card border-success text-center">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Active Months</h6>
                                <h3 class="text-success mb-0"><?php echo $stats['active_months'] ?: 0; ?></h3>
                                <small class="text-muted">With meals</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="card border-warning text-center">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Total Deposits</h6>
                                <h3 class="text-warning mb-0"><?php echo $functions->formatCurrency($stats['total_deposits'] ?: 0); ?></h3>
                                <small class="text-muted">All time</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-6 mb-3">
                        <div class="card border-info text-center">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Deposit Count</h6>
                                <h3 class="text-info mb-0"><?php echo $stats['deposit_count'] ?: 0; ?></h3>
                                <small class="text-muted">Times deposited</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h6 class="mb-3 text-muted">Current Month (<?php echo date('F Y'); ?>)</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card border-primary-subtle">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">This Month's Meals</h6>
                                        <h4 class="text-primary mb-0"><?php echo $month_stats['month_meals'] ?: 0; ?></h4>
                                    </div>
                                    <div class="bg-primary text-white rounded-circle p-3">
                                        <i class="fas fa-utensils fa-2x"></i>
                                    </div>
                                </div>
                                <small class="text-muted">From <?php echo date('M d', strtotime($month_start)); ?> to <?php echo date('M d', strtotime($month_end)); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card border-success-subtle">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">This Month's Deposits</h6>
                                        <h4 class="text-success mb-0"><?php echo $functions->formatCurrency($month_stats['month_deposits'] ?: 0); ?></h4>
                                    </div>
                                    <div class="bg-success text-white rounded-circle p-3">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                                <small class="text-muted">From <?php echo date('M d', strtotime($month_start)); ?> to <?php echo date('M d', strtotime($month_end)); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions & Information -->
    <div class="col-lg-4 mb-4">
        <!-- Account Status -->
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Status</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                        <div class="bg-<?php echo $member['status'] == 'active' ? 'success' : ($member['status'] == 'inactive' ? 'warning' : 'danger'); ?> text-white rounded-circle p-3">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                    </div>
                    <div>
                        <h6 class="mb-1">Status: 
                            <span class="badge bg-<?php echo $member['status'] == 'active' ? 'success' : ($member['status'] == 'inactive' ? 'warning' : 'danger'); ?>">
                                <?php echo ucfirst($member['status']); ?>
                            </span>
                        </h6>
                        <p class="text-muted mb-0 small">
                            <?php if ($member['status'] == 'active'): ?>
                                Your account is active and you can use all features.
                            <?php elseif ($member['status'] == 'inactive'): ?>
                                Your account is inactive. Contact administrator.
                            <?php else: ?>
                                Your account is suspended. Contact administrator.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <?php if ($member['balance'] !== null): ?>
                <div class="mt-4 pt-3 border-top">
                    <h6 class="text-muted mb-2">Current Balance</h6>
                    <h2 class="text-<?php echo $member['balance'] >= 0 ? 'success' : 'danger'; ?> mb-0">
                        <?php echo $functions->formatCurrency($member['balance']); ?>
                    </h2>
                    <small class="text-muted">Available balance for meals</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="deposit.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <div class="bg-primary text-white rounded-circle p-2 me-3">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Make Deposit</h6>
                            <small class="text-muted">Add funds to your account</small>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                    </a>
                    
                    <a href="meals.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <div class="bg-success text-white rounded-circle p-2 me-3">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">View Meals</h6>
                            <small class="text-muted">Check your meal history</small>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                    </a>
                    
                    <a href="reports.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <div class="bg-info text-white rounded-circle p-2 me-3">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">View Reports</h6>
                            <small class="text-muted">See your monthly reports</small>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                    </a>
                    
                    <a href="../auth/logout.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <div class="bg-danger text-white rounded-circle p-2 me-3">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Logout</h6>
                            <small class="text-muted">Sign out from your account</small>
                        </div>
                        <i class="fas fa-chevron-right ms-auto text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php
                // Get recent meals
                $recent_sql = "SELECT * FROM meals WHERE member_id = ? ORDER BY meal_date DESC LIMIT 5";
                $recent_stmt = mysqli_prepare($conn, $recent_sql);
                mysqli_stmt_bind_param($recent_stmt, "i", $member_id);
                mysqli_stmt_execute($recent_stmt);
                $recent_result = mysqli_stmt_get_result($recent_stmt);
                
                if (mysqli_num_rows($recent_result) > 0):
                ?>
                <div class="timeline">
                    <?php while ($activity = mysqli_fetch_assoc($recent_result)): ?>
                    <div class="timeline-item mb-3">
                        <div class="d-flex">
                            <div class="timeline-icon bg-primary text-white rounded-circle p-2 me-3">
                                <i class="fas fa-utensil-spoon"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Meal Recorded</h6>
                                <p class="text-muted mb-1"><?php echo $activity['meal_count']; ?> meal(s) on <?php echo date('M d, Y', strtotime($activity['meal_date'])); ?></p>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($activity['created_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No recent activity found</p>
                </div>
                <?php endif; ?>
                
                <a href="meals.php" class="btn btn-outline-primary btn-sm w-100 mt-3">
                    <i class="fas fa-eye me-2"></i>View All Activity
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>