<?php
require_once '../includes/auth.php';

$auth = new Auth();
$auth->requireLogin();
$user = $auth->getCurrentUser();

if (!$user) {
    header("Location: ../auth/login.php");
    exit();
}

$_SESSION['role'] = $user['role'];

if ($user['role'] === 'manager') {
    audit_log_event('manager.house.leave.rejected', [
        'house_id' => isset($user['house_id']) ? (int) $user['house_id'] : null,
        'reason' => 'manager_must_become_member_first',
    ]);
    $_SESSION['error'] = "Appoint another active manager and change your role to member before requesting to leave.";
    header("Location: settings.php");
    exit();
}

if ($user['role'] === 'member') {
    header("Location: ../member/leave_request.php");
    exit();
}

header("Location: ../index.php");
exit();
