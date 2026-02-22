<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/transfer_functions.php';

$auth = new Auth();
$auth->requireRole('member');

$page_title = "Member Settings";

$conn = getConnection();
$member_id = $_SESSION['member_id'];
$current_house_id = $_SESSION['house_id'];

$errors = [];
$success = '';

// Handle return to current house (clear viewing history)
if (isset($_GET['return']) && $_GET['return'] == 1) {
    // Use existing connection, don't call getConnection() again
    $return_stmt = mysqli_prepare($conn, "UPDATE members SET is_viewing_history = 0, history_house_id = NULL WHERE member_id = ?");
    if ($return_stmt) {
        mysqli_stmt_bind_param($return_stmt, "i", $member_id);
        mysqli_stmt_execute($return_stmt);
        mysqli_stmt_close($return_stmt);
    }
    
    // Clear session variables
    unset($_SESSION['viewing_history']);
    unset($_SESSION['history_house_id']);
    
    // Redirect to dashboard
    header("Location: dashboard.php");
    exit();
}

// Get member's current status
$member_status = getMemberHouseStatus($member_id);

// Get member's current house info
$current_house_sql = "SELECT h.house_name, h.house_code, h.house_id 
                      FROM members m 
                      LEFT JOIN houses h ON m.house_id = h.house_id 
                      WHERE m.member_id = ?";
$current_house_stmt = mysqli_prepare($conn, $current_house_sql);
$current_house = null; // Initialize variable
if ($current_house_stmt) {
    mysqli_stmt_bind_param($current_house_stmt, "i", $member_id);
    mysqli_stmt_execute($current_house_stmt);
    $current_house_result = mysqli_stmt_get_result($current_house_stmt);
    $current_house = mysqli_fetch_assoc($current_house_result);
    mysqli_stmt_close($current_house_stmt);
}

// Get all previous houses from member_archive
$previous_houses_sql = "SELECT ma.*, h.house_name, h.house_code, h.house_id
                        FROM member_archive ma
                        JOIN houses h ON ma.original_house_id = h.house_id
                        WHERE ma.member_id = ?
                        ORDER BY ma.archived_at DESC";
$previous_houses_stmt = mysqli_prepare($conn, $previous_houses_sql);
$previous_houses = []; // Initialize variable
if ($previous_houses_stmt) {
    mysqli_stmt_bind_param($previous_houses_stmt, "i", $member_id);
    mysqli_stmt_execute($previous_houses_stmt);
    $previous_houses_result = mysqli_stmt_get_result($previous_houses_stmt);
    while ($row = mysqli_fetch_assoc($previous_houses_result)) {
        $previous_houses[] = $row;
    }
    mysqli_stmt_close($previous_houses_stmt);
}

// Remove the code that adds current house to previous houses
// Only show archived houses, not the current house

// Get member info for display
$info_sql = "SELECT m.*, h.house_name, h.house_code, u.username 
            FROM members m
            LEFT JOIN houses h ON m.house_id = h.house_id
            LEFT JOIN users u ON m.user_id = u.user_id
            WHERE m.member_id = ?";
$info_stmt = mysqli_prepare($conn, $info_sql);
$member_info = null; // Initialize variable
if ($info_stmt) {
    mysqli_stmt_bind_param($info_stmt, "i", $member_id);
    mysqli_stmt_execute($info_stmt);
    $info_result = mysqli_stmt_get_result($info_stmt);
    $member_info = mysqli_fetch_assoc($info_result);
    mysqli_stmt_close($info_stmt);
}
?>

