<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    public function login($username, $password) {
        // Sanitize input
        $username = mysqli_real_escape_string($this->conn, $username);
        
        // Prepare SQL statement - Join with houses table to get house info
        $sql = "SELECT u.*, h.house_id as user_house_id, h.house_name, h.house_code 
                FROM users u 
                LEFT JOIN houses h ON u.house_id = h.house_id 
                WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        
        if (!$stmt) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "ss", $username, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // Verify password
            if (password_verify($password, $row['password'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Set session variables
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['member_id'] = $row['member_id'];
                $_SESSION['logged_in'] = true;
                
                // Set house information if exists
                if (!empty($row['user_house_id'])) {
                    $_SESSION['house_id'] = $row['user_house_id'];
                    $_SESSION['house_name'] = $row['house_name'] ?? null;
                    $_SESSION['house_code'] = $row['house_code'] ?? null;
                } else {
                    // Clear house_id if user doesn't have one
                    unset($_SESSION['house_id']);
                    unset($_SESSION['house_name']);
                    unset($_SESSION['house_code']);
                }
                
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                $update_stmt = mysqli_prepare($this->conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "i", $row['user_id']);
                mysqli_stmt_execute($update_stmt);
                
                return true;
            }
        }
        
        return false;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            header("Location: ../auth/login.php");
            exit();
        }
    }
    
    public function requireRole($role) {
        $this->requireLogin();
        
        if ($_SESSION['role'] !== $role) {
            $_SESSION['error'] = "Access denied. You need to be a " . $role . " to access this page.";
            
            // Redirect based on user's actual role
            if (isset($_SESSION['role'])) {
                if ($_SESSION['role'] === 'manager') {
                    header("Location: ../manager/dashboard.php");
                } else {
                    header("Location: ../member/dashboard.php");
                }
            } else {
                header("Location: ../index.php");
            }
            exit();
        }
        
        // For manager/member roles, also check if they have a house
        if (in_array($role, ['manager', 'member'])) {
            $this->requireHouse();
        }
    }
    
    public function requireHouse() {
        $this->requireLogin();
        
        // Check if house_id is set in session
        if (!isset($_SESSION['house_id']) || empty($_SESSION['house_id'])) {
            // Try to get house_id from database
            $house_info = $this->getUserHouseInfo();
            
            if ($house_info && !empty($house_info['house_id'])) {
                // Set house info in session
                $_SESSION['house_id'] = $house_info['house_id'];
                $_SESSION['house_name'] = $house_info['house_name'] ?? null;
                $_SESSION['house_code'] = $house_info['house_code'] ?? null;
            } else {
                // No house found, redirect to setup
                if ($_SESSION['role'] === 'manager') {
                    header("Location: ../manager/setup_house.php");
                } else {
                    header("Location: ../manager/setup_house.php");
                }
                exit();
            }
        }
    }
    
    public function hasHouse($user_id = null) {
        if (!$user_id && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (!$user_id) {
            return false;
        }
        
        $sql = "SELECT house_id FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return !empty($row['house_id']);
        }
        
        return false;
    }
    
    public function getUserHouseInfo($user_id = null) {
        if (!$user_id && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (!$user_id) {
            return null;
        }
        
        $sql = "SELECT u.house_id, h.house_name, h.house_code, h.description, h.is_active, h.created_at
                FROM users u 
                LEFT JOIN houses h ON u.house_id = h.house_id 
                WHERE u.user_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    public function createHouse($house_name, $description = '', $user_id = null) {
        if (!$user_id && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (!$user_id) {
            return ['success' => false, 'error' => 'User ID is required'];
        }
        
        // Generate unique house code
        $house_code = strtoupper(substr(md5(uniqid()), 0, 6));
        
        // Start transaction
        mysqli_begin_transaction($this->conn);
        
        try {
            // Insert house
            $sql = "INSERT INTO houses (house_name, description, house_code, created_by, is_active) 
                    VALUES (?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $house_name, $description, $house_code, $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to insert house: " . mysqli_error($this->conn));
            }
            
            $new_house_id = mysqli_insert_id($this->conn);
            
            // Update user with house_id
            $sql = "UPDATE users SET house_id = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $new_house_id, $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update user: " . mysqli_error($this->conn));
            }
            
            // Get user info for member creation
            $sql = "SELECT username, email FROM users WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $current_user = mysqli_fetch_assoc($result);
            
            if (!$current_user) {
                throw new Exception("Failed to get user information");
            }
            
            // Create user as a member of the house
            $sql = "INSERT INTO members (house_id, name, phone, email, join_date, status, created_by) 
                    VALUES (?, ?, ?, ?, CURDATE(), 'active', ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            
            // Create variables for binding
            $member_name = $current_user['username'];
            $member_phone = '';
            $member_email = isset($current_user['email']) ? $current_user['email'] : '';
            
            mysqli_stmt_bind_param($stmt, "isssi", $new_house_id, $member_name, 
                                  $member_phone, $member_email, $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to create member: " . mysqli_error($this->conn));
            }
            
            $member_id = mysqli_insert_id($this->conn);
            
            // Link user to member
            $sql = "UPDATE users SET member_id = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $member_id, $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to link user to member: " . mysqli_error($this->conn));
            }
            
            // Commit transaction
            mysqli_commit($this->conn);
            
            // Update session
            $_SESSION['house_id'] = $new_house_id;
            $_SESSION['member_id'] = $member_id;
            
            return [
                'success' => true,
                'house_id' => $new_house_id,
                'house_code' => $house_code,
                'member_id' => $member_id
            ];
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function joinHouse($house_code, $user_id = null) {
        if (!$user_id && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (!$user_id) {
            return ['success' => false, 'error' => 'User ID is required'];
        }
        
        // Check if house exists
        $sql = "SELECT house_id, house_name, is_active FROM houses WHERE house_code = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $house_code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $house = mysqli_fetch_assoc($result);
        
        if (!$house) {
            return ['success' => false, 'error' => 'Invalid house code'];
        }
        
        if (!$house['is_active']) {
            return ['success' => false, 'error' => 'This house is inactive'];
        }
        
        // Start transaction
        mysqli_begin_transaction($this->conn);
        
        try {
            // Update user with house_id
            $sql = "UPDATE users SET house_id = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $house['house_id'], $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update user with house: " . mysqli_error($this->conn));
            }
            
            // Get user info for member creation
            $sql = "SELECT username, email FROM users WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $current_user = mysqli_fetch_assoc($result);
            
            if (!$current_user) {
                throw new Exception("Failed to get user information");
            }
            
            // Create user as a member of the house
            $sql = "INSERT INTO members (house_id, name, phone, email, join_date, status, created_by) 
                    VALUES (?, ?, ?, ?, CURDATE(), 'active', ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            
            // Create variables for binding
            $member_name = $current_user['username'];
            $member_phone = '';
            $member_email = isset($current_user['email']) ? $current_user['email'] : '';
            
            mysqli_stmt_bind_param($stmt, "isssi", $house['house_id'], $member_name, 
                                  $member_phone, $member_email, $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to create member: " . mysqli_error($this->conn));
            }
            
            $member_id = mysqli_insert_id($this->conn);
            
            // Link user to member
            $sql = "UPDATE users SET member_id = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $member_id, $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to link user to member: " . mysqli_error($this->conn));
            }
            
            // Commit transaction
            mysqli_commit($this->conn);
            
            // Update session
            $_SESSION['house_id'] = $house['house_id'];
            $_SESSION['member_id'] = $member_id;
            
            return [
                'success' => true,
                'house_id' => $house['house_id'],
                'house_name' => $house['house_name'],
                'member_id' => $member_id
            ];
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function leaveHouse($user_id = null) {
        if (!$user_id && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (!$user_id) {
            return ['success' => false, 'error' => 'User ID is required'];
        }
        
        $house_id = $_SESSION['house_id'] ?? null;
        
        if (!$house_id) {
            return ['success' => false, 'error' => 'No house to leave'];
        }
        
        // Start transaction
        mysqli_begin_transaction($this->conn);
        
        try {
            // Remove house_id and member_id from user
            $sql = "UPDATE users SET house_id = NULL, member_id = NULL WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to remove house from user: " . mysqli_error($this->conn));
            }
            
            // If user is a manager, handle manager transition
            if ($_SESSION['role'] === 'manager') {
                // Check if there are other managers in the house
                $sql = "SELECT COUNT(*) as manager_count FROM users 
                        WHERE house_id = ? AND role = 'manager' AND user_id != ?";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $house_id, $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $data = mysqli_fetch_assoc($result);
                
                if ($data['manager_count'] == 0) {
                    // No other managers, assign the oldest active member as manager
                    $sql = "SELECT user_id FROM users 
                            WHERE house_id = ? AND role = 'member' AND is_active = 1 
                            ORDER BY created_at ASC LIMIT 1";
                    $stmt = mysqli_prepare($this->conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $house_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if ($new_manager = mysqli_fetch_assoc($result)) {
                        // Promote this member to manager
                        $sql = "UPDATE users SET role = 'manager' WHERE user_id = ?";
                        $stmt = mysqli_prepare($this->conn, $sql);
                        mysqli_stmt_bind_param($stmt, "i", $new_manager['user_id']);
                        mysqli_stmt_execute($stmt);
                    }
                }
            }
            
            // Update member status to inactive
            $sql = "UPDATE members m 
                    INNER JOIN users u ON m.member_id = u.member_id 
                    SET m.status = 'inactive' 
                    WHERE u.user_id = ? AND m.house_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $house_id);
            mysqli_stmt_execute($stmt);
            
            // Commit transaction
            mysqli_commit($this->conn);
            
            // Clear session house data
            unset($_SESSION['house_id']);
            unset($_SESSION['house_name']);
            unset($_SESSION['house_code']);
            unset($_SESSION['member_id']);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function logout() {
        // Unset all session variables
        $_SESSION = array();
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        // Redirect to login
        header("Location: ../auth/login.php");
        exit();
    }
    
    public function register($username, $email, $password, $role = 'member', $member_id = null) {
        // Check if user already exists
        $check_sql = "SELECT user_id FROM users WHERE username = ? OR email = ?";
        $check_stmt = mysqli_prepare($this->conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            return false; // User already exists
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        if ($member_id) {
            $sql = "INSERT INTO users (username, email, password, role, member_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssi", $username, $email, $hashed_password, $role, $member_id);
        } else {
            $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $hashed_password, $role);
        }
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            $sql = "SELECT u.*, m.name as member_name, h.house_name 
                    FROM users u 
                    LEFT JOIN members m ON u.member_id = m.member_id 
                    LEFT JOIN houses h ON u.house_id = h.house_id 
                    WHERE u.user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            return mysqli_fetch_assoc($result);
        }
        return null;
    }
    
    public function updateProfile($user_id, $username, $email) {
        $sql = "UPDATE users SET username = ?, email = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $username, $email, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            return true;
        }
        return false;
    }
    
    public function changePassword($user_id, $current_password, $new_password) {
        // Get current password
        $sql = "SELECT password FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($current_password, $row['password'])) {
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                
                $sql = "UPDATE users SET password = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $new_hashed, $user_id);
                
                return mysqli_stmt_execute($stmt);
            }
        }
        
        return false;
    }
    
    // Helper function to check if current page is house setup
    public function isHouseSetupPage() {
        $current_page = basename($_SERVER['PHP_SELF']);
        return $current_page === 'setup_house.php';
    }
    
    // Helper function to redirect to house setup if no house
    public function redirectIfNoHouse() {
        if (!$this->isHouseSetupPage() && !$this->hasHouse()) {
            header("Location: setup_house.php");
            exit();
        }
    }
    
    // Get all houses (for admin or manager selection)
    public function getAllHouses() {
        $sql = "SELECT h.*, u.username as manager_name, 
                       (SELECT COUNT(*) FROM users WHERE house_id = h.house_id) as total_members,
                       (SELECT COUNT(*) FROM members WHERE house_id = h.house_id AND status = 'active') as active_members
                FROM houses h
                LEFT JOIN users u ON h.created_by = u.user_id
                ORDER BY h.created_at DESC";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    // In includes/auth.php, add this method to the Auth class
    public function getUserHouseId($user_id) {
        $conn = getConnection();
        $sql = "SELECT house_id FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        return $user ? $user['house_id'] : 0;
    }
    
    // Get house members
    public function getHouseMembers($house_id = null) {
        if (!$house_id && isset($_SESSION['house_id'])) {
            $house_id = $_SESSION['house_id'];
        }
        
        if (!$house_id) {
            return [];
        }
        
        $sql = "SELECT m.*, u.username, u.email as user_email, u.is_active as user_active
                FROM members m
                LEFT JOIN users u ON m.member_id = u.member_id
                WHERE m.house_id = ?
                ORDER BY m.name";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $house_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    /**
 * Generate join token for a member
 */
public function generateJoinToken($member_id, $house_id) {
    // Generate unique token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    $sql = "UPDATE members SET join_token = ?, token_expiry = ? WHERE member_id = ? AND house_id = ?";
    $stmt = mysqli_prepare($this->conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssii", $token, $expiry, $member_id, $house_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Generate join link
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        return $base_url . "/member/register.php?token=" . $token;
    }
    
    return false;
}

/**
 * Validate join token
 */
public function validateJoinToken($token) {
    $sql = "SELECT m.*, h.house_name, h.house_code 
            FROM members m 
            JOIN houses h ON m.house_id = h.house_id 
            WHERE m.join_token = ? AND m.token_expiry > NOW() AND m.status = 'active'";
    
    $stmt = mysqli_prepare($this->conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 1) {
        return mysqli_fetch_assoc($result);
    }
    
    return false;
}

/**
 * Complete member registration with token
 */
public function registerWithToken($username, $email, $password, $token, $phone = '') {
    // Validate token first
    $tokenData = $this->validateJoinToken($token);
    if (!$tokenData) {
        return ['success' => false, 'error' => 'Invalid or expired token'];
    }
    
    // Check if username/email exists
    if ($this->usernameExists($username) || $this->emailExists($email)) {
        return ['success' => false, 'error' => 'Username or email already exists'];
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Start transaction
    mysqli_begin_transaction($this->conn);
    
    try {
        // Insert user with house_id and member_id
        $sql = "INSERT INTO users (username, email, password, role, house_id, member_id, is_active) 
                VALUES (?, ?, ?, 'member', ?, ?, 1)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssii", $username, $email, $hashed_password, 
                              $tokenData['house_id'], $tokenData['member_id']);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to create user: " . mysqli_error($this->conn));
        }
        
        $user_id = mysqli_insert_id($this->conn);
        
        // Update member with email and phone if not set
        if (empty($tokenData['email']) || empty($tokenData['phone'])) {
            $sql = "UPDATE members SET email = ?, phone = ? WHERE member_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $email, $phone, $tokenData['member_id']);
            mysqli_stmt_execute($stmt);
        }
        
        // Clear the join token
        $sql = "UPDATE members SET join_token = NULL, token_expiry = NULL WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $tokenData['member_id']);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($this->conn);
        
        // Set session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'member';
        $_SESSION['member_id'] = $tokenData['member_id'];
        $_SESSION['house_id'] = $tokenData['house_id'];
        $_SESSION['house_name'] = $tokenData['house_name'];
        $_SESSION['house_code'] = $tokenData['house_code'];
        $_SESSION['logged_in'] = true;
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'member_id' => $tokenData['member_id'],
            'house_id' => $tokenData['house_id']
        ];
        
    } catch (Exception $e) {
        mysqli_rollback($this->conn);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check if username exists
 */
private function usernameExists($username) {
    $sql = "SELECT user_id FROM users WHERE username = ?";
    $stmt = mysqli_prepare($this->conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    return mysqli_stmt_num_rows($stmt) > 0;
}

/**
 * Check if email exists
 */
private function emailExists($email) {
    $sql = "SELECT user_id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($this->conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    return mysqli_stmt_num_rows($stmt) > 0;
}

/**
 * Set user session with proper data
 */
private function setUserSession($userData) {
    $_SESSION['user_id'] = $userData['user_id'];
    $_SESSION['username'] = $userData['username'];
    $_SESSION['email'] = $userData['email'];
    $_SESSION['role'] = $userData['role'];
    $_SESSION['logged_in'] = true;
    
    if (isset($userData['house_id'])) {
        $_SESSION['house_id'] = $userData['house_id'];
    }
    if (isset($userData['member_id'])) {
        $_SESSION['member_id'] = $userData['member_id'];
    }
    if (isset($userData['house_name'])) {
        $_SESSION['house_name'] = $userData['house_name'];
    }
    if (isset($userData['full_name'])) {
        $_SESSION['full_name'] = $userData['full_name'];
    }
}

/**
 * Get members without user accounts
 */
public function getMembersWithoutUsers($house_id = null) {
    if (!$house_id && isset($_SESSION['house_id'])) {
        $house_id = $_SESSION['house_id'];
    }
    
    if (!$house_id) {
        return [];
    }
    
    $sql = "SELECT m.* 
            FROM members m 
            LEFT JOIN users u ON m.member_id = u.member_id 
            WHERE m.house_id = ? 
            AND m.status = 'active' 
            AND u.user_id IS NULL 
            AND (m.join_token IS NULL OR m.token_expiry < NOW())
            ORDER BY m.name";
    
    $stmt = mysqli_prepare($this->conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $house_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}
}
?>