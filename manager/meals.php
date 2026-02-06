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

// Get manager's house_id
$user_id = $_SESSION['user_id'];
$house_id = $auth->getUserHouseId($user_id);

$page_title = "All Meal Entries";

$conn = getConnection();

// Handle filters
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$filter_member = isset($_GET['member']) ? intval($_GET['member']) : 0;

// Handle meal deletion BEFORE including header.php
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $meal_id = intval($_GET['delete']);
    
    // First, verify the meal belongs to manager's house
    $verify_sql = "SELECT house_id FROM meals WHERE meal_id = ?";
    $verify_stmt = mysqli_prepare($conn, $verify_sql);
    mysqli_stmt_bind_param($verify_stmt, "i", $meal_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    $meal = mysqli_fetch_assoc($verify_result);
    
    if (!$meal || $meal['house_id'] != $house_id) {
        $_SESSION['error'] = "Unauthorized access or meal not found";
        header("Location: meals.php");
        exit();
    }
    
    $delete_sql = "DELETE FROM meals WHERE meal_id = ? AND house_id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "ii", $meal_id, $house_id);
    
    if (mysqli_stmt_execute($delete_stmt)) {
        $_SESSION['success'] = "Meal entry deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting meal entry";
    }
    
    // Redirect back with filters
    $redirect_url = "meals.php";
    $params = [];
    if ($filter_month > 0) {
        $params[] = "month=$filter_month";
    }
    if ($filter_year > 0) {
        $params[] = "year=$filter_year";
    }
    if ($filter_member > 0) {
        $params[] = "member=$filter_member";
    }
    if (isset($_GET['page']) && $_GET['page'] > 1) {
        $params[] = "page=" . intval($_GET['page']);
    }
    
    if (!empty($params)) {
        $redirect_url .= "?" . implode("&", $params);
    }
    
    header("Location: $redirect_url");
    exit();
}

// Now include the header AFTER potential redirects
require_once '../includes/header.php';

// Build query for meals
$where_conditions = ["m.house_id = ?"];
$params = [$house_id];
$param_types = "i";

if ($filter_month > 0 && $filter_year > 0) {
    $month_start = "$filter_year-" . str_pad($filter_month, 2, '0', STR_PAD_LEFT) . "-01";
    $month_end = date('Y-m-t', strtotime($month_start));
    $where_conditions[] = "m.meal_date BETWEEN ? AND ?";
    $params[] = $month_start;
    $params[] = $month_end;
    $param_types .= "ss";
}

