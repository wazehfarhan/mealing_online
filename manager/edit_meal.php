<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

$auth = new Auth();
$functions = new Functions();
$auth->requireRole('manager');

if (!isset($_GET['id']) || !ctype_digit((string) $_GET['id']) || (int) $_GET['id'] < 1) {
    $_SESSION['error'] = "Invalid meal ID";
    header("Location: meals.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$house_id = (int) $auth->getUserHouseId($user_id);
$meal_id = (int) $_GET['id'];
$page_title = "Edit Meal Entry";
$conn = getConnection();

$sql = "SELECT m.*, mb.name AS member_name,
               uc.username AS created_by_username, uu.username AS updated_by_username
        FROM meals m
        JOIN members mb ON m.member_id = mb.member_id AND m.house_id = mb.house_id
        LEFT JOIN users uc ON m.created_by = uc.user_id
        LEFT JOIN users uu ON m.updated_by = uu.user_id
        WHERE m.meal_id = ? AND m.house_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $meal_id, $house_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$meal = mysqli_fetch_assoc($result);

if (!$meal) {
    $_SESSION['error'] = "Meal entry not found or unauthorized access";
    header("Location: meals.php");
    exit();
}

$original_meal = $meal;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    audit_log_event('manager.meal.edit.view', [
        'meal_id' => $meal_id,
    ]);
}
$error = '';
$success = '';
$csrf_token = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid. Please try again.";
    } else {
        $meal_date = trim($_POST['meal_date'] ?? '');
        $meal_count_raw = $_POST['meal_count'] ?? '';
        $meal_count = is_numeric($meal_count_raw) ? (float) $meal_count_raw : 0;
        $member_id = filter_var($_POST['member_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $date_parts = explode('-', $meal_date);
        $valid_date = count($date_parts) === 3
            && checkdate((int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0]);

        if (!$valid_date) {
            $error = "A valid meal date is required";
        } elseif ($meal_count <= 0 || $meal_count > 3) {
            $error = "Meal count must be greater than 0 and cannot exceed 3 meals";
        } elseif ($member_id <= 0) {
            $error = "Please select a member";
        } else {
            $member_check_sql = "SELECT member_id FROM members WHERE member_id = ? AND house_id = ?";
            $member_check_stmt = mysqli_prepare($conn, $member_check_sql);
            mysqli_stmt_bind_param($member_check_stmt, "ii", $member_id, $house_id);
            mysqli_stmt_execute($member_check_stmt);
            mysqli_stmt_store_result($member_check_stmt);

            if (mysqli_stmt_num_rows($member_check_stmt) === 0) {
                $error = "Selected member does not belong to your house";
            } else {
                $check_sql = "SELECT meal_id FROM meals
                              WHERE member_id = ? AND meal_date = ? AND house_id = ? AND meal_id != ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "isii", $member_id, $meal_date, $house_id, $meal_id);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);

                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "Meal entry already exists for this member on selected date";
                } else {
                    $update_sql = "UPDATE meals SET member_id = ?, meal_date = ?, meal_count = ?,
                                   updated_by = ?, updated_at = NOW()
                                   WHERE meal_id = ? AND house_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param(
                        $update_stmt,
                        "isdiii",
                        $member_id,
                        $meal_date,
                        $meal_count,
                        $user_id,
                        $meal_id,
                        $house_id
                    );

                    if (mysqli_stmt_execute($update_stmt)) {
                        $success = "Meal entry updated successfully!";
                        audit_log_event('manager.meal.update.success', [
                            'meal_id' => $meal_id,
                            'before' => [
                                'member_id' => $original_meal['member_id'],
                                'meal_date' => $original_meal['meal_date'],
                                'meal_count' => $original_meal['meal_count'],
                            ],
                            'after' => [
                                'member_id' => $member_id,
                                'meal_date' => $meal_date,
                                'meal_count' => $meal_count,
                            ],
                        ]);

                        mysqli_stmt_bind_param($stmt, "ii", $meal_id, $house_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        $meal = mysqli_fetch_assoc($result);
                    } else {
                        $error = "Unable to update meal entry. Please try again.";
                        audit_log_event('manager.meal.update.error', [
                            'meal_id' => $meal_id,
                            'error' => mysqli_error($conn),
                        ]);
                    }
                }
            }
        }
    }

    if ($error !== '') {
        audit_log_event('manager.meal.update.rejected', [
            'meal_id' => $meal_id,
            'reason' => $error,
        ]);
    }
}

$members_sql = "SELECT member_id, name, phone FROM members
                WHERE house_id = ? AND (status = 'active' OR member_id = ?) ORDER BY name";
