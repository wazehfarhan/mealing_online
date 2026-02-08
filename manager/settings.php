<?php
session_start();
// Enable debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Check if user has a house
$sql = "SELECT house_id FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);

// If no house, redirect to setup
if (!$user_data || !$user_data['house_id']) {
    $_SESSION['redirect_to'] = 'settings.php';
    header("Location: setup_house.php");
    exit();
}

// Set house_id from database (not session)
$house_id = $user_data['house_id'];
$_SESSION['house_id'] = $house_id; // Ensure session has it

// Initialize variables for regular form submissions
$message = '';
$error = '';

// Fetch house details FIRST
$sql = "SELECT * FROM houses WHERE house_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $house_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$house = mysqli_fetch_assoc($result);

// Check if house exists
if (!$house) {
    $_SESSION['error'] = "House not found in database. Please contact administrator.";
    header("Location: dashboard.php");
    exit();
}

// Get current user info
$current_user = $auth->getCurrentUser();

// Handle AJAX house update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_house'])) {
    $house_name = trim($_POST['house_name']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($house_name)) {
        echo json_encode(['success' => false, 'message' => 'House name is required']);
        exit();
    }
    
    $sql = "UPDATE houses SET house_name = ?, description = ?, is_active = ? WHERE house_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssii", $house_name, $description, $is_active, $house_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'House updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . mysqli_error($conn)]);
    }
    exit();
}


// Fetch members for this house
$sql = "SELECT * FROM members WHERE house_id = ? ORDER BY name";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $house_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$members = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Fetch user accounts linked to members
$sql = "SELECT u.*, m.name as member_name 
        FROM users u 
        LEFT JOIN members m ON u.member_id = m.member_id 
        WHERE u.house_id = ? AND u.role IN ('member', 'manager') AND u.is_active = 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $house_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);

// NOW include the header
$page_title = "House Settings - " . htmlspecialchars($house['house_name'] ?? 'Unknown House');
require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="content-header">
            <h1 class="page-title">
                <i class="fas fa-cog me-2"></i>
                House Settings
                <?php if (isset($house['house_name'])): ?>
                    <small class="text-muted">- <?php echo htmlspecialchars($house['house_name']); ?></small>
                <?php endif; ?>
            </h1>
            <?php if (isset($house['house_code'])): ?>
                <p class="text-muted mb-0">
                    <i class="fas fa-hashtag me-1"></i>House Code: <code><?php echo htmlspecialchars($house['house_code']); ?></code>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Messages will appear here -->
<div id="messageContainer"></div>

