<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

// Get current user's house_id
$user_id = $_SESSION['user_id'];
$house_id = $_SESSION['house_id'] ?? null;

// Check if user has a house
if (!$house_id) {
    $_SESSION['error'] = "You need to set up a house first";
    header("Location: setup_house.php");
    exit();
}

$page_title = "Edit Member";

$conn = getConnection();

// Check if member ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid member ID";
    header("Location: members.php");
    exit();
}

$member_id = intval($_GET['id']);

// Get member details with house check
$sql = "SELECT m.*, h.house_name FROM members m 
        LEFT JOIN houses h ON m.house_id = h.house_id 
        WHERE m.member_id = ? AND m.house_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $member_id, $house_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$member = mysqli_fetch_assoc($result);

if (!$member) {
    $_SESSION['error'] = "Member not found or you don't have permission to edit this member";
    header("Location: members.php");
    exit();
}

$error = '';
$success = '';
$join_url = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $join_date = trim($_POST['join_date']);
    $status = trim($_POST['status']);
    
    // Check if regenerate token requested
    if (isset($_POST['regenerate_token'])) {
        $new_token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $token_sql = "UPDATE members SET join_token = ?, token_expiry = ? WHERE member_id = ? AND house_id = ?";
        $token_stmt = mysqli_prepare($conn, $token_sql);
        mysqli_stmt_bind_param($token_stmt, "ssii", $new_token, $token_expiry, $member_id, $house_id);
        
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
            // Check for duplicate phone/email in the same house (excluding current member)
            if (!empty($phone) || !empty($email)) {
                $check_sql = "SELECT member_id FROM members WHERE house_id = ? AND (phone = ? OR email = ?) AND member_id != ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "issi", $house_id, $phone, $email, $member_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "Another member with same phone or email already exists in this house";
                }
            }
            
            if (empty($error)) {
                $update_sql = "UPDATE members SET name = ?, phone = ?, email = ?, join_date = ?, status = ? WHERE member_id = ? AND house_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "sssssii", $name, $phone, $email, $join_date, $status, $member_id, $house_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success = "Member updated successfully!";
                    
                    // If member has an account, update their email in users table
                    if (!empty($email)) {
                        $update_user_sql = "UPDATE users SET email = ? WHERE member_id = ? AND house_id = ?";
                        $update_user_stmt = mysqli_prepare($conn, $update_user_sql);
                        mysqli_stmt_bind_param($update_user_stmt, "sii", $email, $member_id, $house_id);
                        mysqli_stmt_execute($update_user_stmt);
                    }
                } else {
                    $error = "Error updating member: " . mysqli_error($conn);
                }
            }
        }
    }
    
    // Refresh member data
    $sql = "SELECT m.*, h.house_name FROM members m 
            LEFT JOIN houses h ON m.house_id = h.house_id 
            WHERE m.member_id = ? AND m.house_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $member_id, $house_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $member = mysqli_fetch_assoc($result);
}

