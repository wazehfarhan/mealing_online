<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();
$error = '';
$success = '';
$step = 1; // 1: Enter email, 2: Answer question, 3: Reset password, 4: Success
$email = '';
$question = '';
$show_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email']) && !isset($_POST['answer']) && !isset($_POST['new_password'])) {
        // Step 1: Request reset
        $email = trim($_POST['email']);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
            $step = 1;
        } else {
            // Generate reset token and get security question
            $result = $auth->generateResetToken($email);
            
            if ($result['success']) {
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_token'] = $result['token'];
                $question = $result['question'];
                $step = 2;
            } else {
                $error = $result['error'];
                $step = 1;
            }
        }
        
    } elseif (isset($_POST['answer'])) {
        // Step 2: Verify answer
        $answer = trim($_POST['answer']);
        $email = $_SESSION['reset_email'] ?? '';
        $token = $_SESSION['reset_token'] ?? '';
        
        if (empty($answer)) {
            $error = "Please enter your security answer";
            $step = 2;
            $question = $auth->getSecurityQuestion($email);
        } else {
            $result = $auth->verifyResetToken($email, $token, $answer);
            
            if ($result['success']) {
                $_SESSION['reset_verified'] = true;
                $step = 3;
            } else {
                $error = $result['error'];
                $step = 2;
                $question = $auth->getSecurityQuestion($email);
            }
        }
        
    } elseif (isset($_POST['new_password'])) {
        // Step 3: Reset password
        if (isset($_SESSION['reset_verified']) && $_SESSION['reset_verified'] === true) {
            $email = $_SESSION['reset_email'] ?? '';
            $token = $_SESSION['reset_token'] ?? '';
            $new_password = trim($_POST['new_password']);
            $confirm_password = trim($_POST['confirm_password']);
            
            if (empty($new_password) || empty($confirm_password)) {
                $error = "Both password fields are required";
                $step = 3;
            } elseif (strlen($new_password) < 6) {
                $error = "Password must be at least 6 characters";
                $step = 3;
            } elseif ($new_password !== $confirm_password) {
                $error = "Passwords do not match";
                $step = 3;
            } else {
                $result = $auth->resetPassword($email, $token, $new_password);
                
                if ($result['success']) {
                    $success = "Password reset successfully! You will be redirected to login page in 5 seconds.";
                    $show_success = true;
                    $step = 4; // Success step
                    
                    // Clear session data
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_token']);
                    unset($_SESSION['reset_verified']);
                    session_destroy();
                } else {
                    $error = $result['error'];
                    $step = 3;
                }
            }
        } else {
            $error = "Session expired. Please start over.";
            $step = 1;
            
            // Clear session data
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_token']);
            unset($_SESSION['reset_verified']);
            session_destroy();
        }
    }
}

