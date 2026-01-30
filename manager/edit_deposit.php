<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

// Get manager's house_id
$user_id = $_SESSION['user_id'];
$house_id = $auth->getUserHouseId($user_id);

$page_title = "Edit Deposit";

$conn = getConnection();

// Check if deposit ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid deposit ID";
    header("Location: deposits.php");
    exit();
}

$deposit_id = intval($_GET['id']);

// Get deposit details with house verification
$sql = "SELECT d.*, m.name as member_name, 
               u1.username as created_by_name, 
               u2.username as updated_by_name
        FROM deposits d 
        JOIN members m ON d.member_id = m.member_id AND d.house_id = m.house_id
        LEFT JOIN users u1 ON d.created_by = u1.user_id 
        LEFT JOIN users u2 ON d.updated_by = u2.user_id
        WHERE d.deposit_id = ? AND d.house_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $deposit_id, $house_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$deposit = mysqli_fetch_assoc($result);

if (!$deposit) {
    $_SESSION['error'] = "Deposit not found or unauthorized access";
    header("Location: deposits.php");
    exit();
}

$error = '';
$success = '';

// Get all active members for this house
$members_sql = "SELECT * FROM members WHERE status = 'active' AND house_id = ? ORDER BY name";
$members_stmt = mysqli_prepare($conn, $members_sql);
mysqli_stmt_bind_param($members_stmt, "i", $house_id);
mysqli_stmt_execute($members_stmt);
$members_result = mysqli_stmt_get_result($members_stmt);
$all_members = $members_result ? mysqli_fetch_all($members_result, MYSQLI_ASSOC) : [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = intval($_POST['member_id']);
    $amount = floatval($_POST['amount']);
    $deposit_date = mysqli_real_escape_string($conn, $_POST['deposit_date']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $updated_by = $_SESSION['user_id'];
    
    // Validation
    if (empty($member_id) || $member_id <= 0) {
        $error = "Please select a member";
    } elseif (empty($deposit_date)) {
        $error = "Deposit date is required";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than 0";
    } elseif ($amount > 1000000) {
        $error = "Amount is too high. Please enter a reasonable amount.";
    } else {
        // Check if member belongs to this house
        $member_check_sql = "SELECT house_id FROM members WHERE member_id = ? AND house_id = ?";
        $member_check_stmt = mysqli_prepare($conn, $member_check_sql);
        mysqli_stmt_bind_param($member_check_stmt, "ii", $member_id, $house_id);
        mysqli_stmt_execute($member_check_stmt);
        mysqli_stmt_store_result($member_check_stmt);
        
        if (mysqli_stmt_num_rows($member_check_stmt) == 0) {
            $error = "Selected member does not belong to your house";
        } else {
            // Update deposit with updated_at timestamp and updated_by
            $update_sql = "UPDATE deposits SET member_id = ?, amount = ?, deposit_date = ?, 
                          description = ?, updated_by = ?, updated_at = NOW() 
                          WHERE deposit_id = ? AND house_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "idssiii", $member_id, $amount, $deposit_date, 
                                  $description, $updated_by, $deposit_id, $house_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Deposit updated successfully!";
                
                // Refresh deposit data
                $sql = "SELECT d.*, m.name as member_name, 
                               u1.username as created_by_name, 
                               u2.username as updated_by_name
                        FROM deposits d 
                        JOIN members m ON d.member_id = m.member_id AND d.house_id = m.house_id
                        LEFT JOIN users u1 ON d.created_by = u1.user_id 
                        LEFT JOIN users u2 ON d.updated_by = u2.user_id
                        WHERE d.deposit_id = ? AND d.house_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $deposit_id, $house_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $deposit = mysqli_fetch_assoc($result);
            } else {
                $error = "Error updating deposit: " . mysqli_error($conn);
            }
        }
    }
}

// Get monthly deposit total for reference (for this house only)
$month = date('m', strtotime($deposit['deposit_date']));
$year = date('Y', strtotime($deposit['deposit_date']));
$month_start = "$year-$month-01";
$month_end = date('Y-m-t', strtotime($month_start));

$month_sql = "SELECT SUM(amount) as month_total FROM deposits 
              WHERE deposit_date BETWEEN ? AND ? AND house_id = ?";
$month_stmt = mysqli_prepare($conn, $month_sql);
mysqli_stmt_bind_param($month_stmt, "ssi", $month_start, $month_end, $house_id);
mysqli_stmt_execute($month_stmt);
$month_result = mysqli_stmt_get_result($month_stmt);
$month_total = mysqli_fetch_assoc($month_result);

// Get member's monthly deposit total (for this house only)
$member_month_sql = "SELECT SUM(amount) as member_month_total 
                     FROM deposits 
                     WHERE member_id = ? AND deposit_date BETWEEN ? AND ? AND house_id = ?";
