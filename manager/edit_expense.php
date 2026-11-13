<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

// Check for valid expense ID BEFORE including header (to avoid "headers already sent" error)
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid expense ID";
    header("Location: expenses.php");
    exit();
}

// Get manager's house_id
$user_id = $_SESSION['user_id'];
$house_id = $auth->getUserHouseId($user_id);

$page_title = "Edit Expense";

$conn = getConnection();

$expense_id = intval($_GET['id']);

// Get expense details with house verification
$sql = "SELECT e.*, u.username as created_by_name, u2.username as updated_by_name
        FROM expenses e 
        LEFT JOIN users u ON e.created_by = u.user_id 
        LEFT JOIN users u2 ON e.updated_by = u2.user_id
        WHERE e.expense_id = ? AND e.house_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $expense_id, $house_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$expense = mysqli_fetch_assoc($result);

if (!$expense) {
    $_SESSION['error'] = "Expense not found or unauthorized access";
    header("Location: expenses.php");
    exit();
}
$original_expense = $expense;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    audit_log_event('manager.expense.edit.view', [
        'expense_id' => $expense_id,
    ]);
}

$error = '';
$success = '';

// Expense categories - SAME AS EXPENSES.PHP
$categories = ['Rice', 'Fish', 'Meat', 'Vegetables', 'Spices', 'Oil', 'Food', 'Others'];

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid. Please try again.";
    } else {
        $amount = is_numeric($_POST['amount'] ?? null) ? (float) $_POST['amount'] : 0;
        $category = is_string($_POST['category'] ?? null) ? $_POST['category'] : '';
        $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';
        $expense_date = is_string($_POST['expense_date'] ?? null) ? $_POST['expense_date'] : '';
        $updated_by = $_SESSION['user_id'];

        $date_parts = explode('-', $expense_date);
        $valid_date = count($date_parts) === 3
            && checkdate((int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0]);

    // Validation
    if (!$valid_date) {
        $error = "A valid expense date is required";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than 0";
    } elseif (!in_array($category, $categories, true)) {
        $error = "Select a valid category";
    } elseif ($amount > 1000000) {
        $error = "Amount is too high. Please enter a reasonable amount.";
    } else {
        // Update expense
        $update_sql = "UPDATE expenses SET amount = ?, category = ?, description = ?, 
                      expense_date = ?, updated_by = ?, updated_at = NOW() 
                      WHERE expense_id = ? AND house_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "dsssiii", $amount, $category, $description, 
                              $expense_date, $updated_by, $expense_id, $house_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Expense updated successfully!";
            audit_log_event('manager.expense.update.success', [
                'expense_id' => $expense_id,
                'before' => [
                    'amount' => $original_expense['amount'],
                    'category' => $original_expense['category'],
                    'expense_date' => $original_expense['expense_date'],
                ],
                'after' => [
                    'amount' => $amount,
                    'category' => $category,
                    'expense_date' => $expense_date,
                ],
            ]);
            
            // Refresh expense data
            $sql = "SELECT e.*, u.username as created_by_name, u2.username as updated_by_name
                    FROM expenses e 
                    LEFT JOIN users u ON e.created_by = u.user_id 
                    LEFT JOIN users u2 ON e.updated_by = u2.user_id
                    WHERE e.expense_id = ? AND e.house_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $expense_id, $house_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $expense = mysqli_fetch_assoc($result);
        } else {
            $error = "Unable to update expense. Please try again.";
            audit_log_event('manager.expense.update.error', [
                'expense_id' => $expense_id,
                'error' => mysqli_error($conn),
            ]);
        }
    }
    }

    if ($error !== '') {
        audit_log_event('manager.expense.update.rejected', [
            'expense_id' => $expense_id,
            'reason' => $error,
        ]);
    }
}

// Get monthly expense total for reference (for this house only)
$month = date('m', strtotime($expense['expense_date']));
$year = date('Y', strtotime($expense['expense_date']));
$month_start = "$year-$month-01";
$month_end = date('Y-m-t', strtotime($month_start));

$month_sql = "SELECT SUM(amount) as month_total FROM expenses 
             WHERE expense_date BETWEEN ? AND ? AND house_id = ?";
$month_stmt = mysqli_prepare($conn, $month_sql);
mysqli_stmt_bind_param($month_stmt, "ssi", $month_start, $month_end, $house_id);
mysqli_stmt_execute($month_stmt);
$month_result = mysqli_stmt_get_result($month_stmt);
$month_total = mysqli_fetch_assoc($month_result);

