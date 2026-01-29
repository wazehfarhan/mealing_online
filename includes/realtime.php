<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $conn = getConnection();
    
    switch ($action) {
        case 'calculate_meal_rate':
            $month = date('m');
            $year = date('Y');
            
            $sql = "SELECT SUM(meal_count) as total_meals FROM meals 
                    WHERE DATE_FORMAT(meal_date, '%Y-%m') = '$year-$month'";
            $result = mysqli_query($conn, $sql);
            $total_meals = mysqli_fetch_assoc($result)['total_meals'] ?: 0;
            
            $sql = "SELECT SUM(amount) as total_expenses FROM expenses 
                    WHERE DATE_FORMAT(expense_date, '%Y-%m') = '$year-$month'";
            $result = mysqli_query($conn, $sql);
            $total_expenses = mysqli_fetch_assoc($result)['total_expenses'] ?: 0;
            
            $meal_rate = 0;
            if ($total_meals > 0) {
                $meal_rate = $total_expenses / $total_meals;
            }
            
            echo json_encode([
                'total_meals' => $total_meals,
                'total_expenses' => $total_expenses,
                'meal_rate' => $meal_rate
            ]);
            break;
            
        case 'get_member_stats':
            $member_id = $_POST['member_id'] ?? 0;
            $month = $_POST['month'] ?? date('m');
            $year = $_POST['year'] ?? date('Y');
            
            $month_start = "$year-$month-01";
            $month_end = date('Y-m-t', strtotime($month_start));
            
            // Get meals
            $sql = "SELECT SUM(meal_count) as total_meals FROM meals 
                    WHERE member_id = $member_id 
                    AND meal_date BETWEEN '$month_start' AND '$month_end'";
            $result = mysqli_query($conn, $sql);
            $total_meals = mysqli_fetch_assoc($result)['total_meals'] ?: 0;
            
            // Get deposits
            $sql = "SELECT SUM(amount) as total_deposits FROM deposits 
                    WHERE member_id = $member_id 
                    AND deposit_date BETWEEN '$month_start' AND '$month_end'";
            $result = mysqli_query($conn, $sql);
            $total_deposits = mysqli_fetch_assoc($result)['total_deposits'] ?: 0;
            
            echo json_encode([
                'total_meals' => $total_meals,
                'total_deposits' => $total_deposits
            ]);
            break;
    }
}
?>