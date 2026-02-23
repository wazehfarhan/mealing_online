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

$page_title = "Manage Members";

$conn = getConnection();

// Handle member deletion/removal
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $member_id = intval($_GET['delete']);
    
    // Check if member belongs to current house
    $check_sql = "SELECT m.*, 
                  (SELECT COUNT(*) FROM meals WHERE member_id = m.member_id) as meal_count,
                  (SELECT COUNT(*) FROM deposits WHERE member_id = m.member_id) as deposit_count,
                  (SELECT COUNT(*) FROM users WHERE member_id = m.member_id) as user_count
                  FROM members m 
                  WHERE m.member_id = ? AND m.house_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $member_id, $house_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    $member = mysqli_fetch_assoc($result);
    mysqli_stmt_close($check_stmt);
    
    if (!$member) {
        $_SESSION['error'] = "Member not found or you don't have permission to delete this member";
        header("Location: members.php");
        exit();
    }
    
    $has_meals = $member['meal_count'] > 0;
    $has_deposits = $member['deposit_count'] > 0;
    $has_user_account = $member['user_count'] > 0;
    
    if (!$has_meals && !$has_deposits && !$has_user_account) {
        // Member has no records and no account - can be permanently deleted
        $delete_sql = "DELETE FROM members WHERE member_id = ? AND house_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "ii", $member_id, $house_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $_SESSION['success'] = "Member deleted successfully";
            
            // Log the deletion
            $log_sql = "INSERT INTO house_transfers_log 
                        (member_id, from_house_id, action, performed_by, notes)
                        VALUES (?, ?, 'deleted', ?, 'Member permanently deleted (no records)')";
            $log_stmt = mysqli_prepare($conn, $log_sql);
            mysqli_stmt_bind_param($log_stmt, "iii", $member_id, $house_id, $user_id);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        } else {
            $_SESSION['error'] = "Error deleting member";
        }
        mysqli_stmt_close($delete_stmt);
    } else {
        // Member has records or account - mark as inactive but preserve data
        $update_sql = "UPDATE members SET status = 'inactive' WHERE member_id = ? AND house_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ii", $member_id, $house_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $reason = [];
            if ($has_meals) $reason[] = "meal records";
            if ($has_deposits) $reason[] = "deposit records";
            if ($has_user_account) $reason[] = "user account";
            
            $_SESSION['success'] = "Member marked as inactive (has " . implode(" and ", $reason) . ")";
            
            // Log the deactivation
            $log_sql = "INSERT INTO house_transfers_log 
                        (member_id, from_house_id, action, performed_by, notes)
                        VALUES (?, ?, 'deactivated', ?, 'Member deactivated (has " . implode(", ", $reason) . ")')";
            $log_stmt = mysqli_prepare($conn, $log_sql);
            mysqli_stmt_bind_param($log_stmt, "iii", $member_id, $house_id, $user_id);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        } else {
            $_SESSION['error'] = "Error updating member status";
        }
        mysqli_stmt_close($update_stmt);
    }
    
    header("Location: members.php");
    exit();
}

// Now include the header AFTER potential redirects
require_once '../includes/header.php';

// Get house name for display
$house_sql = "SELECT house_name FROM houses WHERE house_id = ?";
$house_stmt = mysqli_prepare($conn, $house_sql);
mysqli_stmt_bind_param($house_stmt, "i", $house_id);
mysqli_stmt_execute($house_stmt);
$house_result = mysqli_stmt_get_result($house_stmt);
$house = mysqli_fetch_assoc($house_result);
mysqli_stmt_close($house_stmt);
$house_name = $house ? $house['house_name'] : 'Unknown House';

// Get all members for current house only
// Show members with different statuses
$sql = "SELECT m.*, u.username as created_by_name,
        (SELECT COUNT(*) FROM meals WHERE member_id = m.member_id) as meal_count,
        (SELECT COUNT(*) FROM deposits WHERE member_id = m.member_id) as deposit_count,
        (SELECT COUNT(*) FROM users WHERE member_id = m.member_id) as user_count
        FROM members m 
        LEFT JOIN users u ON m.created_by = u.user_id 
        WHERE m.house_id = ?
        AND m.house_status != 'left'
        ORDER BY 
            CASE 
                WHEN m.status = 'active' THEN 1
                WHEN m.status = 'inactive' THEN 2
                ELSE 3
            END,
            m.name ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $house_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$members = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
mysqli_stmt_close($stmt);

// Get previous members who have left the house
$prev_members_sql = "SELECT m.*, u.username as created_by_name, ph.left_at, ph.final_balance,
                     ph.total_deposits, ph.total_meals
                     FROM members m 
                     LEFT JOIN users u ON m.created_by = u.user_id
                     LEFT JOIN previous_houses ph ON m.member_id = ph.member_id AND ph.house_id = ?
                     WHERE m.house_id = ?
                     AND m.house_status = 'left'
                     ORDER BY ph.left_at DESC, m.name ASC";
