<?php
/**
 * Standalone Helper Functions for House Transfer System
 * These functions can be called directly without instantiating the Functions class
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Get member's house history based on email and house code
 */
function getMemberHouseHistory($email, $house_code) {
    $conn = getConnection();
    
    // First, find the house by code
    $house_sql = "SELECT house_id, house_name, house_code FROM houses WHERE house_code = ? AND is_active = 1";
    $house_stmt = mysqli_prepare($conn, $house_sql);
    mysqli_stmt_bind_param($house_stmt, "s", $house_code);
    mysqli_stmt_execute($house_stmt);
    $house_result = mysqli_stmt_get_result($house_stmt);
    $house = mysqli_fetch_assoc($house_result);
    
    if (!$house) {
        mysqli_stmt_close($house_stmt);
        return false;
    }
    mysqli_stmt_close($house_stmt);
    
    // Check if member was part of this house using their email
    // Look in both current members and archived members
    $member_sql = "SELECT member_id, name, email FROM members 
                   WHERE house_id = ? AND email = ? AND status = 'active'";
    $member_stmt = mysqli_prepare($conn, $member_sql);
    mysqli_stmt_bind_param($member_stmt, "is", $house['house_id'], $email);
    mysqli_stmt_execute($member_stmt);
    $member_result = mysqli_stmt_get_result($member_stmt);
    $member = mysqli_fetch_assoc($member_result);
    mysqli_stmt_close($member_stmt);
    
    if (!$member) {
        // Check archived members
        $archive_sql = "SELECT archive_id as member_id, name, email FROM member_archive 
                       WHERE original_house_id = ? AND email = ?";
        $archive_stmt = mysqli_prepare($conn, $archive_sql);
        mysqli_stmt_bind_param($archive_stmt, "is", $house['house_id'], $email);
        mysqli_stmt_execute($archive_stmt);
        $archive_result = mysqli_stmt_get_result($archive_stmt);
        $member = mysqli_fetch_assoc($archive_result);
        mysqli_stmt_close($archive_stmt);
        
        if (!$member) {
            return false;
        }
    }
    
    return [
        'house_id' => $house['house_id'],
        'house_name' => $house['house_name'],
        'house_code' => $house['house_code'],
        'member_id' => $member['member_id'],
        'name' => $member['name'],
        'email' => $member['email']
    ];
}

/**
 * Calculate statistics for member's house history
 */
function calculateHouseHistoryStats($member_id, $house_id) {
    $conn = getConnection();
    
    // Get deposits for this member and house
    $deposit_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM deposits 
                     WHERE member_id = ? AND house_id = ?";
    $deposit_stmt = mysqli_prepare($conn, $deposit_sql);
    mysqli_stmt_bind_param($deposit_stmt, "ii", $member_id, $house_id);
    mysqli_stmt_execute($deposit_stmt);
    $deposit_result = mysqli_stmt_get_result($deposit_stmt);
    $deposits = mysqli_fetch_assoc($deposit_result);
    mysqli_stmt_close($deposit_stmt);
    
    // Get meals for this member and house
    $meal_sql = "SELECT COALESCE(SUM(meal_count), 0) as total FROM meals 
                 WHERE member_id = ? AND house_id = ?";
    $meal_stmt = mysqli_prepare($conn, $meal_sql);
    mysqli_stmt_bind_param($meal_stmt, "ii", $member_id, $house_id);
    mysqli_stmt_execute($meal_stmt);
    $meal_result = mysqli_stmt_get_result($meal_stmt);
    $meals = mysqli_fetch_assoc($meal_result);
    mysqli_stmt_close($meal_stmt);
    
    // Get total expenses for this house
    $expense_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE house_id = ?";
    $expense_stmt = mysqli_prepare($conn, $expense_sql);
    mysqli_stmt_bind_param($expense_stmt, "i", $house_id);
    mysqli_stmt_execute($expense_stmt);
    $expense_result = mysqli_stmt_get_result($expense_stmt);
    $house_expenses = mysqli_fetch_assoc($expense_result);
    mysqli_stmt_close($expense_stmt);
    
    // Get total meals for the house
    $total_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as total FROM meals WHERE house_id = ?";
    $total_meals_stmt = mysqli_prepare($conn, $total_meals_sql);
    mysqli_stmt_bind_param($total_meals_stmt, "i", $house_id);
    $total_meals_stmt->execute();
    $total_meals_result = $total_meals_stmt->get_result();
    $total_meals = mysqli_fetch_assoc($total_meals_result);
    mysqli_stmt_close($total_meals_stmt);
    
    // Calculate meal rate
    $meal_rate = 0;
    if ($total_meals['total'] > 0) {
        $meal_rate = $house_expenses['total'] / $total_meals['total'];
    }
    
    // Calculate member's expenses based on their meals
    $member_expenses = $meals['total'] * $meal_rate;
    
    // Calculate balance
    $balance = $deposits['total'] - $member_expenses;
    
    return [
        'total_deposits' => $deposits['total'],
        'total_meals' => $meals['total'],
        'member_expenses' => $member_expenses,
        'balance' => $balance,
        'meal_rate' => $meal_rate
    ];
}

