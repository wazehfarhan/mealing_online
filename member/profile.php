<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
$page_title = "My Profile";

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('member');
require_once '../includes/header.php';

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
    if (!verifyCSRFToken()) {
        http_response_code(403);
        $error = "Your session security check failed. Please refresh the page and try again.";
        audit_log_event('member.profile.update.rejected', ['reason' => 'csrf_validation_failed']);
    } elseif (isset($_POST['form_type'])) {
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
                        audit_log_event('member.username.update.success', [
                            'changed_fields' => ['username'],
                        ]);
                        $_SESSION['username'] = $new_username;
                        $user['username'] = $new_username;
                        $success = "Username updated successfully!";
                    } else {
                        $error = "Error updating username";
                        audit_log_event('member.username.update.error', [
                            'database_error_code' => mysqli_errno($conn),
                        ]);
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
                        audit_log_event('member.profile.update.success', [
                            'changed_fields' => $member && $member_id
                                ? ['email', 'phone']
                                : ['email'],
                        ]);
                        
                        $_SESSION['email'] = $new_email;
                        $user['email'] = $new_email;
                        $success = "Profile updated successfully!";
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error = "Error updating profile";
                        audit_log_event('member.profile.update.error', ['reason' => 'transaction_failed']);
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
                        audit_log_event('member.password.update.success');
                    } else {
                        $error = "Error updating password";
                        audit_log_event('member.password.update.error', [
                            'database_error_code' => mysqli_errno($conn),
                        ]);
                    }
                } else {
                    $error = "Current password is incorrect";
                    audit_log_event('member.password.update.rejected', ['reason' => 'current_password_mismatch']);
                }
            }
        }
    }
}
?>

<style>
    .profile-page {
        max-width: 1180px;
        padding-top: 1.5rem;
        padding-bottom: 2rem;
    }

    .profile-hero {
        border: 0;
        border-radius: 1rem;
        background: linear-gradient(120deg, #173b67, #2563a6);
        color: #fff;
        overflow: hidden;
    }

    .profile-avatar {
        width: 4.5rem;
        height: 4.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border-radius: 50%;
        background: rgba(255, 255, 255, .16);
        font-size: 1.75rem;
    }

    .profile-page .card {
        height: 100%;
        border: 1px solid #e8edf3;
        border-radius: .9rem;
        box-shadow: 0 .25rem 1rem rgba(23, 59, 103, .06);
    }

    .profile-page .card-header {
        padding: 1.1rem 1.25rem;
        background: #fff;
        border-bottom: 1px solid #edf1f5;
        border-radius: .9rem .9rem 0 0;
    }

    .profile-page .card-body {
        padding: 1.25rem;
    }

    .profile-page .form-control {
        min-height: 2.8rem;
        border-color: #dce3eb;
    }

    .profile-page .form-control:focus {
        border-color: #6ea8fe;
        box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .12);
    }

    .profile-detail {
        height: 100%;
        padding: .9rem 1rem;
        border-radius: .7rem;
        background: #f7f9fc;
    }

    .profile-detail-label {
        display: block;
        margin-bottom: .25rem;
        color: #6c757d;
        font-size: .8rem;
    }

    .profile-stat {
        height: 100%;
        padding: 1rem;
        border: 1px solid #edf1f5;
        border-radius: .75rem;
        background: #fafbfd;
    }

    @media (max-width: 575.98px) {
        .profile-page {
            padding-top: 1rem;
        }

        .profile-hero .card-body,
        .profile-page .card-body {
            padding: 1rem;
        }
    }
</style>