$prev_members_stmt = mysqli_prepare($conn, $prev_members_sql);
mysqli_stmt_bind_param($prev_members_stmt, "ii", $house_id, $house_id);
mysqli_stmt_execute($prev_members_stmt);
$prev_members_result = mysqli_stmt_get_result($prev_members_stmt);
$previous_members = $prev_members_result ? mysqli_fetch_all($prev_members_result, MYSQLI_ASSOC) : [];
mysqli_stmt_close($prev_members_stmt);
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">Manage Members</h4>
                <p class="text-muted mb-0">
                    House: <strong><?php echo htmlspecialchars($house_name); ?></strong> | 
                    Active: <?php 
                        $active_count = 0;
                        $inactive_count = 0;
                        foreach ($members as $m) {
                            if ($m['status'] == 'active') {
                                $active_count++;
                            } else {
                                $inactive_count++;
                            }
                        }
                        echo $active_count; 
                    ?> |
                    Inactive: <?php echo $inactive_count; ?>
                </p>
            </div>
            <div>
                <a href="add_member.php" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Add New Member
                </a>
                <a href="generate_link.php" class="btn btn-success ms-2">
                    <i class="fas fa-link me-2"></i>Generate Join Link
                </a>
                <a href="settings.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-cog me-2"></i>House Settings
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-users me-2"></i>Members of <?php echo htmlspecialchars($house_name); ?>
                </h6>
                <div>
                    <span class="badge bg-primary">House Name: <?php echo $house_name; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($members)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5>No Members Found</h5>
                    <p class="text-muted">Add your first member to get started</p>
                    <div class="mt-3">
                        <a href="add_member.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Add First Member
                        </a>
                        <a href="settings.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-link me-2"></i>Invite via Link
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Contact Info</th>
                                <th>Join Date</th>
                                <th>Status</th>
                                <th>User Account</th>
                                <th>Has Records</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($members as $member): 
                                // Get user account info
                                $user_account = null;
                                $user_sql = "SELECT username, is_active FROM users WHERE member_id = ?";
                                $user_stmt = mysqli_prepare($conn, $user_sql);
                                if ($user_stmt) {
                                    mysqli_stmt_bind_param($user_stmt, "i", $member['member_id']);
                                    mysqli_stmt_execute($user_stmt);
                                    $user_result = mysqli_stmt_get_result($user_stmt);
                                    $user_account = mysqli_fetch_assoc($user_result);
                                    mysqli_stmt_close($user_stmt);
                                }
                                
                                $has_records = ($member['meal_count'] > 0 || $member['deposit_count'] > 0);
                                $can_delete = (!$has_records && $member['user_count'] == 0);
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                    <?php if (isset($_SESSION['member_id']) && $member['member_id'] == $_SESSION['member_id']): ?>
                                    <span class="badge bg-info ms-1">You</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($member['phone']): ?>
                                    <div><i class="fas fa-phone text-muted me-2"></i><?php echo $member['phone']; ?></div>
                                    <?php endif; ?>
                                    <?php if ($member['email']): ?>
                                    <div><i class="fas fa-envelope text-muted me-2"></i><?php echo $member['email']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $functions->formatDate($member['join_date']); ?></td>
                                <td>
                                    <?php if ($member['status'] == 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user_account): ?>
                                        <span class="badge bg-success">Linked</span>
                                    <?php elseif (!empty($member['join_token']) && strtotime($member['token_expiry']) > time()): ?>
                                        <span class="badge bg-warning text-dark">Invited</span>
                                    <?php elseif (!empty($member['join_token'])): ?>
                                        <span class="badge bg-secondary">Expired</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Account</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($has_records): ?>
                                        <span class="badge bg-info" title="Has meal/deposit records">
                                            <i class="fas fa-database me-1"></i>Yes
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $member['created_by_name'] ?: 'System'; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit_member.php?id=<?php echo $member['member_id']; ?>" 
                                           class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="member_report.php?member_id=<?php echo $member['member_id']; ?>" 
                                           class="btn btn-info" title="View Report">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                        <?php if ($member['status'] == 'active' || $can_delete): ?>
                                        <button type="button" class="btn btn-danger" 
                                                onclick="confirmDelete(<?php echo $member['member_id']; ?>, '<?php echo addslashes($member['name']); ?>', <?php echo $has_records ? 'true' : 'false'; ?>, <?php echo $member['user_count'] > 0 ? 'true' : 'false'; ?>)"
                                                title="<?php echo $can_delete ? 'Permanently Delete' : 'Deactivate Member'; ?>">
                                            <i class="fas <?php echo $can_delete ? 'fa-trash' : 'fa-user-slash'; ?>"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h6><i class="fas fa-info-circle me-2"></i>Instructions</h6>
                                <ul class="mb-0">
                                    <li>Click <i class="fas fa-edit text-warning"></i> to edit member details</li>
                                    <li>Click <i class="fas fa-chart-bar text-info"></i> to view member report</li>
                                    <li>
                                        <i class="fas fa-trash text-danger"></i> - 
                                        <strong>Permanently delete</strong> members with no records and no account
                                    </li>
                                    <li>
                                        <i class="fas fa-user-slash text-danger"></i> - 
                                        <strong>Deactivate</strong> members who have records or accounts
                                    </li>
                                    <li>Deactivated members' data is preserved for historical reports</li>
                                    <li>Members with pending leave requests will appear here until approved</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-body">
                                <h6><i class="fas fa-chart-pie me-2"></i>House Member Statistics</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <?php 
                                        $active_count = 0;
                                        $inactive_count = 0;
                                        foreach ($members as $m) {
                                            if ($m['status'] == 'active') {
                                                $active_count++;
                                            } else {
                                                $inactive_count++;
                                            }
                                        }
                                        ?>
                                        <div class="text-center">
                                            <h3 class="text-primary mb-0"><?php echo $active_count; ?></h3>
                                            <small class="text-muted">Active Members</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h3 class="text-danger mb-0"><?php echo $inactive_count; ?></h3>
                                            <small class="text-muted">Inactive Members</small>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-6">
                                        <?php 
                                        $deletable_count = 0;
                                        foreach ($members as $m) {
                                            if ($m['meal_count'] == 0 && $m['deposit_count'] == 0 && $m['user_count'] == 0) {
                                                $deletable_count++;
                                            }
                                        }
                                        ?>
                                        <div class="text-center">
                                            <h3 class="text-success mb-0"><?php echo $deletable_count; ?></h3>
                                            <small class="text-muted">Can be Deleted</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <?php 
                                        $pending_count = 0;
                                        foreach ($members as $m) {
                                            if ($m['house_status'] == 'pending_leave') {
                                                $pending_count++;
                                            }
                                        }
                                        ?>
                                        <div class="text-center">
                                            <h3 class="text-warning mb-0"><?php echo $pending_count; ?></h3>
                                            <small class="text-muted">Pending Leave</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Previous Members Section -->
