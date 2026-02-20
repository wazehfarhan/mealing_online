<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/transfer_functions.php';

$auth = new Auth();
$auth->requireRole('member');

$page_title = "Request to Leave House";

$conn = getConnection();
$member_id = $_SESSION['member_id'];
$current_house_id = $_SESSION['house_id'];

$errors = [];
$success = '';
$warning = ''; // Initialize warning variable

// Function to fetch fresh member data
function getMemberData($conn, $member_id) {
    $sql = "SELECT m.*, h.house_name, h.house_code 
            FROM members m 
            LEFT JOIN houses h ON m.house_id = h.house_id 
            WHERE m.member_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $member_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $data;
}

// Get member info
$member = getMemberData($conn, $member_id);

if (!$member) {
    die("Member not found. Please logout and login again.");
}

// Check if member is already inactive
if ($member['status'] == 'inactive') {
    // Clear session and redirect to login
    session_destroy();
    header("Location: ../auth/login.php?error=inactive");
    exit();
}

// Check today's meals (only if member is active in a house)
$today_meals = ['total' => 0];
if ($member['house_status'] == 'active' || $member['house_status'] == 'pending_leave') {
    $today = date('Y-m-d');
    $today_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as total FROM meals WHERE member_id = ? AND meal_date = ?";
    $today_meals_stmt = mysqli_prepare($conn, $today_meals_sql);
    mysqli_stmt_bind_param($today_meals_stmt, "is", $member_id, $today);
    mysqli_stmt_execute($today_meals_stmt);
    $today_meals_result = mysqli_stmt_get_result($today_meals_stmt);
    $today_meals = mysqli_fetch_assoc($today_meals_result);
    mysqli_stmt_close($today_meals_stmt);
}

// Get member statistics (only if member has data)
$stats_sql = "
    SELECT 
        (SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE member_id = ?) as total_deposits,
        (SELECT COALESCE(SUM(meal_count), 0) FROM meals WHERE member_id = ?) as total_meals
";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "ii", $member_id, $member_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stats_stmt);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle leave request submission
    if (isset($_POST['action']) && $_POST['action'] == 'submit_leave') {
        // Check if member has already left
        if ($member['house_status'] == 'left') {
            $errors[] = "You have already left this house. You cannot submit another leave request.";
        }
        // Check if member is still active
        elseif ($member['status'] != 'active') {
            $errors[] = "You are not an active member of this house.";
        }
        // Check if member already has a pending leave request
        elseif ($member['house_status'] == 'pending_leave') {
            $errors[] = "You already have a pending leave request.";
        }
        // Check if member has a pending join request
        elseif ($member['house_status'] == 'pending_join') {
            $errors[] = "You have a pending join request. Please cancel it first.";
        }
        // Check if checkboxes are checked
        elseif (!isset($_POST['confirm_check']) || !isset($_POST['data_check'])) {
            $errors[] = "Please check both confirmation boxes before submitting.";
        }
        // Check if member has today's meals
        elseif ($today_meals['total'] > 0) {
            $_SESSION['warning'] = "You cannot leave the house today because you have meal entries for today (" . $today_meals['total'] . " meals). Please try again tomorrow.";
        }
        else {
            // Submit leave request
            $update_sql = "UPDATE members 
                          SET house_status = 'pending_leave', 
                              leave_request_date = NOW() 
                          WHERE member_id = ? 
                          AND house_status = 'active' 
                          AND status = 'active'";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "i", $member_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                    // Log activity
                    $log_sql = "INSERT INTO house_transfers_log 
                                (member_id, from_house_id, action, performed_by, notes)
                                VALUES (?, ?, 'leave_requested', ?, 'Member submitted leave request')";
                    $log_stmt = mysqli_prepare($conn, $log_sql);
                    mysqli_stmt_bind_param($log_stmt, "iii", $member_id, $current_house_id, $member_id);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                    
                    $_SESSION['success'] = "Your leave request has been submitted successfully! The manager will review and approve your request.";
                    
                    // Redirect to refresh the page
                    header("Location: leave_request.php");
                    exit();
                } else {
                    $errors[] = "Unable to submit leave request. You may already have a pending request or are not an active member.";
                }
            } else {
                $errors[] = "Failed to submit leave request. Error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        }
    }
    
    // Handle cancel request
    if (isset($_POST['action']) && $_POST['action'] == 'cancel_leave') {
        if ($member['house_status'] == 'pending_leave') {
            $cancel_sql = "UPDATE members 
                          SET house_status = 'active', 
                              leave_request_date = NULL 
                          WHERE member_id = ? 
                          AND house_status = 'pending_leave' 
                          AND status = 'active'";
            $cancel_stmt = mysqli_prepare($conn, $cancel_sql);
            mysqli_stmt_bind_param($cancel_stmt, "i", $member_id);
            
            if (mysqli_stmt_execute($cancel_stmt)) {
                if (mysqli_stmt_affected_rows($cancel_stmt) > 0) {
                    // Log activity
                    $log_sql = "INSERT INTO house_transfers_log 
                                (member_id, from_house_id, action, performed_by, notes)
                                VALUES (?, ?, 'leave_cancelled', ?, 'Member cancelled leave request')";
                    $log_stmt = mysqli_prepare($conn, $log_sql);
                    mysqli_stmt_bind_param($log_stmt, "iii", $member_id, $current_house_id, $member_id);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                    
                    $_SESSION['success'] = "Your leave request has been cancelled.";
                    
                    // Redirect to refresh the page
                    header("Location: leave_request.php");
                    exit();
                }
            } else {
                $errors[] = "Failed to cancel request. Error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($cancel_stmt);
        }
    }
}

