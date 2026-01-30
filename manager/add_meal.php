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

$page_title = "Add Meal Entry";
$conn = getConnection();
$error = '';
$success = '';

// Get all active members for this house
$sql = "SELECT * FROM members WHERE status = 'active' AND house_id = ? ORDER BY name ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $house_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$members = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meal_date = mysqli_real_escape_string($conn, $_POST['meal_date']);
    $entries = $_POST['entries'] ?? [];
    
    if (empty($meal_date)) {
        $error = "Please select a date";
    } elseif (empty($members)) {
        $error = "No active members found";
    } else {
        $success_count = 0;
        $error_messages = [];
        $user_id = $_SESSION['user_id'];
        
        foreach ($members as $member) {
            $member_id = $member['member_id'];
            $meal_count = isset($entries[$member_id]) ? floatval($entries[$member_id]) : 0;
            
            // Skip if meal count is 0 or empty
            if ($meal_count <= 0) {
                continue;
            }
            
            // Check if entry already exists for this date and house
            $check_sql = "SELECT meal_id FROM meals WHERE member_id = ? AND meal_date = ? AND house_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "isi", $member_id, $meal_date, $house_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                // Update existing entry
                $sql = "UPDATE meals SET meal_count = ?, updated_by = ?, updated_at = NOW() 
                        WHERE member_id = ? AND meal_date = ? AND house_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "diisi", $meal_count, $user_id, $member_id, $meal_date, $house_id);
            } else {
                // Insert new entry
                $sql = "INSERT INTO meals (house_id, member_id, meal_date, meal_count, created_by) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iisdi", $house_id, $member_id, $meal_date, $meal_count, $user_id);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            } else {
                $error_messages[] = "Error saving meal for " . $member['name'] . ": " . mysqli_error($conn);
            }
            
            // Close statements
            if (isset($stmt)) {
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($check_stmt);
        }
        
        if ($success_count > 0) {
            $date_formatted = date('M d, Y', strtotime($meal_date));
            $success = "Successfully saved $success_count meal entries for $date_formatted";
            // Clear form
            $_POST = array();
        } else {
            $error = "No meal entries were saved.";
            if (!empty($error_messages)) {
                $error .= " Errors: " . implode(", ", $error_messages);
            }
        }
    }
}

// Get yesterday's date
$yesterday = date('Y-m-d', strtotime('-1 day'));
?>

