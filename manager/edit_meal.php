<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Edit Meal Entry";

$conn = getConnection();

// Check if meal ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid meal ID";
    header("Location: meals.php");
    exit();
}

$meal_id = intval($_GET['id']);

// Get meal details with user names
$sql = "SELECT m.*, 
               mb.name as member_name, 
               mb.member_id,
               uc.username as created_by_username,
               uc.name as created_by_name,
               uu.username as updated_by_username,
               uu.name as updated_by_name
        FROM meals m 
        JOIN members mb ON m.member_id = mb.member_id 
        LEFT JOIN users uc ON m.created_by = uc.user_id
        LEFT JOIN users uu ON m.updated_by = uu.user_id
        WHERE m.meal_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $meal_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$meal = mysqli_fetch_assoc($result);

if (!$meal) {
    $_SESSION['error'] = "Meal entry not found";
    header("Location: meals.php");
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meal_date = mysqli_real_escape_string($conn, $_POST['meal_date']);
    $meal_count = floatval($_POST['meal_count']);
    $member_id = intval($_POST['member_id']);
    
    // Validation
    if (empty($meal_date)) {
        $error = "Meal date is required";
    } elseif ($meal_count <= 0) {
        $error = "Meal count must be greater than 0";
    } else {
        // Check if another entry exists for same member and date
        $check_sql = "SELECT meal_id FROM meals WHERE member_id = ? AND meal_date = ? AND meal_id != ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "isi", $member_id, $meal_date, $meal_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Meal entry already exists for this member on selected date";
        } else {
            // Update meal entry
            $update_sql = "UPDATE meals SET member_id = ?, meal_date = ?, meal_count = ?, created_by = ?, updated_by = ?, updated_at = NOW() WHERE meal_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "isdiii", $member_id, $meal_date, $meal_count, $_SESSION['user_id'], $_SESSION['user_id'], $meal_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Meal entry updated successfully!";
                
                // Refresh meal data
                $sql = "SELECT m.*, 
                               mb.name as member_name, 
                               mb.member_id,
                               uc.username as created_by_username,
                               uc.name as created_by_name,
                               uu.username as updated_by_username,
                               uu.name as updated_by_name
                        FROM meals m 
                        JOIN members mb ON m.member_id = mb.member_id 
                        LEFT JOIN users uc ON m.created_by = uc.user_id
                        LEFT JOIN users uu ON m.updated_by = uu.user_id
                        WHERE m.meal_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $meal_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $meal = mysqli_fetch_assoc($result);
            } else {
                $error = "Error updating meal entry: " . mysqli_error($conn);
            }
        }
    }
}

// Get all active members
$members_sql = "SELECT * FROM members WHERE status = 'active' ORDER BY name";
$members_result = mysqli_query($conn, $members_sql);
$all_members = mysqli_fetch_all($members_result, MYSQLI_ASSOC);
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Meal Entry</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div class="mt-3">
                        <a href="meals.php" class="btn btn-primary">Back to All Meals</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="member_id" class="form-label">Member *</label>
                        <select class="form-select" id="member_id" name="member_id" required>
                            <option value="">Select Member</option>
                            <?php foreach ($all_members as $member): ?>
                            <option value="<?php echo $member['member_id']; ?>" 
                                    <?php echo $member['member_id'] == $meal['member_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['name']); ?>
                                <?php if ($member['phone']): ?> (<?php echo $member['phone']; ?>)<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="meal_date" class="form-label">Meal Date *</label>
                        <input type="date" class="form-control" id="meal_date" name="meal_date" 
                               value="<?php echo $meal['meal_date']; ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="meal_count" class="form-label">Meal Count *</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="meal_count" name="meal_count" 
                                   step="0.5" min="0.5" max="3" value="<?php echo $meal['meal_count']; ?>" required>
                            <span class="input-group-text">meal(s)</span>
                        </div>
                        <div class="form-text">Enter meal count (0.5, 1.0, 1.5, etc.)</div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="card border-info mb-4">
                        <div class="card-body">
                            <h6><i class="fas fa-info-circle me-2"></i>Member Statistics for <?php echo date('F Y', strtotime($meal['meal_date'])); ?></h6>
                            <?php
                            // Get month start and end
                            $month = date('m', strtotime($meal['meal_date']));
                            $year = date('Y', strtotime($meal['meal_date']));
                            $month_start = "$year-$month-01";
                            $month_end = date('Y-m-t', strtotime($month_start));
                            
                            // Get member's monthly stats
                            $stats_sql = "SELECT 
                                SUM(meal_count) as month_total,
                                COUNT(*) as month_entries,
                                AVG(meal_count) as month_avg
                                FROM meals 
                                WHERE member_id = ? AND meal_date BETWEEN ? AND ?";
                            $stats_stmt = mysqli_prepare($conn, $stats_sql);
                            mysqli_stmt_bind_param($stats_stmt, "iss", $meal['member_id'], $month_start, $month_end);
                            mysqli_stmt_execute($stats_stmt);
                            $stats_result = mysqli_stmt_get_result($stats_stmt);
                            $stats = mysqli_fetch_assoc($stats_result);
                            ?>
                            <div class="row">
                                <div class="col-4 text-center">
                                    <h6 class="text-muted mb-1">Month Total</h6>
                                    <h5 class="text-primary"><?php echo number_format($stats['month_total'] ?: 0, 2); ?></h5>
                                </div>
                                <div class="col-4 text-center">
                                    <h6 class="text-muted mb-1">Month Entries</h6>
                                    <h5 class="text-success"><?php echo $stats['month_entries'] ?: 0; ?></h5>
                                </div>
                                <div class="col-4 text-center">
                                    <h6 class="text-muted mb-1">Month Avg</h6>
                                    <h5 class="text-warning"><?php echo number_format($stats['month_avg'] ?: 0, 2); ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Meal Entry
                        </button>
                        <a href="meals.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Additional Information -->
        <div class="card shadow mt-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Entry History</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Original Member:</strong> <?php echo htmlspecialchars($meal['member_name']); ?></p>
                        <p><strong>Original Date:</strong> <?php echo $functions->formatDate($meal['meal_date']); ?></p>
                        <p><strong>Original Count:</strong> <?php echo $meal['meal_count']; ?> meals</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created At:</strong> <?php echo date('M d, Y h:i A', strtotime($meal['created_at'])); ?></p>
                        <p><strong>Created By:</strong> 
                            <?php if (!empty($meal['created_by_name'])): ?>
                                <?php echo htmlspecialchars($meal['created_by_name']); ?>
                            <?php elseif (!empty($meal['created_by_username'])): ?>
                                <?php echo htmlspecialchars($meal['created_by_username']); ?>
                            <?php else: ?>
                                System
                            <?php endif; ?>
                        </p>
                        <?php 
                        // Fixed: Check if updated_at exists and is not null
                        if (isset($meal['updated_at']) && !empty($meal['updated_at']) && $meal['updated_at'] != $meal['created_at']): 
                        ?>
                        <p><strong>Updated At:</strong> <?php echo date('M d, Y h:i A', strtotime($meal['updated_at'])); ?></p>
                        <p><strong>Updated By:</strong> 
                            <?php if (!empty($meal['updated_by_name'])): ?>
                                <?php echo htmlspecialchars($meal['updated_by_name']); ?>
                            <?php elseif (!empty($meal['updated_by_username'])): ?>
                                <?php echo htmlspecialchars($meal['updated_by_username']); ?>
                            <?php else: ?>
                                System
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>