<?php require_once '../includes/header.php'; ?>

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
        
        <!-- House Transfer Section -->
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
                        <?php elseif ($member_status && $member_status['house_status'] == 'left'): ?>
                        <div class="alert alert-warning">
                            <strong>You have left this house</strong>
                            <p class="mb-0 mt-1">You can view your history below or join a new house.</p>
                        </div>
                        <a href="join_request.php" class="btn btn-success">
                            <i class="fas fa-sign-in-alt me-2"></i>Join New House
                        </a>
                        <?php elseif ($member_status && $member_status['house_status'] == 'house_inactive'): ?>
                        <div class="alert alert-danger">
                            <strong>Your house is inactive</strong>
                            <p class="mb-0 mt-1">Your house has been deactivated by the manager. Please join a new house to continue using the system.</p>
                        </div>
                        <a href="join_request.php" class="btn btn-success">
                            <i class="fas fa-sign-in-alt me-2"></i>Join New House
                        </a>
                        <?php elseif ($member_status && $member_info && $member_info['status'] == 'inactive'): ?>
                        <div class="alert alert-danger">
                            <strong>Your account is inactive for this house</strong>
                            <p class="mb-0 mt-1">You have been made inactive by the manager. You can request to leave this house. Your data will be saved as historical record.</p>
                        </div>
                        <a href="leave_request.php" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Request to Leave
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
                            <p class="mb-0 mt-1">You have a pending leave request. Wait for approval to join a new house.</p>
                        </div>
                        <a href="leave_request.php" class="btn btn-warning">
                            <i class="fas fa-eye me-2"></i>View Leave Request
                        </a>
                        <?php elseif ($member_status && $member_status['house_status'] == 'left'): ?>
                        <a href="join_request.php" class="btn btn-success btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Join New House Now
                        </a>
                        <?php elseif ($member_status && $member_status['house_status'] == 'house_inactive'): ?>
                        <div class="alert alert-danger">
                            <strong>Your house is inactive</strong>
                            <p class="mb-0 mt-1">Your house has been deactivated by the manager. Please join a new house.</p>
                        </div>
                        <a href="join_request.php" class="btn btn-success btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Join New House Now
                        </a>
                        <?php elseif ($member_info && $member_info['status'] == 'inactive'): ?>
                        <div class="alert alert-danger">
                            <strong>Your account is inactive for this house</strong>
                            <p class="mb-0 mt-1">You have been made inactive by the manager. Your data will be saved as historical record when you leave.</p>
                        </div>
                        <a href="leave_request.php" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Request to Leave
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
        
        <!-- Current House Summary (if active) -->
        <?php if (($member_status && $member_status['house_status'] == 'active' || $member_status && $member_status['house_status'] == 'house_inactive') && $current_house): ?>
        <div class="card shadow mb-4 <?php echo $member_status['house_status'] == 'house_inactive' ? 'border-danger' : 'border-success'; ?>">
            <div class="card-header <?php echo $member_status['house_status'] == 'house_inactive' ? 'bg-danger' : 'bg-success'; ?> text-white">
                <h5 class="mb-0">
                    <i class="fas fa-home me-2"></i>Current House
                    <?php if ($member_status['house_status'] == 'house_inactive'): ?>
                    <span class="badge bg-warning ms-2">INACTIVE</span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($member_status['house_status'] == 'house_inactive'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Your house is currently inactive!</strong> This house has been deactivated by the manager.
                </div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>House Name:</strong> <?php echo htmlspecialchars($current_house['house_name']); ?></p>
                        <p><strong>House Code:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($current_house['house_code']); ?></span></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Previous Houses List -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>Your House History (Previous Houses)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($previous_houses)): ?>
                <div class="alert alert-info">
                    <p class="mb-0">You haven't been a member of any previous houses yet.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>House Name</th>
                                <th>House Code</th>
                                <th>Period</th>
                                <th>Total Deposits</th>
                                <th>Total Meals</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previous_houses as $house): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($house['house_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($house['house_code']); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $archived_date = new DateTime($house['archived_at']);
                                    echo $archived_date->format('M Y');
                                    ?>
                                    <br>
                                    <small class="text-muted">Left: <?php echo date('M d, Y', strtotime($house['archived_at'])); ?></small>
                                </td>
                                <td>৳<?php echo number_format($house['total_deposits'] ?? 0, 2); ?></td>
                                <td><?php echo number_format($house['total_meals'] ?? 0, 2); ?></td>
                                <td>
                                    <a href="view_history.php?house_id=<?php echo $house['original_house_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-light mt-3">
                    <h6><i class="fas fa-info-circle me-2"></i>About House History:</h6>
                    <ul class="mb-0">
                        <li>This section shows houses you have left in the past</li>
                        <li>Click "View Details" to see your complete historical data from any previous house</li>
                        <li>Historical data includes your deposits, meals, expenses, and final balance</li>
                        <li>Data from previous houses is preserved even after you leave</li>
                        <li>Your current house is shown separately above if you're an active member</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="card shadow">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>Account Information
                </h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($member_info['name'] ?? ''); ?></dd>
                    
                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($member_info['email'] ?? ''); ?></dd>
                    
                    <dt class="col-sm-3">Phone</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($member_info['phone'] ?? 'Not provided'); ?></dd>
                    
                    <dt class="col-sm-3">Current House</dt>
                    <dd class="col-sm-9">
                        <?php if (!empty($member_info['house_name'])): ?>
                            <?php echo htmlspecialchars($member_info['house_name']); ?>
                            <span class="badge bg-info ms-2"><?php echo htmlspecialchars($member_info['house_code'] ?? ''); ?></span>
                        <?php else: ?>
                            <span class="text-muted">No active house</span>
                        <?php endif; ?>
                    </dd>
                    
                    <dt class="col-sm-3">House Status</dt>
                    <dd class="col-sm-9">
                        <?php
                        $status_badges = [
                            'active' => 'success',
                            'pending_leave' => 'warning',
                            'pending_join' => 'info',
                            'house_inactive' => 'danger',
                            'left' => 'secondary'
                        ];
                        $badge = $status_badges[$member_info['house_status'] ?? 'left'] ?? 'secondary';
                        $status_text = ucwords(str_replace('_', ' ', $member_info['house_status'] ?? 'left'));
                        ?>
                        <span class="badge bg-<?php echo $badge; ?>"><?php echo $status_text; ?></span>
                    </dd>
                    
                    <dt class="col-sm-3">Join Date</dt>
                    <dd class="col-sm-9"><?php echo !empty($member_info['join_date']) ? date('M d, Y', strtotime($member_info['join_date'])) : 'N/A'; ?></dd>
                    
                    <dt class="col-sm-3">Username</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($member_info['username'] ?? 'Not linked'); ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php
// No need to close statements here since they're already closed after each query
// DO NOT close $conn - let footer handle it

require_once '../includes/footer.php';
?>