$members_stmt = mysqli_prepare($conn, $members_sql);
mysqli_stmt_bind_param($members_stmt, "ii", $house_id, $meal['member_id']);
mysqli_stmt_execute($members_stmt);
$members_result = mysqli_stmt_get_result($members_stmt);
$all_members = mysqli_fetch_all($members_result, MYSQLI_ASSOC);

$selected_member_id = (int) ($_POST['member_id'] ?? $meal['member_id']);
$selected_date = $_POST['meal_date'] ?? $meal['meal_date'];
$selected_count = $_POST['meal_count'] ?? $meal['meal_count'];

$month_start = date('Y-m-01', strtotime($meal['meal_date']));
$month_end = date('Y-m-t', strtotime($meal['meal_date']));
$stats_sql = "SELECT SUM(meal_count) AS month_total, COUNT(*) AS month_entries,
                     AVG(meal_count) AS month_avg
              FROM meals
              WHERE member_id = ? AND meal_date BETWEEN ? AND ? AND house_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "issi", $meal['member_id'], $month_start, $month_end, $house_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);

require_once '../includes/header.php';
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Meal Entry</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                        <div class="mt-3">
                            <a href="meals.php" class="btn btn-primary">Back to All Meals</a>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="member_id" class="form-label">Member *</label>
                            <select class="form-select" id="member_id" name="member_id" required>
                                <option value="">Select Member</option>
                                <?php foreach ($all_members as $member): ?>
                                    <option value="<?php echo (int) $member['member_id']; ?>"
                                        <?php echo $selected_member_id === (int) $member['member_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (!empty($member['phone'])): ?>
                                            (<?php echo htmlspecialchars($member['phone'], ENT_QUOTES, 'UTF-8'); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="meal_date" class="form-label">Meal Date *</label>
                            <input type="date" class="form-control" id="meal_date" name="meal_date"
                                   value="<?php echo htmlspecialchars($selected_date, ENT_QUOTES, 'UTF-8'); ?>" required>
                            <div class="form-text">The date when this meal was (or will be) served.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="meal_count" class="form-label">Meal Count *</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="meal_count" name="meal_count"
                                   step="0.5" min="0.5" max="3"
                                   value="<?php echo htmlspecialchars((string) $selected_count, ENT_QUOTES, 'UTF-8'); ?>" required>
                            <span class="input-group-text">meal(s)</span>
                        </div>
                        <div class="form-text">Enter meal count (0.5, 1.0, 1.5, etc.)</div>
                    </div>

                    <div class="card border-info mb-4">
                        <div class="card-body">
                            <h6><i class="fas fa-info-circle me-2"></i>Member Statistics for <?php echo date('F Y', strtotime($meal['meal_date'])); ?></h6>
                            <div class="row">
                                <div class="col-4 text-center">
                                    <h6 class="text-muted mb-1">Month Total</h6>
                                    <h5 class="text-primary"><?php echo number_format((float) ($stats['month_total'] ?? 0), 2); ?></h5>
                                </div>
                                <div class="col-4 text-center">
                                    <h6 class="text-muted mb-1">Month Entries</h6>
                                    <h5 class="text-success"><?php echo (int) ($stats['month_entries'] ?? 0); ?></h5>
                                </div>
                                <div class="col-4 text-center">
                                    <h6 class="text-muted mb-1">Month Avg</h6>
                                    <h5 class="text-warning"><?php echo number_format((float) ($stats['month_avg'] ?? 0), 2); ?></h5>
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

        <div class="card shadow mt-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Entry History</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Original Member:</strong> <?php echo htmlspecialchars($original_meal['member_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Original Date:</strong> <?php echo $functions->formatDate($original_meal['meal_date']); ?></p>
                        <p><strong>Original Count:</strong> <?php echo htmlspecialchars((string) $original_meal['meal_count'], ENT_QUOTES, 'UTF-8'); ?> meals</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created At:</strong> <?php echo date('M d, Y h:i A', strtotime($original_meal['created_at'])); ?></p>
                        <p><strong>Created By:</strong>
                            <?php echo htmlspecialchars($original_meal['created_by_username'] ?: 'System', ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <?php if (!empty($original_meal['updated_at']) && $original_meal['updated_at'] !== $original_meal['created_at']): ?>
                            <p><strong>Last Updated:</strong> <?php echo date('M d, Y h:i A', strtotime($original_meal['updated_at'])); ?></p>
                            <?php if (!empty($original_meal['updated_by_username'])): ?>
                                <p><strong>Updated By:</strong> <?php echo htmlspecialchars($original_meal['updated_by_username'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
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
