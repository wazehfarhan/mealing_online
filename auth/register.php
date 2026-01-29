<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'manager') {
        header("Location: ../manager/dashboard.php");
    } else {
        header("Location: ../member/dashboard.php");
    }
    exit();
}

$auth = new Auth();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'manager';
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Register user
        if ($auth->register($username, $email, $password, $role)) {
            $success = "Registration successful! You can now login.";
            // Clear form
            $_POST = array();
        } else {
            $error = "Registration failed. Username or email already exists.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Meal Management System</title>
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
        .register-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-body {
            padding: 30px;
        }
        .btn-register {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-register:hover {
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
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h3><i class="fas fa-user-plus me-2"></i>Create Account</h3>
                <p class="mb-0">Register as <?php echo isset($_POST['role']) ? ucfirst($_POST['role']) : 'Manager'; ?></p>
            </div>
            
            <div class="register-body">
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
                    <div class="mt-3">
                        <a href="login.php" class="btn btn-success">
                            <i class="fas fa-sign-in-alt me-2"></i>Login Now
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="role" value="<?php echo isset($_POST['role']) ? $_POST['role'] : 'manager'; ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                   required>
                        </div>
                        <div class="form-text">Choose a unique username</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
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
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-register btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">
                            Already have an account? 
                            <a href="login.php" class="text-decoration-none">Login here</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-4 text-white">
            <p>&copy; <?php echo date('Y'); ?> Meal Management System</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
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
        
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
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
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrengthBar');
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 25;
            if (password.match(/\d/)) strength += 25;
            if (password.match(/[^a-zA-Z\d]/)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.style.backgroundColor = '#dc3545'; // Red
            } else if (strength < 75) {
                strengthBar.style.backgroundColor = '#ffc107'; // Yellow
            } else {
                strengthBar.style.backgroundColor = '#28a745'; // Green
            }
        });
        
        // Auto-focus username field
        document.getElementById('username').focus();
    </script>
</body>
</html>