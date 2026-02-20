<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/transfer_functions.php';

$auth = new Auth();
$auth->requireRole('manager');

$page_title = "Approve House Requests";

$conn = getConnection();
$manager_id = $_SESSION['user_id'];
$manager_house_id = $_SESSION['house_id'];

$errors = [];
$success = '';

// Handle approval actions FIRST before fetching data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    $reject_reason = $_POST['reject_reason'] ?? '';
    
    if (empty($member_id) || empty($action)) {
        $errors[] = "Invalid request parameters.";
    } else {
        // Get member current details
        $member_sql = "SELECT m.*, 
                              h_current.house_name as current_house_name,
                              h_current.house_code as current_house_code,
                              h_requested.house_name as requested_house_name,
                              h_requested.house_code as requested_house_code,
                              h_requested.house_id as requested_house_id
                       FROM members m
                       LEFT JOIN houses h_current ON m.house_id = h_current.house_id
                       LEFT JOIN houses h_requested ON m.requested_house_id = h_requested.house_id
                       WHERE m.member_id = ?";
        $member_stmt = mysqli_prepare($conn, $member_sql);
        mysqli_stmt_bind_param($member_stmt, "i", $member_id);
        mysqli_stmt_execute($member_stmt);
        $member_result = mysqli_stmt_get_result($member_stmt);
        $member = mysqli_fetch_assoc($member_result);
        mysqli_stmt_close($member_stmt);
        
        if (!$member) {
            $errors[] = "Member not found.";
        } elseif ($member['house_status'] == 'pending_leave') {
            // Handle leave request
            if ($action == 'approve') {
                // Check if member has any today's meals
                $today = date('Y-m-d');
                $today_meals_sql = "SELECT COUNT(*) as count FROM meals WHERE member_id = ? AND meal_date = ?";
                $today_meals_stmt = mysqli_prepare($conn, $today_meals_sql);
                mysqli_stmt_bind_param($today_meals_stmt, "is", $member_id, $today);
                mysqli_stmt_execute($today_meals_stmt);
                $today_meals_result = mysqli_stmt_get_result($today_meals_stmt);
                $today_meals = mysqli_fetch_assoc($today_meals_result);
                mysqli_stmt_close($today_meals_stmt);
                
                if ($today_meals['count'] > 0) {
                    $errors[] = "Cannot approve leave request. Member has meal entries for today.";
                } else {
                    // Get member balance data
                    $balance_check_sql = "
                        SELECT 
                            (SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE member_id = ?) as total_deposits,
                            (SELECT COALESCE(SUM(meal_count), 0) FROM meals WHERE member_id = ?) as total_meals
                    ";
                    $balance_stmt = mysqli_prepare($conn, $balance_check_sql);
                    mysqli_stmt_bind_param($balance_stmt, "ii", $member_id, $member_id);
                    mysqli_stmt_execute($balance_stmt);
                    $balance_result = mysqli_stmt_get_result($balance_stmt);
                    $balance = mysqli_fetch_assoc($balance_result);
                    mysqli_stmt_close($balance_stmt);
                    
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Archive member data
                        $archive_sql = "INSERT INTO member_archive 
                                        (member_id, name, email, phone, original_house_id, 
                                         total_deposits, total_meals, archived_at, archived_by)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                        $archive_stmt = mysqli_prepare($conn, $archive_sql);
                        mysqli_stmt_bind_param($archive_stmt, "issisiii", 
                            $member_id, $member['name'], $member['email'], $member['phone'], 
                            $member['house_id'], $balance['total_deposits'], $balance['total_meals'], 
                            $manager_id);
                        mysqli_stmt_execute($archive_stmt);
                        mysqli_stmt_close($archive_stmt);
                        
                        // Update member - set house_status to 'left' but keep status active
                        // This allows member to view old house data while being able to join new house
                        $update_sql = "UPDATE members 
                                       SET house_status = 'left', 
                                           leave_request_date = NULL,
                                           status = 'active'
                                       WHERE member_id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($update_stmt, "i", $member_id);
                        
                        if (!mysqli_stmt_execute($update_stmt)) {
                            throw new Exception("Failed to update member: " . mysqli_error($conn));
                        }
                        mysqli_stmt_close($update_stmt);
                        
                        // Log the action
                        $log_sql = "INSERT INTO house_transfers_log 
                                    (member_id, from_house_id, to_house_id, action, 
                                     performed_by, performed_at, notes)
                                    VALUES (?, ?, NULL, 'leave_approved', ?, NOW(), ?)";
                        $log_stmt = mysqli_prepare($conn, $log_sql);
                        $notes = "Member left house via manager approval";
                        mysqli_stmt_bind_param($log_stmt, "iiis", $member_id, $member['house_id'], $manager_id, $notes);
                        mysqli_stmt_execute($log_stmt);
                        mysqli_stmt_close($log_stmt);
                        
                        mysqli_commit($conn);
                        $_SESSION['approval_success'] = "Leave request approved. Member can now join another house.";
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $errors[] = "Failed to process leave request: " . $e->getMessage();
                    }
                }
                
            } elseif ($action == 'reject') {
                // Reject leave request
                mysqli_begin_transaction($conn);
                
                try {
                    $reject_sql = "UPDATE members 
                                   SET house_status = 'active', 
                                       leave_request_date = NULL 
                                   WHERE member_id = ?";
                    $reject_stmt = mysqli_prepare($conn, $reject_sql);
                    mysqli_stmt_bind_param($reject_stmt, "i", $member_id);
                    mysqli_stmt_execute($reject_stmt);
                    mysqli_stmt_close($reject_stmt);
                    
                    // Log rejection
                    $log_sql = "INSERT INTO house_transfers_log 
                                (member_id, from_house_id, action, 
                                 performed_by, performed_at, notes)
                                VALUES (?, ?, 'leave_rejected', ?, NOW(), ?)";
                    $log_stmt = mysqli_prepare($conn, $log_sql);
                    $notes = $reject_reason ?: 'No reason provided';
                    mysqli_stmt_bind_param($log_stmt, "iiss", $member_id, $member['house_id'], $manager_id, $notes);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                    
                    mysqli_commit($conn);
                    $_SESSION['approval_success'] = "Leave request rejected.";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $errors[] = "Failed to reject leave request: " . $e->getMessage();
                }
            }
            
        } elseif ($member['house_status'] == 'pending_join') {
            // Handle join request
            if ($action == 'approve') {
                // Verify requested house exists and is open
                if (empty($member['requested_house_id'])) {
                    $errors[] = "No requested house specified.";
                } else {
                    $house_check_sql = "SELECT house_id, house_name, house_code, is_open_for_join 
                                        FROM houses 
                                        WHERE house_id = ? AND is_active = 1";
                    $house_check_stmt = mysqli_prepare($conn, $house_check_sql);
                    mysqli_stmt_bind_param($house_check_stmt, "i", $member['requested_house_id']);
                    mysqli_stmt_execute($house_check_stmt);
                    $house_check_result = mysqli_stmt_get_result($house_check_stmt);
                    $new_house = mysqli_fetch_assoc($house_check_result);
                    mysqli_stmt_close($house_check_stmt);
                    
                    if (!$new_house) {
                        $errors[] = "Requested house is not available.";
                    } else {
                        $old_house_id = $member['house_id'];
                        
                        mysqli_begin_transaction($conn);
                        
                        try {
                            // Get member's current data for archiving
                            $balance_sql = "
                                SELECT 
                                    (SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE member_id = ?) as total_deposits,
                                    (SELECT COALESCE(SUM(meal_count), 0) FROM meals WHERE member_id = ?) as total_meals
                            ";
                            $balance_stmt = mysqli_prepare($conn, $balance_sql);
                            mysqli_stmt_bind_param($balance_stmt, "ii", $member_id, $member_id);
                            mysqli_stmt_execute($balance_stmt);
                            $balance_result = mysqli_stmt_get_result($balance_stmt);
                            $balance = mysqli_fetch_assoc($balance_result);
                            mysqli_stmt_close($balance_stmt);
                            
                            // Archive member's data from old house
                            $archive_sql = "INSERT INTO member_archive 
                                            (member_id, name, email, phone, original_house_id, 
                                             total_deposits, total_meals, archived_at, archived_by)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                            $archive_stmt = mysqli_prepare($conn, $archive_sql);
                            mysqli_stmt_bind_param($archive_stmt, "issisiii", 
                                $member_id, $member['name'], $member['email'], $member['phone'],
                                $old_house_id, $balance['total_deposits'], $balance['total_meals'], 
                                $manager_id);
                            mysqli_stmt_execute($archive_stmt);
                            mysqli_stmt_close($archive_stmt);
                            
                            // Update member to new house
                            $update_sql = "UPDATE members 
                                           SET house_id = ?, 
                                               house_status = 'active',
                                               requested_house_id = NULL,
                                               join_request_date = NULL,
                                               status = 'active'
                                           WHERE member_id = ?";
                            $update_stmt = mysqli_prepare($conn, $update_sql);
                            mysqli_stmt_bind_param($update_stmt, "ii", $new_house['house_id'], $member_id);
                            
                            if (!mysqli_stmt_execute($update_stmt)) {
                                throw new Exception("Failed to update member: " . mysqli_error($conn));
                            }
                            mysqli_stmt_close($update_stmt);
                            
                            // Update user's house_id if user exists
                            $user_update_sql = "UPDATE users SET house_id = ? WHERE member_id = ?";
                            $user_update_stmt = mysqli_prepare($conn, $user_update_sql);
                            mysqli_stmt_bind_param($user_update_stmt, "ii", $new_house['house_id'], $member_id);
                            mysqli_stmt_execute($user_update_stmt);
                            mysqli_stmt_close($user_update_stmt);
                            
                            // Log the transfer
                            $log_sql = "INSERT INTO house_transfers_log 
                                        (member_id, from_house_id, to_house_id, action, 
                                         performed_by, performed_at, notes)
                                        VALUES (?, ?, ?, 'join_approved', ?, NOW(), ?)";
                            $log_stmt = mysqli_prepare($conn, $log_sql);
                            $notes = "Member joined house via manager approval";
                            mysqli_stmt_bind_param($log_stmt, "iiiis", 
                                $member_id, $old_house_id, $new_house['house_id'], $manager_id, $notes);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                            
                            mysqli_commit($conn);
                            
                            // If the member being approved is the current user, update their session
                            if (isset($_SESSION['member_id']) && $_SESSION['member_id'] == $member_id) {
                                $_SESSION['house_id'] = $new_house['house_id'];
                                $_SESSION['house_code'] = $new_house['house_code'];
                            }
                            
                            $_SESSION['approval_success'] = "Join request approved. Member transferred to " . htmlspecialchars($new_house['house_name'] ?? '') . ".";
                            
                        } catch (Exception $e) {
                            mysqli_rollback($conn);
                            $errors[] = "Failed to process join request: " . $e->getMessage();
                        }
                    }
                }
                
            } elseif ($action == 'reject') {
                // Reject join request
                mysqli_begin_transaction($conn);
                
                try {
                    $reject_sql = "UPDATE members 
                                   SET house_status = 'left', 
                                       requested_house_id = NULL,
                                       join_request_date = NULL,
                                       status = 'active'
                                   WHERE member_id = ?";
                    $reject_stmt = mysqli_prepare($conn, $reject_sql);
                    mysqli_stmt_bind_param($reject_stmt, "i", $member_id);
                    
                    if (!mysqli_stmt_execute($reject_stmt)) {
                        throw new Exception("Failed to update member: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($reject_stmt);
                    
                    // Log rejection
                    $log_sql = "INSERT INTO house_transfers_log 
                                (member_id, to_house_id, action, 
                                 performed_by, performed_at, notes)
                                VALUES (?, ?, 'join_rejected', ?, NOW(), ?)";
                    $log_stmt = mysqli_prepare($conn, $log_sql);
                    $notes = $reject_reason ?: 'No reason provided';
                    mysqli_stmt_bind_param($log_stmt, "iiss", $member_id, $member['requested_house_id'], $manager_id, $notes);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                    
                    mysqli_commit($conn);
                    $_SESSION['approval_success'] = "Join request rejected. Member can try joining another house.";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $errors[] = "Failed to reject join request: " . $e->getMessage();
                }
            }
        } else {
            $errors[] = "Member does not have a pending request.";
        }
        
        // Store errors in session if any
        if (!empty($errors)) {
            $_SESSION['approval_errors'] = $errors;
        }
        
        // Redirect to avoid form resubmission
        header("Location: approve_requests.php");
        exit();
    }
}

