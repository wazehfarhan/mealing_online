<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth.php';

$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'] ?? '';
$email = $_SESSION['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } elseif ($new_password === $current_password) {
        $error = "New password must be different from current password";
    } else {
        // Verify current password and change password
        // Using the changePassword method from your Auth class
        $change_result = $auth->changePassword($user_id, $current_password, $new_password);
        
        if ($change_result) {
            $success = "Password changed successfully!";
            
            // Clear password fields
            $_POST['current_password'] = $_POST['new_password'] = $_POST['confirm_password'] = '';
        } else {
            $error = "Current password is incorrect or failed to change password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Meal Management System</title>
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
        
        .change-password-container {
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .change-password-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .change-password-card {
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            }
        }
        
        .change-password-header {
            background: var(--secondary-gradient);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .change-password-header {
                padding: 25px 20px;
            }
        }
        
        .change-password-header h3 {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }
        
        @media (max-width: 768px) {
            .change-password-header h3 {
                font-size: 1.5rem;
            }
        }
        
        .change-password-header p {
            opacity: 0.9;
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        
        .change-password-body {
            padding: 30px;
        }
        
        @media (max-width: 768px) {
            .change-password-body {
                padding: 25px 20px;
            }
        }
        
        .user-info-box {
            background-color: rgba(52, 152, 219, 0.05);
            border: 1px solid rgba(52, 152, 219, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .user-icon {
            font-size: 2.5rem;
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .user-email {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .user-status {
            font-size: 0.9rem;
            color: #28a745;
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
        
        .btn-change {
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
            .btn-change {
                padding: 12px;
                font-size: 1rem;
                min-height: 48px;
            }
        }
        
        .btn-change:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
            color: white;
        }
        
        .btn-change:active {
            transform: translateY(0);
        }
        
        .btn-change:disabled {
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
            .change-password-body {
                padding: 20px 15px;
            }
            
            .change-password-header {
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
        
        .btn-outline-secondary {
            border-color: #ced4da;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
            color: #495057;
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
        
        /* Password requirements */
        .password-requirements {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        .password-requirements h6 {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
        }
        
        .password-requirements ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
            color: #6c757d;
        }
        
        .requirement-met {
            color: #28a745;
        }
        
        .requirement-not-met {
            color: #dc3545;
        }
        
        /* Redirect countdown */
        .redirect-countdown {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="change-password-container">
        <div class="change-password-card">
            <div class="change-password-header">
                <h3><i class="fas fa-lock me-2"></i>Change Password</h3>
                <p class="mb-0">Update your account password</p>
            </div>
            
            <div class="change-password-body">
                <!-- User Info Box -->
                <div class="user-info-box">
                    <div class="user-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
                    <div class="user-status">
                        <i class="fas fa-circle text-success me-1" style="font-size: 0.6rem;"></i>Logged In
                    </div>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="flex-grow-1"><?php echo htmlspecialchars($error); ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <div class="flex-grow-1">
                        <?php echo htmlspecialchars($success); ?>
                        <div class="redirect-countdown" id="redirectCountdown">
                            <i class="fas fa-clock me-1"></i>Redirecting to dashboard in <span id="countdownSeconds">3</span> seconds...
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="changePasswordForm">
                    <!-- Current Password -->
                    <div class="mb-3">
                        <label for="current_password" class="form-label">
                            <i class="fas fa-lock"></i>Current Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" 
                                   class="form-control" 
                                   id="current_password" 
                                   name="current_password" 
                                   required 
                                   autocomplete="current-password"
                                   placeholder="Enter your current password">
                            <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- New Password -->
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
                        
                        <!-- Password Requirements -->
                        <div class="password-requirements">
                            <h6>Password Requirements:</h6>
                            <ul>
                                <li id="req-length">✓ At least 6 characters</li>
                                <li id="req-lowercase">✓ Contains lowercase letter</li>
                                <li id="req-uppercase">✓ Contains uppercase letter</li>
                                <li id="req-number">✓ Contains number</li>
                                <li id="req-special">✓ Contains special character</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Confirm New Password -->
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
                        <button type="submit" class="btn btn-change" id="changeBtn">
                            <i class="fas fa-sync-alt me-2"></i>Change Password
                        </button>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="../manager/dashboard.php" class="back-link">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                        <a href="logout.php" class="back-link">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
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
            // Password fields
            const currentPasswordInput = document.getElementById('current_password');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            // Toggle buttons
            const toggleCurrentPasswordBtn = document.getElementById('toggleCurrentPassword');
            const toggleNewPasswordBtn = document.getElementById('toggleNewPassword');
            const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');
            
            // Password requirement elements
            const reqLength = document.getElementById('req-length');
            const reqLowercase = document.getElementById('req-lowercase');
            const reqUppercase = document.getElementById('req-uppercase');
            const reqNumber = document.getElementById('req-number');
            const reqSpecial = document.getElementById('req-special');
            
            // Toggle password visibility functions
            function setupPasswordToggle(inputElement, toggleButton) {
                if (inputElement && toggleButton) {
                    toggleButton.addEventListener('click', function() {
                        const type = inputElement.type === 'password' ? 'text' : 'password';
                        inputElement.type = type;
                        this.innerHTML = type === 'password' ? 
                            '<i class="fas fa-eye"></i>' : 
                            '<i class="fas fa-eye-slash"></i>';
                    });
                }
            }
            
            // Setup all password toggles
            setupPasswordToggle(currentPasswordInput, toggleCurrentPasswordBtn);
            setupPasswordToggle(newPasswordInput, toggleNewPasswordBtn);
            setupPasswordToggle(confirmPasswordInput, toggleConfirmPasswordBtn);
            
            // Password strength and requirements checker
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strengthBar = document.getElementById('passwordStrengthBar');
                    
                    // Check requirements
                    const hasLength = password.length >= 6;
                    const hasLowercase = /[a-z]/.test(password);
                    const hasUppercase = /[A-Z]/.test(password);
                    const hasNumber = /\d/.test(password);
                    const hasSpecial = /[^a-zA-Z\d]/.test(password);
                    
                    // Update requirement indicators
                    updateRequirement(reqLength, hasLength);
                    updateRequirement(reqLowercase, hasLowercase);
                    updateRequirement(reqUppercase, hasUppercase);
                    updateRequirement(reqNumber, hasNumber);
                    updateRequirement(reqSpecial, hasSpecial);
                    
                    // Calculate strength
                    let strength = 0;
                    if (hasLength) strength += 20;
                    if (hasLowercase) strength += 20;
                    if (hasUppercase) strength += 20;
                    if (hasNumber) strength += 20;
                    if (hasSpecial) strength += 20;
                    
                    // Update strength bar
                    if (strengthBar) {
                        strengthBar.style.width = strength + '%';
                        
                        if (strength < 60) {
                            strengthBar.style.backgroundColor = '#dc3545'; // Red
                        } else if (strength < 80) {
                            strengthBar.style.backgroundColor = '#ffc107'; // Yellow
                        } else {
                            strengthBar.style.backgroundColor = '#28a745'; // Green
                        }
                    }
                });
                
                function updateRequirement(element, met) {
                    if (element) {
                        if (met) {
                            element.className = 'requirement-met';
                            element.innerHTML = element.innerHTML.replace('✗', '✓');
                            if (!element.innerHTML.includes('✓')) {
                                element.innerHTML = '✓ ' + element.textContent;
                            }
                        } else {
                            element.className = 'requirement-not-met';
                            element.innerHTML = element.innerHTML.replace('✓', '✗');
                            if (!element.innerHTML.includes('✗')) {
                                element.innerHTML = '✗ ' + element.textContent;
                            }
                        }
                    }
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
            
            // Form submission handler
            const changePasswordForm = document.getElementById('changePasswordForm');
            
            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', function(e) {
                    // Get values
                    const currentPassword = currentPasswordInput ? currentPasswordInput.value : '';
                    const newPassword = newPasswordInput ? newPasswordInput.value : '';
                    const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
                    
                    // Basic validation
                    let hasError = false;
                    
                    if (!currentPassword) {
                        if (currentPasswordInput) currentPasswordInput.classList.add('is-invalid');
                        hasError = true;
                    }
                    
                    if (!newPassword) {
                        if (newPasswordInput) newPasswordInput.classList.add('is-invalid');
                        hasError = true;
                    }
                    
                    if (!confirmPassword) {
                        if (confirmPasswordInput) confirmPasswordInput.classList.add('is-invalid');
                        hasError = true;
                    }
                    
                    if (newPassword.length < 6) {
                        alert('Password must be at least 6 characters long!');
                        hasError = true;
                    }
                    
                    if (newPassword !== confirmPassword) {
                        alert('New passwords do not match!');
                        hasError = true;
                    }
                    
                    if (newPassword === currentPassword) {
                        alert('New password must be different from current password!');
                        hasError = true;
                    }
                    
                    if (hasError) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Show loading state
                    const btn = document.getElementById('changeBtn');
                    if (btn) {
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Changing Password...';
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
            
            // Auto-hide error alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-danger');
                alerts.forEach(alert => {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        setTimeout(() => {
                            closeBtn.click();
                        }, 100);
                    }
                });
            }, 5000);
            
            // Auto-focus first input
            if (currentPasswordInput && window.innerWidth > 768) {
                setTimeout(() => {
                    currentPasswordInput.focus();
                }, 300);
            }
            
            // Prevent form resubmission
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            <?php if ($success): ?>
            // Clear password fields
            if (currentPasswordInput) currentPasswordInput.value = '';
            if (newPasswordInput) newPasswordInput.value = '';
            if (confirmPasswordInput) confirmPasswordInput.value = '';
            
            // Reset strength bar
            const strengthBar = document.getElementById('passwordStrengthBar');
            if (strengthBar) {
                strengthBar.style.width = '0%';
                strengthBar.style.backgroundColor = '#e9ecef';
            }
            
            // Reset requirements
            const requirements = [reqLength, reqLowercase, reqUppercase, reqNumber, reqSpecial];
            requirements.forEach(req => {
                if (req) {
                    req.className = '';
                    req.innerHTML = req.textContent.replace('✓', '').replace('✗', '').trim();
                    req.innerHTML = '✗ ' + req.textContent;
                }
            });
            
            // Reset match text
            const matchText = document.getElementById('passwordMatch');
            if (matchText) {
                matchText.textContent = '';
                matchText.className = 'form-text';
            }
            
            // Start countdown for redirect
            let seconds = 3;
            const countdownElement = document.getElementById('countdownSeconds');
            
            if (countdownElement) {
                const countdownInterval = setInterval(function() {
                    seconds--;
                    countdownElement.textContent = seconds;
                    
                    if (seconds <= 0) {
                        clearInterval(countdownInterval);
                        // Redirect to dashboard
                        window.location.href = '../manager/dashboard.php';
                    }
                }, 1000);
            } else {
                // Fallback redirect after 3 seconds
                setTimeout(function() {
                    window.location.href = '../manager/dashboard.php';
                }, 3000);
            }
            
            // Scroll to top to show success message
            window.scrollTo(0, 0);
            <?php endif; ?>
        });
    </script>
</body>
</html>