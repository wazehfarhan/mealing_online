<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Edit Expense";

$conn = getConnection();

// Check if expense ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid expense ID";
    header("Location: expenses.php");
    exit();
}

$expense_id = intval($_GET['id']);

// Get expense details
$sql = "SELECT e.*, u.username as created_by_name 
        FROM expenses e 
        LEFT JOIN users u ON e.created_by = u.user_id 
        WHERE e.expense_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $expense_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$expense = mysqli_fetch_assoc($result);

if (!$expense) {
    $_SESSION['error'] = "Expense not found";
    header("Location: expenses.php");
    exit();
}

$error = '';
$success = '';

// Expense categories
$categories = ['Rice', 'Fish', 'Meat', 'Vegetables', 'Gas', 'Internet', 'Utility', 'Others'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $expense_date = mysqli_real_escape_string($conn, $_POST['expense_date']);
    
    // Validation
    if (empty($expense_date)) {
        $error = "Expense date is required";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than 0";
    } elseif (empty($category)) {
        $error = "Category is required";
    } else {
        // Update expense
        $update_sql = "UPDATE expenses SET amount = ?, category = ?, description = ?, expense_date = ?, created_by = ? WHERE expense_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "dsssii", $amount, $category, $description, $expense_date, $_SESSION['user_id'], $expense_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Expense updated successfully!";
            
            // Refresh expense data
            $sql = "SELECT e.*, u.username as created_by_name 
                    FROM expenses e 
                    LEFT JOIN users u ON e.created_by = u.user_id 
                    WHERE e.expense_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $expense_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $expense = mysqli_fetch_assoc($result);
        } else {
            $error = "Error updating expense: " . mysqli_error($conn);
        }
    }
}

// Get monthly expense total for reference
$month = date('m', strtotime($expense['expense_date']));
$year = date('Y', strtotime($expense['expense_date']));
$month_start = "$year-$month-01";
$month_end = date('Y-m-t', strtotime($month_start));

$month_sql = "SELECT SUM(amount) as month_total FROM expenses WHERE expense_date BETWEEN ? AND ?";
$month_stmt = mysqli_prepare($conn, $month_sql);
mysqli_stmt_bind_param($month_stmt, "ss", $month_start, $month_end);
mysqli_stmt_execute($month_stmt);
$month_result = mysqli_stmt_get_result($month_stmt);
$month_total = mysqli_fetch_assoc($month_result);
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Expense</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div class="mt-3">
                        <a href="expenses.php" class="btn btn-primary">Back to All Expenses</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount (৳) *</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       step="0.01" min="0.01" value="<?php echo $expense['amount']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="expense_date" class="form-label">Expense Date *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                   value="<?php echo $expense['expense_date']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" 
                                        <?php echo $expense['category'] == $cat ? 'selected' : ''; ?>>
                                    <?php echo $cat; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description"
                                   value="<?php echo htmlspecialchars($expense['description']); ?>">
                        </div>
                    </div>
                    
                    <!-- Monthly Summary -->
                    <div class="card border-info mb-4">
                        <div class="card-body">
                            <h6><i class="fas fa-chart-bar me-2"></i>Monthly Summary (<?php echo date('F Y', strtotime($expense['expense_date'])); ?>)</h6>
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center">
                                        <h6 class="text-muted mb-1">Month Total</h6>
                                        <h5 class="text-primary"><?php echo $functions->formatCurrency($month_total['month_total'] ?: 0); ?></h5>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h6 class="text-muted mb-1">This Expense</h6>
                                        <h5 class="text-warning"><?php echo $functions->formatCurrency($expense['amount']); ?></h5>
                                    </div>
                                </div>
                            </div>
                            <?php if ($month_total['month_total'] > 0): ?>
                            <div class="mt-2">
                                <div class="progress" style="height: 10px;">
                                    <?php 
                                    $percentage = ($expense['amount'] / $month_total['month_total']) * 100;
                                    if ($percentage > 100) $percentage = 100;
                                    ?>
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%;" 
                                         aria-valuenow="<?php echo $percentage; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">This expense is <?php echo number_format($percentage, 1); ?>% of monthly total</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Expense
                        </button>
                        <a href="expenses.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Expense Details -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Expense Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Original Amount:</strong> <?php echo $functions->formatCurrency($expense['amount']); ?></p>
                        <p><strong>Original Category:</strong> 
                            <span class="badge bg-<?php echo getCategoryColor($expense['category']); ?>">
                                <?php echo $expense['category']; ?>
                            </span>
                        </p>
                        <p><strong>Original Date:</strong> <?php echo $functions->formatDate($expense['expense_date']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created By:</strong> <?php echo $expense['created_by_name'] ?: 'System'; ?></p>
                        <p><strong>Created At:</strong> <?php echo date('M d, Y h:i A', strtotime($expense['created_at'])); ?></p>
                        <?php if ($expense['created_at'] != $expense['updated_at']): ?>
                        <p><strong>Last Updated:</strong> <?php echo date('M d, Y h:i A', strtotime($expense['updated_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($expense['description']): ?>
                <div class="mt-3">
                    <h6>Description:</h6>
                    <p class="mb-0"><?php echo htmlspecialchars($expense['description']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function for category colors
function getCategoryColor($category) {
    $colors = [
        'Rice' => 'primary',
        'Fish' => 'info',
        'Meat' => 'danger',
        'Vegetables' => 'success',
        'Gas' => 'warning',
        'Internet' => 'secondary',
        'Utility' => 'dark',
        'Others' => 'light'
    ];
    return $colors[$category] ?? 'light';
}
?>

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
});
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>