// Get security question for display if in step 2
if (isset($_SESSION['reset_email']) && $step == 2) {
    $email = $_SESSION['reset_email'];
    $question = $auth->getSecurityQuestion($email);
    if (!$question) {
        $error = "No security question found for this account";
        $step = 1;
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_token']);
        session_destroy();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../image/icon.png">
    <title>Forgot Password - Meal Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-bg: #f8f9fa;
        }
        
        * {
            -webkit-tap-highlight-color: transparent;
            box-sizing: border-box;
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
        
        .reset-container {
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .reset-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .reset-card {
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            }
        }
        
        .reset-header {
            background: var(--secondary-gradient);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .reset-header {
                padding: 25px 20px;
            }
        }
        
        .reset-header h3 {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }
        
        @media (max-width: 768px) {
            .reset-header h3 {
                font-size: 1.5rem;
            }
        }
        
        .reset-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        
        .reset-body {
            padding: 30px;
        }
        
        @media (max-width: 768px) {
            .reset-body {
                padding: 25px 20px;
            }
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .step.active .step-circle {
            background: var(--secondary-gradient);
            color: white;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
        }
        
        .step.completed .step-circle {
            background: var(--success-color);
            color: white;
            box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.2);
        }
        
        .step-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .step.active .step-label {
            color: #3498db;
            font-weight: 600;
        }
        
        .step.completed .step-label {
            color: var(--success-color);
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
        
        .btn-reset {
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
            color: white;
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .btn-reset {
                padding: 12px;
                font-size: 1rem;
                min-height: 48px;
            }
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
            color: white;
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .btn-reset:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
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
        
        .alert-info {
            background-color: rgba(23, 162, 184, 0.1);
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }
        
        .security-question-box {
            background-color: rgba(52, 152, 219, 0.05);
            border: 1px solid rgba(52, 152, 219, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .security-question-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .security-question-text {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
            line-height: 1.4;
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
            border-width: 0.15em;
        }
        
        .btn-loading:disabled {
            opacity: 0.8;
        }
        
        @media (max-width: 360px) {
            .reset-body {
                padding: 20px 15px;
            }
            
            .reset-header {
                padding: 20px 15px;
            }
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #6c757d;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: #3498db;
        }
        
        .success-step {
            text-align: center;
            padding: 30px 20px;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
            animation: bounceIn 0.8s ease-out;
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); opacity: 1; }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); }
        }
        
        .success-message {
            font-size: 1.2rem;
            margin-bottom: 30px;
            color: #155724;
            line-height: 1.5;
        }
        
        .countdown {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 10px;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px 20px;
            font-weight: 600;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1ba87e 100%);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.2);
        }
        
        .btn-outline-secondary {
            padding: 12px 20px;
            font-weight: 500;
        }
        
        /* Focus styles for accessibility */
        a:focus,
        button:focus,
        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid #3498db;
            outline-offset: 2px;
        }
        
        /* Error state for inputs */
        .is-invalid {
            border-color: #dc3545 !important;
        }
        
        .is-invalid:focus {
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25) !important;
        }
        
        /* Password toggle button */
        .btn-outline-secondary {
            border-color: #ced4da;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <h3><i class="fas fa-key me-2"></i>Password Recovery</h3>
                <p class="mb-0">Reset your account password</p>
            </div>
            
            <div class="reset-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? 'active' : ''; echo $step > 1 ? ' completed' : ''; ?>">
                        <div class="step-circle">1</div>
                        <div class="step-label">Verify Email</div>
                    </div>
                    <div class="step <?php echo $step == 2 ? 'active' : ''; echo $step > 2 ? ' completed' : ''; ?>">
                        <div class="step-circle">2</div>
                        <div class="step-label">Security Question</div>
                    </div>
                    <div class="step <?php echo $step == 3 ? 'active' : ''; echo $step >= 4 ? ' completed' : ''; ?>">
                        <div class="step-circle">3</div>
                        <div class="step-label">New Password</div>
                    </div>
                </div>
                
                <?php if ($error && !$show_success): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="flex-grow-1"><?php echo htmlspecialchars($error); ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success && $show_success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <div class="flex-grow-1"><?php echo htmlspecialchars($success); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                <!-- Step 1: Email Form -->
                <form method="POST" action="" id="emailForm">
                    <div class="mb-3">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i>Enter your email address
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($email); ?>" 
                                   required 
                                   autocomplete="email"
                                   placeholder="Enter your registered email">
                        </div>
                        <div class="form-text">We'll send a security question to verify your identity</div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-reset" id="continueBtn">
                            <i class="fas fa-arrow-right me-2"></i>Continue
                        </button>
                    </div>
                    
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </form>
                
                <?php elseif ($step == 2): ?>
                <!-- Step 2: Security Question -->
                <form method="POST" action="" id="answerForm">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    
                    <div class="security-question-box">
                        <div class="security-question-label">Security Question:</div>
                        <div class="security-question-text">
                            <i class="fas fa-question-circle me-2 text-primary"></i>
                            <?php echo htmlspecialchars($question); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="answer" class="form-label">
                            <i class="fas fa-shield-alt"></i>Your Answer
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                            <input type="text" 
                                   class="form-control" 
                                   id="answer" 
                                   name="answer" 
                                   required 
                                   autocomplete="off"
                                   placeholder="Enter your security answer">
                        </div>
                        <div class="form-text">Answer the security question you set during registration</div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-reset" id="verifyBtn">
                            <i class="fas fa-check me-2"></i>Verify Answer
                        </button>
                    </div>
                    
                    <a href="forgot_password.php" class="back-link">
                        <i class="fas fa-redo me-2"></i>Start Over
                    </a>
                </form>
                
                <?php elseif ($step == 3): ?>
                <!-- Step 3: Reset Password -->
                <form method="POST" action="" id="passwordForm">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">
                            <i class="fas fa-lock"></i>New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" 
                                   class="form-control" 
                                   id="new_password" 
                                   name="new_password" 
                                   required 
                                   minlength="6"
                                   autocomplete="new-password"
                                   placeholder="Enter new password">
                            <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="form-text">Minimum 6 characters. Use a mix of letters, numbers, and symbols.</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i>Confirm New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required 
                                   autocomplete="new-password"
                                   placeholder="Confirm new password">
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" class="form-text"></div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-reset" id="resetBtn">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </button>
                    </div>
                    
                    <a href="forgot_password.php" class="back-link">
                        <i class="fas fa-redo me-2"></i>Cancel
                    </a>
                </form>
                
                <?php elseif ($step == 4 && $show_success): ?>
                <!-- Success Message Section -->
                <div class="success-step">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="text-success mb-3">Password Reset Successful!</h4>
                    <p class="success-message">
                        Your password has been reset successfully. You can now login with your new password.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="login.php" class="btn btn-success btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                        </a>
                        <button class="btn btn-outline-secondary" onclick="window.location.href='forgot_password.php'">
                            <i class="fas fa-redo me-2"></i>Reset Another Password
                        </button>
                    </div>
                    <div class="countdown mt-3" id="countdown">Auto-redirect to login in <span id="countdown-timer">5</span> seconds</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; <?php echo date('Y'); ?> Meal Management System</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password strength indicator
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const toggleNewPasswordBtn = document.getElementById('toggleNewPassword');
            const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');
            
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strengthBar = document.getElementById('passwordStrengthBar');
                    let strength = 0;
                    
                    if (password.length >= 6) strength += 25;
                    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 25;
                    if (password.match(/\d/)) strength += 25;
                    if (password.match(/[^a-zA-Z\d]/)) strength += 25;
                    
                    if (strengthBar) {
                        strengthBar.style.width = strength + '%';
                        
                        if (strength < 50) {
                            strengthBar.style.backgroundColor = '#dc3545';
                        } else if (strength < 75) {
                            strengthBar.style.backgroundColor = '#ffc107';
                        } else {
                            strengthBar.style.backgroundColor = '#28a745';
                        }
                    }
                });
                
                // Toggle new password visibility
                if (toggleNewPasswordBtn) {
                    toggleNewPasswordBtn.addEventListener('click', function() {
                        const type = newPasswordInput.type === 'password' ? 'text' : 'password';
                        newPasswordInput.type = type;
                        this.innerHTML = type === 'password' ? 
                            '<i class="fas fa-eye"></i>' : 
                            '<i class="fas fa-eye-slash"></i>';
                    });
                }
            }
            
            // Password match checker
            function checkPasswordMatch() {
                if (!newPasswordInput || !confirmPasswordInput) return;
                
                const password = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const matchText = document.getElementById('passwordMatch');
                
                if (!matchText) return;
                
                if (confirmPassword === '') {
                    matchText.textContent = '';
                    matchText.className = 'form-text';
                } else if (password === confirmPassword) {
                    matchText.textContent = '✓ Passwords match';
                    matchText.className = 'form-text text-success';
                } else {
                    matchText.textContent = '✗ Passwords do not match';
                    matchText.className = 'form-text text-danger';
                }
            }
            
            if (newPasswordInput) newPasswordInput.addEventListener('input', checkPasswordMatch);
            if (confirmPasswordInput) confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            
            // Toggle confirm password visibility
            if (toggleConfirmPasswordBtn && confirmPasswordInput) {
                toggleConfirmPasswordBtn.addEventListener('click', function() {
                    const type = confirmPasswordInput.type === 'password' ? 'text' : 'password';
                    confirmPasswordInput.type = type;
                    this.innerHTML = type === 'password' ? 
                        '<i class="fas fa-eye"></i>' : 
                        '<i class="fas fa-eye-slash"></i>';
                });
            }
            
            // Form submission handlers
            const emailForm = document.getElementById('emailForm');
            const answerForm = document.getElementById('answerForm');
            const passwordForm = document.getElementById('passwordForm');
            
            if (emailForm) {
                emailForm.addEventListener('submit', function(e) {
                    const emailInput = document.getElementById('email');
                    if (!emailInput || !emailInput.value.trim()) {
                        e.preventDefault();
                        emailInput.classList.add('is-invalid');
                        return;
                    }
                    
                    const btn = document.getElementById('continueBtn');
                    if (btn) {
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
                        btn.classList.add('btn-loading');
                        btn.disabled = true;
                    }
                });
            }
            
            if (answerForm) {
                answerForm.addEventListener('submit', function(e) {
                    const answerInput = document.getElementById('answer');
                    if (!answerInput || !answerInput.value.trim()) {
                        e.preventDefault();
                        answerInput.classList.add('is-invalid');
                        return;
                    }
                    
                    const btn = document.getElementById('verifyBtn');
                    if (btn) {
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Verifying...';
                        btn.classList.add('btn-loading');
                        btn.disabled = true;
                    }
                });
            }
            
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = newPasswordInput ? newPasswordInput.value : '';
                    const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        if (newPasswordInput) newPasswordInput.classList.add('is-invalid');
                        alert('Password must be at least 6 characters long!');
                        return;
                    }
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        if (confirmPasswordInput) confirmPasswordInput.classList.add('is-invalid');
                        alert('Passwords do not match!');
                        return;
                    }
                    
                    const btn = document.getElementById('resetBtn');
                    if (btn) {
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Resetting...';
                        btn.classList.add('btn-loading');
                        btn.disabled = true;
                    }
                });
            }
            
            // Remove invalid class on input
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                });
            });
            
            // Auto-hide error alerts after 5 seconds (except success)
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-danger');
                alerts.forEach(alert => {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                });
            }, 5000);
            
            // Auto-focus first input
            const firstInput = document.querySelector('input[type="email"], input[type="text"], input[type="password"]');
            if (firstInput && window.innerWidth > 768 && !document.querySelector('.success-step')) {
                setTimeout(() => {
                    firstInput.focus();
                }, 300);
            }
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Auto-redirect after success
            <?php if ($step == 4 && $show_success): ?>
            let countdown = 5;
            const countdownElement = document.getElementById('countdown-timer');
            const countdownText = document.getElementById('countdown');
            let countdownInterval;
            
            function startCountdown() {
                countdownInterval = setInterval(function() {
                    countdown--;
                    if (countdownElement) {
                        countdownElement.textContent = countdown;
                    }
                    
                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = 'login.php';
                    }
                }, 1000);
            }
            
            // Start countdown
            startCountdown();
            
            // Allow user to cancel auto-redirect
            if (countdownText) {
                countdownText.style.cursor = 'pointer';
                countdownText.title = 'Click to cancel auto-redirect';
                countdownText.addEventListener('click', function() {
                    clearInterval(countdownInterval);
                    this.innerHTML = 'Auto-redirect cancelled. <a href="login.php" class="text-primary">Click here to login</a>';
                });
            }
            <?php endif; ?>
            
            // Initialize Bootstrap tooltips if any
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>