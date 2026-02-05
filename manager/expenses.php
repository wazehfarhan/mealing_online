<?php
// =========================
// INCLUDES AND SETUP
// =========================
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$functions = new Functions();

// Only manager can access
$auth->requireRole('manager');

// Get manager's house_id
$user_id = $_SESSION['user_id'];
$house_id = $auth->getUserHouseId($user_id);

$page_title = "All Expenses";

$conn = getConnection();

// =========================
// HANDLE FILTERS
// =========================
$filter_month = isset($_GET['month']) && $_GET['month'] !== '' ? intval($_GET['month']) : '';
$filter_year = isset($_GET['year']) && $_GET['year'] !== '' ? intval($_GET['year']) : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

// Validate filter values
if ($filter_month) {
    $filter_month = max(1, min(12, $filter_month)); // Ensure month is 1-12
}
if ($filter_year) {
    $filter_year = max(2000, min(2100, $filter_year)); // Ensure reasonable year
}

// Sanitize category if provided
if ($filter_category) {
    $filter_category = mysqli_real_escape_string($conn, $filter_category);
}

// =========================
// HANDLE DELETION (BEFORE ANY OUTPUT)
// =========================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $expense_id = intval($_GET['delete']);
    
    // Verify the expense belongs to manager's house
    $verify_sql = "SELECT house_id FROM expenses WHERE expense_id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($verify_stmt, "i", $expense_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    $expense = mysqli_fetch_assoc($verify_result);
    
    if (!$expense || $expense['house_id'] != $house_id) {
        $_SESSION['error'] = "Unauthorized access or expense not found";
        header("Location: expenses.php");
        exit();
    }
    
    $delete_sql = "DELETE FROM expenses WHERE expense_id = ? AND house_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "ii", $expense_id, $house_id);
    
    if (mysqli_stmt_execute($delete_stmt)) {
        $_SESSION['success'] = "Expense deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting expense";
    }
    
    // Redirect back with filters
    $redirect_url = "expenses.php";
    $redirect_params = [];
    if ($filter_month) $redirect_params[] = "month=$filter_month";
    if ($filter_year) $redirect_params[] = "year=$filter_year";
    if ($filter_category) $redirect_params[] = "category=$filter_category";
    if (isset($_GET['page']) && $_GET['page'] > 1) $redirect_params[] = "page=" . intval($_GET['page']);
    
    if (!empty($redirect_params)) {
        $redirect_url .= "?" . implode("&", $redirect_params);
    }
    
    header("Location: $redirect_url");
    exit();
}

// Now include the header AFTER potential redirects
require_once __DIR__ . '/../includes/header.php';

// Build WHERE clause for queries using MySQL functions for flexible filtering
$where_conditions = ["e.house_id = ?"];
$params = [$house_id];
$param_types = "i";

if ($filter_month) {
    $where_conditions[] = "MONTH(e.expense_date) = ?";
    $params[] = $filter_month;
    $param_types .= "i";
}

if ($filter_year) {
    $where_conditions[] = "YEAR(e.expense_date) = ?";
    $params[] = $filter_year;
    $param_types .= "i";
}

if ($filter_category) {
    $where_conditions[] = "e.category = ?";
    $params[] = $filter_category;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// =========================
// TOTAL RECORDS
// =========================
$count_sql = "SELECT COUNT(*) as total FROM expenses e WHERE $where_clause";
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row ? $count_row['total'] : 0;

// =========================
// PAGINATION
// =========================
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_records / $per_page);

// =========================
// FETCH EXPENSES - UPDATED: now includes updated_by
// =========================
$sql = "SELECT e.*, u.username as created_by_name, u2.username as updated_by_name
        FROM expenses e 
        LEFT JOIN users u ON e.created_by = u.user_id 
        LEFT JOIN users u2 ON e.updated_by = u2.user_id
        WHERE $where_clause 
        ORDER BY e.expense_date DESC, e.created_at DESC 
        LIMIT ? OFFSET ?";

$params_with_limit = $params; // preserve original params for summary/breakdown
$param_types_with_limit = $param_types;

// Add LIMIT and OFFSET
$params_with_limit[] = $per_page;
$params_with_limit[] = $offset;
$param_types_with_limit .= "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $param_types_with_limit, ...$params_with_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$expenses = mysqli_fetch_all($result, MYSQLI_ASSOC);

// =========================
// EXPENSE CATEGORIES
// =========================
$categories = ['Rice', 'Fish', 'Meat', 'Vegetables', 'Spices', 'Oil', 'food', 'Others'];

// =========================
// SUMMARY
// =========================
$summary_sql = "SELECT 
                SUM(e.amount) as total_amount,
                COUNT(*) as total_expenses,
                AVG(e.amount) as avg_expense
                FROM expenses e 
                WHERE $where_clause";

