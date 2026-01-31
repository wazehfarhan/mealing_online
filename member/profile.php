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
$member_id = $_SESSION['member_id'];

$error = '';
$success = '';

// Get current user and member information
$sql = "SELECT u.*, m.* FROM users u 
        JOIN members m ON u.member_id = m.member_id 
        WHERE u.user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validation
        if (empty($username) || empty($email)) {
            $error = "Username and email are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (!empty($phone) && !preg_match('/^[0-9+\-\s]{10,15}$/', $phone)) {
            $error = "Invalid phone number format";
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Check if username already exists (excluding current user)
                $check_sql = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "si", $username, $user_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    throw new Exception("Username already exists");
                }
                
                // Check if email already exists (excluding current user)
                $check_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "si", $email, $user_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    throw new Exception("Email already exists");
                }
                
                // Update user table
                $update_user_sql = "UPDATE users SET username = ?, email = ? WHERE user_id = ?";
                $update_user_stmt = mysqli_prepare($conn, $update_user_sql);
                mysqli_stmt_bind_param($update_user_stmt, "ssi", $username, $email, $user_id);
                
                if (!mysqli_stmt_execute($update_user_stmt)) {
                    throw new Exception("Failed to update user information");
                }
                
                // Update member table
                $update_member_sql = "UPDATE members SET email = ?, phone = ? WHERE member_id = ?";
                $update_member_stmt = mysqli_prepare($conn, $update_member_sql);
                mysqli_stmt_bind_param($update_member_stmt, "ssi", $email, $phone, $member_id);
                
                if (!mysqli_stmt_execute($update_member_stmt)) {
                    throw new Exception("Failed to update member information");
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Update session
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                
                $success = "Profile updated successfully!";
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }
}

// Re-fetch updated data
if ($success) {
    mysqli_stmt_close($stmt);
    $sql = "SELECT u.*, m.* FROM users u 
            JOIN members m ON u.member_id = m.member_id 
            WHERE u.user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
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
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card border-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-3 text-muted">Account Information</h6>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username *</label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        <div class="form-text">This will be used for login and notifications</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" readonly>
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
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                                        <div class="form-text">Name can only be changed by house manager</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                               placeholder="+1 (555) 123-4567">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="join_date" class="form-label">Join Date</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo date('M d, Y', strtotime($user['join_date'])); ?>" readonly>
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
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['house_name'] ?? 'Not set'); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">House Code</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['house_code'] ?? 'Not set'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Member ID</label>
                                <input type="text" class="form-control" value="M<?php echo str_pad($member_id, 4, '0', STR_PAD_LEFT); ?>" readonly>
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
            </div>
        </div>
        
        <!-- Change Password Card -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    To change your password, please use the change password page.
                </div>
                <a href="../auth/change_password.php" class="btn btn-warning">
                    <i class="fas fa-key me-2"></i>Go to Change Password
                </a>
            </div>
        </div>
        
        <!-- Account Statistics -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Account Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4 mb-3">
                        <div class="p-3 border rounded">
                            <h6 class="text-muted mb-2">Account Created</h6>
                            <h5><?php echo date('M d, Y', strtotime($user['created_at'])); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="p-3 border rounded">
                            <h6 class="text-muted mb-2">Last Login</h6>
                            <h5>
                                <?php 
                                if ($user['last_login']) {
                                    echo date('M d, Y h:i A', strtotime($user['last_login']));
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </h5>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="p-3 border rounded">
                            <h6 class="text-muted mb-2">Status</h6>
                            <span class="badge bg-<?php echo $user['is_active'] == 1 ? 'success' : 'danger'; ?>">
                                <?php echo $user['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if (isset($stmt)) mysqli_stmt_close($stmt);
mysqli_close($conn);

require_once '../includes/footer.php';
?>