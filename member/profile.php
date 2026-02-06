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

// Debug: Show session data
// echo "<pre>Session Data:\n";
// echo "user_id: $user_id\n";
// echo "member_id: " . ($member_id ?? 'null') . "\n";
// echo "house_id: " . ($house_id ?? 'null') . "\n";
// echo "</pre>";

$error = '';
$success = '';

// Get current user information from users table
$user_sql = "SELECT u.*, m.name as member_name, m.phone, m.email as member_email, 
                    m.join_date, m.status as member_status 
             FROM users u 
             LEFT JOIN members m ON u.member_id = m.member_id 
             WHERE u.user_id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

// Debug: Show user data
// echo "<pre>User Data from DB:\n";
// print_r($user);
// echo "</pre>";

if (!$user) {
    $error = "User not found. Please contact your house manager.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Debug: Show form data
    // echo "<pre>Form Data:\n";
    // echo "username: $username\n";
    // echo "email: $email\n";
    // echo "phone: $phone\n";
    // echo "</pre>";
    
    // Validation
    $validation_errors = [];
    
    if (empty($username)) {
        $validation_errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $validation_errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = "Invalid email format";
    }
    
    if (!empty($phone) && !preg_match('/^[0-9+\-\s]{10,15}$/', $phone)) {
        $validation_errors[] = "Invalid phone number format";
    }
    
    if (!empty($validation_errors)) {
        $error = implode(". ", $validation_errors);
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        $all_success = true;
        $error_message = '';
        
        try {
            // Check if username already exists (excluding current user)
            $check_username_sql = "SELECT user_id FROM users 
                                   WHERE username = ? 
                                   AND user_id != ?";
            $check_username_stmt = mysqli_prepare($conn, $check_username_sql);
            mysqli_stmt_bind_param($check_username_stmt, "si", $username, $user_id);
            mysqli_stmt_execute($check_username_stmt);
            mysqli_stmt_store_result($check_username_stmt);
            
            if (mysqli_stmt_num_rows($check_username_stmt) > 0) {
                throw new Exception("Username '$username' already exists");
            }
            mysqli_stmt_close($check_username_stmt);
            
            // Check if email already exists (excluding current user)
            $check_email_sql = "SELECT user_id FROM users 
                                WHERE email = ? 
                                AND user_id != ?";
            $check_email_stmt = mysqli_prepare($conn, $check_email_sql);
            mysqli_stmt_bind_param($check_email_stmt, "si", $email, $user_id);
            mysqli_stmt_execute($check_email_stmt);
            mysqli_stmt_store_result($check_email_stmt);
            
            if (mysqli_stmt_num_rows($check_email_stmt) > 0) {
                throw new Exception("Email '$email' already exists");
            }
            mysqli_stmt_close($check_email_stmt);
            
            // Update user table
            $update_user_sql = "UPDATE users SET username = ?, email = ? WHERE user_id = ?";
            $update_user_stmt = mysqli_prepare($conn, $update_user_sql);
            mysqli_stmt_bind_param($update_user_stmt, "ssi", $username, $email, $user_id);
            
            if (!mysqli_stmt_execute($update_user_stmt)) {
                throw new Exception("Failed to update user information: " . mysqli_error($conn));
            }
            mysqli_stmt_close($update_user_stmt);
            
            // Update member table if member_id exists and is not null
            if ($member_id) {
                // First check if this user has a member record
                $check_user_member_sql = "SELECT member_id FROM users WHERE user_id = ? AND member_id IS NOT NULL";
                $check_user_member_stmt = mysqli_prepare($conn, $check_user_member_sql);
                mysqli_stmt_bind_param($check_user_member_stmt, "i", $user_id);
                mysqli_stmt_execute($check_user_member_stmt);
                $check_user_member_result = mysqli_stmt_get_result($check_user_member_stmt);
                $user_member = mysqli_fetch_assoc($check_user_member_result);
                mysqli_stmt_close($check_user_member_stmt);
                
                if ($user_member && $user_member['member_id']) {
                    // Update member phone and email
                    $update_member_sql = "UPDATE members SET phone = ?, email = ? WHERE member_id = ?";
                    $update_member_stmt = mysqli_prepare($conn, $update_member_sql);
                    mysqli_stmt_bind_param($update_member_stmt, "ssi", $phone, $email, $member_id);
                    
                    if (!mysqli_stmt_execute($update_member_stmt)) {
                        // Log but don't throw error for member update failure
                        error_log("Member update failed for member_id: $member_id - " . mysqli_error($conn));
                    }
                    
                    if (isset($update_member_stmt)) {
                        mysqli_stmt_close($update_member_stmt);
                    }
                }
            }
            
            // Commit transaction
            if (!mysqli_commit($conn)) {
                throw new Exception("Transaction commit failed");
            }
            
            // Update session
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            
            $success = "Profile updated successfully!";
            
            // Refresh user data
            mysqli_stmt_close($user_stmt);
            $user_sql = "SELECT u.*, m.name as member_name, m.phone, m.email as member_email, 
                                m.join_date, m.status as member_status 
                         FROM users u 
                         LEFT JOIN members m ON u.member_id = m.member_id 
                         WHERE u.user_id = ?";
            $user_stmt = mysqli_prepare($conn, $user_sql);
            mysqli_stmt_bind_param($user_stmt, "i", $user_id);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>My Profile</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!$user): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>No profile data found. Please contact your house manager.
                </div>
                <?php else: ?>
                
                <form method="POST" action="">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-3 text-muted">Account Information</h6>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username *</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                        <div class="form-text">Your unique login username</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                        <div class="form-text">This will be used for login and notifications</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role'] ?? 'member'); ?>" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" value="********" readonly>
                                            <a href="../auth/change_password.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-edit me-1"></i> Change
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-3 text-muted">Personal Information</h6>
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['member_name'] ?? $user['username'] ?? ''); ?>" readonly>
                                        <div class="form-text">Name can only be changed by house manager</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                               placeholder="+880 1XXX-XXXXXX">
                                        <div class="form-text">Optional - for notifications</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="join_date" class="form-label">Join Date</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo isset($user['join_date']) && $user['join_date'] != '0000-00-00' ? date('M d, Y', strtotime($user['join_date'])) : 'N/A'; ?>" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo ucfirst($user['member_status'] ?? 'active'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card border-light mb-4">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-3 text-muted">House Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">House Name</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($_SESSION['house_name'] ?? 'Not set'); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">House Code</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($_SESSION['house_code'] ?? 'Not set'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Member ID</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" 
                                           value="M<?php echo str_pad($user['member_id'] ?? '0000', 4, '0', STR_PAD_LEFT); ?>" readonly>
                                    <span class="input-group-text bg-info text-white">
                                        <i class="fas fa-id-card"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">User ID</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" 
                                           value="U<?php echo str_pad($user['user_id'] ?? '0000', 4, '0', STR_PAD_LEFT); ?>" readonly>
                                    <span class="input-group-text bg-secondary text-white">
                                        <i class="fas fa-user"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </div>
                </form>
                
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($user): ?>
        <!-- Account Statistics -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white">
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
                            <span class="badge bg-<?php echo isset($user['member_status']) && $user['member_status'] == 'active' ? 'success' : 'danger'; ?> p-2">
                                <?php echo isset($user['member_status']) ? ucfirst($user['member_status']) : 'Unknown'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Account Actions -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Account Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="d-grid">
                            <a href="../auth/change_password.php" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Change Password
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="d-grid">
                            <a href="report.php" class="btn btn-info">
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
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-focus on username field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('username').focus();
});

// Form validation
document.querySelector('form')?.addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    
    if (!username || !email) {
        e.preventDefault();
        alert('Username and email are required!');
        return false;
    }
    
    if (!validateEmail(email)) {
        e.preventDefault();
        alert('Please enter a valid email address!');
        return false;
    }
});

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}
</script>

<?php
// Close statement
if (isset($user_stmt)) mysqli_stmt_close($user_stmt);
mysqli_close($conn);

require_once '../includes/footer.php';
?>