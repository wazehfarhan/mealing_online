<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Add Deposit";

$conn = getConnection();

$error = '';
$success = '';

// Get all active members
$sql = "SELECT * FROM members WHERE status = 'active' ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
$members = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = intval($_POST['member_id']);
    $amount = floatval($_POST['amount']);
    $deposit_date = mysqli_real_escape_string($conn, $_POST['deposit_date']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    // Validation
    if (empty($member_id) || $member_id <= 0) {
        $error = "Please select a member";
    } elseif (empty($deposit_date)) {
        $error = "Deposit date is required";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than 0";
    } else {
        // Insert deposit
        $sql = "INSERT INTO deposits (member_id, amount, deposit_date, description, created_by) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "idssi", $member_id, $amount, $deposit_date, $description, $_SESSION['user_id']);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Deposit added successfully!";
            
            // Clear form
            $_POST = array();
        } else {
            $error = "Error adding deposit: " . mysqli_error($conn);
        }
    }
}

// Get recent deposits for reference
$recent_sql = "SELECT d.*, m.name as member_name 
               FROM deposits d 
               JOIN members m ON d.member_id = m.member_id 
               ORDER BY d.deposit_date DESC, d.created_at DESC LIMIT 5";
$recent_result = mysqli_query($conn, $recent_sql);
$recent_deposits = mysqli_fetch_all($recent_result, MYSQLI_ASSOC);

// Get monthly deposit total
$current_month = date('Y-m');
$month_sql = "SELECT SUM(amount) as month_total FROM deposits WHERE DATE_FORMAT(deposit_date, '%Y-%m') = ?";
$month_stmt = mysqli_prepare($conn, $month_sql);
mysqli_stmt_bind_param($month_stmt, "s", $current_month);
mysqli_stmt_execute($month_stmt);
$month_result = mysqli_stmt_get_result($month_stmt);
$month_total = mysqli_fetch_assoc($month_result);

// Get member-wise deposit totals for current month
$member_totals_sql = "SELECT m.name, SUM(d.amount) as total 
                      FROM deposits d 
                      JOIN members m ON d.member_id = m.member_id 
                      WHERE DATE_FORMAT(d.deposit_date, '%Y-%m') = ? 
                      GROUP BY d.member_id 
                      ORDER BY total DESC";
$member_totals_stmt = mysqli_prepare($conn, $member_totals_sql);
mysqli_stmt_bind_param($member_totals_stmt, "s", $current_month);
mysqli_stmt_execute($member_totals_stmt);
$member_totals_result = mysqli_stmt_get_result($member_totals_stmt);
$member_totals = mysqli_fetch_all($member_totals_result, MYSQLI_ASSOC);
?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Add New Deposit</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div class="mt-3">
                        <a href="deposits.php" class="btn btn-primary me-2">View All Deposits</a>
                        <a href="add_deposit.php" class="btn btn-success">Add Another Deposit</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($members)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5>No Active Members</h5>
                    <p class="text-muted">Add some active members first to record deposits</p>
                    <a href="add_member.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Add Members
                    </a>
                </div>
                <?php else: ?>
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="member_id" class="form-label">Member *</label>
                            <select class="form-select" id="member_id" name="member_id" required>
                                <option value="">Select Member</option>
                                <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['member_id']; ?>" 
                                        <?php echo (isset($_POST['member_id']) && $_POST['member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                    <?php if ($member['phone']): ?> (<?php echo $member['phone']; ?>)<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select member who made the deposit</div>
                        </div>
                        <div class="col-md-6">
                            <label for="deposit_date" class="form-label">Deposit Date *</label>
                            <input type="date" class="form-control" id="deposit_date" name="deposit_date" 
                                   value="<?php echo isset($_POST['deposit_date']) ? $_POST['deposit_date'] : date('Y-m-d'); ?>" 
                                   required>
                            <div class="form-text">Date when deposit was made</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount (৳) *</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       step="0.01" min="0.01" 
                                       value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : ''; ?>" 
                                       required>
                            </div>
                            <div class="form-text">Enter deposit amount in Taka</div>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description"
                                   value="<?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?>"
                                   placeholder="e.g., Monthly deposit, Advance payment, etc.">
                            <div class="form-text">Optional description for the deposit</div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Deposit
                        </button>
                        <a href="deposits.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Deposits & Statistics -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Deposits</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_deposits)): ?>
                        <div class="text-center text-muted py-3">No recent deposits</div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_deposits as $deposit): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($deposit['member_name']); ?></h6>
                                    <small class="text-muted"><?php echo $functions->formatDate($deposit['deposit_date']); ?></small>
                                    <?php if ($deposit['description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($deposit['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-success rounded-pill"><?php echo $functions->formatCurrency($deposit['amount']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Monthly Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h3 class="text-success"><?php echo $functions->formatCurrency($month_total['month_total'] ?: 0); ?></h3>
                            <p class="text-muted mb-0">Total deposits for <?php echo date('F Y'); ?></p>
                        </div>
                        
                        <?php if (empty($member_totals)): ?>
                        <div class="text-center text-muted py-3">No deposits this month</div>
                        <?php else: ?>
                        <h6 class="mb-3">Member-wise Deposits:</h6>
                        <?php foreach ($member_totals as $item): 
                            $percentage = $month_total['month_total'] > 0 ? ($item['total'] / $month_total['month_total'] * 100) : 0;
                        ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo htmlspecialchars($item['name']); ?></span>
                                <span><?php echo $functions->formatCurrency($item['total']); ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $percentage; ?>%;" 
                                     aria-valuenow="<?php echo $percentage; ?>" 
                                     aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-bar me-2"></i>View Financial Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Set default date to today
    $('#deposit_date').val('<?php echo date("Y-m-d"); ?>');
    
    // Format amount on blur
    $('#amount').on('blur', function() {
        let value = parseFloat($(this).val());
        if (!isNaN(value)) {
            $(this).val(value.toFixed(2));
        }
    });
    
    // Auto-focus member select
    $('#member_id').focus();
});
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>