// Helper function for category colors - UPDATED TO MATCH EXPENSES.PHP
function getCategoryColor($category) {
    $colors = [
        'Rice' => 'primary',        // Blue
        'Fish' => 'info',           // Cyan
        'Meat' => 'danger',         // Red
        'Vegetables' => 'success',  // Green
        'Spices' => 'warning',      // Yellow
        'Oil' => 'purple',          // Purple
        'Food' => 'orange',         // Orange
        'Others' => 'secondary'     // Gray
    ];
    return $colors[$category] ?? 'secondary';
}

// Helper function for text color based on badge color
function getBadgeTextColor($badge_color) {
    // For light/yellow badges, use dark text
    return ($badge_color == 'warning' || $badge_color == 'light') ? 'text-dark' : 'text-white';
}

require_once '../includes/header.php';
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Expense</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                    <div class="mt-3">
                        <a href="expenses.php" class="btn btn-primary">Back to All Expenses</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount (৳) *</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" class="form-control" id="amount" name="amount"
                                       step="0.01" min="0.01" max="1000000"
                                       value="<?php echo htmlspecialchars((string) ($_POST['amount'] ?? $expense['amount']), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="expense_date" class="form-label">Expense Date *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date"
                                   value="<?php echo htmlspecialchars($_POST['expense_date'] ?? $expense['expense_date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): 
                                    $category_color = getCategoryColor($cat);
                                    $badge_text_color = getBadgeTextColor($category_color);
                                    
                                    // Special case for Oil (purple)
                                    if ($category_color == 'purple') {
                                        $badge_class = 'bg-purple text-white';
                                    } else {
                                        $badge_class = 'bg-' . $category_color . ' ' . $badge_text_color;
                                    }
                                ?>
                                <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo ($_POST['category'] ?? $expense['category']) === $cat ? 'selected' : ''; ?>>
                                    <span class="badge <?php echo $badge_class; ?> me-2">
                                        <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description"
                                   value="<?php echo htmlspecialchars($_POST['description'] ?? $expense['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="Optional description">
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
                                    $current_total = $month_total['month_total'] ?: 0;
                                    $this_expense = $expense['amount'];
                                    $percentage = $current_total > 0 ? ($this_expense / $current_total) * 100 : 0;
                                    if ($percentage > 100) $percentage = 100;
                                    
                                    // Determine progress bar color based on percentage
                                    $progress_bar_class = '';
                                    if ($percentage > 30) {
                                        $progress_bar_class = 'bg-danger';
                                    } elseif ($percentage > 15) {
                                        $progress_bar_class = 'bg-warning';
                                    } elseif ($percentage > 5) {
                                        $progress_bar_class = 'bg-primary';
                                    } else {
                                        $progress_bar_class = 'bg-success';
                                    }
                                    ?>
                                    <div class="progress-bar <?php echo $progress_bar_class; ?>" role="progressbar" 
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
                        <p><strong>Original Amount:</strong> <?php echo $functions->formatCurrency($original_expense['amount']); ?></p>
                        <p><strong>Original Category:</strong> 
                            <?php 
                            $category_color = getCategoryColor($original_expense['category']);
                            $badge_text_color = getBadgeTextColor($category_color);
                            
                            // Special case for Oil (purple)
                            if ($category_color == 'purple') {
                                $badge_class = 'bg-purple text-white';
                            } else {
                                $badge_class = 'bg-' . $category_color . ' ' . $badge_text_color;
                            }
                            ?>
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars($original_expense['category'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </p>
                        <p><strong>Original Date:</strong> <?php echo $functions->formatDate($original_expense['expense_date']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created By:</strong> <?php echo htmlspecialchars($original_expense['created_by_name'] ?: 'System', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Created At:</strong> <?php echo date('M d, Y h:i A', strtotime($original_expense['created_at'])); ?></p>
                        <?php if (!empty($original_expense['updated_at']) && $original_expense['updated_at'] != $original_expense['created_at']): ?>
                        <p><strong>Last Updated:</strong> <?php echo date('M d, Y h:i A', strtotime($original_expense['updated_at'])); ?></p>
                        <?php if (!empty($original_expense['updated_by_name'])): ?>
                        <p><strong>Updated By:</strong> <?php echo htmlspecialchars($original_expense['updated_by_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($original_expense['description']): ?>
                <div class="mt-3">
                    <h6>Description:</h6>
                    <p class="mb-0"><?php echo htmlspecialchars($original_expense['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Add this style for purple badge */
.bg-purple {
    background-color: #6f42c1 !important;
}
</style>

<span id="original-amount" style="display:none"><?php echo $expense['amount']; ?></span>


<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>
