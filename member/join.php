<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$functions = new Functions();

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'manager') {
        header("Location: ../manager/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$page_title = "Join House";

$conn = getConnection();

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$member_info = null;
$house_info = null;

// Security question options
$security_questions = [
    'What was your childhood nickname?',
    'In what city did you meet your spouse/significant other?',
    'What is the name of your favorite childhood friend?',
    'What street did you live on in third grade?',
    'What is your oldest sibling\'s middle name?',
    'What school did you attend for sixth grade?',
    'What is your maternal grandmother\'s maiden name?',
    'In what city or town was your first job?',
    'What is the name of your first pet?',
    'What is your favorite movie?',
    'What was your dream job as a child?',
    'What is the name of the street you grew up on?'
];

// If token is provided, validate it
if (!empty($token)) {
    $sql = "SELECT m.*, h.house_name, h.house_code, h.description as house_description
            FROM members m 
            JOIN houses h ON m.house_id = h.house_id 
            WHERE m.join_token = ? AND m.token_expiry > NOW() AND m.status = 'active' 
            AND h.is_active = 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 1) {
        $member_info = mysqli_fetch_assoc($result);
        $house_info = [
            'house_name' => $member_info['house_name'],
            'house_code' => $member_info['house_code'],
            'description' => $member_info['house_description']
        ];
        
        // Check if this member already has a user account
        $check_sql = "SELECT user_id FROM users WHERE member_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $member_info['member_id']);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "This member already has an account. Please login instead.";
            $token = '';
            $member_info = null;
        }
    } else {
        $error = "Invalid or expired join link. Please request a new one from your house manager.";
        $token = '';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['validate_token'])) {
        $input_token = trim($_POST['join_token'] ?? '');
        
        if (empty($input_token)) {
            $error = "Please enter the join token";
        } else {
            // Extract token if full URL is pasted
            if (strpos($input_token, 'token=') !== false) {
                $parts = parse_url($input_token);
                if (isset($parts['query'])) {
                    parse_str($parts['query'], $query_params);
                    $input_token = $query_params['token'] ?? $input_token;
                }
            }
            
            // Validate the token
            $sql = "SELECT m.*, h.house_name, h.house_code, h.description as house_description
                    FROM members m 
                    JOIN houses h ON m.house_id = h.house_id 
                    WHERE m.join_token = ? AND m.token_expiry > NOW() AND m.status = 'active' 
                    AND h.is_active = 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $input_token);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) === 1) {
                $member_info = mysqli_fetch_assoc($result);
                $house_info = [
                    'house_name' => $member_info['house_name'],
                    'house_code' => $member_info['house_code'],
                    'description' => $member_info['house_description']
                ];
                
                // Check if this member already has a user account
                $check_sql = "SELECT user_id FROM users WHERE member_id = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "i", $member_info['member_id']);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "This member already has an account. Please login instead.";
                    $member_info = null;
                } else {
                    $token = $input_token;
                    $success = "Valid join link! You're joining: <strong>" . htmlspecialchars($member_info['house_name']) . "</strong>";
                }
            } else {
                $error = "Invalid or expired join token. Please check with your house manager.";
            }
        }
    }
    elseif (isset($_POST['register'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $security_question = trim($_POST['security_question'] ?? '');
        $security_answer = trim($_POST['security_answer'] ?? '');
        $token = $_POST['token'] ?? '';
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($security_question) || empty($security_answer)) {
            $error = "All required fields are marked with *";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
        } elseif (empty($token)) {
            $error = "Join token is required";
        } elseif (strlen($security_answer) < 2) {
            $error = "Security answer must be at least 2 characters";
        } else {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Validate token again
                $sql = "SELECT m.* FROM members m 
                        WHERE m.join_token = ? AND m.token_expiry > NOW() AND m.status = 'active'";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "s", $token);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $member_info = mysqli_fetch_assoc($result);
                
                if (!$member_info) {
                    throw new Exception("Invalid or expired token");
                }
                
                // Check if username/email already exists
                $check_sql = "SELECT user_id FROM users WHERE username = ? OR email = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    throw new Exception("Username or email already exists");
                }
                
                // Check if member already has a user account (double check)
                $check_member_sql = "SELECT user_id FROM users WHERE member_id = ?";
                $check_member_stmt = mysqli_prepare($conn, $check_member_sql);
                mysqli_stmt_bind_param($check_member_stmt, "i", $member_info['member_id']);
                mysqli_stmt_execute($check_member_stmt);
                mysqli_stmt_store_result($check_member_stmt);
                
                if (mysqli_stmt_num_rows($check_member_stmt) > 0) {
                    throw new Exception("This member already has an account. Please login instead.");
                }
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Hash security answer
                $hashed_security_answer = password_hash($security_answer, PASSWORD_DEFAULT);
                
                // Create user account with member_id
                $insert_sql = "INSERT INTO users (username, email, password, role, house_id, member_id, 
                               security_question, security_answer, is_active) 
                              VALUES (?, ?, ?, 'member', ?, ?, ?, ?, 1)";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "sssiiss", $username, $email, $hashed_password, 
                                      $member_info['house_id'], $member_info['member_id'],
                                      $security_question, $hashed_security_answer);
                
                if (!mysqli_stmt_execute($insert_stmt)) {
                    throw new Exception("Failed to create user account: " . mysqli_error($conn));
                }
                
                $user_id = mysqli_insert_id($conn);
                
                // Update member with email and phone from form if they were empty
                $update_fields = [];
                $update_values = [];
                $types = "";
                
                // Always update email if it's different or empty in members table
                if (empty($member_info['email']) || $member_info['email'] !== $email) {
                    $update_fields[] = "email = ?";
                    $update_values[] = $email;
                    $types .= "s";
                }
                
                $phone = $_POST['phone'] ?? '';
                if (empty($member_info['phone']) && !empty($phone)) {
                    $update_fields[] = "phone = ?";
                    $update_values[] = $phone;
                    $types .= "s";
                }
                
                if (!empty($update_fields)) {
                    // Clear join token as well
                    $update_fields[] = "join_token = NULL";
                    $update_fields[] = "token_expiry = NULL";
                    
                    $update_values[] = $member_info['member_id'];
                    $types .= "i";
                    
                    $update_sql = "UPDATE members SET " . implode(", ", $update_fields) . " WHERE member_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, $types, ...$update_values);
                    
                    if (!mysqli_stmt_execute($update_stmt)) {
                        throw new Exception("Failed to update member information");
                    }
                } else {
                    // Still clear the join token even if no other fields to update
                    $clear_sql = "UPDATE members SET join_token = NULL, token_expiry = NULL WHERE member_id = ?";
                    $clear_stmt = mysqli_prepare($conn, $clear_sql);
                    mysqli_stmt_bind_param($clear_stmt, "i", $member_info['member_id']);
                    
                    if (!mysqli_stmt_execute($clear_stmt)) {
                        throw new Exception("Failed to clear join token");
                    }
                }
                
                // Get house information for session
                $house_sql = "SELECT house_name, house_code FROM houses WHERE house_id = ?";
                $house_stmt = mysqli_prepare($conn, $house_sql);
                mysqli_stmt_bind_param($house_stmt, "i", $member_info['house_id']);
                mysqli_stmt_execute($house_stmt);
                $house_result = mysqli_stmt_get_result($house_stmt);
                $house_data = mysqli_fetch_assoc($house_result);
                
                // Get member name for session
                $member_sql = "SELECT name FROM members WHERE member_id = ?";
                $member_stmt = mysqli_prepare($conn, $member_sql);
                mysqli_stmt_bind_param($member_stmt, "i", $member_info['member_id']);
                mysqli_stmt_execute($member_stmt);
                $member_result = mysqli_stmt_get_result($member_stmt);
                $member_data = mysqli_fetch_assoc($member_result);
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Set session variables
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'member';
                $_SESSION['member_id'] = $member_info['member_id'];
                $_SESSION['house_id'] = $member_info['house_id'];
                $_SESSION['house_name'] = $house_data['house_name'] ?? '';
                $_SESSION['house_code'] = $house_data['house_code'] ?? '';
                $_SESSION['member_name'] = $member_data['name'] ?? '';
                $_SESSION['logged_in'] = true;
                
                // Update last login
                $update_login_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                $update_login_stmt = mysqli_prepare($conn, $update_login_sql);
                mysqli_stmt_bind_param($update_login_stmt, "i", $user_id);
                mysqli_stmt_execute($update_login_stmt);
                
                // Commit session changes
                session_write_close();
                
                // Clear output buffers
                ob_end_clean();
                
                // Store success message in session for dashboard
                session_start();
                $_SESSION['success'] = "Registration successful! Welcome to your dashboard.";
                session_write_close();
                
                // Redirect immediately to dashboard
                header("Location: dashboard.php");
                exit();
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Registration failed: " . $e->getMessage();
                
                // Refresh member info if available
                if ($token && !$member_info) {
                    $sql = "SELECT m.*, h.house_name, h.house_code, h.description as house_description
                            FROM members m 
                            JOIN houses h ON m.house_id = h.house_id 
                            WHERE m.join_token = ? AND m.token_expiry > NOW() AND m.status = 'active' 
                            AND h.is_active = 1";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "s", $token);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) === 1) {
                        $member_info = mysqli_fetch_assoc($result);
                        $house_info = [
                            'house_name' => $member_info['house_name'],
                            'house_code' => $member_info['house_code'],
                            'description' => $member_info['house_description']
                        ];
                    }
                }
            }
        }
    }
}