$summary_stmt = mysqli_prepare($conn, $summary_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($summary_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($summary_stmt);
$summary_result = mysqli_stmt_get_result($summary_stmt);
$summary_row = mysqli_fetch_assoc($summary_result);
$summary = $summary_row ?: [
    'total_amount' => 0,
    'total_expenses' => 0,
    'avg_expense' => 0
];

// =========================
// CATEGORY BREAKDOWN
// =========================
$breakdown_sql = "SELECT category, SUM(amount) as total, COUNT(*) as count 
                  FROM expenses e
                  WHERE $where_clause 
                  GROUP BY category 
                  ORDER BY total DESC";

$breakdown_stmt = mysqli_prepare($conn, $breakdown_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($breakdown_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($breakdown_stmt);
$breakdown_result = mysqli_stmt_get_result($breakdown_stmt);
$category_breakdown = $breakdown_result ? mysqli_fetch_all($breakdown_result, MYSQLI_ASSOC) : [];
?>
<!-- =========================
     PAGE HEADER
========================= -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">Expense Management</h4>
                <p class="text-muted mb-0">Total: <?php echo $total_records; ?> expense records</p>
            </div>
            <div>
                <a href="add_expense.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Expense
                </a>
            </div>
        </div>
    </div>
</div>

<!-- =========================
     FILTERS
========================= -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-2"></i>Filter Expenses</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="month" class="form-label">Month</label>
                        <select name="month" id="month" class="form-select">
                            <option value="">All Months</option>
                            <?php
                            $months = ['January','February','March','April','May','June',
                                       'July','August','September','October','November','December'];
                            foreach ($months as $index => $month_name):
                                $month_num = $index + 1;
                            ?>
                            <option value="<?php echo $month_num; ?>" <?php echo $month_num == $filter_month ? 'selected' : ''; ?>>
                                <?php echo $month_name; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="year" class="form-label">Year</label>
                        <select name="year" id="year" class="form-select">
                            <option value="">All Years</option>
                            <?php
                            $current_year = date('Y');
                            for ($y = $current_year; $y >= $current_year - 5; $y--):
                            ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $filter_year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="category" class="form-label">Category</label>
                        <select name="category" id="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo $cat == $filter_category ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-grid w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                        </div>
                    </div>
                    
                    <!-- Hidden field to preserve current page if needed -->
                    <?php if (isset($_GET['page']) && $_GET['page'] > 1): ?>
                    <input type="hidden" name="page" value="<?php echo intval($_GET['page']); ?>">
                    <?php endif; ?>
                </form>
                
                <?php if ($filter_month || $filter_year || $filter_category): ?>
                <div class="mt-3">
                    <small class="text-muted">
                        Active filters: 
                        <?php if ($filter_month): ?>
                        <span class="badge bg-info">Month: <?php echo $months[$filter_month-1]; ?></span>
                        <?php endif; ?>
                        <?php if ($filter_year): ?>
                        <span class="badge bg-info">Year: <?php echo $filter_year; ?></span>
                        <?php endif; ?>
                        <?php if ($filter_category): ?>
                        <span class="badge bg-info">Category: <?php echo $filter_category; ?></span>
                        <?php endif; ?>
                        <a href="expenses.php" class="text-danger ms-2"><small>Clear all filters</small></a>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- =========================
     SUMMARY CARDS
========================= -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Expenses</h6>
                <h3 class="text-primary mb-0"><?php echo $functions->formatCurrency($summary['total_amount'] ?: 0); ?></h3>
                <small class="text-muted">Total amount</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Records</h6>
                <h3 class="text-success mb-0"><?php echo $summary['total_expenses'] ?: 0; ?></h3>
                <small class="text-muted">Expense entries</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Average Expense</h6>
                <h3 class="text-warning mb-0"><?php echo $functions->formatCurrency($summary['avg_expense'] ?: 0); ?></h3>
                <small class="text-muted">Per entry</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Filtered Results</h6>
                <h3 class="text-info mb-0"><?php echo count($expenses); ?></h3>
                <small class="text-muted">Showing <?php echo $per_page; ?> per page</small>
            </div>
        </div>
    </div>
</div>

<!-- =========================
     EXPENSE TABLE
========================= -->
<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-money-bill-wave me-2"></i>Expense List</h6>
            </div>
            <div class="card-body">
                <?php if (empty($expenses)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                    <h5>No Expenses Found</h5>
                    <p class="text-muted">
                        <?php if ($filter_month || $filter_year || $filter_category): ?>
                        Try adjusting your filters or
                        <?php endif; ?>
                        Add your first expense to get started
                    </p>
                    <?php if ($filter_month || $filter_year || $filter_category): ?>
                    <a href="expenses.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </a>
                    <?php endif; ?>
                    <a href="add_expense.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add First Expense
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                                <th>Created By</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = ($page - 1) * $per_page + 1; ?>
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo $functions->formatDate($expense['expense_date']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getCategoryColor($expense['category']); ?>">
                                        <?php echo $expense['category']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($expense['description'] ?: '-'); ?></td>
                                <td class="text-end"><strong class="text-warning"><?php echo $functions->formatCurrency($expense['amount']); ?></strong></td>
                                <td>
                                    <?php echo $expense['created_by_name'] ?: 'System'; ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($expense['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($expense['updated_at'] && $expense['updated_at'] != $expense['created_at']): ?>
                                        <?php echo $expense['updated_by_name'] ?: 'System'; ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($expense['updated_at'])); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not updated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit_expense.php?id=<?php echo $expense['expense_id']; ?>" class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger" 
                                                onclick="confirmDelete(<?php echo $expense['expense_id']; ?>, '<?php echo addslashes($expense['category']); ?>', '<?php echo $expense['expense_date']; ?>')" 
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
                <!-- =========================
                     PAGINATION
                ========================= -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?page=<?php echo $page-1; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&category=<?php echo urlencode($filter_category); ?>">
                                Previous
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($p = $start_page; $p <= $end_page; $p++):
                        ?>
                        <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?page=<?php echo $p; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&category=<?php echo urlencode($filter_category); ?>">
                                <?php echo $p; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?page=<?php echo $page+1; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&category=<?php echo urlencode($filter_category); ?>">
                                Next
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; // end if expenses empty check ?>
            </div>
        </div>
    </div>

    <!-- =========================
         CATEGORY BREAKDOWN & EXPORT
    ========================= -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-pie me-2"></i>Category Breakdown</h6>
            </div>
            <div class="card-body">
                <?php if (empty($category_breakdown)): ?>
                <div class="text-center text-muted py-3">No expenses to show</div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($category_breakdown as $item): 
                        $percentage = $summary['total_amount'] > 0 ? ($item['total'] / $summary['total_amount'] * 100) : 0;
                    ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <span class="badge bg-<?php echo getCategoryColor($item['category']); ?> me-2">
                                <?php echo $item['category']; ?>
                            </span>
                            <small class="text-muted">(<?php echo $item['count']; ?> entries)</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold"><?php echo $functions->formatCurrency($item['total']); ?></div>
                            <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Category Chart -->
                <div class="mt-4">
                    <canvas id="categoryChart" height="200"></canvas>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Export Options -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-download me-2"></i>Export Options</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="export_expenses.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&category=<?php echo urlencode($filter_category); ?>&format=csv" 
                       class="btn btn-outline-success">
                        <i class="fas fa-file-csv me-2"></i>Export as CSV
                    </a>
                    <a href="export_expenses.php?month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&category=<?php echo urlencode($filter_category); ?>&format=pdf" 
                       class="btn btn-outline-danger">
                        <i class="fas fa-file-pdf me-2"></i>Export as PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// =========================
// HELPER FUNCTION
// =========================
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

<!-- =========================
     JAVASCRIPT
========================= -->
<script>
function confirmDelete(expenseId, category, date) {
    if (confirm('Are you sure you want to delete expense "' + category + '" from ' + date + '?')) {
        let url = 'expenses.php?delete=' + expenseId;
        
        // Add current filter parameters
        const month = <?php echo $filter_month ? "'$filter_month'" : "''"; ?>;
        const year = <?php echo $filter_year ? "'$filter_year'" : "''"; ?>;
        const categoryFilter = <?php echo $filter_category ? "'" . addslashes($filter_category) . "'" : "''"; ?>;
        const page = <?php echo $page; ?>;
        
        if (month) url += '&month=' + month;
        if (year) url += '&year=' + year;
        if (categoryFilter) url += '&category=' + encodeURIComponent(categoryFilter);
        if (page > 1) url += '&page=' + page;
        
        window.location.href = url;
    }
}

<?php if (!empty($category_breakdown)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php foreach ($category_breakdown as $item): ?>
                '<?php echo $item['category']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($category_breakdown as $item): ?>
                    <?php echo $item['total']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    '#3498db', '#17a2b8', '#e74c3c', '#27ae60',
                    '#f39c12', '#6c757d', '#2c3e50', '#95a5a6'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                }
            }
        }
    });
});
<?php endif; ?>
</script>

<?php 
mysqli_close($conn);
require_once __DIR__ . '/../includes/footer.php'; 
?>