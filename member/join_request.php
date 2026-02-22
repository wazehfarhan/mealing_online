<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/transfer_functions.php';

$auth = new Auth();
$auth->requireRole('member');

$page_title = "Join New House";

$conn = getConnection();
$member_id = $_SESSION['member_id'];
$current_house_id = $_SESSION['house_id'] ?? null;
$current_house_code = $_SESSION['house_code'] ?? '';

$errors = [];
$success = '';

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

// Handle join token submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'use_token') {
    $token = trim($_POST['join_token'] ?? '');
    
    if (empty($token)) {
        $errors[] = "Please enter a join token.";
    } else {
        // Refresh member data to check current status
        $member = getMemberData($conn, $member_id);
        
        // Check if member can join using the helper function
        $can_join = canMemberJoinNewHouse($member_id);
        
        if (!$can_join['success']) {
            $errors[] = $can_join['error'];
        } else {
            // Submit join request using token
            $result = requestJoinHouseByToken($member_id, $token);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                header("Location: join_request.php");
                exit();
            } else {
                $errors[] = $result['error'];
            }
        }
    }
}

// Handle house code search and request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'search_house') {
    $search_code = trim($_POST['house_code'] ?? '');
    
    if (empty($search_code)) {
        $errors[] = "Please enter a house code.";
    } else {
        // Refresh member data to check current status
        $member = getMemberData($conn, $member_id);
        
        // Check if member can join using the helper function
        $can_join = canMemberJoinNewHouse($member_id);
        
        if (!$can_join['success']) {
            $errors[] = $can_join['error'];
        } else {
            // Search for house
            $house_sql = "SELECT house_id, house_name, house_code, is_open_for_join, created_at 
                          FROM houses 
                          WHERE house_code = ? AND is_active = 1";
            $house_stmt = mysqli_prepare($conn, $house_sql);
            mysqli_stmt_bind_param($house_stmt, "s", $search_code);
            mysqli_stmt_execute($house_stmt);
            $house_result = mysqli_stmt_get_result($house_stmt);
            $found_house = mysqli_fetch_assoc($house_result);
            mysqli_stmt_close($house_stmt);
            
            if (!$found_house) {
                $errors[] = "House with code '$search_code' not found or is inactive.";
            } elseif ($found_house['house_id'] == $current_house_id && $member['house_status'] == 'active') {
                $errors[] = "You are already a member of this house.";
            } elseif ($found_house['is_open_for_join'] == 0) {
                $errors[] = "This house is not open for new members. Please use a join token instead.";
            } else {
                // Submit join request
                $result = requestJoinHouseByCode($member_id, $search_code);
                
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                    header("Location: join_request.php");
                    exit();
                } else {
                    $errors[] = $result['error'];
                }
            }
        }
    }
}

// Handle cancel join request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel_join_request') {
    if (cancelJoinRequest($member_id)) {
        $_SESSION['success'] = "Your join request has been cancelled.";
    } else {
        $_SESSION['error'] = "Failed to cancel join request.";
    }
    
    header("Location: join_request.php");
    exit();
}

// Handle cancel leave request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel_leave_request') {
    if (cancelLeaveRequest($member_id)) {
        $_SESSION['success'] = "Your leave request has been cancelled.";
    } else {
        $_SESSION['error'] = "Failed to cancel leave request.";
    }
    
    header("Location: join_request.php");
    exit();
}

// Refresh member data after any processing
$member = getMemberData($conn, $member_id);

// Get any session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $errors[] = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get open houses for listing
$houses_sql = "SELECT house_id, house_name, house_code, created_at 
               FROM houses 
               WHERE is_open_for_join = 1 
               AND is_active = 1";

if (!empty($current_house_id) && $member['house_status'] == 'active') {
    $houses_sql .= " AND house_id != ?";
}

$houses_sql .= " ORDER BY house_name ASC LIMIT 20";

if (!empty($current_house_id) && $member['house_status'] == 'active') {
    $houses_stmt = mysqli_prepare($conn, $houses_sql);
    mysqli_stmt_bind_param($houses_stmt, "i", $current_house_id);
} else {
    $houses_stmt = mysqli_prepare($conn, $houses_sql);
}

