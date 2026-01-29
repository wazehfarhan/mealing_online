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

$page_title = "Manage Members";

$conn = getConnection();

// Handle member deletion BEFORE any output
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $member_id = intval($_GET['delete']);
    
    // Check if member has any records
    $check_sql = "SELECT COUNT(*) as count FROM meals WHERE member_id = ? 
                  UNION SELECT COUNT(*) as count FROM deposits WHERE member_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $member_id, $member_id);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);
    
    $has_records = false;
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['count'] > 0) {
            $has_records = true;
            break;
        }
    }
    
    if ($has_records) {
        // Mark as inactive instead of deleting
        $update_sql = "UPDATE members SET status = 'inactive' WHERE member_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $member_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $_SESSION['success'] = "Member marked as inactive";
        } else {
            $_SESSION['error'] = "Error updating member status";
        }
    } else {
        // Delete member
        $delete_sql = "DELETE FROM members WHERE member_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $member_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $_SESSION['success'] = "Member deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting member";
        }
    }
    
    header("Location: members.php");
    exit();
}

// Now include the header AFTER potential redirects
require_once '../includes/header.php';

// Get all members
$sql = "SELECT m.*, u.username as created_by_name 
        FROM members m 
        LEFT JOIN users u ON m.created_by = u.user_id 
        ORDER BY m.status DESC, m.name ASC";
$result = mysqli_query($conn, $sql);
$members = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

// Count active members
$active_count = 0;
foreach ($members as $member) {
    if ($member['status'] == 'active') {
        $active_count++;
    }
}
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">Manage Members</h4>
                <p class="text-muted mb-0">Total: <?php echo count($members); ?> members (<?php echo $active_count; ?> active)</p>
            </div>
            <div>
                <a href="add_member.php" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Add New Member
                </a>
                <a href="generate_link.php" class="btn btn-success ms-2">
                    <i class="fas fa-link me-2"></i>Generate Join Link
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users me-2"></i>All Members</h6>
            </div>
            <div class="card-body">
                <?php if (empty($members)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5>No Members Found</h5>
                    <p class="text-muted">Add your first member to get started</p>
                    <a href="add_member.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Add First Member
                    </a>
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
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($members as $member): ?>
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
                                        <button type="button" class="btn btn-danger" 
                                                onclick="confirmDelete(<?php echo $member['member_id']; ?>, '<?php echo addslashes($member['name']); ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                                    <li>Click <i class="fas fa-trash text-danger"></i> to delete member</li>
                                    <li>Inactive members are marked as deleted</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-body">
                                <h6><i class="fas fa-chart-pie me-2"></i>Quick Stats</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h3 class="text-primary mb-0"><?php echo $active_count; ?></h3>
                                            <small class="text-muted">Active Members</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h3 class="text-danger mb-0"><?php echo count($members) - $active_count; ?></h3>
                                            <small class="text-muted">Inactive Members</small>
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

<script>
function confirmDelete(memberId, memberName) {
    if (confirm('Are you sure you want to delete "' + memberName + '"?\n\nNote: If the member has meal or deposit records, they will be marked as inactive instead of deletion.')) {
        window.location.href = 'members.php?delete=' + memberId;
    }
}
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>