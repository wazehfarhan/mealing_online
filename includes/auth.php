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
        
        // Prepare SQL statement
        $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1";
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
            header("Location: ../index.php");
            exit();
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
            $sql = "SELECT u.*, m.name as member_name FROM users u 
                    LEFT JOIN members m ON u.member_id = m.member_id 
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
}
?>