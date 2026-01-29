<?php
require_once __DIR__ . '/../config/database.php';

class Functions {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    // Sanitize input data
    public function sanitize($input) {
        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $input[$key] = $this->sanitize($value);
            }
            return $input;
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    // Get dashboard statistics
    public function getDashboardStats($month = null, $year = null) {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');
        
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $stats = array(
            'total_members' => 0,
            'total_meals' => 0,
            'total_expenses' => 0,
            'meal_rate' => 0,
            'today_meals' => 0
        );
        
        // Total active members
        $sql = "SELECT COUNT(*) as total FROM members WHERE status = 'active'";
        $result = mysqli_query($this->conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $stats['total_members'] = $row['total'];
        }
        
        // Total meals for current month
        $sql = "SELECT SUM(meal_count) as total FROM meals WHERE meal_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_meals'] = $row['total'] ?: 0;
        }
        
        // Total expenses for current month
        $sql = "SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_expenses'] = $row['total'] ?: 0;
        }
        
        // Calculate meal rate
        if ($stats['total_meals'] > 0) {
            $stats['meal_rate'] = $stats['total_expenses'] / $stats['total_meals'];
        }
        
        // Today's meals
        $today = date('Y-m-d');
        $sql = "SELECT COUNT(*) as total FROM meals WHERE meal_date = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['today_meals'] = $row['total'];
        }
        
        return $stats;
    }
    
    // Get all active members
    public function getActiveMembers() {
        $sql = "SELECT * FROM members WHERE status = 'active' ORDER BY name ASC";
        $result = mysqli_query($this->conn, $sql);
        
        $members = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $members[] = $row;
        }
        
        return $members;
    }
    
    // Get member by ID
    public function getMember($member_id) {
        $sql = "SELECT * FROM members WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    // Add new member
    public function addMember($name, $phone, $email, $join_date, $created_by) {
        // Generate unique join token
        $join_token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $sql = "INSERT INTO members (name, phone, email, join_date, created_by, join_token, token_expiry) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssiss", $name, $phone, $email, $join_date, $created_by, $join_token, $token_expiry);
        
        if (mysqli_stmt_execute($stmt)) {
            $member_id = mysqli_insert_id($this->conn);
            return array(
                'success' => true,
                'member_id' => $member_id,
                'join_token' => $join_token
            );
        }
        
        return array('success' => false, 'error' => mysqli_error($this->conn));
    }
    
    // Update member
    public function updateMember($member_id, $name, $phone, $email, $status) {
        $sql = "UPDATE members SET name = ?, phone = ?, email = ?, status = ? WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssi", $name, $phone, $email, $status, $member_id);
        
        return mysqli_stmt_execute($stmt);
    }
    
    // Add meal entry
    public function addMeal($member_id, $meal_date, $meal_count, $created_by) {
        // Check if entry already exists
        $check_sql = "SELECT meal_id FROM meals WHERE member_id = ? AND meal_date = ?";
        $check_stmt = mysqli_prepare($this->conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "is", $member_id, $meal_date);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            // Update existing
            $sql = "UPDATE meals SET meal_count = ?, created_by = ? WHERE member_id = ? AND meal_date = ?";
        } else {
            // Insert new
            $sql = "INSERT INTO meals (member_id, meal_date, meal_count, created_by) VALUES (?, ?, ?, ?)";
        }
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "dsis", $meal_count, $created_by, $member_id, $meal_date);
        
        return mysqli_stmt_execute($stmt);
    }
    
    // Add expense
    public function addExpense($amount, $category, $description, $expense_date, $created_by) {
        $sql = "INSERT INTO expenses (amount, category, description, expense_date, created_by) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "dsssi", $amount, $category, $description, $expense_date, $created_by);
        
        return mysqli_stmt_execute($stmt);
    }
    
    // Add deposit
    public function addDeposit($member_id, $amount, $deposit_date, $description, $created_by) {
        $sql = "INSERT INTO deposits (member_id, amount, deposit_date, description, created_by) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "idssi", $member_id, $amount, $deposit_date, $description, $created_by);
        
        return mysqli_stmt_execute($stmt);
    }
    
    // Get monthly report
    public function calculateMonthlyReport($month, $year) {
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $report = array();
        $members = $this->getActiveMembers();
        
        // Get total expenses for the month
        $sql = "SELECT SUM(amount) as total_expenses FROM expenses WHERE expense_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total_expenses = mysqli_fetch_assoc($result)['total_expenses'] ?: 0;
        
        // Get total meals for all members
        $sql = "SELECT SUM(meal_count) as all_meals FROM meals WHERE meal_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $all_meals = mysqli_fetch_assoc($result)['all_meals'] ?: 0;
        
        // Calculate meal rate
        $meal_rate = 0;
        if ($all_meals > 0) {
            $meal_rate = $total_expenses / $all_meals;
        }
        
        // Calculate for each member
        foreach ($members as $member) {
            // Get member's total meals
            $sql = "SELECT SUM(meal_count) as total_meals FROM meals 
                    WHERE member_id = ? AND meal_date BETWEEN ? AND ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "iss", $member['member_id'], $month_start, $month_end);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $total_meals = mysqli_fetch_assoc($result)['total_meals'] ?: 0;
            
            // Get member's total deposits
            $sql = "SELECT SUM(amount) as total_deposits FROM deposits 
                    WHERE member_id = ? AND deposit_date BETWEEN ? AND ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "iss", $member['member_id'], $month_start, $month_end);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $total_deposits = mysqli_fetch_assoc($result)['total_deposits'] ?: 0;
            
            // Calculate member's cost and balance
            $member_cost = $total_meals * $meal_rate;
            $balance = $total_deposits - $member_cost;
            
            $report[] = array(
                'member_id' => $member['member_id'],
                'name' => $member['name'],
                'phone' => $member['phone'],
                'total_meals' => $total_meals,
                'total_deposits' => $total_deposits,
                'total_expenses' => $total_expenses,
                'all_meals' => $all_meals,
                'meal_rate' => $meal_rate,
                'member_cost' => $member_cost,
                'balance' => $balance
            );
        }
        
        return $report;
    }
    
    // Get member's meals for a specific month
    public function getMemberMeals($member_id, $month = null, $year = null) {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');
        
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $sql = "SELECT * FROM meals 
                WHERE member_id = ? AND meal_date BETWEEN ? AND ? 
                ORDER BY meal_date DESC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $member_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $meals = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $meals[] = $row;
        }
        
        return $meals;
    }
    
    // Get member's deposits for a specific month
    public function getMemberDeposits($member_id, $month = null, $year = null) {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');
        
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $sql = "SELECT * FROM deposits 
                WHERE member_id = ? AND deposit_date BETWEEN ? AND ? 
                ORDER BY deposit_date DESC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $member_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $deposits = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $deposits[] = $row;
        }
        
        return $deposits;
    }
    
    // Close month (finalize)
    public function closeMonth($month, $year, $closed_by) {
        $month_start = "$year-$month-01";
        
        // Check if already closed
        $check_sql = "SELECT summary_id FROM monthly_summary WHERE month_year = ?";
        $check_stmt = mysqli_prepare($this->conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $month_start);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            return false; // Already closed
        }
        
        // Calculate report
        $report = $this->calculateMonthlyReport($month, $year);
        
        if (empty($report)) {
            return false;
        }
        
        $first_member = $report[0];
        
        // Insert monthly summary
        $sql = "INSERT INTO monthly_summary (month_year, total_meals, total_expenses, meal_rate, is_closed, closed_by) 
                VALUES (?, ?, ?, ?, 1, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "sdddi", $month_start, $first_member['all_meals'], 
                               $first_member['total_expenses'], $first_member['meal_rate'], $closed_by);
        
        if (!mysqli_stmt_execute($stmt)) {
            return false;
        }
        
        $summary_id = mysqli_insert_id($this->conn);
        
        // Insert member details
        foreach ($report as $member_data) {
            $sql = "INSERT INTO monthly_member_details 
                    (summary_id, member_id, total_meals, total_deposits, total_cost, balance) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "iidddd", $summary_id, $member_data['member_id'], 
                                   $member_data['total_meals'], $member_data['total_deposits'], 
                                   $member_data['member_cost'], $member_data['balance']);
            mysqli_stmt_execute($stmt);
        }
        
        return true;
    }
    
    // Check if month is closed
    public function isMonthClosed($month, $year) {
        $month_start = "$year-$month-01";
        
        $sql = "SELECT summary_id FROM monthly_summary WHERE month_year = ? AND is_closed = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $month_start);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        return mysqli_stmt_num_rows($stmt) > 0;
    }
    
    // Get expense breakdown
    public function getExpenseBreakdown($month, $year) {
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $sql = "SELECT category, SUM(amount) as total FROM expenses 
                WHERE expense_date BETWEEN ? AND ? 
                GROUP BY category ORDER BY total DESC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $breakdown = array();
        $total = 0;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $breakdown[] = $row;
            $total += $row['total'];
        }
        
        return array(
            'breakdown' => $breakdown,
            'total' => $total
        );
    }
    
    // Format currency
    public function formatCurrency($amount) {
        return '৳' . number_format($amount, 2);
    }
    
    // Format date
    public function formatDate($date_string) {
        return date('M d, Y', strtotime($date_string));
    }
}
?>