<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include files
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authorization
$auth = new Auth();
$functions = new Functions();
$auth->requireRole('manager');

// Get manager's house_id
$user_id = $_SESSION['user_id'];
$house_id = $auth->getUserHouseId($user_id);

// Set page title
$page_title = "All Deposits";

// Get database connection
$conn = getConnection();

// Check if connection worked
if (!$conn) {
    die("Database connection failed");
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deposit_id = intval($_GET['delete']);
    
    // First, verify the deposit belongs to manager's house
    $verify_sql = "SELECT house_id FROM deposits WHERE deposit_id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($verify_stmt, "i", $deposit_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    $deposit = mysqli_fetch_assoc($verify_result);
    
    if (!$deposit || $deposit['house_id'] != $house_id) {
        $_SESSION['error'] = "Unauthorized access or deposit not found";
        header("Location: deposits.php");
        exit();
    }
    
    $delete_sql = "DELETE FROM deposits WHERE deposit_id = ? AND house_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "ii", $deposit_id, $house_id);
    
    if (mysqli_stmt_execute($delete_stmt)) {
        $_SESSION['success'] = "Deposit deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting deposit";
    }
    
    // Redirect back with filters
    $redirect_url = "deposits.php";
    $redirect_params = [];
    if (isset($_GET['month']) && intval($_GET['month']) > 0) $redirect_params[] = "month=" . intval($_GET['month']);
    if (isset($_GET['year']) && intval($_GET['year']) > 0) $redirect_params[] = "year=" . intval($_GET['year']);
    if (isset($_GET['member']) && intval($_GET['member']) > 0) $redirect_params[] = "member=" . intval($_GET['member']);
    if (isset($_GET['page']) && intval($_GET['page']) > 1) $redirect_params[] = "page=" . intval($_GET['page']);
    
    if (!empty($redirect_params)) {
        $redirect_url .= "?" . implode("&", $redirect_params);
    }
    
    header("Location: $redirect_url");
    exit();
}

// Now include header
require_once '../includes/header.php';

// Get filter values
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0;
$filter_member = isset($_GET['member']) ? intval($_GET['member']) : 0;

// Function to build WHERE clause for simple queries
function buildSimpleWhereClause($month, $year, $member, $house_id) {
    $where_parts = ["house_id = $house_id"];
    
    // Date filtering
    if ($month > 0 && $year > 0) {
        // Both month and year are selected
        $month_start = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        $where_parts[] = "deposit_date BETWEEN '$month_start' AND '$month_end'";
    } else if ($year > 0 && $month == 0) {
        // Only year is selected (show all months of that year)
        $year_start = "$year-01-01";
        $year_end = "$year-12-31";
        $where_parts[] = "deposit_date BETWEEN '$year_start' AND '$year_end'";
    } else if ($month > 0 && $year == 0) {
        // Only month is selected - use current year
        $current_year = date('Y');
        $month_start = "$current_year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        $where_parts[] = "deposit_date BETWEEN '$month_start' AND '$month_end'";
    }
    
    // Member filtering
    if ($member > 0) {
        $where_parts[] = "member_id = $member";
    }
    
    return implode(" AND ", $where_parts);
}

// Function to build WHERE clause for queries with table alias 'd'
function buildAliasedWhereClause($month, $year, $member, $house_id) {
    $where_parts = ["d.house_id = $house_id"];
    
    // Date filtering
    if ($month > 0 && $year > 0) {
        // Both month and year are selected
        $month_start = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        $where_parts[] = "d.deposit_date BETWEEN '$month_start' AND '$month_end'";
    } else if ($year > 0 && $month == 0) {
        // Only year is selected (show all months of that year)
        $year_start = "$year-01-01";
        $year_end = "$year-12-31";
        $where_parts[] = "d.deposit_date BETWEEN '$year_start' AND '$year_end'";
    } else if ($month > 0 && $year == 0) {
        // Only month is selected - use current year
        $current_year = date('Y');
        $month_start = "$current_year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        $where_parts[] = "d.deposit_date BETWEEN '$month_start' AND '$month_end'";
    }
    
    // Member filtering
    if ($member > 0) {
        $where_parts[] = "d.member_id = $member";
    }
    
    return implode(" AND ", $where_parts);
}

// Build WHERE clauses
$simple_where = buildSimpleWhereClause($filter_month, $filter_year, $filter_member, $house_id);
$aliased_where = buildAliasedWhereClause($filter_month, $filter_year, $filter_member, $house_id);

// Get active members for filter dropdown (only from this house)
$members_query = "SELECT * FROM members WHERE status = 'active' AND house_id = ? ORDER BY name";
$members_stmt = mysqli_prepare($conn, $members_query);
mysqli_stmt_bind_param($members_stmt, "i", $house_id);
mysqli_stmt_execute($members_stmt);
$members_result = mysqli_stmt_get_result($members_stmt);
$all_members = [];
if ($members_result) {
    while ($row = mysqli_fetch_assoc($members_result)) {
        $all_members[] = $row;
    }
}

// Count total deposits - using simple WHERE clause
$count_query = "SELECT COUNT(*) as total FROM deposits WHERE $simple_where";
$count_result = mysqli_query($conn, $count_query);
$total_row = mysqli_fetch_assoc($count_result);
$total_records = $total_row ? $total_row['total'] : 0;

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_records / $per_page);

