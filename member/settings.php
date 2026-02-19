<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/transfer_functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$auth->requireRole('member');

$page_title = "Member Settings";

// Get database connection
$conn = getConnection();
$member_id = $_SESSION['member_id'];
$current_house_id = $_SESSION['house_id'];

$errors = [];
$success = '';

// Handle viewing history
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['view_history'])) {
        $house_code = trim($_POST['house_code']);
        
        if (empty($house_code)) {
            $errors[] = "Please enter a house code";
        } else {
            // Get member email
            $member_sql = "SELECT email FROM members WHERE member_id = ?";
            $member_stmt = mysqli_prepare($conn, $member_sql);
            mysqli_stmt_bind_param($member_stmt, "i", $member_id);
            mysqli_stmt_execute($member_stmt);
            $member_result = mysqli_stmt_get_result($member_stmt);
            $member_data = mysqli_fetch_assoc($member_result);
            
            if ($member_data) {
                $history = getMemberHouseHistory($member_data['email'], $house_code);
                
                if ($history) {
                    // Update member session to view history
                    $update_sql = "UPDATE members 
                                  SET is_viewing_history = 1, 
                                      history_house_id = ? 
                                  WHERE member_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "ii", $history['house_id'], $member_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        $_SESSION['viewing_history'] = true;
                        $_SESSION['history_house_id'] = $history['house_id'];
                        $success = "Now viewing history for house code: " . htmlspecialchars($house_code);
                    } else {
                        $errors[] = "Failed to switch to history view";
                    }
                } else {
                    $errors[] = "No history found for this house code with your email";
                }
            }
        }
    }
    
    // Handle returning to current house
    if (isset($_POST['return_to_current'])) {
        $update_sql = "UPDATE members 
                      SET is_viewing_history = 0, 
                          history_house_id = NULL 
                      WHERE member_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $member_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            unset($_SESSION['viewing_history']);
            unset($_SESSION['history_house_id']);
            $success = "Returned to current house view";
        } else {
            $errors[] = "Failed to return to current house";
        }
    }
}

// Check if currently viewing history
$viewing_history = false;
$history_house_id = null;
$history_stats = null;

$status_sql = "SELECT is_viewing_history, history_house_id FROM members WHERE member_id = ?";
$status_stmt = mysqli_prepare($conn, $status_sql);
mysqli_stmt_bind_param($status_stmt, "i", $member_id);
mysqli_stmt_execute($status_stmt);
$status_result = mysqli_stmt_get_result($status_stmt);
$status = mysqli_fetch_assoc($status_result);

if ($status && $status['is_viewing_history'] && $status['history_house_id']) {
    $viewing_history = true;
    $history_house_id = $status['history_house_id'];
    
    // Get house info
    $house_sql = "SELECT house_name, house_code FROM houses WHERE house_id = ?";
    $house_stmt = mysqli_prepare($conn, $house_sql);
    mysqli_stmt_bind_param($house_stmt, "i", $history_house_id);
    mysqli_stmt_execute($house_stmt);
    $house_result = mysqli_stmt_get_result($house_stmt);
    $history_house = mysqli_fetch_assoc($house_result);
    
    // Calculate history statistics
    $history_stats = calculateHouseHistoryStats($member_id, $history_house_id);
}

// Get member's current status
$member_status = getMemberHouseStatus($member_id);
?>