// Refresh member data after any processing
$member = getMemberData($conn, $member_id);

// Get any session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['warning'])) {
    $warning = $_SESSION['warning'];
    unset($_SESSION['warning']);
}

if (isset($_SESSION['error'])) {
    $errors[] = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Include header AFTER all processing
require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-sign-out-alt me-2"></i>Request to Leave House
            </h1>
            <a href="settings.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Settings
            </a>
        </div>
        
        <!-- Display Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($warning): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $warning; ?>
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
        
        <!-- Check if member has left -->
        <?php if ($member['house_status'] == 'left'): ?>
        <div class="card shadow mb-4 border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>You Have Left the House
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <strong><i class="fas fa-info-circle me-2"></i>You have successfully left your previous house.</strong>
                    <p class="mb-0 mt-2">Your data has been archived and preserved. You can now join a new house.</p>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Previous House Information:</h6>
                                <p class="mb-1"><strong>House Name:</strong> <?php echo htmlspecialchars($member['house_name'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>House Code:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($member['house_code'] ?? 'N/A'); ?></span></p>
                                <p class="mb-0"><strong>Join Date:</strong> <?php echo date('M d, Y', strtotime($member['join_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Your Statistics from Previous House:</h6>
                                <p class="mb-1"><strong>Total Deposits:</strong> ৳<?php echo number_format($stats['total_deposits'] ?? 0, 2); ?></p>
                                <p class="mb-1"><strong>Total Meals:</strong> <?php echo number_format($stats['total_meals'] ?? 0, 2); ?></p>
                                <p class="mb-0"><strong>Status:</strong> <span class="badge bg-warning">Left</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="join_request.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Join New House
                    </a>
                    <a href="settings.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-cog me-2"></i>View Settings
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Check if member is inactive -->
        <?php elseif ($member['status'] == 'inactive'): ?>
        <div class="card shadow mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Account Inactive
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <strong><i class="fas fa-ban me-2"></i>Your account is inactive.</strong>
                    <p class="mb-0 mt-2">You have already left this house. Please contact your manager if you believe this is an error.</p>
                </div>
                <a href="../auth/logout.php" class="btn btn-primary">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
        
        <?php else: ?>
        
        <!-- Current Status for Active Members -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-home me-2"></i>Current House Status
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">House Name</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($member['house_name']); ?></dd>
                            
                            <dt class="col-sm-4">House Code</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-info"><?php echo htmlspecialchars($member['house_code']); ?></span>
                            </dd>
                            
                            <dt class="col-sm-4">Join Date</dt>
                            <dd class="col-sm-8"><?php echo date('M d, Y', strtotime($member['join_date'])); ?></dd>
                            
                            <dt class="col-sm-4">Member Status</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-success">Active</span>
                            </dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Request Status</dt>
                            <dd class="col-sm-8">
                                <?php if ($member['house_status'] == 'active'): ?>
                                <span class="badge bg-success">No Pending Request</span>
                                <?php elseif ($member['house_status'] == 'pending_leave'): ?>
                                <span class="badge bg-warning">Pending Leave Request</span>
                                <?php elseif ($member['house_status'] == 'pending_join'): ?>
                                <span class="badge bg-info">Pending Join Request</span>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-4">Today's Meals</dt>
                            <dd class="col-sm-8">
                                <?php if ($today_meals['total'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $today_meals['total']; ?> meals today</span>
                                <?php else: ?>
                                <span class="badge bg-success">No meals today</span>
                                <?php endif; ?>
                            </dd>
                            
                            <?php if ($member['house_status'] == 'pending_leave' && $member['leave_request_date']): ?>
                            <dt class="col-sm-4">Request Date</dt>
                            <dd class="col-sm-8"><?php echo date('M d, Y g:i A', strtotime($member['leave_request_date'])); ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Member Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h3 class="card-title">৳<?php echo number_format($stats['total_deposits'], 2); ?></h3>
                        <p class="card-text text-muted">Total Deposits</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo number_format($stats['total_meals'], 2); ?></h3>
                        <p class="card-text text-muted">Total Meals</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h3 class="card-title">৳<?php echo number_format($stats['total_deposits'] - ($stats['total_meals'] * 50), 2); ?></h3>
                        <p class="card-text text-muted">Estimated Balance</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo date('M Y', strtotime($member['join_date'])); ?></h3>
                        <p class="card-text text-muted">Member Since</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Leave Request Form -->
        <div class="card shadow">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Important Information Before Leaving
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-light">
                    <h6><i class="fas fa-info-circle me-2"></i>Please read carefully:</h6>
                    <ul class="mb-3">
                        <li>You can only leave the house if you have <strong>no meal entries for today</strong></li>
                        <li>Once your leave request is approved, your data will be archived</li>
                        <li>You will be marked as having left and can join another house</li>
                        <li>Your historical data (deposits, meals) will be preserved and can be viewed later</li>
                        <li>The manager of your current house must approve your leave request</li>
                        <li>If you have a pending balance, please settle it with the manager before leaving</li>
                        <li class="text-info"><strong>After leaving, you can join a new house from the Join New House page</strong></li>
                    </ul>
                </div>
                
                <?php if ($member['house_status'] == 'pending_leave'): ?>
                <!-- Already submitted request -->
                <div class="alert alert-warning">
                    <strong><i class="fas fa-clock me-2"></i>Your leave request is pending</strong>
                    <p class="mb-0 mt-2">Submitted on: <?php echo date('M d, Y g:i A', strtotime($member['leave_request_date'])); ?></p>
                    <p>Please wait for the manager to review and approve your request.</p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="cancel_leave">
                    <button type="submit" name="cancel_request" class="btn btn-secondary" 
                            onclick="return confirm('Are you sure you want to cancel your leave request?');">
                        <i class="fas fa-times me-2"></i>Cancel Leave Request
                    </button>
                </form>
                
                <?php elseif ($member['house_status'] == 'pending_join'): ?>
                <!-- Cannot leave due to pending join request -->
                <div class="alert alert-info">
                    <strong><i class="fas fa-info-circle me-2"></i>Pending Join Request</strong>
                    <p class="mb-0 mt-2">You have a pending join request. Please cancel it before requesting to leave.</p>
                </div>
                <a href="join_request.php" class="btn btn-info">
                    <i class="fas fa-eye me-2"></i>View Join Request
                </a>
                
                <?php elseif ($today_meals['total'] > 0): ?>
                <!-- Cannot leave due to today's meals -->
                <div class="alert alert-danger">
                    <strong><i class="fas fa-ban me-2"></i>Cannot submit leave request</strong>
                    <p class="mb-0 mt-2">You have <?php echo $today_meals['total']; ?> meal(s) recorded for today (<?php echo date('M d, Y'); ?>).</p>
                    <p>Please try again tomorrow after all meal entries for today are finalized.</p>
                </div>
                
                <?php elseif ($member['house_status'] == 'active'): ?>
                <!-- Can submit request -->
                <div class="alert alert-success">
                    <strong><i class="fas fa-check-circle me-2"></i>You are eligible to leave</strong>
                    <p class="mb-0 mt-2">You have no meal entries for today.</p>
                </div>
                
                <hr>
                
                <form method="POST" action="" id="leaveForm">
                    <input type="hidden" name="action" value="submit_leave">
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmCheck" name="confirm_check" value="1" required>
                        <label class="form-check-label" for="confirmCheck">
                            I understand that once my leave request is approved, I will no longer be an active member of this house.
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="dataCheck" name="data_check" value="1" required>
                        <label class="form-check-label" for="dataCheck">
                            I understand that my historical data will be archived and can be viewed later by entering the house code.
                        </label>
                    </div>
                    
                    <button type="submit" name="confirm_leave" class="btn btn-danger btn-lg" id="submitBtn">
                        <i class="fas fa-sign-out-alt me-2"></i>Submit Leave Request
                    </button>
                </form>
                
                <script>
                document.getElementById('leaveForm')?.addEventListener('submit', function(e) {
                    var confirmCheck = document.getElementById('confirmCheck');
                    var dataCheck = document.getElementById('dataCheck');
                    
                    if (!confirmCheck.checked) {
                        e.preventDefault();
                        alert('Please check the first box to confirm you understand.');
                        return false;
                    }
                    
                    if (!dataCheck.checked) {
                        e.preventDefault();
                        alert('Please check the second box to confirm you understand.');
                        return false;
                    }
                    
                    if (!confirm('Are you sure you want to submit a leave request?')) {
                        e.preventDefault();
                        return false;
                    }
                    
                    return true;
                });
                </script>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contact Manager -->
        <div class="card shadow mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-tie me-2"></i>Contact Your Manager
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Get manager info
                $manager_sql = "SELECT u.username, u.email 
                              FROM users u 
                              WHERE u.house_id = ? AND u.role = 'manager' 
                              LIMIT 1";
                $manager_stmt = mysqli_prepare($conn, $manager_sql);
                mysqli_stmt_bind_param($manager_stmt, "i", $current_house_id);
                mysqli_stmt_execute($manager_stmt);
                $manager_result = mysqli_stmt_get_result($manager_stmt);
                $manager = mysqli_fetch_assoc($manager_result);
                mysqli_stmt_close($manager_stmt);
                ?>
                
                <p>If you have any questions or need assistance with your leave request, please contact your house manager:</p>
                
                <?php if ($manager): ?>
                <div class="bg-light p-3 rounded">
                    <p class="mb-1"><strong>Manager:</strong> <?php echo htmlspecialchars($manager['username']); ?></p>
                    <p class="mb-0"><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($manager['email']); ?>"><?php echo htmlspecialchars($manager['email']); ?></a></p>
                </div>
                <?php else: ?>
                <p class="text-muted">Manager information not available.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// The connection is managed by getConnection() singleton
require_once '../includes/footer.php';
?>