<?php if (!empty($previous_members)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow border-warning">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-warning">
                    <i class="fas fa-history me-2"></i>Previous Members (Left House)
                </h6>
                <div>
                    <span class="badge bg-warning"><?php echo count($previous_members); ?> members</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Contact Info</th>
                                <th>Join Date</th>
                                <th>Left Date</th>
                                <th>Total Meals</th>
                                <th>Total Deposits</th>
                                <th>Final Balance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($previous_members as $pmember): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($pmember['name']); ?></strong>
                                    <span class="badge bg-secondary ms-1">Former</span>
                                </td>
                                <td>
                                    <?php if ($pmember['phone']): ?>
                                    <div><i class="fas fa-phone text-muted me-2"></i><?php echo $pmember['phone']; ?></div>
                                    <?php endif; ?>
                                    <?php if ($pmember['email']): ?>
                                    <div><i class="fas fa-envelope text-muted me-2"></i><?php echo $pmember['email']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $functions->formatDate($pmember['join_date']); ?></td>
                                <td>
                                    <?php echo $pmember['left_at'] ? $functions->formatDate($pmember['left_at']) : 'N/A'; ?>
                                </td>
                                <td><?php echo $pmember['total_meals'] ?: 0; ?></td>
                                <td><?php echo $functions->formatCurrency($pmember['total_deposits'] ?: 0); ?></td>
                                <td>
                                    <?php 
                                    $final_balance = isset($pmember['final_balance']) ? $pmember['final_balance'] : 0;
                                    $balance_class = $final_balance >= 0 ? 'text-success' : 'text-danger';
                                    ?>
                                    <span class="<?php echo $balance_class; ?>">
                                        <?php echo $functions->formatCurrency($final_balance); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="member_report.php?member_id=<?php echo $pmember['member_id']; ?>&house_id=<?php echo $house_id; ?>" 
                                           class="btn btn-info" title="View Report">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    These members have left the house. Their data is archived and cannot be modified.
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function confirmDelete(memberId, memberName, hasRecords, hasAccount) {
    let message = '';
    
    if (!hasRecords && !hasAccount) {
        // Can be permanently deleted
        message = 'Are you sure you want to permanently delete "' + memberName + '"?\n\n' +
                 'This member has no meal records, no deposit records, and no user account.\n' +
                 'This action CANNOT be undone!';
    } else {
        // Must be deactivated
        let reasons = [];
        if (hasRecords) reasons.push('meal/deposit records');
        if (hasAccount) reasons.push('a user account');
        
        message = 'Are you sure you want to deactivate "' + memberName + '"?\n\n' +
                 'This member has ' + reasons.join(' and ') + '.\n' +
                 'They will be marked as inactive but their data will be preserved.\n' +
                 'You can still view their historical reports.';
    }
    
    if (confirm(message)) {
        window.location.href = 'members.php?delete=' + memberId;
    }
}

// Initialize DataTables if available
if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $(document).ready(function() {
        $('.datatable').DataTable({
            pageLength: 10,
            responsive: true,
            order: [[0, 'asc']],
            language: {
                search: '_INPUT_',
                searchPlaceholder: 'Search members...'
            }
        });
    });
}
</script>

<style>
.btn-group .btn i {
    font-size: 0.875rem;
}
</style>

<?php 
// Only close the connection at the very end, after all database operations
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
require_once '../includes/footer.php'; 
?>