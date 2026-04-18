<?php
/**
 * Rate Limiter for Login Attempts
 * Prevents brute force attacks by limiting login attempts
 */

class RateLimiter {
    private $conn;
    private $max_attempts = 5;
    private $lockout_duration = 900; // 15 minutes
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Get user's IP address
     */
    private function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // Validate IP
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
    
    /**
     * Check if a username/email is currently locked out
     */
    public function isLockedOut($identifier) {
        if (!is_string($identifier) || empty($identifier)) {
            return false;
        }
        
        $sql = "SELECT is_blocked, locked_until FROM login_attempts 
                WHERE identifier = ? AND (is_blocked = 1 OR locked_until > NOW())
                ORDER BY locked_until DESC LIMIT 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $identifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            // If blocked by admin, return true
            if ($row['is_blocked'] == 1) {
                return true;
            }
            // If lockout time hasn't passed, return true
            if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
                return true;
            }
        }
        
        mysqli_stmt_close($stmt);
        return false;
    }
    
    /**
     * Record a failed login attempt
     */
    public function recordFailedAttempt($identifier) {
        if (!is_string($identifier) || empty($identifier)) {
            return false;
        }
        
        $ip_address = $this->getClientIp();
        $current_time = date('Y-m-d H:i:s');
        
        // Check if this identifier has a recent attempt
        $check_sql = "SELECT attempt_id, attempts FROM login_attempts 
                     WHERE identifier = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                     LIMIT 1";
        $check_stmt = mysqli_prepare($this->conn, $check_sql);
        if (!$check_stmt) {
            return false;
        }
        
        mysqli_stmt_bind_param($check_stmt, "s", $identifier);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if ($existing = mysqli_fetch_assoc($check_result)) {
            mysqli_stmt_close($check_stmt);
            
            // Update existing attempt
            $new_attempts = $existing['attempts'] + 1;
            $locked_until = null;
            
            // Lock out after max attempts
            if ($new_attempts >= $this->max_attempts) {
                $locked_until = date('Y-m-d H:i:s', time() + $this->lockout_duration);
            }
            
            $update_sql = "UPDATE login_attempts 
                          SET attempts = ?, locked_until = ?, last_attempt = NOW(), ip_address = ?
                          WHERE identifier = ?";
            $update_stmt = mysqli_prepare($this->conn, $update_sql);
            if (!$update_stmt) {
                return false;
            }
            
            mysqli_stmt_bind_param($update_stmt, "isss", $new_attempts, $locked_until, $ip_address, $identifier);
            $result = mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
            
            return $result;
        } else {
            mysqli_stmt_close($check_stmt);
            
            // Insert new attempt record
            $insert_sql = "INSERT INTO login_attempts (identifier, ip_address, attempts, last_attempt) 
                          VALUES (?, ?, 1, NOW())";
            $insert_stmt = mysqli_prepare($this->conn, $insert_sql);
            if (!$insert_stmt) {
                return false;
            }
            
            mysqli_stmt_bind_param($insert_stmt, "ss", $identifier, $ip_address);
            $result = mysqli_stmt_execute($insert_stmt);
            mysqli_stmt_close($insert_stmt);
            
            return $result;
        }
    }
    
    /**
     * Clear attempts for successful login
     */
    public function clearAttempts($identifier) {
        if (!is_string($identifier) || empty($identifier)) {
            return false;
        }
        
        $sql = "DELETE FROM login_attempts WHERE identifier = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $identifier);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $result;
    }
    
    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts($identifier) {
        if (!is_string($identifier) || empty($identifier)) {
            return $this->max_attempts;
        }
        
        $sql = "SELECT attempts FROM login_attempts 
                WHERE identifier = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY last_attempt DESC LIMIT 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            return $this->max_attempts;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $identifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            return max(0, $this->max_attempts - $row['attempts']);
        }
        
        mysqli_stmt_close($stmt);
        return $this->max_attempts;
    }
    
    /**
     * Clean up old attempts (should be called periodically)
     */
    public function cleanupOldAttempts() {
        $sql = "DELETE FROM login_attempts 
                WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        mysqli_query($this->conn, $sql);
    }
}
?>
