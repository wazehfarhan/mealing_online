<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('member');

$page_title = "My Profile";

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$member_id = $_SESSION['member_id'] ?? null;
$house_id = $_SESSION['house_id'] ?? null;

$error = '';
$success = '';

// Get user data
$user_sql = "SELECT u.*, h.house_name, h.house_code 
             FROM users u 
             LEFT JOIN houses h ON u.house_id = h.house_id
             WHERE u.user_id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

// Get member data separately
$member = null;
if ($member_id) {
    $member_sql = "SELECT * FROM members WHERE member_id = ?";
    $member_stmt = mysqli_prepare($conn, $member_sql);
    mysqli_stmt_bind_param($member_stmt, "i", $member_id);
    mysqli_stmt_execute($member_stmt);
    $member_result = mysqli_stmt_get_result($member_stmt);
    $member = mysqli_fetch_assoc($member_result);
}

if (!$user) {
    $error = "User not found. Please contact your house manager.";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_type'])) {
        $form_type = $_POST['form_type'];
        
        if ($form_type === 'change_username') {
            $new_username = trim($_POST['username'] ?? '');
            
            if (empty($new_username)) {
                $error = "Username is required";
            } elseif (strlen($new_username) < 3) {
                $error = "Username must be at least 3 characters";
            } elseif ($new_username === $user['username']) {
                $error = "New username is the same as current username";
            } else {
                // Check if username exists
                $check_sql = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "si", $new_username, $user_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "Username already taken";
                } else {
                    $update_sql = "UPDATE users SET username = ? WHERE user_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "si", $new_username, $user_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $_SESSION['username'] = $new_username;
                        $user['username'] = $new_username;
                        $success = "Username updated successfully!";
                    } else {
                        $error = "Error updating username";
                    }
                }
            }
        }
        elseif ($form_type === 'update_profile') {
            $new_email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            if (empty($new_email)) {
                $error = "Email is required";
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format";
            } else {
                // Check if email exists
                $check_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "si", $new_email, $user_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "Email already registered";
                } else {
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Update users table
                        $sql1 = "UPDATE users SET email = ? WHERE user_id = ?";
                        $stmt1 = mysqli_prepare($conn, $sql1);
                        mysqli_stmt_bind_param($stmt1, "si", $new_email, $user_id);
                        mysqli_stmt_execute($stmt1);
                        
                        // Update members table if exists
                        if ($member && $member_id) {
                            $sql2 = "UPDATE members SET email = ?, phone = ? WHERE member_id = ?";
                            $stmt2 = mysqli_prepare($conn, $sql2);
                            mysqli_stmt_bind_param($stmt2, "ssi", $new_email, $phone, $member_id);
                            mysqli_stmt_execute($stmt2);
                            
                            $member['email'] = $new_email;
                            $member['phone'] = $phone;
                        }
                        
                        mysqli_commit($conn);
                        
                        $_SESSION['email'] = $new_email;
                        $user['email'] = $new_email;
                        $success = "Profile updated successfully!";
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error = "Error updating profile";
                    }
                }
            }
        }
        elseif ($form_type === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            
            if (empty($current) || empty($new) || empty($confirm)) {
                $error = "All password fields are required";
            } elseif (strlen($new) < 6) {
                $error = "Password must be at least 6 characters";
            } elseif ($new !== $confirm) {
                $error = "New passwords don't match";
            } elseif ($current === $new) {
                $error = "New password must be different from current password";
            } else {
                if (password_verify($current, $user['password'])) {
                    $hashed = password_hash($new, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET password = ? WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "si", $hashed, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Password changed successfully!";
                    } else {
                        $error = "Error updating password";
                    }
                } else {
                    $error = "Current password is incorrect";
                }
            }
        }
    }
}
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="fas fa-user me-2"></i>My Profile</h2>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Change Username -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>Change Username</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="form_type" value="change_username">
                        <div class="mb-3">
                            <label class="form-label">Current Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Username</label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" 
                                   required minlength="3" maxlength="50">
                            <div class="form-text">Minimum 3 characters</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Username
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Update Profile -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Update Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="form_type" value="update_profile">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <?php if ($member): ?>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($member['name']); ?>" readonly>
                            <div class="form-text">Name can only be changed by house manager</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>"
                                   maxlength="20" placeholder="+880 1XXX-XXXXXX">
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Save Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                        <a href="../auth/forgot_password.php" class="btn btn-outline-dark btn-sm">
                            <i class="fas fa-question-circle me-1"></i>Forgot Password?
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="form_type" value="change_password">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="6">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-lock me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Account Summary -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Account Type:</strong> 
                        <span class="badge bg-<?php echo $user['role'] === 'manager' ? 'primary' : 'success'; ?>">
                            <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                        </span>
                    </p>
                    <p><strong>Account Created:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
                <?php if ($member): ?>
                <div class="col-md-6">
                    <p><strong>Member Name:</strong> <?php echo htmlspecialchars($member['name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($member['phone'] ?? 'Not set'); ?></p>
                    <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($member['join_date'])); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge bg-<?php echo $member['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo htmlspecialchars(ucfirst($member['status'])); ?>
                        </span>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- House Information -->
            <div class="row mt-3">
                <div class="col-md-6">
                    <p><strong>House Name:</strong> <?php echo htmlspecialchars($user['house_name'] ?? 'Not set'); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>House Code:</strong> <?php echo htmlspecialchars($user['house_code'] ?? 'Not set'); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Member ID:</strong> M<?php echo $member_id ? str_pad($member_id, 4, '0', STR_PAD_LEFT) : '0000'; ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>User ID:</strong> U<?php echo str_pad($user['user_id'] ?? '0000', 4, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Account Statistics -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Account Statistics</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3 mb-3">
                    <div class="p-3 border rounded bg-light">
                        <h6 class="text-muted mb-2">Account Created</h6>
                        <h5 class="text-primary">
                            <?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?>
                        </h5>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="p-3 border rounded bg-light">
                        <h6 class="text-muted mb-2">Last Login</h6>
                        <h5 class="text-info">
                            <?php 
                            if (isset($user['last_login']) && $user['last_login'] != '0000-00-00 00:00:00' && $user['last_login'] != null) {
                                echo date('M d, Y h:i A', strtotime($user['last_login']));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </h5>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="p-3 border rounded bg-light">
                        <h6 class="text-muted mb-2">Account Status</h6>
                        <span class="badge bg-<?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'success' : 'danger'; ?> p-2">
                            <?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="p-3 border rounded bg-light">
                        <h6 class="text-muted mb-2">Member Status</h6>
                        <span class="badge bg-<?php echo isset($member['status']) && $member['status'] == 'active' ? 'success' : 'danger'; ?> p-2">
                            <?php echo isset($member['status']) ? ucfirst($member['status']) : 'Unknown'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="d-grid">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="d-grid">
                        <a href="report.php" class="btn btn-outline-info">
                            <i class="fas fa-file-alt me-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Note:</strong> If you need to change your name or other member details, please contact your house manager.
            </div>
        </div>
    </div>
</div>

<script>
// Client-side validation
document.addEventListener('DOMContentLoaded', function() {
    const passwordForm = document.getElementById('passwordForm');
    
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const newPass = this.querySelector('[name="new_password"]').value;
            const confirmPass = this.querySelector('[name="confirm_password"]').value;
            
            if (newPass.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('New passwords do not match.');
                return false;
            }
            
            // Show loading
            const btn = this.querySelector('button[type="submit"]');
            if (btn) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                btn.disabled = true;
                
                setTimeout(function() {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }, 3000);
            }
        });
    }
    
    // Add loading state to all forms
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                btn.disabled = true;
            }
        });
    });
});
</script>

<?php
// Close statements
if (isset($user_stmt)) mysqli_stmt_close($user_stmt);
if (isset($member_stmt)) mysqli_stmt_close($member_stmt);
mysqli_close($conn);

require_once '../includes/footer.php';