// Check for session messages
if (isset($_SESSION['approval_success'])) {
    $success = $_SESSION['approval_success'];
    unset($_SESSION['approval_success']);
}

if (isset($_SESSION['approval_errors'])) {
    $errors = $_SESSION['approval_errors'];
    unset($_SESSION['approval_errors']);
}

// NOW fetch all pending requests AFTER processing
$pending_sql = "
    SELECT 
        m.member_id,
        m.name,
        m.email,
        m.phone,
        m.house_status,
        m.leave_request_date,
        m.join_request_date,
        m.requested_house_id,
        m.house_id as current_house_id,
        h_current.house_name as current_house_name,
        h_current.house_code as current_house_code,
        h_requested.house_name as requested_house_name,
        h_requested.house_code as requested_house_code,
        h_requested.house_id as requested_house_id,
        (SELECT COUNT(*) FROM meals WHERE member_id = m.member_id AND meal_date = CURDATE()) as today_meals,
        (SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE member_id = m.member_id) as total_deposits,
        (SELECT COALESCE(SUM(meal_count), 0) FROM meals WHERE member_id = m.member_id) as total_meals
    FROM members m
    LEFT JOIN houses h_current ON m.house_id = h_current.house_id
    LEFT JOIN houses h_requested ON m.requested_house_id = h_requested.house_id
    WHERE m.house_status IN ('pending_leave', 'pending_join')
    AND (m.house_id = ? OR m.requested_house_id = ?)
    ORDER BY 
        CASE m.house_status 
            WHEN 'pending_leave' THEN 1 
            WHEN 'pending_join' THEN 2 
        END,
        COALESCE(m.leave_request_date, m.join_request_date) DESC