<main class="container-fluid profile-page">
    <section class="card profile-hero mb-4">
        <div class="card-body d-flex align-items-center gap-3 p-4">
            <div class="profile-avatar" aria-hidden="true"><i class="fas fa-user"></i></div>
            <div class="flex-grow-1">
                <p class="mb-1 text-white-50">Member account</p>
                <h1 class="h3 mb-1"><?php echo htmlspecialchars($member['name'] ?? $user['username']); ?></h1>
                <p class="mb-0 text-white-50">
                    <?php echo htmlspecialchars($user['house_name'] ?? 'House not set up'); ?>
                    <?php if (!empty($user['house_code'])): ?>
                        <span class="mx-1">·</span>House code <?php echo htmlspecialchars($user['house_code']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <span class="badge rounded-pill bg-light text-primary px-3 py-2">
                <?php echo htmlspecialchars(ucfirst($user['role'] ?? 'member')); ?>
            </span>
        </div>
    </section>

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

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <section class="card">
                <div class="card-header">
                    <h2 class="h5 mb-1"><i class="fas fa-user-edit text-primary me-2"></i>Username</h2>
                    <p class="text-muted small mb-0">Choose a username you can easily remember.</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="form_type" value="change_username">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="current-username">Current username</label>
                            <input id="current-username" type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="new-username">New username</label>
                            <input id="new-username" type="text" class="form-control" name="username"
                                   value="<?php echo htmlspecialchars($user['username']); ?>"
                                   required minlength="3" maxlength="50" autocomplete="username">
                            <div class="form-text">At least 3 characters.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update username
                        </button>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card">
                <div class="card-header">
                    <h2 class="h5 mb-1"><i class="fas fa-id-card text-success me-2"></i>Contact details</h2>
                    <p class="text-muted small mb-0">Keep your email and phone number up to date.</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="form_type" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="profile-email">Email address</label>
                            <input id="profile-email" type="email" class="form-control" name="email"
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required autocomplete="email">
                        </div>
                        <?php if ($member): ?>
                        <div class="mb-3">
                            <label class="form-label" for="profile-name">Full name</label>
                            <input id="profile-name" type="text" class="form-control"
                                   value="<?php echo htmlspecialchars($member['name']); ?>" readonly>
                            <div class="form-text">Contact your house manager to change your name.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profile-phone">Phone number</label>
                            <input id="profile-phone" type="text" class="form-control" name="phone"
                                   value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>"
                                   maxlength="20" placeholder="+880 1XXX-XXXXXX" autocomplete="tel">
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Save contact details
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <section class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-1"><i class="fas fa-key text-warning me-2"></i>Password &amp; security</h2>
                <p class="text-muted small mb-0">Use a password you do not use on other sites.</p>
            </div>
            <a href="../auth/forgot_password.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-question-circle me-1"></i>Forgot password?
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="passwordForm">
                <input type="hidden" name="form_type" value="change_password">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="current-password">Current password</label>
                        <input id="current-password" type="password" class="form-control" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="new-password">New password</label>
                        <input id="new-password" type="password" class="form-control" name="new_password" required minlength="6" autocomplete="new-password">
                        <div class="form-text">At least 6 characters.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="confirm-password">Confirm new password</label>
                        <input id="confirm-password" type="password" class="form-control" name="confirm_password" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-lock me-2"></i>Change password
                </button>
            </form>
        </div>
    </section>
    
    <section class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-1"><i class="fas fa-address-card text-primary me-2"></i>Account information</h2>
            <p class="text-muted small mb-0">Your member and house details at a glance.</p>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">Username</span>
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">Email</span>
                        <strong class="text-break"><?php echo htmlspecialchars($user['email']); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">Account type</span>
                        <span class="badge bg-<?php echo $user['role'] === 'manager' ? 'primary' : 'success'; ?>">
                            <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                        </span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">Account created</span>
                        <strong><?php echo date('F j, Y', strtotime($user['created_at'])); ?></strong>
                    </div>
                </div>
                <?php if ($member): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">Member name</span>
                        <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">Phone</span>
                        <strong><?php echo htmlspecialchars($member['phone'] ?: 'Not set'); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">Member since</span>
                        <strong><?php echo date('F j, Y', strtotime($member['join_date'])); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">Member status</span>
                        <span class="badge bg-<?php echo $member['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo htmlspecialchars(ucfirst($member['status'])); ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">House name</span>
                        <strong><?php echo htmlspecialchars($user['house_name'] ?? 'Not set'); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">House code</span>
                        <strong><?php echo htmlspecialchars($user['house_code'] ?? 'Not set'); ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">Member ID</span>
                        <strong>M<?php echo $member_id ? str_pad($member_id, 4, '0', STR_PAD_LEFT) : '0000'; ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-detail">
                        <span class="profile-detail-label">User ID</span>
                        <strong>U<?php echo str_pad($user['user_id'] ?? '0000', 4, '0', STR_PAD_LEFT); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <section class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-1"><i class="fas fa-chart-bar text-primary me-2"></i>Account status</h2>
            <p class="text-muted small mb-0">Sign-in and membership status.</p>
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-stat">
                        <span class="profile-detail-label">Account created</span>
                        <strong><?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?></strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-stat">
                        <span class="profile-detail-label">Last login</span>
                        <strong>
                            <?php
                            if (!empty($user['last_login']) && $user['last_login'] !== '0000-00-00 00:00:00') {
                                echo date('M d, Y h:i A', strtotime($user['last_login']));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </strong>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-stat">
                        <span class="profile-detail-label">Account status</span>
                        <span class="badge bg-<?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'success' : 'danger'; ?> px-3 py-2">
                            <?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="profile-stat">
                        <span class="profile-detail-label">Member status</span>
                        <span class="badge bg-<?php echo isset($member['status']) && $member['status'] == 'active' ? 'success' : 'secondary'; ?> px-3 py-2">
                            <?php echo isset($member['status']) ? htmlspecialchars(ucfirst($member['status'])) : 'Unknown'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Quick Actions -->
    <section class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-1"><i class="fas fa-bolt text-primary me-2"></i>Quick actions</h2>
            <p class="text-muted small mb-0">Return to your regular member tools.</p>
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
            <div class="alert alert-info mt-3 mb-0">
                <i class="fas fa-info-circle me-2"></i>
                Need to update your name or other member details? Please contact your house manager.
            </div>
        </div>
    </section>
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
// DO NOT close $conn - it's managed by getConnection() singleton

require_once '../includes/footer.php';