<!-- Display regular messages -->
<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php 
        echo $_SESSION['success'];
        unset($_SESSION['success']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php 
        echo $_SESSION['error'];
        unset($_SESSION['error']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <!-- House Details Card -->
        <div class="card stat-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-house-user me-2"></i>House Details</h5>
                <span class="badge <?php echo ($house['is_active'] ?? 0) ? 'bg-success' : 'bg-secondary'; ?>">
                    <?php echo ($house['is_active'] ?? 0) ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            <div class="card-body">
                <form id="houseForm" method="POST" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="house_name" class="form-label">House Name *</label>
                            <input type="text" class="form-control" id="house_name" name="house_name" 
                                   value="<?php echo htmlspecialchars($house['house_name'] ?? ''); ?>" required>
                            <div class="invalid-feedback">
                                Please provide a house name.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="house_code" class="form-label">House Code</label>
                            <input type="text" class="form-control" id="house_code" 
                                   value="<?php echo htmlspecialchars($house['house_code'] ?? ''); ?>" readonly>
                            <small class="text-muted">This code is unique and cannot be changed</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($house['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                               <?php echo ($house['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Active House</label>
                    </div>
                    
                    <button type="submit" name="update_house" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Update House
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <!-- Quick Stats Card -->
        <div class="card stat-card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>House Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="stat-number text-primary">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h4><?php echo count($members); ?></h4>
                            <p class="text-muted mb-0">Total Members</p>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-number text-success">
                            <i class="fas fa-user-check fa-2x mb-2"></i>
                            <?php 
                            $active_users = 0;
                            foreach ($user_accounts as $user) {
                                if ($user['is_active']) $active_users++;
                            }
                            ?>
                            <h4><?php echo $active_users; ?></h4>
                            <p class="text-muted mb-0">Active Users</p>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <small class="text-muted">House created: <?php echo date('F d, Y', strtotime($house['created_at'] ?? 'now')); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- House Management Section -->
<?php if ($_SESSION['role'] === 'manager'): ?>
<div class="card stat-card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-exchange-alt me-2"></i>House Management</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="alert alert-info">
                    <h6><i class="fas fa-user-crown me-2"></i>Manager Privileges</h6>
                    <p class="mb-2">As the house manager, you can:</p>
                    <ul class="mb-0">
                        <li>Add/remove members</li>
                        <li>Manage meal records</li>
                        <li>Handle expenses and deposits</li>
                        <li>Generate reports</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
                    <p class="mb-2">House Code: <code><?php echo $house['house_code']; ?></code></p>
                    <p class="small mb-0">Share this code with members so they can join your house.</p>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" onclick="window.location.href='update_profile.php'">
                        <i class="fas fa-user-edit me-2"></i>Update Profile
                    </button>
                    <button type="button" class="btn btn-outline-danger" 
                            data-bs-toggle="modal" data-bs-target="#leaveHouseModal">
                        <i class="fas fa-sign-out-alt me-2"></i>Leave House
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- For Regular Members -->
<div class="card stat-card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Member Information</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <p class="mb-2">You are a member of this house. For house management tasks, please contact the house manager.</p>
            <p class="mb-0"><strong>House Manager:</strong> 
                <?php 
                // Get manager info
                $sql = "SELECT u.username FROM users u 
                        WHERE u.house_id = ? AND u.role = 'manager' 
                        LIMIT 1";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $house_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $manager = mysqli_fetch_assoc($result);
                echo $manager ? htmlspecialchars($manager['username']) : 'Not assigned';
                ?>
            </p>
        </div>
        
        <!-- Action Buttons for Members -->
        <div class="d-grid gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.location.href='update_profile.php'">
                <i class="fas fa-user-edit me-2"></i>Update Profile
            </button>
            <button type="button" class="btn btn-outline-danger" 
                    data-bs-toggle="modal" data-bs-target="#leaveHouseModal">
                <i class="fas fa-sign-out-alt me-2"></i>Leave This House
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Members Management Card -->
<div class="card stat-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="fas fa-users me-2"></i>House Members</h5>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
            <i class="fas fa-plus-circle me-1"></i> Add Member
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="membersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Join Date</th>
                        <th>Status</th>
                        <th>User Account</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['name']); ?></td>
                            <td>
                                <?php if (!empty($member['phone'])): ?>
                                    <div><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($member['phone']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($member['email'])): ?>
                                    <div><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($member['email']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($member['join_date'])); ?></td>
                            <td>
                                <span class="badge <?php echo $member['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo ucfirst($member['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $has_account = false;
                                $user_account_type = '';
                                foreach ($user_accounts as $user) {
                                    if ($user['member_id'] == $member['member_id']) {
                                        $has_account = true;
                                        $user_account_type = $user['role'];
                                        break;
                                    }
                                }
                                
                                if ($has_account) {
                                    echo '<span class="badge bg-success">' . ucfirst($user_account_type) . '</span>';
                                } elseif (!$has_account && !empty($member['join_token'])) {
                                    echo '<span class="badge bg-warning text-dark">Invited</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">No Account</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary" 
                                            data-bs-toggle="modal" data-bs-target="#editMemberModal<?php echo $member['member_id']; ?>"
                                            title="Edit Member">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!$has_account): ?>
                                    <button type="button" class="btn btn-outline-info" 
                                            data-bs-toggle="modal" data-bs-target="#inviteModal<?php echo $member['member_id']; ?>"
                                            title="Generate Invite">
                                        <i class="fas fa-link"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-users fa-2x mb-3"></i>
                                <p class="mb-0">No members found. Add your first member!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Member Modal -->

<?php foreach ($members as $member): ?>
    <!-- Edit Member Modal -->
    <div class="modal fade" id="editMemberModal<?php echo $member['member_id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($member['name']); ?>" required>
                            <div class="invalid-feedback">
                                Please provide member name.
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo $member['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $member['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_member" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Invite Modal -->
    <div class="modal fade" id="inviteModal<?php echo $member['member_id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Invite Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                        
                        <p>Generate an invite link for <strong><?php echo htmlspecialchars($member['name']); ?></strong>.</p>
                        
                        <?php if (!empty($member['join_token']) && strtotime($member['token_expiry']) > time()): ?>
                            <div class="alert alert-warning">
                                <div class="mb-2">
                                    <strong>Current Invite Link:</strong><br>
                                    <code class="d-block p-2 bg-light rounded mt-1" style="word-break: break-all;">
                                        <?php echo BASE_URL . "join.php?token=" . $member['join_token']; ?>
                                    </code>
                                </div>
                                <p class="mb-0 small">
                                    <i class="fas fa-clock me-1"></i>
                                    Expires: <?php echo date('M d, Y H:i', strtotime($member['token_expiry'])); ?>
                                </p>
                            </div>
                        <?php elseif (!empty($member['join_token'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Previous invite link has expired.
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            New invite link will be valid for 7 days.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="generate_invite" class="btn btn-primary">
                            <?php echo !empty($member['join_token']) ? 'Regenerate Link' : 'Generate Invite Link'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Leave House Modal -->
<div class="modal fade" id="leaveHouseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave House</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone!
                </div>
                
                <p>Are you sure you want to leave <strong><?php echo htmlspecialchars($house['house_name']); ?></strong>?</p>
                
                <?php if ($_SESSION['role'] === 'manager'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-crown me-2"></i>
                    <strong>As a manager</strong>, leaving the house will:
                    <ul class="mb-0 mt-2">
                        <li>Remove all your administrative privileges</li>
                        <li>You'll need to join another house or create a new one</li>
                        <li>The house will remain active for other members</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="leave_house.php" style="display: inline;">
                    <input type="hidden" name="house_id" value="<?php echo $house_id; ?>">
                    <button type="submit" class="btn btn-danger">Leave House</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for AJAX House Update -->
<script>
document.getElementById('houseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('update_house', '1');
    
    fetch('settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('messageContainer');
        if (data.success) {
            container.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            // Update the house name in the title
            document.querySelector('.page-title small').textContent = ' - ' + document.getElementById('house_name').value;
        } else {
            container.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('messageContainer').innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>An error occurred
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    });
});
</script>

<?php 
$custom_js = "
$(document).ready(function() {
    // Form validation for non-AJAX forms
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })();
    
    // Initialize DataTables only if not already initialized
    if ($.fn.DataTable.isDataTable('#membersTable')) {
        $('#membersTable').DataTable().destroy();
    }
    
    $('#membersTable').DataTable({
        pageLength: 10,
        responsive: true,
        order: [],
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting for actions column
        ],
        language: {
            search: '_INPUT_',
            searchPlaceholder: 'Search members...'
        }
    });
});
";

require_once '../includes/footer.php';
?>