/**
 * Generate a unique join token for house transfer
 */
function generateJoinToken($house_id, $member_id = null, $token_type = 'house_transfer') {
    $conn = getConnection();
    
    // Generate unique token
    do {
        $token = strtoupper(bin2hex(random_bytes(8)));
        $check_sql = "SELECT token_id FROM join_tokens WHERE token = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $token);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        $exists = mysqli_stmt_num_rows($check_stmt) > 0;
        mysqli_stmt_close($check_stmt);
    } while ($exists);
    
    // Set expiry to 7 days from now
    $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Insert the token
    $sql = "INSERT INTO join_tokens (token, house_id, member_id, token_type, expires_at, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    $created_by = $_SESSION['user_id'] ?? null;
    mysqli_stmt_bind_param($stmt, "siisss", $token, $house_id, $member_id, $token_type, $expires_at, $created_by);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [
            'success' => true,
            'token' => $token,
            'expires_at' => $expires_at
        ];
    }
    
    mysqli_stmt_close($stmt);
    return ['success' => false, 'error' => mysqli_error($conn)];
}

/**
 * Validate and use a join token
 */
function useJoinToken($token, $member_id) {
    $conn = getConnection();
    
    // Check if token exists and is valid
    $sql = "SELECT * FROM join_tokens 
            WHERE token = ? AND is_used = 0 AND expires_at > NOW()";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $token_data = mysqli_fetch_assoc($result);
    
    if (!$token_data) {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Invalid or expired token.'];
    }
    mysqli_stmt_close($stmt);
    
    // Mark token as used
    $update_sql = "UPDATE join_tokens 
                   SET is_used = 1, used_by = ?, used_at = NOW() 
                   WHERE token_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ii", $member_id, $token_data['token_id']);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
    
    return [
        'success' => true,
        'house_id' => $token_data['house_id'],
        'token_type' => $token_data['token_type']
    ];
}

/**
 * Check if member can join a house via house code
 */
function canJoinHouseViaCode($member_id, $house_id) {
    $conn = getConnection();
    
    // Check if house is open for joining
    $house_sql = "SELECT is_open_for_join FROM houses WHERE house_id = ? AND is_active = 1";
    $house_stmt = mysqli_prepare($conn, $house_sql);
    mysqli_stmt_bind_param($house_stmt, "i", $house_id);
    mysqli_stmt_execute($house_stmt);
    $house_result = mysqli_stmt_get_result($house_stmt);
    $house = mysqli_fetch_assoc($house_result);
    mysqli_stmt_close($house_stmt);
    
    if (!$house || $house['is_open_for_join'] == 0) {
        return ['success' => false, 'error' => 'House is not open for joining'];
    }
    
    // Check if member already has a pending request
    $member_sql = "SELECT house_status FROM members WHERE member_id = ?";
    $member_stmt = mysqli_prepare($conn, $member_sql);
    mysqli_stmt_bind_param($member_stmt, "i", $member_id);
    mysqli_stmt_execute($member_stmt);
    $member_result = mysqli_stmt_get_result($member_stmt);
    $member = mysqli_fetch_assoc($member_result);
    mysqli_stmt_close($member_stmt);
    
    if ($member['house_status'] != 'active') {
        return ['success' => false, 'error' => 'You already have a pending request'];
    }
    
    return ['success' => true];
}

/**
 * Get houses open for joining (excluding current house)
 */
