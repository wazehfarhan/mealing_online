<?php
// Start session
session_start();

// Include files
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authorization
$auth = new Auth();
$functions = new Functions();
$auth->requireRole('manager');

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
    
    // Simple delete query
    $sql = "DELETE FROM deposits WHERE deposit_id = $deposit_id";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Deposit deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting deposit";
    }
    
    // Simple redirect back
    header("Location: deposits.php");
    exit();
}

// Now include header
require_once '../includes/header.php';

// Get filter values
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0;
$filter_member = isset($_GET['member']) ? intval($_GET['member']) : 0;

// Function to build WHERE clause for simple queries (without table aliases)
function buildSimpleWhereClause($month, $year, $member) {
    $where_parts = [];
    
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
    
    return empty($where_parts) ? "1=1" : implode(" AND ", $where_parts);
}

// Function to build WHERE clause for queries with table alias 'd'
function buildAliasedWhereClause($month, $year, $member) {
    $where_parts = [];
    
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
    
    return empty($where_parts) ? "1=1" : implode(" AND ", $where_parts);
}

// Build WHERE clauses
$simple_where = buildSimpleWhereClause($filter_month, $filter_year, $filter_member);
$aliased_where = buildAliasedWhereClause($filter_month, $filter_year, $filter_member);

// Get active members for filter dropdown
$members_query = "SELECT * FROM members WHERE status = 'active' ORDER BY name";
$members_result = mysqli_query($conn, $members_query);
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
$total_records = $total_row['total'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_records / $per_page);

// Get deposits - using aliased WHERE clause
$deposits = [];
if ($total_records > 0) {
    $query = "SELECT d.*, m.name as member_name, u.username as created_by_name 
              FROM deposits d 
              LEFT JOIN members m ON d.member_id = m.member_id 
              LEFT JOIN users u ON d.created_by = u.user_id 
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
                <h3 class="text-success mb-0"><?php echo $functions->formatCurrency($summary['total_amount']); ?></h3>
                <small class="text-muted">Total amount</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Records</h6>
                <h3 class="text-primary mb-0"><?php echo $summary['total_deposits']; ?></h3>
                <small class="text-muted">Deposit entries</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Average Deposit</h6>
                <h3 class="text-warning mb-0"><?php echo $functions->formatCurrency($summary['avg_deposit']); ?></h3>
                <small class="text-muted">Per deposit</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Unique Members</h6>
                <h3 class="text-info mb-0"><?php echo $summary['unique_members']; ?></h3>
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
                    <p class="text-muted">Add your first deposit to get started</p>
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
                                <td><?php echo htmlspecialchars($deposit['created_by_name'] ?: 'System'); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit_deposit.php?id=<?php echo $deposit['deposit_id']; ?>" 
                                           class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="deposits.php?delete=<?php echo $deposit['deposit_id']; ?>&month=<?php echo $filter_month; ?>&year=<?php echo $filter_year; ?>&member=<?php echo $filter_member; ?>&page=<?php echo $page; ?>" 
                                           class="btn btn-danger" title="Delete"
                                           onclick="return confirm('Delete deposit for <?php echo addslashes($deposit['member_name']); ?>?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
                        
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
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

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>