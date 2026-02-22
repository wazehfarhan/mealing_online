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
    
    // Validate email
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    // Validate phone number (basic validation)
    public function validatePhone($phone) {
        return preg_match('/^[0-9]{10,15}$/', $phone);
    }
    
    // Generate random string
    public function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }
    
    // Generate unique house code
    public function generateHouseCode() {
        do {
            $code = 'HM' . strtoupper($this->generateRandomString(6));
            $sql = "SELECT house_id FROM houses WHERE house_code = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $code);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
        } while (mysqli_stmt_num_rows($stmt) > 0);
        
        return $code;
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
            'today_meals' => 0,
            'total_deposits' => 0,
            'monthly_deposits' => 0,
            'average_meal_per_member' => 0
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
        
        // Total deposits for this house
        $sql = "SELECT SUM(amount) as total FROM deposits WHERE house_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_deposits'] = $row['total'] ?: 0;
        }
        
        // Monthly deposits for this house
        $sql = "SELECT SUM(amount) as total FROM deposits WHERE house_id = ? AND deposit_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $house_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['monthly_deposits'] = $row['total'] ?: 0;
        }
        
        // Average meals per member
        if ($stats['total_members'] > 0 && $stats['total_meals'] > 0) {
            $stats['average_meal_per_member'] = $stats['total_meals'] / $stats['total_members'];
        }
        
        return $stats;
    }
    
    /**
     * Get system-wide statistics for homepage - REAL TIME
     */
    public function getSystemStats() {
        $stats = array(
            'total_houses' => 0,
            'total_members' => 0,
            'today_meals' => 0,
            'total_money_managed' => 0,
            'new_houses_30_days' => 0,
            'active_houses_today' => 0
        );
        
        // Total active houses - FIXED: using is_active column instead of status
        $sql = "SELECT COUNT(*) as total FROM houses WHERE is_active = 1";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_houses'] = $row['total'];
        }
        
        // Total active members
        $sql = "SELECT COUNT(*) as total FROM members WHERE status = 'active'";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_members'] = $row['total'];
        }
        
        // Today's meals across all houses
        $today = date('Y-m-d');
        $sql = "SELECT COALESCE(SUM(meal_count), 0) as total FROM meals WHERE meal_date = '$today'";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['today_meals'] = $row['total'];
        }
        
        // Total money managed (all deposits)
        $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM deposits";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['total_money_managed'] = $row['total'];
        }
        
        // Get system growth (new houses in last 30 days)
        $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
        $sql = "SELECT COUNT(*) as new_houses FROM houses WHERE created_at >= '$thirty_days_ago' AND is_active = 1";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['new_houses_30_days'] = $row['new_houses'];
        }
        
        // Get active today (houses with activity today)
        $sql = "SELECT COUNT(DISTINCT house_id) as active_houses FROM meals WHERE meal_date = '$today'";
        $result = mysqli_query($this->conn, $sql);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['active_houses_today'] = $row['active_houses'];
        }
        
        return $stats;
    }

    /**
     * Get developer information for the homepage
     */
    public function getDeveloperInfo() {
        return array(
            'name' => 'Kazi Md. Wazeh Ullah Farhan',
            'title' => 'Software Engineering Student | Full-Stack & Cloud Enthusiast',
            'bio' => 'I am a Computer Science and Engineering (CSE) student at American International University-Bangladesh (AIUB), currently in my 10th semester. I have a strong passion for software engineering, full-stack web development, and cloud computing.',
            'education' => array(
                'degree' => 'BSc in Computer Science & Engineering (CSE)',
                'university' => 'American International University-Bangladesh (AIUB)',
                'semester' => '10th Semester',
                'student_id' => '23-50577-1'
            ),
            'skills' => array(
                'Programming' => 'C, C++, Java, C#, JavaScript, SQL',
                'Web Technologies' => 'HTML, CSS, JavaScript, PHP',
                'Frameworks' => '.NET (Windows Forms), MySQL',
                'Concepts' => 'OOP, Data Structures & Algorithms, Database Management, CRUD Operations',
                'Learning' => 'Full-Stack Web Development, AWS Cloud Engineering, AI & Machine Learning'
            ),
            'projects' => array(
                'AgriCore Operation Platform' => 'A C# Windows Application for smart agriculture and farm resource management',
                'SmartField Resource Platform' => 'A desktop-based system for crop, field, irrigation, and market tracking using C# and SQL Server',
                'Car Rental Management System' => 'A database-driven system for managing vehicles, customers, drivers, and rentals',
                'Meal Management System' => 'A web-based application for meal entry, cost calculation, and member management',
                'Digital School Management System' => 'A complete academic management system for students, teachers, attendance, and results',
                'Crop Management Module' => 'A CRUD-based C# module for farmer-wise crop tracking with secure authentication',
                'Field Management Module' => 'A resource allocation and monitoring system for agricultural fields',
                'Irrigation & Fertilizer Scheduler' => 'An automated scheduling module for efficient water and fertilizer usage',
                'Pest and Disease Control System' => 'A monitoring and record-keeping module for crop health management',
                'Market & Sales Tracking System' => 'A sales analytics and profit tracking module for agricultural products',
                'User Management System' => 'A role-based authentication and authorization system using C# and encrypted credentials',
                'Full Stack Web Projects' => 'PHP, MySQL, HTML, CSS, JavaScript based academic and management systems'
            ),
            'contact' => array(
                'email' => 'wzullah.farhan@gmail.com',
                'phone' => '+880 1828-658811',
                'location' => 'Bangladesh'
            ),
            'profiles' => array(
                'github' => 'https://github.com/wazehfarhan',
                'linkedin' => 'https://www.linkedin.com/in/w2zfrhn'
            ),
            'quote' => 'Driven by curiosity, powered by code, and focused on building meaningful software.'
        );
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
    
    /**
     * Get all members (including inactive) for a specific house
     */
    public function getAllMembers($house_id = null) {
        if (!$house_id) {
            return array();
        }
        
        $sql = "SELECT * FROM members WHERE house_id = ? ORDER BY status DESC, name ASC";
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
        $sql = "SELECT m.*, h.house_name FROM members m 
                LEFT JOIN houses h ON m.house_id = h.house_id 
                WHERE m.member_id = ?";
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
    
    // Delete member (soft delete by changing status)
    public function deleteMember($member_id, $house_id) {
        $sql = "UPDATE members SET status = 'inactive' WHERE member_id = ? AND house_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $member_id, $house_id);
        
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
    
    // Add multiple meals at once (for batch entry)
    public function addMultipleMeals($house_id, $meal_date, $meal_data, $created_by) {
        $success_count = 0;
        $error_count = 0;
        $errors = array();
        
        foreach ($meal_data as $member_id => $meal_count) {
            $result = $this->addMeal($house_id, $member_id, $meal_date, $meal_count, $created_by);
            if ($result) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Member ID $member_id: Failed to add meal";
            }
        }
        
        return array(
            'success' => true,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        );
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
    
    // Add multiple deposits at once
    public function addMultipleDeposits($house_id, $deposit_date, $deposit_data, $created_by) {
        $success_count = 0;
        $error_count = 0;
        $errors = array();
        
        foreach ($deposit_data as $member_id => $amount) {
            if ($amount > 0) {
                $result = $this->addDeposit($house_id, $member_id, $amount, $deposit_date, "Batch deposit", $created_by);
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = "Member ID $member_id: Failed to add deposit";
                }
            }
        }
        
        return array(
            'success' => true,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        );
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
            
            // Calculate previous month's balance if available
            $previous_balance = $this->getPreviousMonthBalance($house_id, $member['member_id'], $month, $year);
            
            // Calculate adjusted balance
            $adjusted_balance = $balance + $previous_balance;
            
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
                'balance' => $balance,
                'previous_balance' => $previous_balance,
                'adjusted_balance' => $adjusted_balance,
                'share_percentage' => ($all_meals > 0) ? ($total_meals / $all_meals) * 100 : 0
            );
        }
        
        return $report;
    }
    
    /**
     * Get previous month's balance for a member
     */
    private function getPreviousMonthBalance($house_id, $member_id, $current_month, $current_year) {
        // Calculate previous month
        $prev_month = date('Y-m', strtotime("$current_year-$current_month-01 -1 month"));
        list($prev_year, $prev_month_num) = explode('-', $prev_month);
        
        $month_start = "$prev_year-$prev_month_num-01";
        
        // Check in monthly_member_details
        $sql = "SELECT mmd.balance 
                FROM monthly_summary ms
                JOIN monthly_member_details mmd ON ms.summary_id = mmd.summary_id
                WHERE ms.house_id = ? 
                AND ms.month_year = ? 
                AND mmd.member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "isi", $house_id, $month_start, $member_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['balance'] ?: 0;
        }
        
        return 0;
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
    
    // Format date with time
    public function formatDateTime($date_string) {
        return date('M d, Y h:i A', strtotime($date_string));
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
                       (SELECT SUM(meal_count) FROM meals WHERE member_id = m.member_id AND m.house_id = ?) as total_meals,
                       (SELECT SUM(amount) FROM deposits WHERE member_id = m.member_id AND m.house_id = ?) as total_deposits
                FROM members m 
                LEFT JOIN users u ON m.created_by = u.user_id 
                WHERE m.house_id = ? 
                ORDER BY m.name ASC";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $house_id, $house_id, $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $members = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $members[] = $row;
        }
        
        return $members;
    }
    
    /**
     * Get member's monthly summary
     */
    public function getMemberMonthlySummary($member_id, $month, $year, $house_id) {
        $summary = array(
            'total_meals' => 0,
            'total_deposits' => 0,
            'average_meal_per_day' => 0,
            'highest_meal_day' => 0,
            'meal_days' => 0,
            'total_cost' => 0
        );
        
        $month_start = "$year-$month-01";
        $month_end = date('Y-m-t', strtotime($month_start));
        
        // Get total meals
        $sql = "SELECT SUM(meal_count) as total_meals FROM meals 
                WHERE house_id = ? AND member_id = ? AND meal_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $house_id, $member_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $summary['total_meals'] = $row['total_meals'] ?: 0;
        }
        
        // Get total deposits
        $sql = "SELECT SUM(amount) as total_deposits FROM deposits 
                WHERE house_id = ? AND member_id = ? AND deposit_date BETWEEN ? AND ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $house_id, $member_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $summary['total_deposits'] = $row['total_deposits'] ?: 0;
        }
        
        // Get meal details for calculation
        $sql = "SELECT meal_date, meal_count FROM meals 
                WHERE house_id = ? AND member_id = ? AND meal_date BETWEEN ? AND ? 
                ORDER BY meal_date";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $house_id, $member_id, $month_start, $month_end);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $meal_days = 0;
        $highest_meal = 0;
        $days_with_meals = 0;
        
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['meal_count'] > 0) {
                $days_with_meals++;
                if ($row['meal_count'] > $highest_meal) {
                    $highest_meal = $row['meal_count'];
                }
            }
            $meal_days++;
        }
        
        if ($meal_days > 0) {
            $summary['average_meal_per_day'] = $summary['total_meals'] / $meal_days;
        }
        
        $summary['highest_meal_day'] = $highest_meal;
        $summary['meal_days'] = $days_with_meals;
        
        // Get house stats to calculate cost
        $house_stats = $this->getDashboardStats($month, $year, $house_id);
        $summary['total_cost'] = $summary['total_meals'] * $house_stats['meal_rate'];
        
        return $summary;
    }
    
    /**
     * Export data to CSV
     */
    public function exportToCSV($data, $filename = 'export.csv') {
        if (empty($data)) {
            return false;
        }
        
        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, array_keys($data[0]));
        
        // Add data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Backup database
     */
    public function backupDatabase($house_id = null) {
        $backup_data = array();
        
        if ($house_id) {
            // Backup specific house data
            $backup_data['house_info'] = $this->getHouseInfo($house_id);
            $backup_data['members'] = $this->getHouseMembers($house_id);
            $backup_data['meals'] = $this->getHouseMeals($house_id);
            $backup_data['expenses'] = $this->getHouseExpenses($house_id);
            $backup_data['deposits'] = $this->getHouseDeposits($house_id);
        } else {
            // Backup all data (for admin)
            $backup_data['houses'] = $this->getAllHouses();
            // Add more tables as needed
        }
        
        return json_encode($backup_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Get house information
     */
    public function getHouseInfo($house_id) {
        $sql = "SELECT * FROM houses WHERE house_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    
    /**
     * Get all houses
     */
    public function getAllHouses() {
        $sql = "SELECT * FROM houses ORDER BY house_name ASC";
        $result = mysqli_query($this->conn, $sql);
        
        $houses = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $houses[] = $row;
        }
        
        return $houses;
    }
    
    /**
     * Check if user has access to house
     */
    public function checkHouseAccess($user_id, $house_id) {
        // For manager: check if user is the manager of this house
        $sql = "SELECT house_id FROM houses WHERE house_id = ? AND manager_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $house_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            return true; // User is manager of this house
        }
        
        // For member: check if user is a member of this house
        $sql = "SELECT member_id FROM members WHERE house_id = ? AND user_id = ? AND status = 'active'";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $house_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        return mysqli_stmt_num_rows($stmt) > 0;
    }
    
    /**
     * Send notification (placeholder for future implementation)
     */
    public function sendNotification($user_id, $title, $message, $type = 'info') {
        // This is a placeholder for future notification system
        // Could be implemented with email, SMS, or in-app notifications
        return true;
    }
    
    /**
     * Log activity
     */
    public function logActivity($user_id, $action, $details = '') {
        $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $action, $details, $ip_address, $user_agent);
        mysqli_stmt_execute($stmt);
        
        return true;
    }

    /**
     * Count pending requests for manager's house only
     * @param mysqli|null $conn Database connection (optional, for backward compatibility)
     * @param int|null $house_id House ID to count requests for
     * @return int Number of pending requests
     */
    public function countPendingRequests($conn = null, $house_id = null) {
        // Use provided connection or default to $this->conn
        $db = $conn ?: $this->conn;
        
        // If house_id not provided, get from session
        if (!$house_id && isset($_SESSION['house_id'])) {
            $house_id = $_SESSION['house_id'];
        }
        
        // If still no house_id, return 0
        if (!$house_id) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count FROM members 
                WHERE house_status IN ('pending_leave', 'pending_join')
                AND (house_id = ? OR requested_house_id = ?)";
        
        $stmt = mysqli_prepare($db, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $house_id, $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        
        return $data['count'] ?? 0;
    }
    
    /**
     * Get member's house status and request info
     */
    public function getMemberHouseStatus($member_id) {
        $sql = "SELECT house_status, requested_house_id, leave_request_date, join_request_date 
                FROM members WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * Get all pending leave requests
     */
    public function getPendingLeaveRequests($house_id = null) {
        $sql = "SELECT m.*, h.house_name as current_house_name 
                FROM members m 
                JOIN houses h ON m.house_id = h.house_id 
                WHERE m.house_status = 'pending_leave'";
        
        if ($house_id) {
            $sql .= " AND m.house_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $house_id);
        } else {
            $stmt = mysqli_prepare($this->conn, $sql);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $requests = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = $row;
        }
        
        return $requests;
    }
    
    /**
     * Get all pending join requests
     */
    public function getPendingJoinRequests($house_id = null) {
        $sql = "SELECT m.*, 
                       h_current.house_name as current_house_name,
                       h_requested.house_name as requested_house_name,
                       h_requested.house_id as requested_house_id
                FROM members m 
                JOIN houses h_current ON m.house_id = h_current.house_id
                LEFT JOIN houses h_requested ON m.requested_house_id = h_requested.house_id
                WHERE m.house_status = 'pending_join'";
        
        if ($house_id) {
            $sql .= " AND (m.house_id = ? OR m.requested_house_id = ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $house_id, $house_id);
        } else {
            $stmt = mysqli_prepare($this->conn, $sql);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $requests = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = $row;
        }
        
        return $requests;
    }
    
    /**
     * Request to leave house
     */
    public function requestLeaveHouse($member_id, $house_id) {
        // Check if member has any meal entries for today
        $today = date('Y-m-d');
        $check_sql = "SELECT COUNT(*) as count FROM meals 
                      WHERE member_id = ? AND house_id = ? AND meal_date = ?";
        $check_stmt = mysqli_prepare($this->conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "iis", $member_id, $house_id, $today);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $check_data = mysqli_fetch_assoc($check_result);
        
        if ($check_data['count'] > 0) {
            return ['success' => false, 'error' => 'Cannot leave house today. You have meal entries for today.'];
        }
        
        // Update member status to pending_leave
        $sql = "UPDATE members 
                SET house_status = 'pending_leave', 
                    leave_request_date = NOW() 
                WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        
        if (mysqli_stmt_execute($stmt)) {
            return ['success' => true, 'message' => 'Leave request submitted successfully.'];
        } else {
            return ['success' => false, 'error' => 'Failed to submit leave request.'];
        }
    }
    
    /**
     * Request to join house
     */
    public function requestJoinHouse($member_id, $requested_house_id) {
        // Get current house_id
        $current_sql = "SELECT house_id FROM members WHERE member_id = ?";
        $current_stmt = mysqli_prepare($this->conn, $current_sql);
        mysqli_stmt_bind_param($current_stmt, "i", $member_id);
        mysqli_stmt_execute($current_stmt);
        $current_result = mysqli_stmt_get_result($current_stmt);
        $current_data = mysqli_fetch_assoc($current_result);
        
        if (!$current_data) {
            return ['success' => false, 'error' => 'Member not found.'];
        }
        
        // Check if requested house exists and is open for joining
        $house_sql = "SELECT house_id, house_name, is_open_for_join 
                      FROM houses 
                      WHERE house_id = ? AND is_open_for_join = 1";
        $house_stmt = mysqli_prepare($this->conn, $house_sql);
        mysqli_stmt_bind_param($house_stmt, "i", $requested_house_id);
        mysqli_stmt_execute($house_stmt);
        $house_result = mysqli_stmt_get_result($house_stmt);
        
        if (mysqli_num_rows($house_result) === 0) {
            return ['success' => false, 'error' => 'Requested house is not available for joining.'];
        }
        
        $house_data = mysqli_fetch_assoc($house_result);
        
        // Update member to request joining new house
        $sql = "UPDATE members 
                SET house_status = 'pending_join', 
                    requested_house_id = ?, 
                    join_request_date = NOW() 
                WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $requested_house_id, $member_id);
        
        if (mysqli_stmt_execute($stmt)) {
            return [
                'success' => true, 
                'message' => 'Join request submitted successfully.',
                'house_name' => $house_data['house_name']
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to submit join request.'];
        }
    }
    
    /**
     * Cancel leave request
     */
    public function cancelLeaveRequest($member_id) {
        $sql = "UPDATE members 
                SET house_status = 'active', 
                    leave_request_date = NULL 
                WHERE member_id = ? AND house_status = 'pending_leave'";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Cancel join request
     */
    public function cancelJoinRequest($member_id) {
        $sql = "UPDATE members 
                SET house_status = 'active', 
                    requested_house_id = NULL, 
                    join_request_date = NULL 
                WHERE member_id = ? AND house_status = 'pending_join'";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Approve leave request
     */
    public function approveLeaveRequest($member_id, $performed_by) {
        // Get member details
        $member_sql = "SELECT * FROM members WHERE member_id = ?";
        $member_stmt = mysqli_prepare($this->conn, $member_sql);
        mysqli_stmt_bind_param($member_stmt, "i", $member_id);
        mysqli_stmt_execute($member_stmt);
        $member_result = mysqli_stmt_get_result($member_stmt);
        $member = mysqli_fetch_assoc($member_result);
        
        if (!$member) {
            return ['success' => false, 'error' => 'Member not found.'];
        }
        
        // Check if member has any balance or pending meals
        $balance_check_sql = "
            SELECT 
                (SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE member_id = ?) as total_deposits,
                (SELECT COALESCE(SUM(meal_count), 0) FROM meals WHERE member_id = ?) as total_meals
        ";
        $balance_stmt = mysqli_prepare($this->conn, $balance_check_sql);
        mysqli_stmt_bind_param($balance_stmt, "ii", $member_id, $member_id);
        mysqli_stmt_execute($balance_stmt);
        $balance_result = mysqli_stmt_get_result($balance_stmt);
        $balance = mysqli_fetch_assoc($balance_result);
        
        mysqli_begin_transaction($this->conn);
        
        try {
            // Archive member data
            $archive_sql = "INSERT INTO member_archive 
                            (member_id, name, email, phone, original_house_id, 
                             total_deposits, total_meals, archived_at, archived_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            $archive_stmt = mysqli_prepare($this->conn, $archive_sql);
            mysqli_stmt_bind_param($archive_stmt, "issisiii", 
                $member_id, $member['name'], $member['email'], $member['phone'], 
                $member['house_id'], $balance['total_deposits'], $balance['total_meals'], 
                $performed_by);
            
            if (!mysqli_stmt_execute($archive_stmt)) {
                throw new Exception("Failed to archive member.");
            }
            
            // Update member status
            $update_sql = "UPDATE members 
                           SET house_status = 'active', 
                               leave_request_date = NULL,
                               status = 'inactive'
                           WHERE member_id = ?";
            $update_stmt = mysqli_prepare($this->conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "i", $member_id);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Failed to update member status.");
            }
            
            // Log the action
            $log_sql = "INSERT INTO house_transfers_log 
                        (member_id, from_house_id, to_house_id, action, 
                         performed_by, performed_at, notes)
                        VALUES (?, ?, NULL, 'leave_approved', ?, NOW(), ?)";
            $log_stmt = mysqli_prepare($this->conn, $log_sql);
            $notes = "Member left house via manager approval";
            mysqli_stmt_bind_param($log_stmt, "iiis", $member_id, $member['house_id'], $performed_by, $notes);
            
            if (!mysqli_stmt_execute($log_stmt)) {
                throw new Exception("Failed to log action.");
            }
            
            mysqli_commit($this->conn);
            
            return ['success' => true, 'message' => 'Leave request approved successfully.'];
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Approve join request
     */
    public function approveJoinRequest($member_id, $performed_by) {
        // Get member details including requested house
        $member_sql = "SELECT m.*, h_requested.house_id as requested_house_id, 
                              h_requested.house_name as requested_house_name
                       FROM members m
                       LEFT JOIN houses h_requested ON m.requested_house_id = h_requested.house_id
                       WHERE m.member_id = ?";
        $member_stmt = mysqli_prepare($this->conn, $member_sql);
        mysqli_stmt_bind_param($member_stmt, "i", $member_id);
        mysqli_stmt_execute($member_stmt);
        $member_result = mysqli_stmt_get_result($member_stmt);
        $member = mysqli_fetch_assoc($member_result);
        
        if (!$member || !$member['requested_house_id']) {
            return ['success' => false, 'error' => 'Member or requested house not found.'];
        }
        
        $old_house_id = $member['house_id'];
        $new_house_id = $member['requested_house_id'];
        
        mysqli_begin_transaction($this->conn);
        
        try {
            // Update member to new house
            $update_sql = "UPDATE members 
                           SET house_id = ?, 
                               house_status = 'active',
                               requested_house_id = NULL,
                               join_request_date = NULL,
                               status = 'active'
                           WHERE member_id = ?";
            $update_stmt = mysqli_prepare($this->conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ii", $new_house_id, $member_id);
            
            if (!mysqli_stmt_execute($update_stmt)) {
                throw new Exception("Failed to update member house.");
            }
            
            // Update user session data if user exists
            $user_update_sql = "UPDATE users SET house_id = ? WHERE member_id = ?";
            $user_update_stmt = mysqli_prepare($this->conn, $user_update_sql);
            mysqli_stmt_bind_param($user_update_stmt, "ii", $new_house_id, $member_id);
            mysqli_stmt_execute($user_update_stmt);
            
            // Log the transfer
            $log_sql = "INSERT INTO house_transfers_log 
                        (member_id, from_house_id, to_house_id, action, 
                         performed_by, performed_at, notes)
                        VALUES (?, ?, ?, 'join_approved', ?, NOW(), ?)";
            $log_stmt = mysqli_prepare($this->conn, $log_sql);
            $notes = "Member joined house via manager approval";
            mysqli_stmt_bind_param($log_stmt, "iiiis", $member_id, $old_house_id, $new_house_id, $performed_by, $notes);
            
            if (!mysqli_stmt_execute($log_stmt)) {
                throw new Exception("Failed to log transfer.");
            }
            
            mysqli_commit($this->conn);
            
            return [
                'success' => true, 
                'message' => 'Join request approved successfully.',
                'new_house_name' => $member['requested_house_name']
            ];
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Reject leave request
     */
    public function rejectLeaveRequest($member_id, $performed_by, $reason = '') {
        $sql = "UPDATE members 
                SET house_status = 'active', 
                    leave_request_date = NULL 
                WHERE member_id = ? AND house_status = 'pending_leave'";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Get current house for logging
            $house_sql = "SELECT house_id FROM members WHERE member_id = ?";
            $house_stmt = mysqli_prepare($this->conn, $house_sql);
            mysqli_stmt_bind_param($house_stmt, "i", $member_id);
            mysqli_stmt_execute($house_stmt);
            $house_result = mysqli_stmt_get_result($house_stmt);
            $house = mysqli_fetch_assoc($house_result);
            
            // Log rejection
            $log_sql = "INSERT INTO house_transfers_log 
                        (member_id, from_house_id, action, 
                         performed_by, performed_at, notes)
                        VALUES (?, ?, 'leave_rejected', ?, NOW(), ?)";
            $log_stmt = mysqli_prepare($this->conn, $log_sql);
            mysqli_stmt_bind_param($log_stmt, "iiss", $member_id, $house['house_id'], $performed_by, $reason);
            mysqli_stmt_execute($log_stmt);
            
            return ['success' => true, 'message' => 'Leave request rejected successfully.'];
        } else {
            return ['success' => false, 'error' => 'Failed to reject leave request.'];
        }
    }
    
    /**
     * Reject join request
     */
    public function rejectJoinRequest($member_id, $performed_by, $reason = '') {
        $sql = "UPDATE members 
                SET house_status = 'active', 
                    requested_house_id = NULL,
                    join_request_date = NULL 
                WHERE member_id = ? AND house_status = 'pending_join'";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Get requested house for logging
            $member_sql = "SELECT requested_house_id FROM members WHERE member_id = ?";
            $member_stmt = mysqli_prepare($this->conn, $member_sql);
            mysqli_stmt_bind_param($member_stmt, "i", $member_id);
            mysqli_stmt_execute($member_stmt);
            $member_result = mysqli_stmt_get_result($member_stmt);
            $member_data = mysqli_fetch_assoc($member_result);
            
            // Log rejection
            $log_sql = "INSERT INTO house_transfers_log 
                        (member_id, to_house_id, action, 
                         performed_by, performed_at, notes)
                        VALUES (?, ?, 'join_rejected', ?, NOW(), ?)";
            $log_stmt = mysqli_prepare($this->conn, $log_sql);
            mysqli_stmt_bind_param($log_stmt, "iiss", $member_id, $member_data['requested_house_id'], $performed_by, $reason);
            mysqli_stmt_execute($log_stmt);
            
            return ['success' => true, 'message' => 'Join request rejected successfully.'];
        } else {
            return ['success' => false, 'error' => 'Failed to reject join request.'];
        }
    }
    
    /**
     * Get houses open for joining (excluding current house)
     */
    public function getHousesOpenForJoining($current_house_id = null) {
        $sql = "SELECT house_id, house_name, house_code, created_at 
                FROM houses 
                WHERE is_open_for_join = 1 
                AND is_active = 1";
        
        if ($current_house_id) {
            $sql .= " AND house_id != ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $current_house_id);
        } else {
            $stmt = mysqli_prepare($this->conn, $sql);
        }
        
        $sql .= " ORDER BY house_name ASC";
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $houses = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $houses[] = $row;
        }
        
        return $houses;
    }
    
    /**
     * Get transfer history for a member or house
     */
    public function getTransferHistory($member_id = null, $house_id = null, $limit = 50) {
        $sql = "SELECT 
                    l.*,
                    m.name as member_name,
                    h_from.house_name as from_house_name,
                    h_to.house_name as to_house_name,
                    u.username as performed_by_name
                FROM house_transfers_log l
                LEFT JOIN members m ON l.member_id = m.member_id
                LEFT JOIN houses h_from ON l.from_house_id = h_from.house_id
                LEFT JOIN houses h_to ON l.to_house_id = h_to.house_id
                LEFT JOIN users u ON l.performed_by = u.user_id
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if ($member_id) {
            $sql .= " AND l.member_id = ?";
            $params[] = $member_id;
            $types .= "i";
        }
        
        if ($house_id) {
            $sql .= " AND (l.from_house_id = ? OR l.to_house_id = ?)";
            $params[] = $house_id;
            $params[] = $house_id;
            $types .= "ii";
        }
        
        $sql .= " ORDER BY l.performed_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
        
        return $history;
    }
    
    /**
     * Get archived members
     */
    public function getArchivedMembers($original_house_id = null, $limit = 100) {
        $sql = "SELECT a.*, u.username as archived_by_name 
                FROM member_archive a
                LEFT JOIN users u ON a.archived_by = u.user_id";
        
        if ($original_house_id) {
            $sql .= " WHERE a.original_house_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $original_house_id);
        } else {
            $stmt = mysqli_prepare($this->conn, $sql);
        }
        
        $sql .= " ORDER BY a.archived_at DESC LIMIT ?";
        
        if ($original_house_id) {
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $original_house_id, $limit);
        } else {
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $limit);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $archived = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $archived[] = $row;
        }
        
        return $archived;
    }
    
    /**
     * Toggle house join status (open/closed for joining)
     */
    public function toggleHouseJoinStatus($house_id, $status) {
        $sql = "UPDATE houses SET is_open_for_join = ? WHERE house_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $status, $house_id);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get member's house history based on email and house code
     * Used for viewing past house data
     */
    public function getMemberHouseHistory($email, $house_code) {
        // First, find the house by code
        $house_sql = "SELECT house_id, house_name, house_code FROM houses WHERE house_code = ? AND is_active = 1";
        $house_stmt = mysqli_prepare($this->conn, $house_sql);
        mysqli_stmt_bind_param($house_stmt, "s", $house_code);
        mysqli_stmt_execute($house_stmt);
        $house_result = mysqli_stmt_get_result($house_stmt);
        $house = mysqli_fetch_assoc($house_result);
        
        if (!$house) {
            return false;
        }
        
        // Check if member was part of this house using their email
        // Look in both current members and archived members
        $member_sql = "SELECT member_id, name, email FROM members 
                       WHERE house_id = ? AND email = ? AND status = 'active'";
        $member_stmt = mysqli_prepare($this->conn, $member_sql);
        mysqli_stmt_bind_param($member_stmt, "is", $house['house_id'], $email);
        mysqli_stmt_execute($member_stmt);
        $member_result = mysqli_stmt_get_result($member_stmt);
        $member = mysqli_fetch_assoc($member_result);
        
        if (!$member) {
            // Check archived members
            $archive_sql = "SELECT archive_id as member_id, name, email FROM member_archive 
                           WHERE original_house_id = ? AND email = ?";
            $archive_stmt = mysqli_prepare($this->conn, $archive_sql);
            mysqli_stmt_bind_param($archive_stmt, "is", $house['house_id'], $email);
            mysqli_stmt_execute($archive_stmt);
            $archive_result = mysqli_stmt_get_result($archive_stmt);
            $member = mysqli_fetch_assoc($archive_result);
            
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
    public function calculateHouseHistoryStats($member_id, $house_id) {
        // Get deposits for this member and house
        $deposit_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM deposits 
                         WHERE member_id = ? AND house_id = ?";
        $deposit_stmt = mysqli_prepare($this->conn, $deposit_sql);
        mysqli_stmt_bind_param($deposit_stmt, "ii", $member_id, $house_id);
        mysqli_stmt_execute($deposit_stmt);
        $deposit_result = mysqli_stmt_get_result($deposit_stmt);
        $deposits = mysqli_fetch_assoc($deposit_result);
        
        // Get meals for this member and house
        $meal_sql = "SELECT COALESCE(SUM(meal_count), 0) as total FROM meals 
                     WHERE member_id = ? AND house_id = ?";
        $meal_stmt = mysqli_prepare($this->conn, $meal_sql);
        mysqli_stmt_bind_param($meal_stmt, "ii", $member_id, $house_id);
        mysqli_stmt_execute($meal_stmt);
        $meal_result = mysqli_stmt_get_result($meal_stmt);
        $meals = mysqli_fetch_assoc($meal_result);
        
        // Get total expenses for this house
        $expense_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE house_id = ?";
        $expense_stmt = mysqli_prepare($this->conn, $expense_sql);
        mysqli_stmt_bind_param($expense_stmt, "i", $house_id);
        mysqli_stmt_execute($expense_stmt);
        $expense_result = mysqli_stmt_get_result($expense_stmt);
        $house_expenses = mysqli_fetch_assoc($expense_result);
        
        // Get total meals for the house
        $total_meals_sql = "SELECT COALESCE(SUM(meal_count), 0) as total FROM meals WHERE house_id = ?";
        $total_meals_stmt = mysqli_prepare($this->conn, $total_meals_sql);
        mysqli_stmt_bind_param($total_meals_stmt, "i", $house_id);
        $total_meals_stmt->execute();
        $total_meals_result = $total_meals_stmt->get_result();
        $total_meals = mysqli_fetch_assoc($total_meals_result);
        
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
    public function generateJoinToken($house_id, $member_id = null, $token_type = 'house_transfer') {
        // Generate unique token
        do {
            $token = strtoupper(bin2hex(random_bytes(8)));
            $check_sql = "SELECT token_id FROM join_tokens WHERE token = ?";
            $check_stmt = mysqli_prepare($this->conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $token);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
        } while (mysqli_stmt_num_rows($check_stmt) > 0);
        
        // Set expiry to 7 days from now
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Insert the token
        $sql = "INSERT INTO join_tokens (token, house_id, member_id, token_type, expires_at, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "siisss", $token, $house_id, $member_id, $token_type, $expires_at, $_SESSION['user_id'] ?? null);
        
        if (mysqli_stmt_execute($stmt)) {
            return [
                'success' => true,
                'token' => $token,
                'expires_at' => $expires_at
            ];
        }
        
        return ['success' => false, 'error' => mysqli_error($this->conn)];
    }
    
    /**
     * Validate and use a join token
     */
    public function useJoinToken($token, $member_id) {
        // Check if token exists and is valid
        $sql = "SELECT * FROM join_tokens 
                WHERE token = ? AND is_used = 0 AND expires_at > NOW()";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $token_data = mysqli_fetch_assoc($result);
        
        if (!$token_data) {
            return ['success' => false, 'error' => 'Invalid or expired token.'];
        }
        
        // Mark token as used
        $update_sql = "UPDATE join_tokens 
                       SET is_used = 1, used_by = ?, used_at = NOW() 
                       WHERE token_id = ?";
        $update_stmt = mysqli_prepare($this->conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ii", $member_id, $token_data['token_id']);
        mysqli_stmt_execute($update_stmt);
        
        return [
            'success' => true,
            'house_id' => $token_data['house_id'],
            'token_type' => $token_data['token_type']
        ];
    }
    
    /**
     * Get pending join requests for a member (incoming requests to join member's current house)
     */
    public function getIncomingJoinRequests($house_id) {
        $sql = "SELECT m.*, h_requested.house_name as requested_house_name
                FROM members m
                LEFT JOIN houses h_requested ON m.requested_house_id = h_requested.house_id
                WHERE m.requested_house_id = ? AND m.house_status = 'pending_join'";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $requests = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $requests[] = $row;
        }
        
        return $requests;
    }
    
    /**
     * Close database connection
     */
    public function close() {
        if ($this->conn) {
            mysqli_close($this->conn);
        }
    }
}
?>