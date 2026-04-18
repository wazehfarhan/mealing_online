<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Security: Require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate CSRF token if needed for state-changing operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['house_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'No house assigned']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    $conn = getConnection();
    
    // Validate house_id is numeric
    $house_id = (int)$_SESSION['house_id'];
    
    switch ($action) {
        case 'calculate_meal_rate':
            $month = (int)$_POST['month'] ?? date('m');
            $year = (int)$_POST['year'] ?? date('Y');
            
            // Ensure month is 1-12 and year is reasonable
            $month = max(1, min(12, $month));
            $year = max(2020, min(date('Y') + 1, $year));
            
    $sql = "SELECT SUM(meal_count) as total_meals FROM meals 
                    WHERE house_id = ? AND YEAR(meal_date) = ? AND MONTH(meal_date) = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "iii", $house_id, $year, $month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_meals = mysqli_fetch_assoc($result)['total_meals'] ?: 0;
    mysqli_stmt_close($stmt);
    
    $sql = "SELECT SUM(amount) as total_expenses FROM expenses 
                    WHERE house_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "iii", $house_id, $year, $month);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_expenses = mysqli_fetch_assoc($result)['total_expenses'] ?: 0;
    mysqli_stmt_close($stmt);
            
            $meal_rate = 0;
            if ($total_meals > 0) {
                $meal_rate = $total_expenses / $total_meals;
            }
            
            echo json_encode([
                'success' => true,
                'total_meals' => $total_meals,
                'total_expenses' => $total_expenses,
                'meal_rate' => round($meal_rate, 2)
            ]);
            break;
            
        case 'get_member_stats':
            $member_id = (int)($_POST['member_id'] ?? 0);
            $month = (int)($_POST['month'] ?? date('m'));
            $year = (int)($_POST['year'] ?? date('Y'));
            
            // Validate inputs
            $month = max(1, min(12, $month));
            $year = max(2020, min(date('Y') + 1, $year));
            
            if ($member_id <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid member ID']);
                exit;
            }
            
            // Verify member belongs to the current house
            $verify_sql = "SELECT member_id FROM members WHERE member_id = ? AND house_id = ?";
            $verify_stmt = mysqli_prepare($conn, $verify_sql);
            if (!$verify_stmt) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
            mysqli_stmt_bind_param($verify_stmt, "ii", $member_id, $house_id);
            mysqli_stmt_execute($verify_stmt);
            mysqli_stmt_store_result($verify_stmt);
            
            if (mysqli_stmt_num_rows($verify_stmt) === 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied']);
                exit;
            }
            mysqli_stmt_close($verify_stmt);
            
            $month_start = "$year-$month-01";
            $month_end = date('Y-m-t', strtotime($month_start));
            
            // Get meals
    $sql = "SELECT SUM(meal_count) as total_meals FROM meals 
                    WHERE member_id = ? AND house_id = ?
                    AND meal_date BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "iiss", $member_id, $house_id, $month_start, $month_end);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_meals = mysqli_fetch_assoc($result)['total_meals'] ?: 0;
    mysqli_stmt_close($stmt);
    
    $sql = "SELECT SUM(amount) as total_deposits FROM deposits 
                    WHERE member_id = ? AND house_id = ?
                    AND deposit_date BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "iiss", $member_id, $house_id, $month_start, $month_end);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_deposits = mysqli_fetch_assoc($result)['total_deposits'] ?: 0;
    mysqli_stmt_close($stmt);
            
            echo json_encode([
                'success' => true,
                'total_meals' => round($total_meals, 2),
                'total_deposits' => round($total_deposits, 2)
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