if ($filter_member > 0) {
    $where_conditions[] = "m.member_id = ?";
    $params[] = $filter_member;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total records
$count_params = $params;
$count_param_types = $param_types;

$count_sql = "SELECT COUNT(*) as total FROM meals m WHERE $where_clause";
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($count_params)) {
    mysqli_stmt_bind_param($count_stmt, $count_param_types, ...$count_params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row ? $count_row['total'] : 0;

// Get meals with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_records / $per_page);

// Add pagination parameters
$list_params = $params;
$list_param_types = $param_types;

$sql = "SELECT m.*, mb.name as member_name, 
               u1.username as created_by_name, 
               u2.username as updated_by_name 
        FROM meals m 
        JOIN members mb ON m.member_id = mb.member_id AND m.house_id = mb.house_id 
        LEFT JOIN users u1 ON m.created_by = u1.user_id 
        LEFT JOIN users u2 ON m.updated_by = u2.user_id 
        WHERE $where_clause 
        ORDER BY m.meal_date DESC, m.created_at DESC 
        LIMIT ? OFFSET ?";
        
$list_params[] = $per_page;
$list_params[] = $offset;
$list_param_types .= "ii";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($list_params)) {
    mysqli_stmt_bind_param($stmt, $list_param_types, ...$list_params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$meals = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get all active members for filter (only from manager's house)
$members_sql = "SELECT * FROM members WHERE status = 'active' AND house_id = ? ORDER BY name";
$members_stmt = mysqli_prepare($conn, $members_sql);
mysqli_stmt_bind_param($members_stmt, "i", $house_id);
mysqli_stmt_execute($members_stmt);
$members_result = mysqli_stmt_get_result($members_stmt);
$all_members = $members_result ? mysqli_fetch_all($members_result, MYSQLI_ASSOC) : [];

// Calculate summary
$summary_sql = "SELECT 
                COUNT(DISTINCT m.member_id) as unique_members,
                SUM(m.meal_count) as total_meals,
                COUNT(*) as total_entries,
                AVG(m.meal_count) as avg_per_entry
                FROM meals m 
                WHERE $where_clause";
$summary_stmt = mysqli_prepare($conn, $summary_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($summary_stmt, $param_types, ...$params);
}
mysqli_stmt_execute($summary_stmt);
$summary_result = mysqli_stmt_get_result($summary_stmt);
$summary = $summary_result ? mysqli_fetch_assoc($summary_result) : [
    'unique_members' => 0,
    'total_meals' => 0,
    'total_entries' => 0,
    'avg_per_entry' => 0
];
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">Meal Entries</h4>
                <p class="text-muted mb-0">Total: <?php echo $total_records; ?> entries</p>
            </div>
            <div>
                <a href="add_meal.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Entry
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-2"></i>Filter Meals</h6>
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
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<?php if ($total_records > 0): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Entries</h6>
                <h3 class="text-primary mb-0"><?php echo $total_records; ?></h3>
                <small class="text-muted">Meal records</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Meals</h6>
                <h3 class="text-success mb-0"><?php echo number_format($summary['total_meals'] ?: 0, 2); ?></h3>
                <small class="text-muted">Meal count</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Unique Members</h6>
                <h3 class="text-warning mb-0"><?php echo $summary['unique_members'] ?: 0; ?></h3>
                <small class="text-muted">With meals</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Avg per Entry</h6>
                <h3 class="text-info mb-0"><?php echo number_format($summary['avg_per_entry'] ?: 0, 2); ?></h3>
                <small class="text-muted">Meals per entry</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Meal Entries Table -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-utensils me-2"></i>Meal Entries</h6>
            </div>
            <div class="card-body">
                <?php if (empty($meals)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                    <h5>No Meal Entries Found</h5>
                    <p class="text-muted"><?php echo $total_records == 0 ? 'Add your first meal entry to get started' : 'No entries match your current filters'; ?></p>
                    <?php if ($total_records == 0): ?>
                    <a href="add_meal.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add First Entry
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Member</th>
                                <th class="text-center">Meal Count</th>
                                <th>Created By</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = ($page - 1) * $per_page + 1; ?>
                            <?php foreach ($meals as $meal): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <?php echo $functions->formatDate($meal['meal_date']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('l', strtotime($meal['meal_date'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($meal['member_name']); ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill" style="font-size: 1em;">
                                        <?php echo $meal['meal_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $meal['created_by_name'] ?: 'System'; ?>
                                    <br>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($meal['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($meal['updated_at']): ?>
                                        <?php echo $meal['updated_by_name'] ?: 'System'; ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($meal['updated_at'])); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not updated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="edit_meal.php?id=<?php echo $meal['meal_id']; ?>" 
                                           class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger" 
                                                onclick="confirmDelete(<?php echo $meal['meal_id']; ?>, '<?php echo addslashes($meal['member_name']); ?>', '<?php echo $meal['meal_date']; ?>')"
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
                
                <!-- Export Options -->
                <div class="row mt-4">                    
                    <div class="col-md-6">
                        <div class="card border-warning">
                            <div class="card-body">
                                <h6><i class="fas fa-chart-bar me-2"></i>Quick Stats</h6>
                                <p class="mb-1"><strong>Filtered Results:</strong> <?php echo count($meals); ?> of <?php echo $total_records; ?> entries</p>
                                <p class="mb-1"><strong>Total Meals:</strong> <?php echo number_format($summary['total_meals'] ?: 0, 2); ?></p>
                                <p class="mb-0"><strong>Average:</strong> <?php echo number_format($summary['avg_per_entry'] ?: 0, 2); ?> per entry</p>
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
function confirmDelete(mealId, memberName, mealDate) {
    if (confirm('Are you sure you want to delete meal entry for "' + memberName + '" on ' + mealDate + '?')) {
        let url = 'meals.php?delete=' + mealId;
        
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