// Include header without navigation (since user isn't logged in yet)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Meal Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .join-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .join-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .join-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .join-body {
            padding: 30px;
        }
        .btn-join {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-join:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
        }
        .btn-token {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-token:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        .house-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #27ae60;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step-indicator:before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: #6c757d;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        .step.active .step-number {
            background: #27ae60;
        }
        .step.completed .step-number {
            background: #27ae60;
        }
        .step-label {
            font-size: 14px;
            color: #6c757d;
        }
        .step.active .step-label {
            color: #27ae60;
            font-weight: bold;
        }
        .security-note {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 4px solid #ffc107;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="join-container">
        <div class="join-card">
            <div class="join-header">
                <h3><i class="fas fa-user-plus me-2"></i>Join House as Member</h3>
                <p class="mb-0">Use the join link provided by your house manager</p>
            </div>
            
            <div class="join-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo empty($token) ? 'active' : 'completed'; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Enter Token</div>
                    </div>
                    <div class="step <?php echo !empty($token) && $member_info ? 'active' : ''; ?>">
                        <div class="step-number">2</div>
                        <div class="step-label">Create Account</div>
                    </div>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (empty($token) || !$member_info): ?>
                <!-- Step 1: Token Entry Form -->
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="join_token" class="form-label">Join Token/Link *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="text" class="form-control" id="join_token" name="join_token" 
                                   placeholder="Enter the token or paste the full join link" required
                                   value="<?php echo isset($_POST['join_token']) ? htmlspecialchars($_POST['join_token']) : (isset($_GET['token']) ? htmlspecialchars($_GET['token']) : ''); ?>">
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Get this token from your house manager or click the join link they sent you
                        </div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" name="validate_token" class="btn btn-token btn-lg">
                            <i class="fas fa-check-circle me-2"></i>Validate Token
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-2">
                            <i class="fas fa-question-circle me-1"></i>
                            Don't have a token?
                        </p>
                        <p class="mb-0">
                            Contact your house manager to get an invitation link.
                        </p>
                        <div class="mt-3">
                            <a href="../auth/login.php" class="text-decoration-none">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Already have an account? Login here
                            </a>
                        </div>
                        <div class="mt-2">
                            <a href="../auth/register.php" class="text-decoration-none">
                                <i class="fas fa-user-plus me-2"></i>
                                Register as Manager instead?
                            </a>
                        </div>
                    </div>
                </form>
                
                <?php else: ?>
                <!-- Step 2: Registration Form -->
                <?php if ($member_info && $house_info): ?>
                <div class="house-info">
                    <h5><i class="fas fa-home me-2"></i>Joining House</h5>
                    <p class="mb-1"><strong>House:</strong> <?php echo htmlspecialchars($house_info['house_name']); ?></p>
                    <p class="mb-1"><strong>House Code:</strong> <?php echo htmlspecialchars($house_info['house_code']); ?></p>
                    <p class="mb-0"><strong>Your Name:</strong> <?php echo htmlspecialchars($member_info['name']); ?></p>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required>
                            </div>
                            <div class="form-text">Choose a unique username</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : (isset($member_info['email']) && !empty($member_info['email']) ? htmlspecialchars($member_info['email']) : ''); ?>"
                                       required>
                            </div>
                            <?php if (isset($member_info['email']) && !empty($member_info['email'])): ?>
                            <div class="form-text text-muted">Email already set by manager. You can change it if needed.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="security_question" class="form-label">Security Question *</label>
                            <select class="form-select" id="security_question" name="security_question" required>
                                <option value="">Select a security question</option>
                                <?php foreach ($security_questions as $question): ?>
                                <option value="<?php echo htmlspecialchars($question); ?>"
                                    <?php echo (isset($_POST['security_question']) && $_POST['security_question'] === $question) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($question); ?>
                                </option>
                                <?php endforeach; ?>
                                <option value="other">Other (enter your own question)</option>
                            </select>
                            <div class="form-text">Select a question for password recovery</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="security_answer" class="form-label">Security Answer *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                                <input type="text" class="form-control" id="security_answer" name="security_answer"
                                       value="<?php echo isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>"
                                       required>
                            </div>
                            <div class="form-text">Your answer for password recovery</div>
                        </div>
                    </div>
                    
                    <div id="custom_question_container" style="display: none;" class="mb-3">
                        <label for="custom_question" class="form-label">Custom Security Question *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-question-circle"></i></span>
                            <input type="text" class="form-control" id="custom_question" name="custom_question"
                                   placeholder="Enter your own security question">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="phone" class="form-label">Phone Number (Optional)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : (isset($member_info['phone']) && !empty($member_info['phone']) ? htmlspecialchars($member_info['phone']) : ''); ?>"
                                   placeholder="+1 (555) 123-4567">
                        </div>
                        <?php if (isset($member_info['phone']) && !empty($member_info['phone'])): ?>
                        <div class="form-text text-muted">Phone already set by manager. You can change it if needed.</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="security-note">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> The security question and answer will be used for password recovery. 
                        Choose a question and answer that you'll remember but others can't easily guess.
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" name="register" class="btn btn-join btn-lg mt-3">
                            <i class="fas fa-user-plus me-2"></i>Complete Registration
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <a href="join.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to token entry
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-4 text-white">
            <p>&copy; <?php echo date('Y'); ?> Meal Management System</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const passwordInput = document.getElementById('password');
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }
        
        if (toggleConfirmPassword) {
            toggleConfirmPassword.addEventListener('click', function() {
                const confirmInput = document.getElementById('confirm_password');
                const icon = this.querySelector('i');
                
                if (confirmInput.type === 'password') {
                    confirmInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    confirmInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }
        
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthBar = document.getElementById('passwordStrengthBar');
                let strength = 0;
                
                if (password.length >= 6) strength += 25;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 25;
                if (password.match(/\d/)) strength += 25;
                if (password.match(/[^a-zA-Z\d]/)) strength += 25;
                
                strengthBar.style.width = strength + '%';
                
                if (strength < 50) {
                    strengthBar.style.backgroundColor = '#dc3545';
                } else if (strength < 75) {
                    strengthBar.style.backgroundColor = '#ffc107';
                } else {
                    strengthBar.style.backgroundColor = '#28a745';
                }
            });
        }
        
        // Handle custom security question
        const securityQuestionSelect = document.getElementById('security_question');
        const customQuestionContainer = document.getElementById('custom_question_container');
        const customQuestionInput = document.getElementById('custom_question');
        
        if (securityQuestionSelect) {
            securityQuestionSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    customQuestionContainer.style.display = 'block';
                    customQuestionInput.required = true;
                    // Update the selected value to empty so we know it's custom
                    this.value = '';
                } else {
                    customQuestionContainer.style.display = 'none';
                    customQuestionInput.required = false;
                }
            });
            
            // Trigger change on page load if "other" is selected
            if (securityQuestionSelect.value === '') {
                const savedCustomQuestion = '<?php echo isset($_POST["custom_question"]) ? addslashes($_POST["custom_question"]) : ""; ?>';
                if (savedCustomQuestion) {
                    securityQuestionSelect.value = 'other';
                    customQuestionContainer.style.display = 'block';
                    customQuestionInput.value = savedCustomQuestion;
                    customQuestionInput.required = true;
                }
            }
        }
        
        // Update security question value before form submission
        const registrationForm = document.querySelector('form[method="POST"]');
        if (registrationForm && registrationForm.querySelector('button[name="register"]')) {
            registrationForm.addEventListener('submit', function(e) {
                const securityQuestionSelect = this.querySelector('#security_question');
                const customQuestionInput = this.querySelector('#custom_question');
                
                if (securityQuestionSelect && customQuestionInput && 
                    securityQuestionSelect.value === '' && customQuestionInput.value.trim() !== '') {
                    // If custom question is selected and filled, update the select value
                    securityQuestionSelect.value = customQuestionInput.value.trim();
                }
                
                // Validate custom question if "other" is selected
                if (customQuestionContainer.style.display === 'block' && 
                    (!customQuestionInput.value || customQuestionInput.value.trim() === '')) {
                    e.preventDefault();
                    alert('Please enter your custom security question.');
                    customQuestionInput.focus();
                }
            });
        }
        
        // Auto-focus first field
        const firstField = document.getElementById('join_token') || document.getElementById('username');
        if (firstField) {
            firstField.focus();
        }
        
        // Auto-extract token if full URL is pasted
        const joinTokenInput = document.getElementById('join_token');
        if (joinTokenInput) {
            // If token is in URL parameters, use it
            const urlParams = new URLSearchParams(window.location.search);
            const urlToken = urlParams.get('token');
            
            if (urlToken && !joinTokenInput.value) {
                joinTokenInput.value = urlToken;
            }
            
            // Also handle manual pasting
            joinTokenInput.addEventListener('input', function() {
                const value = this.value;
                // If it looks like a URL with token parameter
                if (value.includes('token=')) {
                    try {
                        const url = new URL(value);
                        const token = url.searchParams.get('token');
                        if (token) {
                            this.value = token;
                        }
                    } catch (e) {
                        // Not a valid URL, try manual extraction
                        const tokenMatch = value.match(/token=([^&]+)/);
                        if (tokenMatch) {
                            this.value = tokenMatch[1];
                        }
                    }
                }
            });
        }
        
        // Auto-generate username from name if available
        const usernameInput = document.getElementById('username');
        if (usernameInput && !usernameInput.value) {
            // Try to generate username from member name (if we have it)
            const memberName = '<?php echo isset($member_info["name"]) ? addslashes($member_info["name"]) : ""; ?>';
            if (memberName) {
                // Convert to lowercase, remove spaces and special characters
                let suggestedUsername = memberName.toLowerCase()
                    .replace(/[^a-z0-9]/g, '')
                    .substring(0, 20);
                
                if (suggestedUsername) {
                    usernameInput.placeholder = suggestedUsername;
                    // usernameInput.value = suggestedUsername; // Uncomment to auto-fill
                }
            }
        }
    </script>
</body>
</html>

<?php
// Close database connection
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
if (isset($check_stmt)) {
    mysqli_stmt_close($check_stmt);
}
mysqli_close($conn);