<?php
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

$auth = new Auth();
$auth->requireRole('manager');

$conn = getConnection();
$manager_id = (int) $_SESSION['user_id'];
$house_id = (int) $_SESSION['house_id'];
$member_id = filter_input(INPUT_POST, 'member_id', FILTER_VALIDATE_INT);
$target_role = $_POST['target_role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: members.php");
    exit();
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    audit_log_event('manager.member.role.change.rejected', [
        'member_id' => $member_id ?: null,
        'reason' => 'invalid_csrf_token',
    ]);
    $_SESSION['error'] = "Security validation failed. Please try again.";
    header("Location: members.php");
    exit();
}

if (!$member_id || !in_array($target_role, ['manager', 'member'], true)) {
    audit_log_event('manager.member.role.change.rejected', [
        'member_id' => $member_id ?: null,
        'reason' => 'invalid_request',
    ]);
    $_SESSION['error'] = "Invalid member role request.";
    header("Location: members.php");
    exit();
}

$error = '';
$success = '';
$transaction_started = false;

try {
    if (!mysqli_begin_transaction($conn)) {
        throw new RuntimeException("Unable to start role update transaction.");
    }
    $transaction_started = true;

    $house_stmt = mysqli_prepare($conn, "SELECT house_id FROM houses WHERE house_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($house_stmt, "i", $house_id);
    mysqli_stmt_execute($house_stmt);
    $house = mysqli_fetch_assoc(mysqli_stmt_get_result($house_stmt));
    mysqli_stmt_close($house_stmt);

    $actor_stmt = mysqli_prepare(
        $conn,
        "SELECT role, is_active FROM users WHERE user_id = ? AND house_id = ? FOR UPDATE"
    );
    mysqli_stmt_bind_param($actor_stmt, "ii", $manager_id, $house_id);
    mysqli_stmt_execute($actor_stmt);
    $actor = mysqli_fetch_assoc(mysqli_stmt_get_result($actor_stmt));
    mysqli_stmt_close($actor_stmt);

    if (!$house || !$actor || $actor['role'] !== 'manager' || (int) $actor['is_active'] !== 1) {
        $error = "You are no longer an active manager of this house.";
        $reason = 'actor_not_active_manager';
    } else {
        $target_stmt = mysqli_prepare(
            $conn,
            "SELECT u.user_id, u.role, u.is_active, m.status, m.house_status
             FROM users u
             INNER JOIN members m ON m.member_id = u.member_id
             WHERE m.member_id = ? AND m.house_id = ? AND u.house_id = ?
             ORDER BY u.user_id ASC
             LIMIT 1 FOR UPDATE"
        );
        mysqli_stmt_bind_param($target_stmt, "iii", $member_id, $house_id, $house_id);
        mysqli_stmt_execute($target_stmt);
        $target = mysqli_fetch_assoc(mysqli_stmt_get_result($target_stmt));
        mysqli_stmt_close($target_stmt);

        if (!$target || !in_array($target['role'], ['manager', 'member'], true)) {
            $error = "No eligible user account is linked to this member.";
            $reason = 'target_account_not_found';
        } elseif ($target['role'] === $target_role) {
            $error = "This member already has that role.";
            $reason = 'role_unchanged';
        } elseif (
            $target_role === 'manager'
            && ((int) $target['is_active'] !== 1
                || $target['status'] !== 'active'
                || $target['house_status'] !== 'active')
        ) {
            $error = "Only an active member with an active account can become a manager.";
            $reason = 'target_not_active_member';
        } elseif ($target_role === 'member') {
            $manager_stmt = mysqli_prepare(
                $conn,
                "SELECT user_id FROM users
                 WHERE house_id = ? AND role = 'manager' AND is_active = 1 AND user_id != ?
                 FOR UPDATE"
            );
            mysqli_stmt_bind_param($manager_stmt, "ii", $house_id, $target['user_id']);
            mysqli_stmt_execute($manager_stmt);
            $other_manager = mysqli_fetch_assoc(mysqli_stmt_get_result($manager_stmt));
            mysqli_stmt_close($manager_stmt);

            if (!$other_manager) {
                $error = "Keep at least one other active manager in the house before changing this role.";
                $reason = 'last_active_manager';
            }
        }
    }

    if ($error === '') {
        $update_stmt = mysqli_prepare(
            $conn,
            "UPDATE users SET role = ? WHERE user_id = ? AND house_id = ? AND role = ?"
        );
        mysqli_stmt_bind_param(
            $update_stmt,
            "siis",
            $target_role,
            $target['user_id'],
            $house_id,
            $target['role']
        );
        if (!mysqli_stmt_execute($update_stmt) || mysqli_stmt_affected_rows($update_stmt) !== 1) {
            mysqli_stmt_close($update_stmt);
            throw new RuntimeException("The account role could not be updated.");
        }
        mysqli_stmt_close($update_stmt);

        if (!mysqli_commit($conn)) {
            throw new RuntimeException("The account role update could not be committed.");
        }
        $transaction_started = false;

        audit_log_event('manager.member.role.change.success', [
            'member_id' => (int) $member_id,
            'target_user_id' => (int) $target['user_id'],
            'previous_role' => $target['role'],
            'new_role' => $target_role,
        ]);
        $success = $target_role === 'manager'
            ? "Member is now a manager."
            : "Manager is now a member.";

        if ((int) $target['user_id'] === $manager_id) {
            $_SESSION['role'] = 'member';
        }
    } else {
        mysqli_rollback($conn);
        $transaction_started = false;
        audit_log_event('manager.member.role.change.rejected', [
            'member_id' => (int) $member_id,
            'reason' => $reason,
        ]);
    }
} catch (Throwable $e) {
    if ($transaction_started) {
        mysqli_rollback($conn);
    }
    audit_log_event('manager.member.role.change.error', [
        'member_id' => (int) $member_id,
        'database_error_code' => mysqli_errno($conn),
    ]);
    $error = "The role could not be changed. Please try again.";
}

if ($error !== '') {
    $_SESSION['error'] = $error;
    header("Location: members.php");
} elseif ((int) ($target['user_id'] ?? 0) === $manager_id && $target_role === 'member') {
    $_SESSION['success'] = $success;
    header("Location: ../member/dashboard.php");
} else {
    $_SESSION['success'] = $success;
    header("Location: members.php");
}
exit();