$member_month_stmt = mysqli_prepare($conn, $member_month_sql);
mysqli_stmt_bind_param($member_month_stmt, "issi", $deposit['member_id'], $month_start, $month_end, $house_id);
mysqli_stmt_execute($member_month_stmt);
$member_month_result = mysqli_stmt_get_result($member_month_stmt);
$member_month_total = mysqli_fetch_assoc($member_month_result);
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Deposit</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div class="mt-3">
                        <a href="deposits.php" class="btn btn-primary">Back to All Deposits</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="member_id" class="form-label">Member *</label>
                            <select class="form-select" id="member_id" name="member_id" required>
                                <option value="">Select Member</option>
                                <?php foreach ($all_members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>" 
                                        <?php echo $member['member_id'] == $deposit['member_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                    <?php if ($member['phone']): ?> (<?php echo $member['phone']; ?>)<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="deposit_date" class="form-label">Deposit Date *</label>
                            <input type="date" class="form-control" id="deposit_date" name="deposit_date" 
                                   value="<?php echo $deposit['deposit_date']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount (৳) *</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       step="0.01" min="0.01" max="1000000" value="<?php echo $deposit['amount']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description"
                                   value="<?php echo htmlspecialchars($deposit['description']); ?>"
                                   placeholder="Optional description">
                        </div>
                    </div>
                    
                    <!-- Monthly Summary -->
                    <div class="card border-success mb-4">
                        <div class="card-body">
                            <h6><i class="fas fa-chart-bar me-2"></i>Monthly Summary (<?php echo date('F Y', strtotime($deposit['deposit_date'])); ?>)</h6>
                            <div class="row">
                                <div class="col-4">
                                    <div class="text-center">
                                        <h6 class="text-muted mb-1">Month Total</h6>
                                        <h5 class="text-primary"><?php echo $functions->formatCurrency($month_total['month_total'] ?: 0); ?></h5>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center">
                                        <h6 class="text-muted mb-1">Member Total</h6>
                                        <h5 class="text-success"><?php echo $functions->formatCurrency($member_month_total['member_month_total'] ?: 0); ?></h5>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center">
                                        <h6 class="text-muted mb-1">This Deposit</h6>
                                        <h5 class="text-warning"><?php echo $functions->formatCurrency($deposit['amount']); ?></h5>
                                    </div>
                                </div>
                            </div>
                            <?php if ($month_total['month_total'] > 0): ?>
                            <div class="mt-2">
                                <div class="progress" style="height: 10px;">
                                    <?php 
                                    $member_total = $member_month_total['member_month_total'] ?: 0;
                                    $month_total_val = $month_total['month_total'] ?: 0;
                                    $member_percentage = $month_total_val > 0 ? ($member_total / $month_total_val) * 100 : 0;
                                    if ($member_percentage > 100) $member_percentage = 100;
                                    ?>
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $member_percentage; ?>%;" 
                                         aria-valuenow="<?php echo $member_percentage; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($member_percentage, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">This member contributed <?php echo number_format($member_percentage, 1); ?>% of monthly deposits</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Deposit
                        </button>
                        <a href="deposits.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Deposit Details -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Deposit Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Original Member:</strong> <?php echo htmlspecialchars($deposit['member_name']); ?></p>
                        <p><strong>Original Amount:</strong> <?php echo $functions->formatCurrency($deposit['amount']); ?></p>
                        <p><strong>Original Date:</strong> <?php echo $functions->formatDate($deposit['deposit_date']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created By:</strong> <?php echo $deposit['created_by_name'] ?: 'System'; ?></p>
                        <p><strong>Created At:</strong> <?php echo date('M d, Y h:i A', strtotime($deposit['created_at'])); ?></p>
                        
                        <?php if (!empty($deposit['updated_at']) && $deposit['updated_at'] != $deposit['created_at']): ?>
                        <p><strong>Last Updated:</strong> <?php echo date('M d, Y h:i A', strtotime($deposit['updated_at'])); ?></p>
                        <?php if (!empty($deposit['updated_by_name'])): ?>
                        <p><strong>Updated By:</strong> <?php echo $deposit['updated_by_name']; ?></p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($deposit['description']): ?>
                <div class="mt-3">
                    <h6>Description:</h6>
                    <p class="mb-0"><?php echo htmlspecialchars($deposit['description']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Format amount on blur
    $('#amount').on('blur', function() {
        let value = parseFloat($(this).val());
        if (!isNaN(value)) {
            $(this).val(value.toFixed(2));
        }
    });
    
    // Auto-focus amount field
    $('#amount').focus();
    
    // Show warning if amount is being increased significantly
    let originalAmount = <?php echo $deposit['amount']; ?>;
    $('#amount').on('change', function() {
        let newAmount = parseFloat($(this).val());
        if (!isNaN(newAmount) && newAmount > originalAmount * 1.5) {
            if (confirm('You are increasing the amount significantly (more than 50%). Are you sure this is correct?')) {
                return true;
            } else {
                $(this).val(originalAmount.toFixed(2));
            }
        }
    });
});
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>