mysqli_stmt_execute($houses_stmt);
$houses_result = mysqli_stmt_get_result($houses_stmt);
$open_houses = [];
while ($row = mysqli_fetch_assoc($houses_result)) {
    $open_houses[] = $row;
}
mysqli_stmt_close($houses_stmt);

// Include header AFTER all processing is done
require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-sign-in-alt me-2"></i>Join New House
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
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php foreach ($errors as $error): ?>
            <div><?php echo $error; ?></div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Current Status -->
        <div class="card shadow mb-4">
            <div class="card-header <?php 
                echo $member['house_status'] == 'active' ? 'bg-success text-white' : 
                    ($member['house_status'] == 'left' ? 'bg-warning' : 
                    ($member['house_status'] == 'pending_leave' ? 'bg-warning' : 'bg-info text-white')); 
            ?>">
                <h5 class="mb-0">
                    <i class="fas fa-home me-2"></i>Current House Status
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">House Name</dt>
                            <dd class="col-sm-8">
                                <?php if ($member['house_status'] == 'left'): ?>
                                    <span class="text-muted">No active house</span>
                                    <small class="d-block text-info">(Viewing history of: <?php echo htmlspecialchars($member['house_name'] ?? 'Unknown'); ?>)</small>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($member['house_name'] ?? 'None'); ?>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-4">House Code</dt>
                            <dd class="col-sm-8">
                                <?php if ($member['house_code']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($member['house_code']); ?></span>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-4">Join Date</dt>
                            <dd class="col-sm-8">
                                <?php echo $member['join_date'] ? date('M d, Y', strtotime($member['join_date'])) : 'N/A'; ?>
                            </dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Account Status</dt>
                            <dd class="col-sm-8">
                                <?php if ($member['status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-4">House Status</dt>
                            <dd class="col-sm-8">
                                <?php if ($member['house_status'] == 'active' && !empty($member['house_id'])): ?>
                                <span class="badge bg-success">Active Member</span>
                                <?php elseif ($member['house_status'] == 'left'): ?>
                                <span class="badge bg-warning">Left Previous House</span>
                                <small class="d-block text-success">✓ Eligible to join new house</small>
                                <?php elseif ($member['house_status'] == 'pending_leave'): ?>
                                <span class="badge bg-warning">Pending Leave Request</span>
                                <?php elseif ($member['house_status'] == 'pending_join'): ?>
                                <span class="badge bg-info">Pending Join Request</span>
                                <?php endif; ?>
                            </dd>
                            
                            <?php if ($member['house_status'] == 'pending_join'): ?>
                            <dt class="col-sm-4">Requested House</dt>
                            <dd class="col-sm-8">
                                <?php
                                if ($member['requested_house_id']) {
                                    $req_house_sql = "SELECT house_name, house_code FROM houses WHERE house_id = ?";
                                    $req_house_stmt = mysqli_prepare($conn, $req_house_sql);
                                    mysqli_stmt_bind_param($req_house_stmt, "i", $member['requested_house_id']);
                                    mysqli_stmt_execute($req_house_stmt);
                                    $req_house_result = mysqli_stmt_get_result($req_house_stmt);
                                    $req_house = mysqli_fetch_assoc($req_house_result);
                                    mysqli_stmt_close($req_house_stmt);
                                    
                                    if ($req_house): ?>
                                        <?php echo htmlspecialchars($req_house['house_name']); ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($req_house['house_code']); ?></span>
                                    <?php else: ?>
                                        <span class="text-danger">House not found</span>
                                    <?php endif;
                                } else { ?>
                                    <span class="text-danger">No house specified</span>
                                <?php } ?>
                            </dd>
                            
                            <dt class="col-sm-4">Request Date</dt>
                            <dd class="col-sm-8"><?php echo $member['join_request_date'] ? date('M d, Y g:i A', strtotime($member['join_request_date'])) : 'N/A'; ?></dd>
                            <?php endif; ?>
                            
                            <?php if ($member['house_status'] == 'pending_leave'): ?>
                            <dt class="col-sm-4">Leave Request</dt>
                            <dd class="col-sm-8"><?php echo $member['leave_request_date'] ? date('M d, Y g:i A', strtotime($member['leave_request_date'])) : 'N/A'; ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Request Actions -->
        <?php if ($member['house_status'] == 'pending_join'): ?>
        <div class="card shadow mb-4 border-info">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Join Request Pending
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p class="mb-2"><strong>Your join request is currently pending approval.</strong></p>
                    <p class="mb-0">Please wait for the manager of the requested house to review and approve your request.</p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="cancel_join_request">
                    <button type="submit" class="btn btn-warning" 
                            onclick="return confirm('Are you sure you want to cancel your join request?');">
                        <i class="fas fa-times me-2"></i>Cancel Join Request
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($member['house_status'] == 'pending_leave'): ?>
        <div class="card shadow mb-4 border-warning">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>Leave Request Pending
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <p class="mb-2"><strong>Your leave request is currently pending approval.</strong></p>
                    <p class="mb-0">Please wait for your manager to review your request.</p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="cancel_leave_request">
                    <button type="submit" class="btn btn-secondary" 
                            onclick="return confirm('Are you sure you want to cancel your leave request?');">
                        <i class="fas fa-times me-2"></i>Cancel Leave Request
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($member['house_status'] == 'left'): ?>
        
        <!-- Join Methods - Show for members who have left -->
        <div class="row">
            <!-- Method 1: Join Token -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100 border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-key me-2"></i>Join with Token
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Use a join token provided by a house manager.</p>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="use_token">
                            <div class="mb-3">
                                <label for="join_token" class="form-label">Join Token</label>
                                <input type="text" class="form-control" id="join_token" name="join_token" 
                                       placeholder="Enter token" required>
                                <div class="form-text">Enter the token provided by the house manager.</div>
                            </div>
                            
                            <button type="submit" name="use_token" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Token
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Method 2: House Code -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100 border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-home me-2"></i>Join with House Code
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Search for a house by its code (if open for joining).</p>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="search_house">
                            <div class="mb-3">
                                <label for="house_code" class="form-label">House Code</label>
                                <input type="text" class="form-control" id="house_code" name="house_code" 
                                       placeholder="Enter house code" required>
                                <div class="form-text">Enter the house code.</div>
                            </div>
                            
                            <button type="submit" name="search_house" class="btn btn-success">
                                <i class="fas fa-search me-2"></i>Search & Request
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Open Houses List -->
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-building me-2"></i>Houses Open for Joining
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($open_houses)): ?>
                <div class="alert alert-info">
                    <p class="mb-0">No houses are currently open for joining. Please use a join token instead.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>House Name</th>
                                <th>House Code</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($open_houses as $house): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($house['house_name']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($house['house_code']); ?></span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($house['created_at'])); ?></td>
                                <td>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="action" value="search_house">
                                        <input type="hidden" name="house_code" value="<?php echo htmlspecialchars($house['house_code']); ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-plus me-1"></i>Request to Join
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($member['house_status'] == 'active'): ?>
        <!-- Show message for active members -->
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>You are currently in an active house.</strong> 
            You must <a href="leave_house.php" class="alert-link">request to leave</a> your current house before joining a new one.
        </div>
        <?php endif; ?>
        
        <!-- Important Info -->
        <div class="card shadow mt-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Important Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>House Status Meanings:</h6>
                        <ul>
                            <li><span class="badge bg-success">Active Member</span> - You are in a house and cannot join another until you leave</li>
                            <li><span class="badge bg-warning">Left Previous House</span> - You can join a new house</li>
                            <li><span class="badge bg-warning">Pending Leave</span> - Your leave request is waiting for approval</li>
                            <li><span class="badge bg-info">Pending Join</span> - Your join request is waiting for approval</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Important Rules:</h6>
                        <ul class="mb-0">
                            <li>You can only have one active request at a time</li>
                            <li>If you leave your current house, your data will be archived</li>
                            <li>You can view your previous house data from Settings → View Past House History</li>
                            <li>The new house manager must approve your join request</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>