";

$pending_stmt = mysqli_prepare($conn, $pending_sql);
mysqli_stmt_bind_param($pending_stmt, "ii", $manager_house_id, $manager_house_id);
mysqli_stmt_execute($pending_stmt);
$pending_result = mysqli_stmt_get_result($pending_stmt);

$leave_requests = [];
$join_requests = [];

while ($request = mysqli_fetch_assoc($pending_result)) {
    if ($request['house_status'] == 'pending_leave') {
        $leave_requests[] = $request;
    } elseif ($request['house_status'] == 'pending_join') {
        $join_requests[] = $request;
    }
}
mysqli_stmt_close($pending_stmt);

// Get house transfer log for history (for manager's house only)
$history_sql = "
    SELECT 
        l.*,
        m.name as member_name,
        h_from.house_name as from_house_name,
        h_to.house_name as to_house_name,
        u.username as performed_by_name
    FROM house_transfers_log l
    LEFT JOIN members m ON l.member_id = m.member_id
    LEFT JOIN houses h_from ON l.from_house_id = h_from.house_id
    LEFT JOIN houses h_to ON l.to_house_id = h_to.house_id
    LEFT JOIN users u ON l.performed_by = u.user_id
    WHERE (l.from_house_id = ? OR l.to_house_id = ?)
    ORDER BY l.performed_at DESC
    LIMIT 50