// Get deposits - using aliased WHERE clause
$deposits = [];
if ($total_records > 0) {
    $query = "SELECT d.*, m.name as member_name, 
                     u1.username as created_by_name, 
                     u2.username as updated_by_name
              FROM deposits d 
              JOIN members m ON d.member_id = m.member_id AND d.house_id = m.house_id
              LEFT JOIN users u1 ON d.created_by = u1.user_id 
              LEFT JOIN users u2 ON d.updated_by = u2.user_id
              WHERE $aliased_where 
              ORDER BY d.deposit_date DESC, d.deposit_id DESC
              LIMIT $per_page OFFSET $offset";
    
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $deposits[] = $row;
        }
    }
}

// Get summary - using simple WHERE clause
$summary = ['total_amount' => 0, 'total_deposits' => 0, 'avg_deposit' => 0, 'unique_members' => 0];
$summary_query = "SELECT 
                  SUM(amount) as total_amount,
                  COUNT(*) as total_deposits,
                  AVG(amount) as avg_deposit,
                  COUNT(DISTINCT member_id) as unique_members
                  FROM deposits WHERE $simple_where";
                  
$summary_result = mysqli_query($conn, $summary_query);
if ($summary_result) {
    $summary = mysqli_fetch_assoc($summary_result);
    if (!$summary) {
        $summary = ['total_amount' => 0, 'total_deposits' => 0, 'avg_deposit' => 0, 'unique_members' => 0];
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">Deposit Management</h4>
                <p class="text-muted mb-0">Total: <?php echo $total_records; ?> deposit records</p>
            </div>
            <div>
                <a href="add_deposit.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Deposit
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Messages -->
<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-2"></i>Filter Deposits</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="month" class="form-label">Month</label>
                        <select name="month" id="month" class="form-select">
                            <option value="0">All Months</option>
                            <?php
                            $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                     'July', 'August', 'September', 'October', 'November', 'December'];
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
                            <option value="0">All Years</option>
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
                        <label for="member" class="form-label">Member</label>
                        <select name="member" id="member" class="form-select">
                            <option value="0">All Members</option>
                            <?php foreach ($all_members as $member): ?>
                            <option value="<?php echo $member['member_id']; ?>" 
                                    <?php echo $member['member_id'] == $filter_member ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-grid w-100">
                            <button type="submit" class="btn btn-primary mb-2">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                            <a href="deposits.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear Filters
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Summary -->
<?php if ($total_records > 0): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Deposits</h6>
                <h3 class="text-success mb-0"><?php echo $functions->formatCurrency($summary['total_amount'] ?: 0); ?></h3>
                <small class="text-muted">Total amount</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Records</h6>
                <h3 class="text-primary mb-0"><?php echo $summary['total_deposits'] ?: 0; ?></h3>
                <small class="text-muted">Deposit entries</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Average Deposit</h6>
                <h3 class="text-warning mb-0"><?php echo $functions->formatCurrency($summary['avg_deposit'] ?: 0); ?></h3>
                <small class="text-muted">Per deposit</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Unique Members</h6>
                <h3 class="text-info mb-0"><?php echo $summary['unique_members'] ?: 0; ?></h3>
                <small class="text-muted">Made deposits</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Deposits Table -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-wallet me-2"></i>Deposit List</h6>
            </div>
            <div class="card-body">
                <?php if (empty($deposits)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-wallet fa-3x text-muted mb-3"></i>
                    <h5>No Deposits Found</h5>
                    <p class="text-muted">
                        <?php if ($filter_month > 0 || $filter_year > 0 || $filter_member > 0): ?>
                        Try adjusting your filters or
                        <?php endif; ?>
                        Add your first deposit to get started
                    </p>
                    <?php if ($filter_month > 0 || $filter_year > 0 || $filter_member > 0): ?>
                    <a href="deposits.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </a>
                    <?php endif; ?>
                    <a href="add_deposit.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add First Deposit
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                                <th>Created By</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = ($page - 1) * $per_page + 1; ?>
                            <?php foreach ($deposits as $deposit): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo $functions->formatDate($deposit['deposit_date']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($deposit['member_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($deposit['description'] ?: '-'); ?></td>
                                <td class="text-end">
                                    <strong class="text-success"><?php echo $functions->formatCurrency($deposit['amount']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($deposit['created_by_name'] ?: 'System'); ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($deposit['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($deposit['updated_at']) && $deposit['updated_at'] != $deposit['created_at']): ?>
                                        <?php echo htmlspecialchars($deposit['updated_by_name'] ?: 'System'); ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($deposit['updated_at'])); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not updated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit_deposit.php?id=<?php echo $deposit['deposit_id']; ?>" 
                                           class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger" 
                                                onclick="confirmDelete(<?php echo $deposit['deposit_id']; ?>, '<?php echo addslashes($deposit['member_name']); ?>', '<?php echo $deposit['deposit_date']; ?>')"
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
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?page=<?php echo $page-1; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&member=<?php echo $filter_member; ?>">
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
                               href="?page=<?php echo $p; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&member=<?php echo $filter_member; ?>">
                                <?php echo $p; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?page=<?php echo $page+1; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&member=<?php echo $filter_member; ?>">
                                Next
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(depositId, memberName, depositDate) {
    if (confirm('Are you sure you want to delete deposit for "' + memberName + '" on ' + depositDate + '?')) {
        let url = 'deposits.php?delete=' + depositId;
        
        // Add current filter parameters
        const month = <?php echo $filter_month; ?>;
        const year = <?php echo $filter_year; ?>;
        const member = <?php echo $filter_member; ?>;
        const page = <?php echo $page; ?>;
        
        if (month > 0) url += '&month=' + month;
        if (year > 0) url += '&year=' + year;
        if (member > 0) url += '&member=' + member;
        if (page > 1) url += '&page=' + page;
        
        window.location.href = url;
    }
}
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>