// Now include the header AFTER all processing
require_once '../includes/header.php';
?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Member</h5>
                <span class="badge bg-primary">House: <?php echo htmlspecialchars($member['house_name']); ?></span>
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
                
                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error']; ?>
                </div>
                <?php unset($_SESSION['error']); endif; ?>
                
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
                                   value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
                            <small class="text-muted">If member has an account, this will update their login email</small>
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
                            <label class="form-label">Member ID</label>
                            <div class="form-control" style="background-color: #f8f9fa;">
                                #<?php echo $member['member_id']; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="update" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Member
                        </button>
                        <?php if (empty($member['join_token']) || strtotime($member['token_expiry']) < time()): ?>
                        <button type="submit" name="regenerate_token" class="btn btn-warning">
                            <i class="fas fa-sync me-2"></i>Generate Join Link
                        </button>
                        <?php else: ?>
                        <button type="submit" name="regenerate_token" class="btn btn-warning">
                            <i class="fas fa-sync me-2"></i>Regenerate Join Link
                        </button>
                        <?php endif; ?>
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
                    </small>
                </div>
                <?php elseif ($member['join_token']): ?>
                <div class="mt-4 pt-4 border-top">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Previous join link expired on <?php echo date('M d, Y h:i A', strtotime($member['token_expiry'])); ?>
                    </div>
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
                        // Get member statistics for this house
                        $stats_sql = "SELECT 
                            (SELECT COUNT(*) FROM meals WHERE member_id = ? AND house_id = ?) as total_meals,
                            (SELECT COUNT(DISTINCT DATE_FORMAT(meal_date, '%Y-%m')) FROM meals WHERE member_id = ? AND house_id = ?) as active_months,
                            (SELECT SUM(amount) FROM deposits WHERE member_id = ? AND house_id = ?) as total_deposits";
                        $stats_stmt = mysqli_prepare($conn, $stats_sql);
                        mysqli_stmt_bind_param($stats_stmt, "iiiiii", $member_id, $house_id, $member_id, $house_id, $member_id, $house_id);
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
                                Account Status
                                <span class="badge bg-<?php echo ($member['join_token'] && strtotime($member['token_expiry']) > time()) ? 'warning' : ($member['join_token'] ? 'danger' : 'success'); ?> rounded-pill">
                                    <?php echo ($member['join_token'] && strtotime($member['token_expiry']) > time()) ? 'Invited' : ($member['join_token'] ? 'Expired' : 'Linked'); ?>
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
                        $user_sql = "SELECT u.username, u.email, u.last_login, u.is_active 
                                     FROM users u 
                                     WHERE u.member_id = ? AND u.house_id = ?";
                        $user_stmt = mysqli_prepare($conn, $user_sql);
                        mysqli_stmt_bind_param($user_stmt, "ii", $member_id, $house_id);
                        mysqli_stmt_execute($user_stmt);
                        $user_result = mysqli_stmt_get_result($user_stmt);
                        $user_account = mysqli_fetch_assoc($user_result);
                        ?>
                        
                        <?php if ($user_account): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>Account Created</h6>
                            <p class="mb-1"><strong>Username:</strong> <?php echo htmlspecialchars($user_account['username']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($user_account['email']); ?></p>
                            <p class="mb-1"><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $user_account['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user_account['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </p>
                            <p class="mb-0"><strong>Last Login:</strong> 
                                <?php echo $user_account['last_login'] ? date('M d, Y h:i A', strtotime($user_account['last_login'])) : 'Never'; ?>
                            </p>
                        </div>
                        <?php elseif ($member['join_token'] && strtotime($member['token_expiry']) > time()): ?>
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-clock me-2"></i>Pending Account Creation</h6>
                            <p class="mb-2">Member has not created their account yet.</p>
                            <p class="mb-1">Invite link is active.</p>
                            <p class="mb-0">Share the join link above for them to create their account.</p>
                        </div>
                        <?php elseif ($member['join_token']): ?>
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-circle me-2"></i>Invite Expired</h6>
                            <p class="mb-2">The join link has expired.</p>
                            <p class="mb-0">Regenerate a new join link to invite this member.</p>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>No Account Link</h6>
                            <p class="mb-2">This member was added before the join system was implemented.</p>
                            <p class="mb-0">Generate a join link to create an account for them.</p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!$user_account && ($member['join_token'] && strtotime($member['token_expiry']) > time())): ?>
                        <div class="mt-3">
                            <a href="#" class="btn btn-sm btn-outline-primary" onclick="copyCurrentJoinLink()">
                                <i class="fas fa-copy me-1"></i>Copy Join Link
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-warning" onclick="shareJoinLink()">
                                <i class="fas fa-share me-1"></i>Share Link
                            </a>
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

function shareJoinLink() {
    const joinLink = document.getElementById('currentJoinLink')?.value || document.getElementById('joinLink')?.value;
    if (joinLink && navigator.share) {
        navigator.share({
            title: 'Join Our Meal System',
            text: 'Click the link to join our meal management system',
            url: joinLink
        }).catch(console.error);
    } else if (joinLink) {
        alert('Share this link:\n\n' + joinLink);
    }
}

// Auto-focus on name field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('name').focus();
});
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>