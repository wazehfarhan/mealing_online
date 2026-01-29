<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Edit Member";

$conn = getConnection();

// Check if member ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid member ID";
    header("Location: members.php");
    exit();
}

$member_id = intval($_GET['id']);

// Get member details
$sql = "SELECT * FROM members WHERE member_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $member_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);

if (!$member) {
    $_SESSION['error'] = "Member not found";
    header("Location: members.php");
    exit();
}

$error = '';
$success = '';
$join_url = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $join_date = mysqli_real_escape_string($conn, $_POST['join_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Check if regenerate token requested
    if (isset($_POST['regenerate_token'])) {
        $new_token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $token_sql = "UPDATE members SET join_token = ?, token_expiry = ? WHERE member_id = ?";
        $token_stmt = mysqli_prepare($conn, $token_sql);
        mysqli_stmt_bind_param($token_stmt, "ssi", $new_token, $token_expiry, $member_id);
        
        if (mysqli_stmt_execute($token_stmt)) {
            $base_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__)));
            $join_url = 'http://' . $_SERVER['HTTP_HOST'] . $base_url . '/member/join.php?token=' . $new_token;
            $success = "Join token regenerated successfully!";
        } else {
            $error = "Error regenerating token: " . mysqli_error($conn);
        }
    } else {
        // Update member details
        if (empty($name)) {
            $error = "Name is required";
        } elseif (empty($join_date)) {
            $error = "Join date is required";
        } else {
            // Check for duplicate phone/email
            if (!empty($phone) || !empty($email)) {
                $check_sql = "SELECT member_id FROM members WHERE (phone = ? OR email = ?) AND member_id != ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "ssi", $phone, $email, $member_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "Another member with same phone or email already exists";
                }
            }
            
            if (empty($error)) {
                $update_sql = "UPDATE members SET name = ?, phone = ?, email = ?, join_date = ?, status = ? WHERE member_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "sssssi", $name, $phone, $email, $join_date, $status, $member_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success = "Member updated successfully!";
                    
                    // If member has an account, update their email in users table
                    if (!empty($email)) {
                        $update_user_sql = "UPDATE users SET email = ? WHERE member_id = ? AND email != ?";
                        $update_user_stmt = mysqli_prepare($conn, $update_user_sql);
                        mysqli_stmt_bind_param($update_user_stmt, "sis", $email, $member_id, $email);
                        mysqli_stmt_execute($update_user_stmt);
                    }
                } else {
                    $error = "Error updating member: " . mysqli_error($conn);
                }
            }
        }
    }
    
    // Refresh member data
    $sql = "SELECT * FROM members WHERE member_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $member_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $member = mysqli_fetch_assoc($result);
}
?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Member: <?php echo htmlspecialchars($member['name']); ?></h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    
                    <?php if ($join_url): ?>
                    <div class="mt-3">
                        <h6><i class="fas fa-link me-2"></i>New Join Link:</h6>
                        <div class="input-group">
                            <input type="text" class="form-control" id="joinLink" value="<?php echo $join_url; ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyJoinLink()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <small class="text-muted">This link expires in 7 days. Share it with the member.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($member['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($member['phone']); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($member['email']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="join_date" class="form-label">Join Date *</label>
                            <input type="date" class="form-control" id="join_date" name="join_date" 
                                   value="<?php echo $member['join_date']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo $member['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $member['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <div class="form-text">Inactive members cannot login</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Member Since</label>
                            <div class="form-control" style="background-color: #f8f9fa;">
                                <?php echo $functions->formatDate($member['created_at']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Member
                        </button>
                        <button type="submit" name="regenerate_token" class="btn btn-warning">
                            <i class="fas fa-sync me-2"></i>Regenerate Join Link
                        </button>
                        <a href="members.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
                
                <!-- Current Join Link -->
                <?php if ($member['join_token'] && strtotime($member['token_expiry']) > time()): ?>
                <?php
                $base_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__)));
                $current_join_url = 'http://' . $_SERVER['HTTP_HOST'] . $base_url . '/member/join.php?token=' . $member['join_token'];
                ?>
                <div class="mt-4 pt-4 border-top">
                    <h6><i class="fas fa-link me-2"></i>Current Join Link</h6>
                    <div class="input-group">
                        <input type="text" class="form-control" id="currentJoinLink" value="<?php echo $current_join_url; ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyCurrentJoinLink()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted">
                        Expires: <?php echo date('M d, Y h:i A', strtotime($member['token_expiry'])); ?>
                        <?php if (strtotime($member['token_expiry']) < time()): ?>
                        <span class="text-danger">(Expired)</span>
                        <?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Member Statistics -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Member Statistics</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get member statistics
                        $stats_sql = "SELECT 
                            (SELECT COUNT(*) FROM meals WHERE member_id = ?) as total_meals,
                            (SELECT COUNT(DISTINCT DATE_FORMAT(meal_date, '%Y-%m')) FROM meals WHERE member_id = ?) as active_months,
                            (SELECT SUM(amount) FROM deposits WHERE member_id = ?) as total_deposits,
                            (SELECT SUM(amount) FROM expenses) as total_expenses";
                        $stats_stmt = mysqli_prepare($conn, $stats_sql);
                        mysqli_stmt_bind_param($stats_stmt, "iii", $member_id, $member_id, $member_id);
                        mysqli_stmt_execute($stats_stmt);
                        $stats_result = mysqli_stmt_get_result($stats_stmt);
                        $stats = mysqli_fetch_assoc($stats_result);
                        ?>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Meals
                                <span class="badge bg-primary rounded-pill"><?php echo $stats['total_meals'] ?: 0; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Active Months
                                <span class="badge bg-success rounded-pill"><?php echo $stats['active_months'] ?: 0; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Deposits
                                <span class="badge bg-warning rounded-pill"><?php echo $functions->formatCurrency($stats['total_deposits'] ?: 0); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Has Account
                                <span class="badge bg-<?php echo $member['join_token'] ? 'danger' : 'success'; ?> rounded-pill">
                                    <?php echo $member['join_token'] ? 'No' : 'Yes'; ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0"><i class="fas fa-user-check me-2"></i>Account Status</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        // Check if member has user account
                        $user_sql = "SELECT u.username, u.email, u.last_login FROM users u WHERE u.member_id = ?";
                        $user_stmt = mysqli_prepare($conn, $user_sql);
                        mysqli_stmt_bind_param($user_stmt, "i", $member_id);
                        mysqli_stmt_execute($user_stmt);
                        $user_result = mysqli_stmt_get_result($user_stmt);
                        $user_account = mysqli_fetch_assoc($user_result);
                        ?>
                        
                        <?php if ($user_account): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>Account Created</h6>
                            <p class="mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($user_account['username']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($user_account['email']); ?></p>
                            <p class="mb-0"><strong>Last Login:</strong> 
                                <?php echo $user_account['last_login'] ? date('M d, Y h:i A', strtotime($user_account['last_login'])) : 'Never'; ?>
                            </p>
                        </div>
                        <?php elseif ($member['join_token']): ?>
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-clock me-2"></i>Pending Account Creation</h6>
                            <p class="mb-2">Member has not created their account yet.</p>
                            <p class="mb-0">Share the join link above for them to create their account.</p>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>No Account Link</h6>
                            <p class="mb-2">This member was added before the join system was implemented.</p>
                            <p class="mb-0">Use "Regenerate Join Link" to create an account for them.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyJoinLink() {
    const joinLink = document.getElementById('joinLink');
    if (joinLink) {
        joinLink.select();
        joinLink.setSelectionRange(0, 99999);
        
        try {
            document.execCommand('copy');
            alert('Join link copied to clipboard!');
        } catch (err) {
            console.error('Failed to copy: ', err);
        }
    }
}

function copyCurrentJoinLink() {
    const joinLink = document.getElementById('currentJoinLink');
    if (joinLink) {
        joinLink.select();
        joinLink.setSelectionRange(0, 99999);
        
        try {
            document.execCommand('copy');
            alert('Join link copied to clipboard!');
        } catch (err) {
            console.error('Failed to copy: ', err);
        }
    }
}
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>