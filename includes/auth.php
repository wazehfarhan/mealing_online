<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logging.php';

init_logging();
audit_log_event('request.received', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'path' => parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
    'query_parameter_names' => array_keys($_GET),
    'form_field_names' => array_keys($_POST),
]);

class Auth {
    private mysqli $conn;
    
    // Security questions list
    private array $security_questions = [
        "What was your childhood nickname?",
        "In what city did you meet your spouse/significant other?",
        "What is the name of your favorite childhood friend?",
        "What street did you live on in third grade?",
        "What is your oldest sibling's middle name?",
        "What is the name of your first pet?",
        "What was your favorite food as a child?",
        "What school did you attend for sixth grade?",
        "What is your maternal grandmother's maiden name?",
        "In what city or town was your first job?",
        "What is the name of your favorite teacher?",
        "What is your favorite movie?",
        "What was the make of your first car?",
        "What is your favorite book?",
        "What is the name of the company of your first job?",
    ];
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    public function getSecurityQuestions(): array {
        return $this->security_questions;
    }
    
    /**
     * Refresh house code in session
     * This ensures the session always has the latest house code
     */
    public function refreshHouseCode(): void {
        if (isset($_SESSION['house_id']) && !empty($_SESSION['house_id'])) {
            $sql = "SELECT house_code FROM houses WHERE house_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_SESSION['house_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $house = mysqli_fetch_assoc($result);
            
            if ($house) {
                $_SESSION['house_code'] = $house['house_code'];
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Updated login method
    public function login(string $username, string $password): bool {
        // Note: No need to escape — using prepared statements with parameter binding
        $username = trim($username);
        // Prepare SQL statement - Join with houses table to get house info
        $sql = "SELECT u.*, h.house_id as user_house_id, h.house_name, h.house_code 
                FROM users u 
                LEFT JOIN houses h ON u.house_id = h.house_id 
                WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        
        if (!$stmt) {
            audit_log_event('auth.login.failed', ['reason' => 'query_prepare_failed']);
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
                    
                    // If house_code is not set in the row but house_id exists, fetch it
                    if (empty($_SESSION['house_code']) && !empty($row['user_house_id'])) {
                        $this->refreshHouseCode();
                    }
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
                mysqli_stmt_close($update_stmt);
                audit_log_event('auth.login.success');
                
                return true;
            }
        }
        
        audit_log_event('auth.login.failed', ['reason' => 'invalid_credentials']);
        return false;
    }
    
    public function isLoggedIn(): bool {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * @return void
     */
    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            audit_log_event('auth.login.required');
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            header("Location: ../auth/login.php");
            exit();
        }
    }
    
    /**
     * @return void
     */
    public function requireRole(string $role): void {
        $this->requireLogin();

        $user_id = (int) ($_SESSION['user_id'] ?? 0);
        if ($user_id < 1) {
            audit_log_event('auth.role.rejected', ['reason' => 'missing_user_id']);
            header("Location: ../auth/login.php");
            exit();
        }

        $role_sql = "SELECT role FROM users WHERE user_id = ?";
        $role_stmt = mysqli_prepare($this->conn, $role_sql);
        mysqli_stmt_bind_param($role_stmt, "i", $user_id);
        mysqli_stmt_execute($role_stmt);
        $role_result = mysqli_stmt_get_result($role_stmt);
        $current_user = mysqli_fetch_assoc($role_result);
        mysqli_stmt_close($role_stmt);

        if (!$current_user) {
            audit_log_event('auth.role.rejected', ['reason' => 'user_not_found']);
            unset($_SESSION['role']);
            header("Location: ../auth/login.php");
            exit();
        }
        $_SESSION['role'] = $current_user['role'];
        
        if ($_SESSION['role'] !== $role) {
            audit_log_event('auth.role.rejected', ['required_role' => $role]);
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
    
    /**
     * @return void
     */
    public function requireHouse(): void {
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
                
                // Refresh house code
                $this->refreshHouseCode();
            } else {
                // No house found, redirect to setup
                if ($_SESSION['role'] === 'manager') {
                    header("Location: ../manager/setup_house.php");
                } else {
                    header("Location: ../member/dashboard.php");
                }
                exit();
            }
        }
    }
    
    public function hasHouse(?int $user_id = null): bool {
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
    
    /**
     * @return (float|int|null|string)[]|false|null
     *
     * @psalm-return array<string, float|int|null|string>|false|null
     */
    public function getUserHouseInfo(?int $user_id = null): array|false|null {
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
    
    /**
     * @return (bool|mixed|string)[]
     *
     * @psalm-return array{success: bool, error?: string, house_id?: mixed, house_code?: mixed, member_id?: mixed}
     */
    public function createHouse(string $house_name, string $description = '', ?int $user_id = null): array {
        if (!$user_id && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (!$user_id) {
            return ['success' => false, 'error' => 'User ID is required'];
        }

        // Generate unique house code (cryptographically secure)
        $house_code = strtoupper(bin2hex(random_bytes(3)));  // 6-character hex code

        // Ensure unique
        $sql_check = "SELECT house_id FROM houses WHERE house_code = ?";
        $stmt_check = mysqli_prepare($this->conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "s", $house_code);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        $attempts = 0;
        while (mysqli_stmt_num_rows($stmt_check) > 0 && $attempts < 10) {
            $house_code = strtoupper(bin2hex(random_bytes(3)));
            mysqli_stmt_free_result($stmt_check);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            $attempts++;
        }
        mysqli_stmt_close($stmt_check);

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
            $_SESSION['house_code'] = $house_code;
            $_SESSION['member_id'] = $member_id;
            $_SESSION['house_name'] = $house_name;
            
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
    
    /**
     * @return (mixed|null|scalar)[]
     *
     * @psalm-return array{success: bool, error?: string, house_id?: float|int|null|string, house_name?: float|int|null|string, member_id?: mixed}
     */
    public function joinHouse(string $house_code, ?int $user_id = null): array {
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
            $_SESSION['house_name'] = $house['house_name'];
            $_SESSION['member_id'] = $member_id;
            
            // Refresh house code
            $this->refreshHouseCode();
            
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
    
    /**
     * @return (bool|string)[]
     *
     * @psalm-return array{success: bool, error?: string}
     */
    public function leaveHouse(?int $user_id = null): array {
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

        $role_sql = "SELECT role, house_id FROM users WHERE user_id = ?";
        $role_stmt = mysqli_prepare($this->conn, $role_sql);
        mysqli_stmt_bind_param($role_stmt, "i", $user_id);
        mysqli_stmt_execute($role_stmt);
        $role_result = mysqli_stmt_get_result($role_stmt);
        $current_user = mysqli_fetch_assoc($role_result);
        mysqli_stmt_close($role_stmt);

        if (!$current_user || (int) $current_user['house_id'] !== (int) $house_id) {
            return ['success' => false, 'error' => 'Your current house could not be verified'];
        }

        if ($current_user['role'] === 'manager') {
            return [
                'success' => false,
                'error' => 'Assign another active manager and change your role to member before requesting to leave.',
            ];
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
    
    /**
     * @return never
     */
    public function logout(): never {
        audit_log_event('auth.logout');

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
    
    // Updated register method with security questions
    public function register(string $username, string $email, string $password, string $role = 'member', ?string $security_question = null, ?string $security_answer = null, ?int $member_id = null): bool {
        // Check if user already exists
        $check_sql = "SELECT user_id FROM users WHERE username = ? OR email = ?";
        $check_stmt = mysqli_prepare($this->conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            audit_log_event('auth.register.rejected', ['reason' => 'account_already_exists']);
            return false; // User already exists
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Hash security answer if provided
        $hashed_answer = null;
        if (!empty($security_answer)) {
            $hashed_answer = password_hash($security_answer, PASSWORD_DEFAULT);
        }
        
        if ($member_id) {
            $sql = "INSERT INTO users (username, email, password, role, security_question, security_answer, member_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssi", $username, $email, $hashed_password, 
                                 $role, $security_question, $hashed_answer, $member_id);
        } else {
            $sql = "INSERT INTO users (username, email, password, role, security_question, security_answer) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssss", $username, $email, $hashed_password, 
                                 $role, $security_question, $hashed_answer);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            audit_log_event('auth.register.success', [
                'user_id' => mysqli_insert_id($this->conn),
                'role' => $role,
            ]);
            return true;
        }

        audit_log_event('auth.register.error', [
            'database_error_code' => mysqli_errno($this->conn),
        ]);
        return false;
    }
    
    /**
     * @return (float|int|null|string)[]|false|null
     *
     * @psalm-return array<string, float|int|null|string>|false|null
     */
    public function getCurrentUser(): array|false|null {
        if ($this->isLoggedIn()) {
            $sql = "SELECT u.*, m.name as member_name, h.house_name, h.house_code 
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
    
    public function updateProfile(int $user_id, string $username, string $email): bool {
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
    
    public function changePassword(int $user_id, string $current_password, string $new_password): bool {
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
                if (mysqli_stmt_execute($stmt)) {
                    audit_log_event('auth.password.update.success');
                    return true;
                }

                audit_log_event('auth.password.update.error', [
                    'database_error_code' => mysqli_errno($this->conn),
                ]);
                return false;
            }
        }
        
        audit_log_event('auth.password.update.rejected', ['reason' => 'current_password_mismatch']);
        return false;
    }
    
    // Helper function to check if current page is house setup
    public function isHouseSetupPage(): bool {
        $current_page = basename($_SERVER['PHP_SELF']);
        return $current_page === 'setup_house.php';
    }
    
    // Helper function to redirect to house setup if no house
    /**
     * @return void
     */
    public function redirectIfNoHouse(): void {
        if (!$this->isHouseSetupPage() && !$this->hasHouse()) {
            header("Location: setup_house.php");
            exit();
        }
    }
    
    // Get all houses (for admin or manager selection)
    /**
     * @return (float|int|null|string)[][]
     *
     * @psalm-return list<array<string, float|int|null|string>>
     */
    public function getAllHouses(): array {
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
    
    public function getUserHouseId(int $user_id): int|float|string|null {
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
    /**
     * @return (float|int|null|string)[][]
     *
     * @psalm-return list<array<string, float|int|null|string>>
     */
    public function getHouseMembers(?int $house_id = null): array {
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
     *
     * @return false|string
     */
    public function generateJoinToken(int $member_id, int $house_id): string|false {
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
     *
     * @return (float|int|null|string)[]|false|null
     *
     * @psalm-return array<string, float|int|null|string>|false|null
     */
    public function validateJoinToken(string $token): array|false|null {
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
     * Complete member registration with token (updated with security questions)
     *
     * @return (bool|int|mixed|string)[]
     *
     * @psalm-return array{success: bool, error?: string, user_id?: int|string, member_id?: mixed, house_id?: mixed}
     */
    public function registerWithToken(string $username, string $email, string $password, string $token, ?string $security_question = null, ?string $security_answer = null, string $phone = ''): array {
        // Validate token first
        $tokenData = $this->validateJoinToken($token);
        if (!$tokenData) {
            audit_log_event('member.account.register.rejected', ['reason' => 'invalid_or_expired_invite']);
            return ['success' => false, 'error' => 'Invalid or expired token'];
        }
        
        // Check if username/email exists
        if ($this->usernameExists($username) || $this->emailExists($email)) {
            audit_log_event('member.account.register.rejected', ['reason' => 'account_already_exists']);
            return ['success' => false, 'error' => 'Username or email already exists'];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Hash security answer if provided
        $hashed_answer = null;
        if (!empty($security_answer)) {
            $hashed_answer = password_hash($security_answer, PASSWORD_DEFAULT);
        }
        
        // Start transaction
        mysqli_begin_transaction($this->conn);
        
        try {
            // Insert user with house_id and member_id
            $sql = "INSERT INTO users (username, email, password, role, house_id, member_id, security_question, security_answer, is_active) 
                    VALUES (?, ?, ?, 'member', ?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssiisss", $username, $email, $hashed_password, 
                                  $tokenData['house_id'], $tokenData['member_id'], 
                                  $security_question, $hashed_answer);
            
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
            audit_log_event('member.account.register.success', [
                'user_id' => (int) $user_id,
                'member_id' => (int) $tokenData['member_id'],
            ]);
            
            return [
                'success' => true,
                'user_id' => $user_id,
                'member_id' => $tokenData['member_id'],
                'house_id' => $tokenData['house_id']
            ];
            
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            audit_log_event('member.account.register.error', [
                'reason' => 'registration_transaction_failed',
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check if username exists
     */
    private function usernameExists(string $username): bool {
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
    private function emailExists(string $email): bool {
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
    private function setUserSession(array $userData): void {
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
        if (isset($userData['house_code'])) {
            $_SESSION['house_code'] = $userData['house_code'];
        }
        if (isset($userData['full_name'])) {
            $_SESSION['full_name'] = $userData['full_name'];
        }
    }
    
    /**
     * Get members without user accounts
     *
     * @return (float|int|null|string)[][]
     *
     * @psalm-return list<array<string, float|int|null|string>>
     */
    public function getMembersWithoutUsers(?int $house_id = null): array {
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
    
    // ========== NEW SECURITY QUESTION METHODS ==========
    
    /**
     * Get security question for a user by email
     *
     * @return float|int|null|string
     */
    public function getSecurityQuestion(string $email): int|float|string|null {
        $sql = "SELECT security_question FROM users WHERE email = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            return $row['security_question'];
        }
        
        return null;
    }
    
    /**
     * Verify security answer
     */
    public function verifySecurityAnswer(string $email, string $answer): bool {
        $sql = "SELECT security_answer FROM users WHERE email = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            
            // Check if security answer is set
            if (empty($row['security_answer'])) {
                return false;
            }
            
            return password_verify($answer, $row['security_answer']);
        }
        
        return false;
    }
    
    /**
     * Generate password reset token
     *
     * @return (mixed|scalar)[]
     *
     * @psalm-return array{success: bool, error?: 'Email not found'|'Failed to generate reset token'|'No security question set for this account', token?: mixed, question?: float|int|string}
     */
    public function generateResetToken(string $email): array {
        // Check if email exists and has security question
        $sql = "SELECT user_id, security_question FROM users WHERE email = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) !== 1) {
            audit_log_event('auth.password_reset.rejected', ['reason' => 'account_not_found']);
            return ['success' => false, 'error' => 'Email not found'];
        }
        
        $row = mysqli_fetch_assoc($result);
        
        // Check if security question is set
        if (empty($row['security_question'])) {
            audit_log_event('auth.password_reset.rejected', ['reason' => 'security_question_not_configured']);
            return ['success' => false, 'error' => 'No security question set for this account'];
        }
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $token, $expiry, $email);
        
        if (mysqli_stmt_execute($stmt)) {
            audit_log_event('auth.password_reset.requested', [
                'user_id' => (int) $row['user_id'],
                'expires_at' => $expiry,
            ]);
            return [
                'success' => true,
                'token' => $token,
                'question' => $row['security_question']
            ];
        }
        audit_log_event('auth.password_reset.error', [
            'user_id' => (int) $row['user_id'],
            'database_error_code' => mysqli_errno($this->conn),
        ]);
        
        return ['success' => false, 'error' => 'Failed to generate reset token'];
    }
    
    /**
     * Verify reset token and answer
     *
     * @return (bool|string)[]
     *
     * @psalm-return array{success: bool, error?: string}
     */
    public function verifyResetToken(string $email, string $token, string $answer): array {
        $sql = "SELECT reset_token, reset_token_expiry, security_answer 
                FROM users 
                WHERE email = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            
            // Check if token matches and is not expired
            if ($row['reset_token'] !== $token) {
                return ['success' => false, 'error' => 'Invalid token'];
            }
            
            if (strtotime($row['reset_token_expiry']) < time()) {
                return ['success' => false, 'error' => 'Token has expired'];
            }
            
            // Verify security answer
            if (!password_verify($answer, $row['security_answer'])) {
                return ['success' => false, 'error' => 'Incorrect security answer'];
            }
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Email not found'];
    }
    
    /**
     * Reset password with token
     *
     * @return (bool|string)[]
     *
     * @psalm-return array{success: bool, error?: 'Failed to reset password'|'Invalid token'|'Token has expired'}
     */
    public function resetPassword(string $email, string $token, string $new_password): array {
        // Verify token first
        $sql = "SELECT reset_token, reset_token_expiry 
                FROM users 
                WHERE email = ? AND reset_token = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $email, $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) !== 1) {
            audit_log_event('auth.password_reset.rejected', ['reason' => 'invalid_token']);
            return ['success' => false, 'error' => 'Invalid token'];
        }
        
        $row = mysqli_fetch_assoc($result);
        
        // Check if token is expired
        if (strtotime($row['reset_token_expiry']) < time()) {
            audit_log_event('auth.password_reset.rejected', ['reason' => 'expired_token']);
            return ['success' => false, 'error' => 'Token has expired'];
        }
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $sql = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE email = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $email);
        
        if (mysqli_stmt_execute($stmt)) {
            audit_log_event('auth.password_reset.success');
            return ['success' => true];
        }
        
        audit_log_event('auth.password_reset.error', [
            'database_error_code' => mysqli_errno($this->conn),
        ]);
        return ['success' => false, 'error' => 'Failed to reset password'];
    }
    
    /**
     * Update security question and answer
     *
     * @return (bool|string)[]
     *
     * @psalm-return array{success: bool, error?: 'Current password is incorrect'|'Failed to update security question'|'User not found'}
     */
    public function updateSecurityQuestion(int $user_id, string $question, string $answer, ?string $current_password = null): array {
        // Verify current password if provided
        if ($current_password !== null) {
            $sql = "SELECT password FROM users WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($row = mysqli_fetch_assoc($result)) {
                if (!password_verify($current_password, $row['password'])) {
                    audit_log_event('auth.security_question.update.rejected', [
                        'user_id' => $user_id,
                        'reason' => 'current_password_mismatch',
                    ]);
                    return ['success' => false, 'error' => 'Current password is incorrect'];
                }
            } else {
                audit_log_event('auth.security_question.update.rejected', [
                    'user_id' => $user_id,
                    'reason' => 'user_not_found',
                ]);
                return ['success' => false, 'error' => 'User not found'];
            }
        }
        
        // Hash the new security answer
        $hashed_answer = password_hash($answer, PASSWORD_DEFAULT);
        
        // Update security question and answer
        $sql = "UPDATE users SET security_question = ?, security_answer = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $question, $hashed_answer, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            audit_log_event('auth.security_question.update.success', [
                'user_id' => $user_id,
            ]);
            return ['success' => true];
        }
        
        audit_log_event('auth.security_question.update.error', [
            'user_id' => $user_id,
            'database_error_code' => mysqli_errno($this->conn),
        ]);
        return ['success' => false, 'error' => 'Failed to update security question'];
    }
    
    /**
     * Check if user has security question set
     */
    public function hasSecurityQuestion(?int $user_id = null): bool {
        if (!$user_id && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        if (!$user_id) {
            return false;
        }
        
        $sql = "SELECT security_question FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            return !empty($row['security_question']);
        }
        
        return false;
    }
    
    /**
     * Get user by email
     *
     * @return (float|int|null|string)[]|false|null
     *
     * @psalm-return array<string, float|int|null|string>|false|null
     */
    public function getUserByEmail(string $email): array|false|null {
        $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 1) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Validate reset token only (without answer check)
     *
     * @return (bool|string)[]
     *
     * @psalm-return array{success: bool, error?: 'Invalid token'|'Token has expired'}
     */
    public function validateResetTokenOnly(string $email, string $token): array {
        $sql = "SELECT reset_token, reset_token_expiry 
                FROM users 
                WHERE email = ? AND reset_token = ? AND is_active = 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $email, $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            
            // Check if token is expired
            if (strtotime($row['reset_token_expiry']) < time()) {
                return ['success' => false, 'error' => 'Token has expired'];
            }
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Invalid token'];
    }
    
    /**
     * Clear reset token (for cleanup or after successful reset)
     */
    public function clearResetToken(string $email): bool {
        $sql = "UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE email = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get member's current house status
     *
     * @return (float|int|null|string)[]|false|null
     *
     * @psalm-return array<string, float|int|null|string>|false|null
     */
    public function getMemberStatus(int $member_id): array|false|null {
        $sql = "SELECT house_status, requested_house_id, leave_request_date, join_request_date 
                FROM members WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * Update member's house after transfer approval
     */
    public function updateMemberHouse(int $member_id, int $new_house_id): bool {
        $sql = "UPDATE members SET house_id = ?, house_status = 'active', 
                requested_house_id = NULL, join_request_date = NULL 
                WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $new_house_id, $member_id);
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Update user's house after transfer approval
     */
    public function updateUserHouse(int $member_id, int $new_house_id): bool {
        $sql = "UPDATE users SET house_id = ? WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $new_house_id, $member_id);
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Archive member when leaving house
     */
    public function archiveMember(int $member_id, int $archived_by): bool {
        $sql = "INSERT INTO member_archive (member_id, name, email, phone, original_house_id, 
                total_deposits, total_meals, archived_at, archived_by)
                SELECT m.member_id, m.name, m.email, m.phone, m.house_id,
                       (SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE member_id = m.member_id),
                       (SELECT COALESCE(SUM(meal_count), 0) FROM meals WHERE member_id = m.member_id),
                       NOW(), ?
                FROM members m
                WHERE m.member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $archived_by, $member_id);
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Log house transfer action
     */
    public function logHouseTransfer(int $member_id, int $from_house_id, int $to_house_id, string $action, int $performed_by, string $notes = ''): bool {
        $sql = "INSERT INTO house_transfers_log 
                (member_id, from_house_id, to_house_id, action, performed_by, performed_at, notes)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiisss", $member_id, $from_house_id, $to_house_id, $action, $performed_by, $notes);
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Check if user is viewing history
     *
     * @return (float|int|null|string)[]|false|null
     *
     * @psalm-return array<string, float|int|null|string>|false|null
     */
    public function isViewingHistory(int $member_id): array|false|null {
        $sql = "SELECT is_viewing_history, history_house_id FROM members WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * Set history view mode
     */
    public function setHistoryView(int $member_id, int $house_id): bool {
        $sql = "UPDATE members SET is_viewing_history = 1, history_house_id = ? WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $house_id, $member_id);
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Clear history view mode
     */
    public function clearHistoryView(int $member_id): bool {
        $sql = "UPDATE members SET is_viewing_history = 0, history_house_id = NULL WHERE member_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $member_id);
        return mysqli_stmt_execute($stmt);
    }
}
?>