<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-cog me-2"></i>Member Settings
            </h1>
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
        
        <!-- Current Status Card -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-home me-2"></i>
                    <?php echo $viewing_history ? 'Viewing House History' : 'Current House'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($viewing_history && isset($history_house)): ?>
                <div class="alert alert-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Viewing History:</strong> <?php echo htmlspecialchars($history_house['house_name']); ?>
                            <span class="badge bg-info ms-2"><?php echo $history_house['house_code']; ?></span>
                        </div>
                        <form method="POST" action="" class="d-inline">
                            <button type="submit" name="return_to_current" class="btn btn-sm btn-primary">
                                <i class="fas fa-arrow-left me-1"></i>Return to Current House
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- History Statistics -->
                <?php if ($history_stats): ?>
                <div class="row mt-3">
                    <div class="col-md-3 mb-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h3 class="card-title">৳<?php echo number_format($history_stats['total_deposits'], 2); ?></h3>
                                <p class="card-text text-muted">Total Deposits</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <h3 class="card-title"><?php echo number_format($history_stats['total_meals'], 2); ?></h3>
                                <p class="card-text text-muted">Total Meals</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h3 class="card-title">৳<?php echo number_format($history_stats['member_expenses'], 2); ?></h3>
                                <p class="card-text text-muted">Your Expenses</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-<?php echo ($history_stats['balance'] >= 0) ? 'primary' : 'danger'; ?>">
                            <div class="card-body text-center">
                                <h3 class="card-title">৳<?php echo number_format($history_stats['balance'], 2); ?></h3>
                                <p class="card-text text-muted">Balance</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <p>You are currently viewing your active house.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- House Transfer Section -->
        <?php if (!$viewing_history): ?>
        <div class="row mb-4">
            <!-- Leave House Card -->
            <div class="col-md-6 mb-3">
                <div class="card shadow h-100 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-sign-out-alt me-2"></i>Leave Current House
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Request to leave your current house. Your data will be archived.</p>
                        
                        <?php if ($member_status && $member_status['house_status'] == 'pending_leave'): ?>
                        <div class="alert alert-warning">
                            <strong>Leave Request Pending</strong>
                            <p class="mb-0 mt-1">Submitted on: <?php echo date('M d, Y g:i A', strtotime($member_status['leave_request_date'])); ?></p>
                        </div>
                        <a href="leave_request.php" class="btn btn-secondary">
                            <i class="fas fa-eye me-2"></i>View Request
                        </a>
                        <?php elseif ($member_status && $member_status['house_status'] == 'pending_join'): ?>
                        <div class="alert alert-info">
                            <strong>Join Request Pending</strong>
                            <p class="mb-0 mt-1">You have a pending join request. Cancel it to leave this house.</p>
                        </div>
                        <a href="join_request.php" class="btn btn-info">
                            <i class="fas fa-eye me-2"></i>View Join Request
                        </a>
                        <?php else: ?>
                        <a href="leave_request.php" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Request to Leave
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Join New House Card -->
            <div class="col-md-6 mb-3">
                <div class="card shadow h-100 border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-sign-in-alt me-2"></i>Join New House
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Join a new house using a token or house code.</p>
                        
                        <?php if ($member_status && $member_status['house_status'] == 'pending_join'): ?>
                        <div class="alert alert-warning">
                            <strong>Join Request Pending</strong>
                            <p class="mb-0 mt-1">Submitted on: <?php echo date('M d, Y g:i A', strtotime($member_status['join_request_date'])); ?></p>
                        </div>
                        <a href="join_request.php" class="btn btn-secondary">
                            <i class="fas fa-eye me-2"></i>View Request
                        </a>
                        <?php elseif ($member_status && $member_status['house_status'] == 'pending_leave'): ?>
                        <div class="alert alert-warning">
                            <strong>Leave Request Pending</strong>
                            <p class="mb-0 mt-1">You have a pending leave request. Cancel it to join a new house.</p>
                        </div>
                        <a href="leave_request.php" class="btn btn-warning">
                            <i class="fas fa-eye me-2"></i>View Leave Request
                        </a>
                        <?php else: ?>
                        <a href="join_request.php" class="btn btn-success">
                            <i class="fas fa-sign-in-alt me-2"></i>Join New House
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- View History Form -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>View Past House History
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="house_code" class="form-label">Enter House Code to View History</label>
                                <input type="text" class="form-control" id="house_code" name="house_code" 
                                       placeholder="Enter the house code (e.g., D58E64)" required>
                                <div class="form-text">
                                    Enter a house code where you were previously a member to view your historical data.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid">
                                <button type="submit" name="view_history" class="btn btn-primary btn-lg mt-4">
                                    <i class="fas fa-search me-2"></i>View History
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
                
                <hr>
                
                <div class="alert alert-light">
                    <h6><i class="fas fa-info-circle me-2"></i>How it works:</h6>
                    <ul class="mb-0">
                        <li>Enter any house code where you were previously a member</li>
                        <li>View your deposits, meals, and balance from that house</li>
                        <li>Your current house activities remain unaffected</li>
                        <li>Return to your current house view anytime</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="card shadow mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>Account Information
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Get member info
                $info_sql = "SELECT m.*, h.house_name, h.house_code, u.username 
                            FROM members m
                            LEFT JOIN houses h ON m.house_id = h.house_id
                            LEFT JOIN users u ON m.user_id = u.user_id
                            WHERE m.member_id = ?";
                $info_stmt = mysqli_prepare($conn, $info_sql);
                mysqli_stmt_bind_param($info_stmt, "i", $member_id);
                mysqli_stmt_execute($info_stmt);
                $info_result = mysqli_stmt_get_result($info_stmt);
                $member_info = mysqli_fetch_assoc($info_result);
                ?>
                
                <dl class="row">
                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($member_info['name']); ?></dd>
                    
                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($member_info['email']); ?></dd>
                    
                    <dt class="col-sm-3">Phone</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($member_info['phone'] ?? 'Not provided'); ?></dd>
                    
                    <dt class="col-sm-3">Current House</dt>
                    <dd class="col-sm-9">
                        <?php echo htmlspecialchars($member_info['house_name']); ?>
                        <span class="badge bg-info ms-2"><?php echo htmlspecialchars($member_info['house_code'] ?? ''); ?></span>
                    </dd>
                    
                    <dt class="col-sm-3">House Status</dt>
                    <dd class="col-sm-9">
                        <?php
                        $status_badges = [
                            'active' => 'success',
                            'pending_leave' => 'warning',
                            'pending_join' => 'info'
                        ];
                        $badge = $status_badges[$member_info['house_status']] ?? 'secondary';
                        $status_text = ucwords(str_replace('_', ' ', $member_info['house_status']));
                        ?>
                        <span class="badge bg-<?php echo $badge; ?>"><?php echo $status_text; ?></span>
                    </dd>
                    
                    <dt class="col-sm-3">Join Date</dt>
                    <dd class="col-sm-9"><?php echo date('M d, Y', strtotime($member_info['join_date'])); ?></dd>
                    
                    <dt class="col-sm-3">Username</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($member_info['username'] ?? 'Not linked'); ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php
// Close statements but NOT the connection
if (isset($member_stmt)) mysqli_stmt_close($member_stmt);
if (isset($status_stmt)) mysqli_stmt_close($status_stmt);
if (isset($info_stmt)) mysqli_stmt_close($info_stmt);
if (isset($house_stmt)) mysqli_stmt_close($house_stmt);
if (isset($update_stmt)) mysqli_stmt_close($update_stmt);
// DO NOT close $conn

require_once '../includes/footer.php';
?>