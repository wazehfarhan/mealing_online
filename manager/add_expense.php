<?php
// Start output buffering to prevent header errors
ob_start();

require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

// Get manager's house_id
$user_id = $_SESSION['user_id'];
$house_id = $auth->getUserHouseId($user_id);

$page_title = "Add New Expense";

$conn = getConnection();

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
    $created_by = $_SESSION['user_id'];
    
    // Validation
    if (empty($expense_date)) {
        $error = "Expense date is required";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than 0";
    } elseif (empty($category)) {
        $error = "Category is required";
    } else {
        // Insert new expense with house_id
        $insert_sql = "INSERT INTO expenses (house_id, amount, category, description, expense_date, created_by, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "idsssi", $house_id, $amount, $category, $description, 
                               $expense_date, $created_by);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $success = "Expense added successfully!";
            
            // Clear form fields on success
            $_POST = array();
        } else {
            $error = "Error adding expense: " . mysqli_error($conn);
        }
    }
}

// Now include the header after all potential redirects
require_once '../includes/header.php';
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Expense</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div class="mt-3">
                        <a href="expenses.php" class="btn btn-primary me-2">Back to All Expenses</a>
                        <a href="add_expense.php" class="btn btn-success">Add Another Expense</a>
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
                                       step="0.01" min="0.01" value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="expense_date" class="form-label">Expense Date *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                   value="<?php echo isset($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" 
                                        <?php echo (isset($_POST['category']) && $_POST['category'] == $cat) ? 'selected' : ''; ?>>
                                    <?php echo $cat; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description"
                                   value="<?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?>"
                                   placeholder="Optional description of the expense">
                        </div>
                    </div>
                    
                    <?php if (isset($_POST['expense_date']) && !empty($_POST['expense_date'])): 
                        // Get monthly expense total for reference (for this house only)
                        $month = date('m', strtotime($_POST['expense_date']));
                        $year = date('Y', strtotime($_POST['expense_date']));
                        $month_start = "$year-$month-01";
                        $month_end = date('Y-m-t', strtotime($month_start));
                        
                        $month_sql = "SELECT SUM(amount) as month_total FROM expenses 
                                     WHERE expense_date BETWEEN ? AND ? AND house_id = ?";
                        $month_stmt = mysqli_prepare($conn, $month_sql);
                        mysqli_stmt_bind_param($month_stmt, "ssi", $month_start, $month_end, $house_id);
                        mysqli_stmt_execute($month_stmt);
                        $month_result = mysqli_stmt_get_result($month_stmt);
                        $month_total = mysqli_fetch_assoc($month_result);
                    ?>
                    <!-- Monthly Summary -->
                    <div class="card border-info mb-4">
                        <div class="card-body">
                            <h6><i class="fas fa-chart-bar me-2"></i>Monthly Summary (<?php echo date('F Y', strtotime($_POST['expense_date'])); ?>)</h6>
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-center">
                                        <h6 class="text-muted mb-1">Current Month Total</h6>
                                        <h5 class="text-primary"><?php echo $functions->formatCurrency($month_total['month_total'] ?: 0); ?></h5>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h6 class="text-muted mb-1">New Expense</h6>
                                        <h5 class="text-warning"><?php echo $functions->formatCurrency(isset($_POST['amount']) ? $_POST['amount'] : 0); ?></h5>
                                    </div>
                                </div>
                            </div>
                            <?php if ($month_total['month_total'] > 0): ?>
                            <div class="mt-2">
                                <?php 
                                $new_amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
                                $current_total = $month_total['month_total'] ?: 0;
                                $new_total = $current_total + $new_amount;
                                $percentage = $new_total > 0 ? ($new_amount / $new_total) * 100 : 0;
                                if ($percentage > 100) $percentage = 100;
                                ?>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%;" 
                                         aria-valuenow="<?php echo $percentage; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">This new expense will be <?php echo number_format($percentage, 1); ?>% of monthly total</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add Expense
                        </button>
                        <a href="expenses.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Recent Expenses -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Expenses</h6>
            </div>
            <div class="card-body">
                <?php 
                // Get recent 5 expenses for this house only
                $recent_sql = "SELECT e.*, u.username as created_by_name 
                              FROM expenses e 
                              LEFT JOIN users u ON e.created_by = u.user_id 
                              WHERE e.house_id = ? 
                              ORDER BY e.expense_date DESC, e.created_at DESC 
                              LIMIT 5";
                $recent_stmt = mysqli_prepare($conn, $recent_sql);
                mysqli_stmt_bind_param($recent_stmt, "i", $house_id);
                mysqli_stmt_execute($recent_stmt);
                $recent_result = mysqli_stmt_get_result($recent_stmt);
                $recent_expenses = $recent_result ? mysqli_fetch_all($recent_result, MYSQLI_ASSOC) : [];
                ?>
                
                <?php if (empty($recent_expenses)): ?>
                <div class="text-center text-muted py-3">
                    No expenses recorded yet
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_expenses as $recent): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <span class="badge bg-<?php echo getCategoryColor($recent['category']); ?> me-2">
                                <?php echo $recent['category']; ?>
                            </span>
                            <small class="text-muted"><?php echo $functions->formatDate($recent['expense_date']); ?></small>
                            <?php if ($recent['description']): ?>
                            <div class="small text-muted"><?php echo htmlspecialchars($recent['description']); ?></div>
                            <?php endif; ?>
                            <div class="small text-muted">
                                <i class="fas fa-user"></i> <?php echo $recent['created_by_name'] ?: 'System'; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-warning"><?php echo $functions->formatCurrency($recent['amount']); ?></div>
                            <a href="edit_expense.php?id=<?php echo $recent['expense_id']; ?>" class="btn btn-sm btn-outline-primary mt-1">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="expenses.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-list me-1"></i> View All Expenses
                    </a>
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
    
    // Set default date to today if empty
    if (!$('#expense_date').val()) {
        $('#expense_date').val('<?php echo date('Y-m-d'); ?>');
    }
    
    // Update monthly summary when date or amount changes
    $('#expense_date, #amount').on('change', function() {
        // Submit form via AJAX or just let user submit normally
        // For now, we'll rely on form submission
    });
});
</script>

<?php 
mysqli_close($conn);
ob_end_flush(); // Send output buffer and turn off output buffering
require_once '../includes/footer.php'; 
?>