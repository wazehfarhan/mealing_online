<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/transfer_functions.php';

$auth = new Auth();
$auth->requireRole('member');

$page_title = "Join New House";

$conn = getConnection();
$member_id = $_SESSION['member_id'];
$current_house_id = $_SESSION['house_id'];
$current_house_code = $_SESSION['house_code'] ?? '';

$errors = [];
$success = '';

// Function to fetch fresh member data
function getMemberData($conn, $member_id) {
    $sql = "SELECT m.*, h.house_name, h.house_code 
            FROM members m 
            JOIN houses h ON m.house_id = h.house_id 
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
        
        // Check if member already has pending request
        if ($member['house_status'] != 'active') {
            $errors[] = "You already have a pending request: " . ucfirst(str_replace('_', ' ', $member['house_status']));
        } else {
            // Validate and use the token
            $token_result = useJoinToken($token, $member_id);
            
            if ($token_result['success']) {
                $requested_house_id = $token_result['house_id'];
                
                // Get requested house info
                $house_sql = "SELECT house_id, house_name, house_code FROM houses WHERE house_id = ?";
                $house_stmt = mysqli_prepare($conn, $house_sql);
                mysqli_stmt_bind_param($house_stmt, "i", $requested_house_id);
                mysqli_stmt_execute($house_stmt);
                $house_result = mysqli_stmt_get_result($house_stmt);
                $requested_house = mysqli_fetch_assoc($house_result);
                mysqli_stmt_close($house_stmt);
                
                if (!$requested_house) {
                    $errors[] = "House not found.";
                } else {
                    // Submit join request
                    $update_sql = "UPDATE members 
                                  SET house_status = 'pending_join', 
                                      requested_house_id = ?, 
                                      join_request_date = NOW() 
                                  WHERE member_id = ? AND house_status = 'active'";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "ii", $requested_house_id, $member_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                            $_SESSION['success'] = "Join request submitted successfully for house: " . htmlspecialchars($requested_house['house_name']) . "! Waiting for manager approval.";
                            
                            // Log activity - FIXED: to_house_id can be NULL for from_house_id
                            $log_sql = "INSERT INTO house_transfers_log 
                                        (member_id, from_house_id, to_house_id, action, performed_by, notes)
                                        VALUES (?, ?, ?, 'join_requested', ?, 'Member used token to request join')";
                            $log_stmt = mysqli_prepare($conn, $log_sql);
                            // We have 5 placeholders: member_id, from_house_id, to_house_id, performed_by
                            mysqli_stmt_bind_param($log_stmt, "iiiii", $member_id, $current_house_id, $requested_house_id, $member_id);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                            
                            // Redirect to avoid form resubmission
                            header("Location: join_request.php");
                            exit();
                        } else {
                            $errors[] = "Unable to submit join request. You may already have a pending request.";
                        }
                    } else {
                        $errors[] = "Failed to submit join request. Error: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($update_stmt);
                }
            } else {
                $errors[] = $token_result['error'];
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
        
        // Check if member already has pending request
        if ($member['house_status'] != 'active') {
            $errors[] = "You already have a pending request: " . ucfirst(str_replace('_', ' ', $member['house_status']));
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
            } elseif ($found_house['house_id'] == $current_house_id) {
                $errors[] = "You are already a member of this house.";
            } elseif ($found_house['is_open_for_join'] == 0) {
                $errors[] = "This house is not open for new members. Please use a join token instead.";
            } else {
                // Check if can join via house code
                $can_join = canJoinHouseViaCode($member_id, $found_house['house_id']);
                
                if ($can_join['success']) {
                    // Submit join request
                    $update_sql = "UPDATE members 
                                  SET house_status = 'pending_join', 
                                      requested_house_id = ?, 
                                      join_request_date = NOW() 
                                  WHERE member_id = ? AND house_status = 'active'";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "ii", $found_house['house_id'], $member_id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                            $_SESSION['success'] = "Join request submitted successfully for house: " . htmlspecialchars($found_house['house_name']) . "! Waiting for manager approval.";
                            
                            // Log activity - FIXED: to_house_id can be NULL for from_house_id
                            $log_sql = "INSERT INTO house_transfers_log 
                                        (member_id, from_house_id, to_house_id, action, performed_by, notes)
                                        VALUES (?, ?, ?, 'join_requested', ?, 'Member requested to join by house code')";
                            $log_stmt = mysqli_prepare($conn, $log_sql);
                            // We have 5 placeholders: member_id, from_house_id, to_house_id, performed_by
                            mysqli_stmt_bind_param($log_stmt, "iiiii", $member_id, $current_house_id, $found_house['house_id'], $member_id);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                            
                            // Redirect to avoid form resubmission
                            header("Location: join_request.php");
                            exit();
                        } else {
                            $errors[] = "Unable to submit join request. You may already have a pending request.";
                        }
                    } else {
                        $errors[] = "Failed to submit join request. Error: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    $errors[] = $can_join['error'];
                }
            }
        }
    }
}

