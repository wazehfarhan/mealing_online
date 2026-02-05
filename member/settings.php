<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('member');

$page_title = "Settings";

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$member_id = $_SESSION['member_id'];
$house_id = $_SESSION['house_id'];

$errors = [];
$success = '';

// Get current user information
$sql = "SELECT u.username, u.email, u.created_at, u.last_login, u.is_active,
               m.member_id, m.name, m.phone, m.email as member_email, m.join_date, m.status
        FROM users u 
        JOIN members m ON u.member_id = m.member_id 
        WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Get house information
$house_sql = "SELECT house_name, house_code FROM houses WHERE house_id = ?";
$house_stmt = mysqli_prepare($conn, $house_sql);
mysqli_stmt_bind_param($house_stmt, "i", $house_id);
mysqli_stmt_execute($house_stmt);
$house_result = mysqli_stmt_get_result($house_stmt);
$house = mysqli_fetch_assoc($house_result);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle password change
    if (isset($_POST['change_password'])) {
        // First get the current password hash
        $password_sql = "SELECT password FROM users WHERE user_id = ?";
        $password_stmt = mysqli_prepare($conn, $password_sql);
        mysqli_stmt_bind_param($password_stmt, "i", $user_id);
        mysqli_stmt_execute($password_stmt);
        $password_result = mysqli_stmt_get_result($password_stmt);
        $password_data = mysqli_fetch_assoc($password_result);
        
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate
        if (empty($current_password)) {
            $errors[] = "Current password is required.";
        } elseif (empty($new_password)) {
            $errors[] = "New password is required.";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match.";
        } else {
            // Verify current password
            if (password_verify($current_password, $password_data['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $user_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success = "Password changed successfully!";
                } else {
                    $errors[] = "Failed to update password.";
                }
            } else {
                $errors[] = "Current password is incorrect.";
            }
        }
    }
    
    // Handle data export
    if (isset($_POST['export_data'])) {
        $export_type = $_POST['export_type'] ?? 'all';
        
        // Generate CSV file
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="member_data_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($export_type == 'all' || $export_type == 'meals') {
            fputcsv($output, ['Meal History']);
            fputcsv($output, ['Date', 'Meal Count', 'Recorded At']);
            
            $meals_sql = "SELECT meal_date, meal_count, created_at FROM meals 
                          WHERE member_id = ? AND house_id = ? 
                          ORDER BY meal_date DESC";
            $meals_stmt = mysqli_prepare($conn, $meals_sql);
            mysqli_stmt_bind_param($meals_stmt, "ii", $member_id, $house_id);
            mysqli_stmt_execute($meals_stmt);
            $meals_result = mysqli_stmt_get_result($meals_stmt);
            
            if (mysqli_num_rows($meals_result) > 0) {
                while ($meal = mysqli_fetch_assoc($meals_result)) {
                    fputcsv($output, [
                        $meal['meal_date'],
                        $meal['meal_count'],
                        $meal['created_at']
                    ]);
                }
            } else {
                fputcsv($output, ['No meal records found']);
            }
            
            fputcsv($output, []); // Empty row for separation
        }
        
        if ($export_type == 'all' || $export_type == 'deposits') {
            fputcsv($output, ['Deposit History']);
            fputcsv($output, ['Date', 'Amount', 'Description', 'Recorded At']);
            
            $deposits_sql = "SELECT deposit_date, amount, description, created_at FROM deposits 
                             WHERE member_id = ? AND house_id = ? 
                             ORDER BY deposit_date DESC";
            $deposits_stmt = mysqli_prepare($conn, $deposits_sql);
            mysqli_stmt_bind_param($deposits_stmt, "ii", $member_id, $house_id);
            mysqli_stmt_execute($deposits_stmt);
            $deposits_result = mysqli_stmt_get_result($deposits_stmt);
            
            if (mysqli_num_rows($deposits_result) > 0) {
                while ($deposit = mysqli_fetch_assoc($deposits_result)) {
                    fputcsv($output, [
                        $deposit['deposit_date'],
                        $deposit['amount'],
                        $deposit['description'] ?? '',
                        $deposit['created_at']
                    ]);
                }
            } else {
                fputcsv($output, ['No deposit records found']);
            }
            
            fputcsv($output, []); // Empty row for separation
        }
        
        if ($export_type == 'all' || $export_type == 'summary') {
            fputcsv($output, ['Account Summary']);
            fputcsv($output, ['Member Name', 'House', 'Total Deposits', 'Total Meals', 'Last Updated']);
            
            // Get total deposits
            $total_deposits_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM deposits WHERE member_id = ?";
            $total_deposits_stmt = mysqli_prepare($conn, $total_deposits_sql);
            mysqli_stmt_bind_param($total_deposits_stmt, "i", $member_id);
            mysqli_stmt_execute($total_deposits_stmt);
            $total_deposits_result = mysqli_stmt_get_result($total_deposits_stmt);
            $total_deposits = mysqli_fetch_assoc($total_deposits_result);
            
            // Get total meals count
            $total_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as total FROM meals WHERE member_id = ?";
            $total_meals_stmt = mysqli_prepare($conn, $total_meals_sql);
            mysqli_stmt_bind_param($total_meals_stmt, "i", $member_id);
            mysqli_stmt_execute($total_meals_stmt);
            $total_meals_result = mysqli_stmt_get_result($total_meals_stmt);
            $total_meals = mysqli_fetch_assoc($total_meals_result);
            
            fputcsv($output, [
                $user['name'] ?? '',
                $house['house_name'] ?? '',
                number_format($total_deposits['total'] ?? 0, 2),
                $total_meals['total'] ?? '0',
                date('Y-m-d H:i:s')
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// Get statistics for display
$meals_count_sql = "SELECT COUNT(*) as count, COALESCE(SUM(meal_count), 0) as total_meals FROM meals WHERE member_id = ?";
$meals_stmt = mysqli_prepare($conn, $meals_count_sql);
mysqli_stmt_bind_param($meals_stmt, "i", $member_id);
mysqli_stmt_execute($meals_stmt);
$meals_result = mysqli_stmt_get_result($meals_stmt);
$meals_stats = mysqli_fetch_assoc($meals_result);

$deposits_count_sql = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount FROM deposits WHERE member_id = ?";
$deposits_stmt = mysqli_prepare($conn, $deposits_count_sql);
mysqli_stmt_bind_param($deposits_stmt, "i", $member_id);
mysqli_stmt_execute($deposits_stmt);
$deposits_result = mysqli_stmt_get_result($deposits_stmt);
$deposits_stats = mysqli_fetch_assoc($deposits_result);
?>

<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-cog me-2"></i>Settings
            </h1>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Display Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php foreach ($errors as $error): ?>
            <div><?php echo $error; ?></div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Settings Cards -->
        <div class="row">
            <!-- Account Information -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user-circle me-2"></i>Account Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%">Member ID:</th>
                                    <td><span class="badge bg-secondary">M<?php echo str_pad($member_id, 4, '0', STR_PAD_LEFT); ?></span></td>
                                </tr>
                                <tr>
                                    <th>Username:</th>
                                    <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                    <th>Full Name:</th>
                                    <td><?php echo !empty($user['name']) ? htmlspecialchars($user['name']) : 'Not set'; ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not set'; ?></td>
                                </tr>
                                <tr>
                                    <th>House:</th>
                                    <td><?php echo htmlspecialchars($house['house_name'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                    <th>House Code:</th>
                                    <td><span class="badge bg-info"><?php echo $house['house_code'] ?? ''; ?></span></td>
                                </tr>
                                <tr>
                                    <th>Joined:</th>
                                    <td><?php echo !empty($user['join_date']) ? date('M d, Y', strtotime($user['join_date'])) : 'Unknown'; ?></td>
                                </tr>
                                <tr>
                                    <th>Member Status:</th>
                                    <td>
                                        <span class="badge bg-<?php echo ($user['status'] == 'active') ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Account Status:</th>
                                    <td>
                                        <span class="badge bg-<?php echo ($user['is_active'] ?? 1) ? 'success' : 'danger'; ?>">
                                            <?php echo ($user['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Last Login:</th>
                                    <td><?php echo !empty($user['last_login']) ? date('M d, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="profile.php" class="btn btn-outline-primary">
                                <i class="fas fa-edit me-2"></i>Edit Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-key me-2"></i>Change Password
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" 
                                           name="current_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimum 6 characters</div>
                                <div class="progress mt-2" id="passwordStrength" style="height: 5px; display: none;">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Make sure your new password is strong and different from your previous passwords.
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Data Management -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-database me-2"></i>Data Management
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="export_type" class="form-label">Export Data</label>
                                <select class="form-select" id="export_type" name="export_type">
                                    <option value="all">All Data (Meals & Deposits)</option>
                                    <option value="meals">Meal History Only</option>
                                    <option value="deposits">Deposit History Only</option>
                                    <option value="summary">Account Summary</option>
                                </select>
                                <div class="form-text">Download your data as CSV file</div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                This will generate a CSV file with your selected data. The download will start immediately.
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="export_data" class="btn btn-success">
                                    <i class="fas fa-download me-2"></i>Export Data
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <h6 class="mb-3">Data Statistics</h6>
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body p-3">
                                        <h3 class="text-primary mb-1"><?php echo $meals_stats['count'] ?? 0; ?></h3>
                                        <small class="text-muted">Meal Records</small>
                                        <br>
                                        <small class="text-muted"><?php echo $meals_stats['total_meals'] ?? 0; ?> total meals</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="card border-success">
                                    <div class="card-body p-3">
                                        <h3 class="text-success mb-1"><?php echo $deposits_stats['count'] ?? 0; ?></h3>
                                        <small class="text-muted">Deposit Records</small>
                                        <br>
                                        <small class="text-muted">$<?php echo number_format($deposits_stats['total_amount'] ?? 0, 2); ?> total</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Security & Session -->
            <div class="col-md-6 mb-4">
                <div class="card shadow border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i>Security & Session
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Security Information</h6>
                            <ul class="mb-0">
                                <li>Account created: <?php echo !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'Unknown'; ?></li>
                                <li>Current session: <?php echo date('M d, Y g:i A'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Security Tips</h6>
                            <ul class="mb-0">
                                <li>Change your password every 90 days</li>
                                <li>Use a combination of letters, numbers, and symbols</li>
                                <li>Never share your password with anyone</li>
                                <li>Log out when using public computers</li>
                            </ul>
                        </div>
                        
                        <div class="d-grid gap-3">
                            <a href="../auth/logout.php" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                            
                            <a href="../auth/forgot_password.php" class="btn btn-outline-warning">
                                <i class="fas fa-question-circle me-2"></i>Forgot Password?
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    function togglePasswordVisibility(inputId, button) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            button.innerHTML = '<i class="fas fa-eye-slash"></i>';
        } else {
            input.type = 'password';
            button.innerHTML = '<i class="fas fa-eye"></i>';
        }
    }
    
    // Setup toggle buttons
    document.getElementById('toggleCurrentPassword')?.addEventListener('click', function() {
        togglePasswordVisibility('current_password', this);
    });
    
    document.getElementById('toggleNewPassword')?.addEventListener('click', function() {
        togglePasswordVisibility('new_password', this);
    });
    
    document.getElementById('toggleConfirmPassword')?.addEventListener('click', function() {
        togglePasswordVisibility('confirm_password', this);
    });
    
    // Password strength checker
    const newPasswordInput = document.getElementById('new_password');
    const passwordStrengthBar = document.getElementById('passwordStrength');
    
    if (newPasswordInput && passwordStrengthBar) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const bar = passwordStrengthBar.querySelector('.progress-bar');
            
            if (password.length === 0) {
                passwordStrengthBar.style.display = 'none';
                return;
            }
            
            passwordStrengthBar.style.display = 'block';
            
            let strength = 0;
            if (password.length >= 6) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            if (/[^A-Za-z0-9]/.test(password)) strength += 25;
            
            bar.style.width = strength + '%';
            
            if (strength < 50) {
                bar.className = 'progress-bar bg-danger';
            } else if (strength < 75) {
                bar.className = 'progress-bar bg-warning';
            } else {
                bar.className = 'progress-bar bg-success';
            }
        });
    }
    
    // Form validation for password change
    const changePasswordForm = document.querySelector('button[name="change_password"]');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('click', function(e) {
            const form = this.closest('form');
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            
            if (!currentPassword) {
                e.preventDefault();
                alert('Current password is required!');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        });
    }
    
    // Export data confirmation
    const exportButton = document.querySelector('button[name="export_data"]');
    if (exportButton) {
        exportButton.addEventListener('click', function(e) {
            if (!confirm('This will download your data as a CSV file. Continue?')) {
                e.preventDefault();
            }
        });
    }
    
    // Logout confirmation
    const logoutLink = document.querySelector('a[href*="logout.php"]');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php
// Close statements
if (isset($stmt)) mysqli_stmt_close($stmt);
if (isset($house_stmt)) mysqli_stmt_close($house_stmt);
if (isset($password_stmt)) mysqli_stmt_close($password_stmt);
if (isset($update_stmt)) mysqli_stmt_close($update_stmt);
if (isset($meals_stmt)) mysqli_stmt_close($meals_stmt);
if (isset($deposits_stmt)) mysqli_stmt_close($deposits_stmt);
if (isset($total_deposits_stmt)) mysqli_stmt_close($total_deposits_stmt);
if (isset($total_meals_stmt)) mysqli_stmt_close($total_meals_stmt);
mysqli_close($conn);

require_once '../includes/footer.php';
?>