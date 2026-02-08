<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    require_once '../includes/auth.php';
    $auth = new Auth();
    
    if ($auth->hasHouse()) {
        $redirect = ($_SESSION['role'] === 'manager') ? 
            '../manager/dashboard.php' : 
            '../member/dashboard.php';
        header("Location: " . $redirect);
    } else {
        header("Location: ../manager/setup_house.php");
    }
    exit();
}

require_once '../includes/auth.php';

$auth = new Auth();
$error = '';
$success = '';
$username = '';

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']); // Clear the message after displaying
}

// Check for other success messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Check for error messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token invalid. Please try again.";
    } else {
        // FIXED: Replace FILTER_SANITIZE_STRING with FILTER_SANITIZE_FULL_SPECIAL_CHARS
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password";
        } else {
            if ($auth->login($username, $password)) {
                if ($auth->hasHouse()) {
                    $redirect = ($_SESSION['role'] === 'manager') ? 
                        '../manager/dashboard.php' : 
                        '../member/dashboard.php';
                    header("Location: " . $redirect);
                } else {
                    $redirect = ($_SESSION['role'] === 'manager') ? 
                        '../manager/setup_house.php' : 
                        '../member/waiting_approval.php';
                    header("Location: " . $redirect);
                }
                exit();
            } else {
                $error = "Invalid username or password";
                error_log("Failed login attempt for username: " . htmlspecialchars($username));
            }
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure login to Meal Management System">
    <title>mealsa - Login</title>
    <link rel="icon" type="image/png" href="../image/icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            --success-color: #28a745;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
        }
        
        * {
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            input, select, textarea {
                font-size: 16px !important;
            }
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .login-card {
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            }
        }
        
        .login-header {
            background: var(--secondary-gradient);
            color: white;
            padding: 35px 30px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .login-header {
                padding: 25px 20px;
            }
        }
        
        .login-header h2 {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }
        
        @media (max-width: 768px) {
            .login-header h2 {
                font-size: 1.5rem;
            }
        }
        
        .login-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        
        .login-body {
            padding: 35px 30px;
        }
        
        @media (max-width: 768px) {
            .login-body {
                padding: 25px 20px;
            }
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }
        
        .form-label i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
        
        .input-group {
            margin-bottom: 1.25rem;
        }
        
        .input-group-text {
            background-color: var(--light-bg);
            border: 1px solid #ced4da;
            border-right: none;
            padding: 12px 15px;
        }
        
        .form-control {
            border-left: none;
            padding: 12px;
            font-size: 1rem;
            min-height: 48px;
        }
        
        @media (max-width: 768px) {
            .form-control {
                min-height: 44px;
                padding: 10px;
            }
            
            .input-group-text {
                padding: 10px 12px;
            }
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.15);
            border-color: #3498db;
        }
        
        #togglePassword {
            min-width: 50px;
            border-left: none;
        }
        
        .btn-login {
            background: var(--secondary-gradient);
            border: none;
            padding: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            width: 100%;
            min-height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .btn-login {
                padding: 12px;
                font-size: 1rem;
                min-height: 48px;
            }
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        @media (max-width: 768px) {
            .alert {
                padding: 12px 16px;
                margin-bottom: 15px;
            }
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-left: 4px solid var(--success-color);
            color: #155724;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger-color);
            color: #721c24;
        }
        
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.2em;
        }
        
        .form-check-input:checked {
            background-color: #3498db;
            border-color: #3498db;
        }
        
        .form-check-label {
            padding-left: 8px;
            user-select: none;
        }
        
        .login-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            margin-top: 25px;
        }
        
        @media (max-width: 768px) {
            .login-footer {
                padding-top: 15px;
                margin-top: 20px;
            }
        }
        
        .login-footer a {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 0;
            min-height: 44px;
        }
        
        .login-footer a:hover {
            color: #3498db;
        }
        
        .login-footer a i {
            margin-right: 8px;
        }
        
        .copyright {
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 25px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .copyright {
                margin-top: 20px;
                font-size: 0.85rem;
            }
        }
        
        .btn-loading .spinner-border {
            display: inline-block;
            margin-right: 8px;
            width: 1rem;
            height: 1rem;
        }
        
        .btn-loading:disabled {
            opacity: 0.8;
        }
        
        @media (max-width: 360px) {
            .login-body {
                padding: 20px 15px;
            }
            
            .login-header {
                padding: 20px 15px;
            }
        }
        
        @media screen and (max-height: 600px) and (orientation: landscape) {
            .login-container {
                margin: 10px auto;
            }
            
            .login-header {
                padding: 15px 20px;
            }
            
            .login-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="fas fa-utensils me-2"></i>Meal Management System</h2>
                <p class="mb-0">Sign in to your account</p>
            </div>
            
            <div class="login-body">
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <div class="flex-grow-1"><?php echo htmlspecialchars($success); ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="flex-grow-1"><?php echo htmlspecialchars($error); ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i>Username or Email
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   value="<?php echo htmlspecialchars($username); ?>" 
                                   required 
                                   autocomplete="username"
                                   autocapitalize="none"
                                   autofocus
                                   placeholder="Enter your username or email">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="fas fa-key"></i>Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   autocomplete="current-password"
                                   placeholder="Enter your password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-4 d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        
                        <a href="forgot_password.php" class="text-decoration-none small">
                            <i class="fas fa-key me-1"></i>Forgot Password?
                        </a>
                    </div>
                    
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-login" id="loginBtn">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </button>
                    </div>
                    
                    <div class="login-footer">
                        <p class="mb-3 text-muted">Don't have an account?</p>
                        <a href="choose_role.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-user-plus me-2"></i>Create New Account
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; <?php echo date('Y'); ?> Meal Management System</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const loginForm = document.getElementById('loginForm');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const loginBtn = document.getElementById('loginBtn');
            const usernameInput = document.getElementById('username');
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                this.innerHTML = type === 'password' ? 
                    '<i class="fas fa-eye"></i>' : 
                    '<i class="fas fa-eye-slash"></i>';
            });
            
            // Form submission
            loginForm.addEventListener('submit', function(e) {
                const username = usernameInput.value.trim();
                const password = passwordInput.value;
                
                if (!username || !password) {
                    e.preventDefault();
                    
                    // Show error
                    if (!username) {
                        usernameInput.focus();
                        usernameInput.classList.add('is-invalid');
                    } else if (!password) {
                        passwordInput.focus();
                        passwordInput.classList.add('is-invalid');
                    }
                    return;
                }
                
                // Show loading state
                loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Signing in...';
                loginBtn.classList.add('btn-loading');
                loginBtn.disabled = true;
            });
            
            // Remove invalid class on input
            usernameInput.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
            
            passwordInput.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
            
            // Auto-focus username on desktop
            if (window.innerWidth > 768) {
                usernameInput.focus();
            }
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Add enter key support for password field
            passwordInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    loginForm.submit();
                }
            });
        });
    </script>
</body>
</html>