// Handle cancel request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel_request') {
    // Refresh member data first
    $member = getMemberData($conn, $member_id);
    
    if ($member['house_status'] == 'pending_join') {
        $cancel_sql = "UPDATE members 
                      SET house_status = 'active', 
                          requested_house_id = NULL, 
                          join_request_date = NULL 
                      WHERE member_id = ? AND house_status = 'pending_join'";
        $cancel_stmt = mysqli_prepare($conn, $cancel_sql);
        mysqli_stmt_bind_param($cancel_stmt, "i", $member_id);
        
        if (mysqli_stmt_execute($cancel_stmt)) {
            if (mysqli_stmt_affected_rows($cancel_stmt) > 0) {
                $_SESSION['success'] = "Your join request has been cancelled.";
                
                // Log activity - FIXED: For cancel, we don't need to_house_id
                $log_sql = "INSERT INTO house_transfers_log 
                            (member_id, from_house_id, action, performed_by, notes)
                            VALUES (?, ?, 'join_cancelled', ?, 'Member cancelled join request')";
                $log_stmt = mysqli_prepare($conn, $log_sql);
                // We have 3 placeholders: member_id, from_house_id, performed_by
                mysqli_stmt_bind_param($log_stmt, "iii", $member_id, $current_house_id, $member_id);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
                
                // Redirect to avoid form resubmission
                header("Location: join_request.php");
                exit();
            }
        } else {
            $errors[] = "Failed to cancel request. Error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($cancel_stmt);
    }
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
               AND is_active = 1 
               AND house_id != ?
               ORDER BY house_name ASC 
               LIMIT 20";
$houses_stmt = mysqli_prepare($conn, $houses_sql);
mysqli_stmt_bind_param($houses_stmt, "i", $current_house_id);
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
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Account Status</dt>
                            <dd class="col-sm-8">
                                <?php if ($member['house_status'] == 'active'): ?>
                                <span class="badge bg-success">Active Member</span>
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
                                $req_house_sql = "SELECT house_name, house_code FROM houses WHERE house_id = ?";
                                $req_house_stmt = mysqli_prepare($conn, $req_house_sql);
                                mysqli_stmt_bind_param($req_house_stmt, "i", $member['requested_house_id']);
                                mysqli_stmt_execute($req_house_stmt);
                                $req_house_result = mysqli_stmt_get_result($req_house_stmt);
                                $req_house = mysqli_fetch_assoc($req_house_result);
                                mysqli_stmt_close($req_house_stmt);
                                ?>
                                <?php if ($req_house): ?>
                                <?php echo htmlspecialchars($req_house['house_name']); ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($req_house['house_code']); ?></span>
                                <?php else: ?>
                                <span class="text-danger">House not found</span>
                                <?php endif; ?>
                            </dd>
                            
                            <dt class="col-sm-4">Request Date</dt>
                            <dd class="col-sm-8"><?php echo date('M d, Y g:i A', strtotime($member['join_request_date'])); ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Request Actions -->
        <?php if ($member['house_status'] == 'pending_join'): ?>
        <div class="card shadow mb-4">
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
                    <input type="hidden" name="action" value="cancel_request">
                    <button type="submit" name="cancel_request" class="btn btn-secondary" 
                            onclick="return confirm('Are you sure you want to cancel your join request?');">
                        <i class="fas fa-times me-2"></i>Cancel Join Request
                    </button>
                </form>
            </div>
        </div>
        <?php elseif ($member['house_status'] == 'active'): ?>
        
        <!-- Join Methods -->
        <div class="row">
            <!-- Method 1: Join Token -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
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
                                       placeholder="Enter token (e.g., D58E64)" required>
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
                <div class="card shadow h-100">
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
                                       placeholder="Enter house code (e.g., 430346)" required>
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
            <div class="card-header">
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
                                <th>Joined System</th>
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
                                        <button type="submit" name="search_house" class="btn btn-sm btn-success">
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
                
                <div class="alert alert-light mt-3">
                    <h6><i class="fas fa-info-circle me-2"></i>How joining works:</h6>
                    <ul class="mb-0">
                        <li><strong>With Token:</strong> Enter the token provided by a house manager. This gives you direct access if approved.</li>
                        <li><strong>With House Code:</strong> Search for a house and request to join. The house must be "open for joining".</li>
                        <li><strong>Manager Approval:</strong> Your request will be reviewed by the manager of the house you want to join.</li>
                        <li><strong>Transfer:</strong> Once approved, you'll be transferred to the new house and your current house data will be archived.</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Important Info -->
        <div class="card shadow mt-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Important Information
                </h5>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>You can only have one active join request at a time</li>
                    <li>If you leave your current house, your data will be archived and preserved</li>
                    <li>You can view your previous house data anytime from Settings → View Past House History</li>
                    <li>The new house manager must approve your join request</li>
                    <li>Your current house manager will be notified of your transfer</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Close statements but NOT the connection
if (isset($info_stmt)) mysqli_stmt_close($info_stmt);
if (isset($stmt)) mysqli_stmt_close($stmt);
if (isset($check_stmt)) mysqli_stmt_close($check_stmt);
if (isset($house_stmt)) mysqli_stmt_close($house_stmt);
if (isset($update_stmt)) mysqli_stmt_close($update_stmt);
if (isset($log_stmt)) mysqli_stmt_close($log_stmt);
if (isset($req_house_stmt)) mysqli_stmt_close($req_house_stmt);
// DO NOT close $conn

require_once '../includes/footer.php';
?>