";
$history_stmt = mysqli_prepare($conn, $history_sql);
mysqli_stmt_bind_param($history_stmt, "ii", $manager_house_id, $manager_house_id);
mysqli_stmt_execute($history_stmt);
$history_result = mysqli_stmt_get_result($history_stmt);

require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-clipboard-check me-2"></i>Approve House Requests
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
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?php echo count($leave_requests); ?></h3>
                                <p class="text-muted mb-0">Pending Leave Requests</p>
                            </div>
                            <div class="bg-warning p-3 rounded">
                                <i class="fas fa-sign-out-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card border-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?php echo count($join_requests); ?></h3>
                                <p class="text-muted mb-0">Pending Join Requests</p>
                            </div>
                            <div class="bg-info p-3 rounded">
                                <i class="fas fa-sign-in-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Leave Requests Section -->
        <?php if (!empty($leave_requests)): ?>
        <div class="card shadow mb-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="fas fa-sign-out-alt me-2"></i>Leave Requests
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Current House</th>
                                <th>Request Date</th>
                                <th>Today's Meals</th>
                                <th>Total Data</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['name'] ?? ''); ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($request['email'] ?? ''); ?><br>
                                        <?php echo htmlspecialchars($request['phone'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($request['current_house_name'] ?? ''); ?><br>
                                    <small class="badge bg-info"><?php echo htmlspecialchars($request['current_house_code'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php echo $request['leave_request_date'] ? date('M d, Y g:i A', strtotime($request['leave_request_date'])) : 'N/A'; ?>
                                </td>
                                <td>
                                    <?php if (isset($request['today_meals']) && $request['today_meals'] > 0): ?>
                                        <span class="badge bg-danger">
                                            <?php echo $request['today_meals']; ?> meal(s)
                                        </span>
                                        <small class="text-danger d-block">Cannot leave today</small>
                                    <?php else: ?>
                                        <span class="badge bg-success">No meals today</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        Deposits: $<?php echo number_format($request['total_deposits'] ?? 0, 2); ?><br>
                                        Meals: <?php echo $request['total_meals'] ?? 0; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if (isset($request['today_meals']) && $request['today_meals'] == 0): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="member_id" value="<?php echo $request['member_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm" 
                                                    onclick="return confirm('Approve leave request for <?php echo addslashes($request['name'] ?? ''); ?>?\n\nThis will archive the member and mark them as left. They can then join another house.')">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectLeaveModal<?php echo $request['member_id']; ?>">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </div>
                                    
                                    <!-- Reject Reason Modal -->
                                    <div class="modal fade" id="rejectLeaveModal<?php echo $request['member_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reject Leave Request</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <p>Reject leave request for <strong><?php echo htmlspecialchars($request['name'] ?? ''); ?></strong>?</p>
                                                        <div class="mb-3">
                                                            <label for="reject_reason" class="form-label">Reason (Optional):</label>
                                                            <textarea class="form-control" id="reject_reason" name="reject_reason" rows="3" 
                                                                      placeholder="Enter reason for rejection..."></textarea>
                                                        </div>
                                                        <input type="hidden" name="member_id" value="<?php echo $request['member_id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Reject Request</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Join Requests Section -->
        <?php if (!empty($join_requests)): ?>
        <div class="card shadow mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-sign-in-alt me-2"></i>Join Requests
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Current/Previous House</th>
                                <th>Requested House</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($join_requests as $request): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['name'] ?? ''); ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($request['email'] ?? ''); ?><br>
                                        <?php echo htmlspecialchars($request['phone'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($request['current_house_name'] ?? 'No active house'); ?><br>
                                    <small class="badge bg-secondary"><?php echo htmlspecialchars($request['current_house_code'] ?? 'LEFT'); ?></small>
                                    <?php if (empty($request['current_house_id'])): ?>
                                        <br><small class="text-info">(Previously left)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($request['requested_house_name']) && $request['requested_house_name']): ?>
                                        <strong><?php echo htmlspecialchars($request['requested_house_name']); ?></strong><br>
                                        <small class="badge bg-info"><?php echo htmlspecialchars($request['requested_house_code'] ?? ''); ?></small>
                                    <?php else: ?>
                                        <span class="text-danger">House not found!</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $request['join_request_date'] ? date('M d, Y g:i A', strtotime($request['join_request_date'])) : 'N/A'; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if (isset($request['requested_house_name']) && $request['requested_house_name']): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="member_id" value="<?php echo $request['member_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm"
                                                    onclick="return confirm('Approve join request for <?php echo addslashes($request['name'] ?? ''); ?>?\n\nFrom: <?php echo addslashes($request['current_house_name'] ?? 'No house'); ?>\nTo: <?php echo addslashes($request['requested_house_name'] ?? ''); ?>\n\nThis will transfer the member to your house.')">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-danger btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#rejectJoinModal<?php echo $request['member_id']; ?>">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </div>
                                    
                                    <!-- Reject Reason Modal -->
                                    <div class="modal fade" id="rejectJoinModal<?php echo $request['member_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Reject Join Request</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <p>Reject join request for <strong><?php echo htmlspecialchars($request['name'] ?? ''); ?></strong>?</p>
                                                        <p>
                                                            From: <?php echo htmlspecialchars($request['current_house_name'] ?? 'No active house'); ?><br>
                                                            To: <?php echo htmlspecialchars($request['requested_house_name'] ?? 'Unknown'); ?>
                                                        </p>
                                                        <div class="mb-3">
                                                            <label for="reject_reason" class="form-label">Reason (Optional):</label>
                                                            <textarea class="form-control" id="reject_reason" name="reject_reason" rows="3"
                                                                      placeholder="Enter reason for rejection..."></textarea>
                                                        </div>
                                                        <input type="hidden" name="member_id" value="<?php echo $request['member_id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Reject Request</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- No Requests Message -->
        <?php if (empty($leave_requests) && empty($join_requests)): ?>
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                <h4>No Pending Requests</h4>
                <p class="text-muted">There are no pending house transfer requests at the moment.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Transfer History -->
        <div class="card shadow mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Transfer History
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Action</th>
                                <th>From/To</th>
                                <th>Performed By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($history_result) > 0) {
                                while ($history = mysqli_fetch_assoc($history_result)): 
                            ?>
                            <tr>
                                <td>
                                    <?php echo date('M d, Y', strtotime($history['performed_at'] ?? '')); ?><br>
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($history['performed_at'] ?? '')); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($history['member_name'] ?? ''); ?></td>
                                <td>
                                    <?php
                                    $action_badges = [
                                        'leave_approved' => ['badge' => 'success', 'text' => 'Leave Approved'],
                                        'leave_rejected' => ['badge' => 'danger', 'text' => 'Leave Rejected'],
                                        'join_approved' => ['badge' => 'success', 'text' => 'Join Approved'],
                                        'join_rejected' => ['badge' => 'danger', 'text' => 'Join Rejected'],
                                        'leave_requested' => ['badge' => 'warning', 'text' => 'Leave Requested'],
                                        'join_requested' => ['badge' => 'info', 'text' => 'Join Requested'],
                                        'leave_cancelled' => ['badge' => 'secondary', 'text' => 'Leave Cancelled'],
                                        'join_cancelled' => ['badge' => 'secondary', 'text' => 'Join Cancelled']
                                    ];
                                    $badge = $action_badges[$history['action'] ?? ''] ?? ['badge' => 'secondary', 'text' => $history['action'] ?? 'Unknown'];
                                    ?>
                                    <span class="badge bg-<?php echo $badge['badge']; ?>">
                                        <?php echo $badge['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (isset($history['from_house_name']) && $history['from_house_name']): ?>
                                        <small>From: <?php echo htmlspecialchars($history['from_house_name']); ?></small><br>
                                    <?php endif; ?>
                                    <?php if (isset($history['to_house_name']) && $history['to_house_name']): ?>
                                        <small>To: <?php echo htmlspecialchars($history['to_house_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($history['performed_by_name'] ?? ''); ?></td>
                                <td><small><?php echo htmlspecialchars($history['notes'] ?? ''); ?></small></td>
                            </tr>
                            <?php 
                                endwhile;
                            } else {
                                echo '<tr><td colspan="6" class="text-center text-muted">No transfer history found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close statements
if (isset($history_stmt)) mysqli_stmt_close($history_stmt);
require_once '../includes/footer.php';
?>