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
    
    /**
     * Get dashboard statistics for a specific house
     */
    public function getDashboardStats($month = null, $year = null, $house_id = null) {
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
        
        if (!$house_id) {
            return $stats;
        }
        
        // Total active members for this house
        $sql = "SELECT COUNT(*) as total FROM members WHERE house_id = ? AND status = 'active'";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_members'] = $row['total'];
        }
        
        // Total meals for current month for this house
        $sql = "SELECT SUM(meal_count) as total FROM meals WHERE house_id = ? AND meal_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $house_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_meals'] = $row['total'] ?: 0;
        }
        
        // Total expenses for current month for this house
        $sql = "SELECT SUM(amount) as total FROM expenses WHERE house_id = ? AND expense_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $house_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_expenses'] = $row['total'] ?: 0;
        }
        
        // Calculate meal rate
        if ($stats['total_meals'] > 0) {
            $stats['meal_rate'] = $stats['total_expenses'] / $stats['total_meals'];
        }
        
        // Today's meals for this house
        $today = date('Y-m-d');
        $sql = "SELECT COUNT(*) as total FROM meals WHERE house_id = ? AND meal_date = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $house_id, $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['today_meals'] = $row['total'];
        }
        
        return $stats;
    }
    
    /**
     * Get all active members for a specific house
     */
    public function getActiveMembers($house_id = null) {
        if (!$house_id) {
            return array();
        }
        
        $sql = "SELECT * FROM members WHERE house_id = ? AND status = 'active' ORDER BY name ASC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $members = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $members[] = $row;
        }
        
        return $members;
    }
    
    // Get member by ID (for any house - used by super admin)
    public function getMember($member_id) {
        $sql = "SELECT * FROM members WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * Get member by ID for a specific house (for security)
     */
    public function getHouseMember($member_id, $house_id) {
        $sql = "SELECT * FROM members WHERE member_id = ? AND house_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $member_id, $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    // Add new member to a house
    public function addMember($house_id, $name, $phone, $email, $join_date, $created_by) {
        // Generate unique join token
        $join_token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $sql = "INSERT INTO members (house_id, name, phone, email, join_date, created_by, join_token, token_expiry) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "issssiss", $house_id, $name, $phone, $email, $join_date, $created_by, $join_token, $token_expiry);
        
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
    
    // Update member (with house check)
    public function updateMember($member_id, $house_id, $name, $phone, $email, $status) {
        $sql = "UPDATE members SET name = ?, phone = ?, email = ?, status = ? 
                WHERE member_id = ? AND house_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssii", $name, $phone, $email, $status, $member_id, $house_id);
        
        return mysqli_stmt_execute($stmt);
    }
    
    // Add meal entry for a house
    public function addMeal($house_id, $member_id, $meal_date, $meal_count, $created_by) {
        // Check if entry already exists for this house
        $check_sql = "SELECT meal_id FROM meals WHERE house_id = ? AND member_id = ? AND meal_date = ?";
        $check_stmt = mysqli_prepare($this->conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "iis", $house_id, $member_id, $meal_date);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            // Update existing
            $sql = "UPDATE meals SET meal_count = ?, created_by = ?, updated_at = NOW() 
                    WHERE house_id = ? AND member_id = ? AND meal_date = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "diiis", $meal_count, $created_by, $house_id, $member_id, $meal_date);
        } else {
            // Insert new
            $sql = "INSERT INTO meals (house_id, member_id, meal_date, meal_count, created_by) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "iisdi", $house_id, $member_id, $meal_date, $meal_count, $created_by);
        }
        
        return mysqli_stmt_execute($stmt);
    }
    
    // Add expense for a house
    public function addExpense($house_id, $amount, $category, $description, $expense_date, $created_by) {
        $sql = "INSERT INTO expenses (house_id, amount, category, description, expense_date, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "idsssi", $house_id, $amount, $category, $description, $expense_date, $created_by);
        
        return mysqli_stmt_execute($stmt);
    }
    
    // Add deposit for a house member
    public function addDeposit($house_id, $member_id, $amount, $deposit_date, $description, $created_by) {
        $sql = "INSERT INTO deposits (house_id, member_id, amount, deposit_date, description, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iidssi", $house_id, $member_id, $amount, $deposit_date, $description, $created_by);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get monthly report for a specific house
     */
    public function calculateMonthlyReport($month, $year, $house_id = null) {
        if (!$house_id) {
            return array();
        }
        
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $report = array();
        $members = $this->getActiveMembers($house_id);
        
        if (empty($members)) {
            return $report;
        }
        
        // Get total expenses for the month for this house
        $sql = "SELECT SUM(amount) as total_expenses FROM expenses 
                WHERE house_id = ? AND expense_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $house_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total_expenses = mysqli_fetch_assoc($result)['total_expenses'] ?: 0;
        
        // Get total meals for all members in this house
        $sql = "SELECT SUM(meal_count) as all_meals FROM meals 
                WHERE house_id = ? AND meal_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $house_id, $month_start, $month_end);
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
            // Get member's total meals for this house
            $sql = "SELECT SUM(meal_count) as total_meals FROM meals 
                    WHERE house_id = ? AND member_id = ? AND meal_date BETWEEN ? AND ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiss", $house_id, $member['member_id'], $month_start, $month_end);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $total_meals = mysqli_fetch_assoc($result)['total_meals'] ?: 0;
            
            // Get member's total deposits for this house
            $sql = "SELECT SUM(amount) as total_deposits FROM deposits 
                    WHERE house_id = ? AND member_id = ? AND deposit_date BETWEEN ? AND ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiss", $house_id, $member['member_id'], $month_start, $month_end);
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
                'email' => $member['email'],
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
    
    /**
     * Get member's meals for a specific month and house
     */
    public function getMemberMeals($member_id, $month = null, $year = null, $house_id = null) {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');
        if (!$house_id) return array();
        
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $sql = "SELECT * FROM meals 
                WHERE house_id = ? AND member_id = ? AND meal_date BETWEEN ? AND ? 
                ORDER BY meal_date DESC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $house_id, $member_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $meals = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $meals[] = $row;
        }
        
        return $meals;
    }
    
    /**
     * Get member's deposits for a specific month and house
     */
    public function getMemberDeposits($member_id, $month = null, $year = null, $house_id = null) {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');
        if (!$house_id) return array();
        
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $sql = "SELECT * FROM deposits 
                WHERE house_id = ? AND member_id = ? AND deposit_date BETWEEN ? AND ? 
                ORDER BY deposit_date DESC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $house_id, $member_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $deposits = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $deposits[] = $row;
        }
        
        return $deposits;
    }
    
    /**
     * Close month for a specific house
     */
    public function closeMonth($month, $year, $closed_by, $house_id = null) {
        if (!$house_id) {
            return false;
        }
        
        $month_start = "$year-$month-01";
        
        // Check if already closed for this house
        $check_sql = "SELECT summary_id FROM monthly_summary WHERE house_id = ? AND month_year = ?";
        $check_stmt = mysqli_prepare($this->conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "is", $house_id, $month_start);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            return false; // Already closed
        }
        
        // Calculate report for this house
        $report = $this->calculateMonthlyReport($month, $year, $house_id);
        
        if (empty($report)) {
            return false;
        }
        
        $first_member = $report[0];
        
        // Insert monthly summary for this house
        $sql = "INSERT INTO monthly_summary (house_id, month_year, total_meals, total_expenses, meal_rate, is_closed, closed_by) 
                VALUES (?, ?, ?, ?, ?, 1, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "isdddi", $house_id, $month_start, $first_member['all_meals'], 
                               $first_member['total_expenses'], $first_member['meal_rate'], $closed_by);
        
        if (!mysqli_stmt_execute($stmt)) {
            return false;
        }
        
        $summary_id = mysqli_insert_id($this->conn);
        
        // Insert member details for this house
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
    
    /**
     * Check if month is closed for a specific house
     */
    public function isMonthClosed($month, $year, $house_id = null) {
        if (!$house_id) {
            return false;
        }
        
        $month_start = "$year-$month-01";
        
        $sql = "SELECT summary_id FROM monthly_summary 
                WHERE house_id = ? AND month_year = ? AND is_closed = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "is", $house_id, $month_start);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        return mysqli_stmt_num_rows($stmt) > 0;
    }
    
    /**
     * Get expense breakdown for a specific house
     */
    public function getExpenseBreakdown($month, $year, $house_id = null) {
        if (!$house_id) {
            return array('breakdown' => array(), 'total' => 0);
        }
        
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        $sql = "SELECT category, SUM(amount) as total FROM expenses 
                WHERE house_id = ? AND expense_date BETWEEN ? AND ? 
                GROUP BY category ORDER BY total DESC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $house_id, $month_start, $month_end);
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
    
    /**
     * Get all meals for a house (for export)
     */
    public function getHouseMeals($house_id = null, $start_date = null, $end_date = null) {
        if (!$house_id) {
            return array();
        }
        
        $sql = "SELECT m.meal_date, mb.name, m.meal_count, m.created_at 
                FROM meals m 
                JOIN members mb ON m.member_id = mb.member_id 
                WHERE m.house_id = ?";
        
        $params = array($house_id);
        $types = "i";
        
        if ($start_date && $end_date) {
            $sql .= " AND m.meal_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= "ss";
        }
        
        $sql .= " ORDER BY m.meal_date DESC, mb.name ASC";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $meals = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $meals[] = $row;
        }
        
        return $meals;
    }
    
    /**
     * Get all expenses for a house (for export)
     */
    public function getHouseExpenses($house_id = null, $start_date = null, $end_date = null) {
        if (!$house_id) {
            return array();
        }
        
        $sql = "SELECT e.expense_date, e.category, e.amount, e.description, u.username as created_by, e.created_at 
                FROM expenses e 
                JOIN users u ON e.created_by = u.user_id 
                WHERE e.house_id = ?";
        
        $params = array($house_id);
        $types = "i";
        
        if ($start_date && $end_date) {
            $sql .= " AND e.expense_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= "ss";
        }
        
        $sql .= " ORDER BY e.expense_date DESC, e.created_at DESC";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $expenses = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $expenses[] = $row;
        }
        
        return $expenses;
    }
    
    /**
     * Get all deposits for a house (for export)
     */
    public function getHouseDeposits($house_id = null, $start_date = null, $end_date = null) {
        if (!$house_id) {
            return array();
        }
        
        $sql = "SELECT d.deposit_date, mb.name, d.amount, d.description, u.username as created_by, d.created_at 
                FROM deposits d 
                JOIN members mb ON d.member_id = mb.member_id 
                JOIN users u ON d.created_by = u.user_id 
                WHERE d.house_id = ?";
        
        $params = array($house_id);
        $types = "i";
        
        if ($start_date && $end_date) {
            $sql .= " AND d.deposit_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= "ss";
        }
        
        $sql .= " ORDER BY d.deposit_date DESC, mb.name ASC";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $deposits = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $deposits[] = $row;
        }
        
        return $deposits;
    }
    
    /**
     * Get all members for a house (for export)
     */
    public function getHouseMembers($house_id = null) {
        if (!$house_id) {
            return array();
        }
        
        $sql = "SELECT m.member_id, m.name, m.phone, m.email, m.join_date, m.status, 
                       u.username as created_by, m.created_at,
                       (SELECT SUM(meal_count) FROM meals WHERE member_id = m.member_id) as total_meals,
                       (SELECT SUM(amount) FROM deposits WHERE member_id = m.member_id) as total_deposits
                FROM members m 
                LEFT JOIN users u ON m.created_by = u.user_id 
                WHERE m.house_id = ? 
                ORDER BY m.name ASC";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $members = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $members[] = $row;
        }
        
        return $members;
    }
}
?>