<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-utensils me-2"></i>Add Meal Entry</h5>
                <div class="btn-group">
                    <button type="button" id="fillDefault" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-bolt me-1"></i>Fill Default (1.0)
                    </button>
                    <button type="button" id="fillHalf" class="btn btn-sm btn-outline-warning ms-1">
                        <i class="fas fa-bolt me-1"></i>Fill Half (0.5)
                    </button>
                    <button type="button" id="clearAll" class="btn btn-sm btn-outline-danger ms-1">
                        <i class="fas fa-times me-1"></i>Clear All
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <div class="mt-3">
                            <a href="meals.php" class="btn btn-primary me-2">View All Meals</a>
                            <a href="add_meal.php" class="btn btn-success">Add More Meals</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($members)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No Active Members</h5>
                        <p class="text-muted">Add some active members first to record meals</p>
                        <a href="add_member.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Add Members
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label for="meal_date" class="form-label">Meal Date *</label>
                                <input type="date" class="form-control" id="meal_date" name="meal_date" value="<?php echo isset($_POST['meal_date']) ? $_POST['meal_date'] : date('Y-m-d'); ?>" required>
                                <div class="form-text">Select date for meal entry</div>
                            </div>
                            <div class="col-md-8">
                                <div class="alert alert-info h-100 d-flex align-items-center">
                                    <div>
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Instructions:</strong> Enter meal count for each member. Use decimal values (e.g., 0.5, 1.0, 2.5). Empty or 0 values will be skipped.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member Name</th>
                                        <th>Phone</th>
                                        <th width="200">Meal Count</th>
                                        <th width="120">Yesterday</th>
                                        <th width="150">Last 7 Days Avg</th>
                                        <th width="150">This Month Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): 
                                        // Get yesterday's meal for this house
                                        $yesterday_sql = "SELECT meal_count FROM meals WHERE member_id = ? AND meal_date = ? AND house_id = ?";
                                        $yesterday_stmt = mysqli_prepare($conn, $yesterday_sql);
                                        mysqli_stmt_bind_param($yesterday_stmt, "isi", $member['member_id'], $yesterday, $house_id);
                                        mysqli_stmt_execute($yesterday_stmt);
                                        $yesterday_result = mysqli_stmt_get_result($yesterday_stmt);
                                        $yesterday_meal = mysqli_fetch_assoc($yesterday_result);
                                        
                                        // Get last 7 days average for this house
                                        $week_ago = date('Y-m-d', strtotime('-7 days'));
                                        $avg_sql = "SELECT AVG(meal_count) as avg_meals FROM meals WHERE member_id = ? AND meal_date BETWEEN ? AND ? AND house_id = ?";
                                        $avg_stmt = mysqli_prepare($conn, $avg_sql);
                                        mysqli_stmt_bind_param($avg_stmt, "issi", $member['member_id'], $week_ago, $yesterday, $house_id);
                                        mysqli_stmt_execute($avg_stmt);
                                        $avg_result = mysqli_stmt_get_result($avg_stmt);
                                        $avg_meals = mysqli_fetch_assoc($avg_result);
                                        
                                        // Get this month total for this house
                                        $month_start = date('Y-m-01');
                                        $month_total_sql = "SELECT SUM(meal_count) as month_total FROM meals WHERE member_id = ? AND meal_date >= ? AND house_id = ?";
                                        $month_total_stmt = mysqli_prepare($conn, $month_total_sql);
                                        mysqli_stmt_bind_param($month_total_stmt, "isi", $member['member_id'], $month_start, $house_id);
                                        mysqli_stmt_execute($month_total_stmt);
                                        $month_total_result = mysqli_stmt_get_result($month_total_stmt);
                                        $month_total = mysqli_fetch_assoc($month_total_result);
                                        
                                        // Close prepared statements
                                        mysqli_stmt_close($yesterday_stmt);
                                        mysqli_stmt_close($avg_stmt);
                                        mysqli_stmt_close($month_total_stmt);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo $member['phone'] ? htmlspecialchars($member['phone']) : '<span class="text-muted">N/A</span>'; ?>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" class="form-control meal-input" name="entries[<?php echo $member['member_id']; ?>]" step="0.5" min="0" max="3" value="<?php echo isset($_POST['entries'][$member['member_id']]) ? htmlspecialchars($_POST['entries'][$member['member_id']]) : ''; ?>" placeholder="0.0">
                                                <span class="input-group-text">meal</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($yesterday_meal && $yesterday_meal['meal_count'] > 0): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($yesterday_meal['meal_count']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($avg_meals['avg_meals']): ?>
                                                <span class="badge bg-secondary"><?php echo number_format($avg_meals['avg_meals'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($month_total['month_total']): ?>
                                                <span class="badge bg-primary"><?php echo number_format($month_total['month_total'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="6" class="text-end">
                                            <button type="submit" class="btn btn-primary btn-lg px-4">
                                                <i class="fas fa-save me-2"></i>Save All Entries
                                            </button>
                                            <a href="meals.php" class="btn btn-secondary btn-lg px-4 ms-2">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </a>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <?php if (!empty($members)): ?>
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Active Members</h6>
                        <h3 class="text-primary mb-0"><?php echo count($members); ?></h3>
                        <small class="text-muted">Can add meals</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Yesterday's Total Meals</h6>
                        <h3 class="text-success mb-0">
                            <?php 
                            $yesterday_sql = "SELECT SUM(meal_count) as total FROM meals WHERE meal_date = ? AND house_id = ?";
                            $yesterday_stmt = mysqli_prepare($conn, $yesterday_sql);
                            mysqli_stmt_bind_param($yesterday_stmt, "si", $yesterday, $house_id);
                            mysqli_stmt_execute($yesterday_stmt);
                            $yesterday_result = mysqli_stmt_get_result($yesterday_stmt);
                            $yesterday_total = mysqli_fetch_assoc($yesterday_result);
                            echo number_format($yesterday_total['total'] ?: 0, 2);
                            mysqli_stmt_close($yesterday_stmt);
                            ?>
                        </h3>
                        <small class="text-muted">Total meals yesterday</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">This Month Total</h6>
                        <h3 class="text-warning mb-0">
                            <?php 
                            $month_sql = "SELECT SUM(meal_count) as total FROM meals WHERE meal_date >= ? AND house_id = ?";
                            $month_stmt = mysqli_prepare($conn, $month_sql);
                            mysqli_stmt_bind_param($month_stmt, "si", $month_start, $house_id);
                            mysqli_stmt_execute($month_stmt);
                            $month_result = mysqli_stmt_get_result($month_stmt);
                            $month_total = mysqli_fetch_assoc($month_result);
                            echo number_format($month_total['total'] ?: 0, 2);
                            mysqli_stmt_close($month_stmt);
                            ?>
                        </h3>
                        <small class="text-muted">Current month meals</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Fill default values (1.0)
    $('#fillDefault').click(function() {
        $('.meal-input').val('1.0');
        highlightChanged();
    });
    
    // Fill half values (0.5)
    $('#fillHalf').click(function() {
        $('.meal-input').val('0.5');
        highlightChanged();
    });
    
    // Clear all inputs
    $('#clearAll').click(function() {
        $('.meal-input').val('');
        highlightChanged();
    });
    
    // Auto-focus first input
    $('.meal-input:first').focus();
    
    // Highlight changed inputs
    $('.meal-input').on('input', function() {
        $(this).addClass('is-valid');
    });
    
    function highlightChanged() {
        $('.meal-input').each(function() {
            if ($(this).val()) {
                $(this).addClass('is-valid');
            } else {
                $(this).removeClass('is-valid');
            }
        });
    }
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    $('#meal_date').val(today);
});
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>