function getHousesOpenForJoining($current_house_id = null) {
    $conn = getConnection();
    
    $sql = "SELECT house_id, house_name, house_code, created_at 
            FROM houses 
            WHERE is_open_for_join = 1 
            AND is_active = 1";
    
    if ($current_house_id) {
        $sql .= " AND house_id != ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $current_house_id);
    } else {
        $stmt = mysqli_prepare($conn, $sql);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $houses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $houses[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $houses;
}

/**
 * Request to leave house
 */
function requestLeaveHouse($member_id) {
    $conn = getConnection();
    
    // Check if member has any meal entries for today
    $today = date('Y-m-d');
    $check_sql = "SELECT COUNT(*) as count FROM meals 
                  WHERE member_id = ? AND meal_date = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "is", $member_id, $today);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_stmt);
    
    if ($check_data['count'] > 0) {
        return ['success' => false, 'error' => 'Cannot leave house today. You have meal entries for today.'];
    }
    
    // Update member status to pending_leave
    $sql = "UPDATE members 
            SET house_status = 'pending_leave', 
                leave_request_date = NOW() 
            WHERE member_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $member_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Leave request submitted successfully.'];
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Failed to submit leave request.'];
    }
}

/**
 * Request to join house by house code
 */
function requestJoinHouseByCode($member_id, $house_code) {
    $conn = getConnection();
    
    // Get current house_id
    $current_sql = "SELECT house_id FROM members WHERE member_id = ?";
    $current_stmt = mysqli_prepare($conn, $current_sql);
    mysqli_stmt_bind_param($current_stmt, "i", $member_id);
    mysqli_stmt_execute($current_stmt);
    $current_result = mysqli_stmt_get_result($current_stmt);
    $current_data = mysqli_fetch_assoc($current_result);
    mysqli_stmt_close($current_stmt);
    
    if (!$current_data) {
        return ['success' => false, 'error' => 'Member not found.'];
    }
    
    // Check if requested house exists and is open for joining
    $house_sql = "SELECT house_id, house_name, is_open_for_join 
                  FROM houses 
                  WHERE house_code = ? AND is_active = 1";
    $house_stmt = mysqli_prepare($conn, $house_sql);
    mysqli_stmt_bind_param($house_stmt, "s", $house_code);
    mysqli_stmt_execute($house_stmt);
    $house_result = mysqli_stmt_get_result($house_stmt);
    
    if (mysqli_num_rows($house_result) === 0) {
        mysqli_stmt_close($house_stmt);
        return ['success' => false, 'error' => 'House not found or is inactive.'];
    }
    
    $house_data = mysqli_fetch_assoc($house_result);
    mysqli_stmt_close($house_stmt);
    
    if ($house_data['is_open_for_join'] == 0) {
        return ['success' => false, 'error' => 'This house is not open for joining. Please use a join token instead.'];
    }
    
    if ($house_data['house_id'] == $current_data['house_id']) {
        return ['success' => false, 'error' => 'You are already a member of this house.'];
    }
    
    // Update member to request joining new house
    $sql = "UPDATE members 
            SET house_status = 'pending_join', 
                requested_house_id = ?, 
                join_request_date = NOW() 
            WHERE member_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $house_data['house_id'], $member_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [
            'success' => true, 
            'message' => 'Join request submitted successfully.',
            'house_name' => $house_data['house_name']
        ];
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Failed to submit join request.'];
    }
}

/**
 * Cancel leave request
 */
function cancelLeaveRequest($member_id) {
    $conn = getConnection();
    
    $sql = "UPDATE members 
            SET house_status = 'active', 
                leave_request_date = NULL 
            WHERE member_id = ? AND house_status = 'pending_leave'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $member_id);
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Cancel join request
 */
function cancelJoinRequest($member_id) {
    $conn = getConnection();
    
    $sql = "UPDATE members 
            SET house_status = 'active', 
                requested_house_id = NULL, 
                join_request_date = NULL 
            WHERE member_id = ? AND house_status = 'pending_join'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $member_id);
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Get member's house status
 */
function getMemberHouseStatus($member_id) {
    $conn = getConnection();
    
    $sql = "SELECT house_status, requested_house_id, leave_request_date, join_request_date 
            FROM members WHERE member_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $member_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $status = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $status;
}

/**
 * Get house info by house code
 */
function getHouseByCode($house_code) {
    $conn = getConnection();
    
    $sql = "SELECT house_id, house_name, house_code, is_open_for_join, is_active 
            FROM houses WHERE house_code = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $house_code);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $house = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $house;
}
?>