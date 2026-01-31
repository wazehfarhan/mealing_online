<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'manager') {
        header("Location: ../manager/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$auth = new Auth();
$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$tokenData = null;

// If token is provided in URL, validate it
if (!empty($token)) {
    $tokenData = $auth->validateJoinToken($token);
    if (!$tokenData) {
        $error = "Invalid or expired join link. Please request a new one from your house manager.";
        $token = ''; // Clear invalid token
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a token validation form (first step)
    if (isset($_POST['validate_token'])) {
        $inputToken = $_POST['join_token'] ?? '';
        
        if (empty($inputToken)) {
            $error = "Please enter the join token";
        } else {
            $tokenData = $auth->validateJoinToken($inputToken);
            if ($tokenData) {
                $token = $inputToken;
                $success = "Valid token! You're joining: <strong>" . htmlspecialchars($tokenData['house_name']) . "</strong>";
            } else {
                $error = "Invalid or expired join token. Please check with your house manager.";
            }
        }
    }
    // Check if it's the registration form (second step)
    elseif (isset($_POST['register'])) {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $token = $_POST['token'] ?? '';
        
        // Validation
        if (empty($username) || empty($email) || empty($password)) {
            $error = "All required fields are marked with *";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
        } elseif (!empty($phone) && !preg_match('/^[0-9+\-\s]{10,15}$/', $phone)) {
            $error = "Invalid phone number format";
        } elseif (empty($token)) {
            $error = "Join token is required";
        } else {
            // Register user with token
            $result = $auth->registerWithToken($username, $email, $password, $token, $phone);
            
            if ($result['success']) {
                $success = "Registration successful! Redirecting to dashboard...";
                // Redirect to member dashboard after 2 seconds
                echo '<script>
                    setTimeout(function() {
                        window.location.href = "dashboard.php";
                    }, 2000);
                </script>';
            } else {
                $error = "Registration failed: " . $result['error'];
            }
        }
    }
}

// If token is invalid, reset tokenData
if (!empty($token) && !$tokenData) {
    $token = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join as Member - Meal Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .register-container {
            max-width: 550px;
            margin: 0 auto;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-body {
            padding: 30px;
        }
        .btn-register {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-register:hover {
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
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h3><i class="fas fa-user-plus me-2"></i>Join as Member</h3>
                <p class="mb-0">Connect to your house using the provided join link</p>
            </div>
            
            <div class="register-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo empty($token) ? 'active' : 'completed'; ?>">
                        <div class="step-number">1</div>
                        <div class="step-label">Enter Token</div>
                    </div>
                    <div class="step <?php echo !empty($token) ? 'active' : ''; ?>">
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
                
                <?php if (empty($token)): ?>
                <!-- Step 1: Token Entry Form -->
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="join_token" class="form-label">Join Token/Link *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="text" class="form-control" id="join_token" name="join_token" 
                                   placeholder="Enter the token or paste the full join link" required
                                   value="<?php echo isset($_POST['join_token']) ? htmlspecialchars($_POST['join_token']) : ''; ?>">
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
                            <a href="../auth/register.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-2"></i>
                                Register as Manager instead?
                            </a>
                        </div>
                    </div>
                </form>
                
                <?php else: ?>
                <!-- Step 2: Registration Form -->
                <?php if ($tokenData): ?>
                <div class="house-info">
                    <h5><i class="fas fa-home me-2"></i>Joining House</h5>
                    <p class="mb-1"><strong>House:</strong> <?php echo htmlspecialchars($tokenData['house_name']); ?></p>
                    <p class="mb-1"><strong>House Code:</strong> <?php echo htmlspecialchars($tokenData['house_code']); ?></p>
                    <p class="mb-0"><strong>Your Name:</strong> <?php echo htmlspecialchars($tokenData['name']); ?></p>
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
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       required>
                            </div>
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
                    
                    <div class="mb-4">
                        <label for="phone" class="form-label">Phone Number (Optional)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                   placeholder="+1 (555) 123-4567">
                        </div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" name="register" class="btn btn-register btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Complete Registration
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <a href="register.php" class="text-decoration-none">
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
        
        // Auto-focus first field
        const firstField = document.getElementById('join_token') || document.getElementById('username');
        if (firstField) {
            firstField.focus();
        }
        
        // Auto-extract token from URL if it's a full URL
        const joinTokenInput = document.getElementById('join_token');
        if (joinTokenInput) {
            // Get token from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const urlToken = urlParams.get('token');
            
            if (urlToken) {
                joinTokenInput.value = urlToken;
            } else {
                // Try to extract token from any text (if someone pastes full URL)
                joinTokenInput.addEventListener('input', function() {
                    const value = this.value;
                    // If it looks like a URL, extract token
                    if (value.includes('token=')) {
                        const tokenMatch = value.match(/token=([^&]+)/);
                        if (tokenMatch) {
                            this.value = tokenMatch[1];
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>