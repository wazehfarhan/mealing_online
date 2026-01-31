<?php
// member/join.php - FIXED VERSION
session_start(); // Add session start
require_once '../config/database.php';

$conn = getConnection();

$error = '';
$success = '';
$show_form = false;
$member_name = '';
$house_id = null;

// Handle token from GET
if (isset($_GET['token'])) {
    $token = mysqli_real_escape_string($conn, $_GET['token']);
    
    // Check token validity - join with house information
    $sql = "SELECT m.*, h.house_id, h.house_name 
            FROM members m 
            JOIN houses h ON m.house_id = h.house_id 
            WHERE m.join_token = ? AND m.token_expiry > NOW() AND m.status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $member_id = $row['member_id'];
        $member_name = $row['name'];
        $house_id = $row['house_id'];
        $house_name = $row['house_name'];
        $show_form = true;
        
        // Store in session for form submission
        $_SESSION['join_token'] = $token;
        $_SESSION['join_member_id'] = $member_id;
        $_SESSION['join_house_id'] = $house_id;
    } else {
        $error = "Invalid or expired join link. Please contact your manager for a new link.";
    }
}
// Handle form submission
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $token = $_SESSION['join_token'] ?? '';
    $member_id = $_SESSION['join_member_id'] ?? 0;
    $house_id = $_SESSION['join_house_id'] ?? 0;
    
    if (empty($token) || $member_id == 0 || $house_id == 0) {
        $error = "Invalid session. Please use the join link again.";
    } elseif (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Check if username/email already exists
        $check_sql = "SELECT user_id FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Username or email already exists";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Create user account with house_id and role
            $insert_sql = "INSERT INTO users (username, email, password, role, house_id, member_id) 
                          VALUES (?, ?, ?, 'member', ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt, "sssii", $username, $email, $hashed_password, $house_id, $member_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $user_id = mysqli_insert_id($conn);
                
                // Update member record with user_id
                $update_member_sql = "UPDATE members SET user_id = ? WHERE member_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_member_sql);
                mysqli_stmt_bind_param($update_stmt, "ii", $user_id, $member_id);
                mysqli_stmt_execute($update_stmt);
                
                // Clear join token
                $update_token_sql = "UPDATE members SET join_token = NULL, token_expiry = NULL WHERE member_id = ?";
                $update_token_stmt = mysqli_prepare($conn, $update_token_sql);
                mysqli_stmt_bind_param($update_token_stmt, "i", $member_id);
                mysqli_stmt_execute($update_token_stmt);
                
                // Auto-login the user
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'member';
                $_SESSION['house_id'] = $house_id;
                $_SESSION['member_id'] = $member_id;
                
                // Clear session variables
                unset($_SESSION['join_token']);
                unset($_SESSION['join_member_id']);
                unset($_SESSION['join_house_id']);
                
                // Get house name for success message
                $house_sql = "SELECT house_name FROM houses WHERE house_id = ?";
                $house_stmt = mysqli_prepare($conn, $house_sql);
                mysqli_stmt_bind_param($house_stmt, "i", $house_id);
                mysqli_stmt_execute($house_stmt);
                $house_result = mysqli_stmt_get_result($house_stmt);
                $house_data = mysqli_fetch_assoc($house_result);
                $house_name = $house_data['house_name'] ?? '';
                
                // Set success message and redirect
                $_SESSION['success'] = "Account created successfully! Welcome to " . htmlspecialchars($house_name) . ".";
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Error creating account: " . mysqli_error($conn);
            }
        }
    }
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .join-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            margin: 0 auto;
        }
        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 30px;
        }
        .btn-join {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-join:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
        }
        .member-info {
            background-color: #e8f4fc;
            border-left: 4px solid #3498db;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="join-card">
            <div class="card-header text-center">
                <h2><i class="fas fa-user-plus me-2"></i>Join as Member</h2>
            </div>
            <div class="card-body p-4">
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
                        <a href="../auth/login.php" class="btn btn-success">
                            <i class="fas fa-sign-in-alt me-2"></i>Login Now
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($show_form && isset($house_name)): ?>
                <div class="member-info">
                    <h5><i class="fas fa-user-check me-2"></i>Joining as:</h5>
                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($member_name); ?></p>
                    <p class="mb-1"><strong>House:</strong> <?php echo htmlspecialchars($house_name); ?></p>
                    <p class="mb-0"><strong>Role:</strong> Member</p>
                    <p class="mb-0"><small><i class="fas fa-info-circle me-1"></i>Link expires: 7 days from invitation</small></p>
                </div>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Choose Username *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   required minlength="3" maxlength="50"
                                   pattern="[a-zA-Z0-9_]+" title="Only letters, numbers and underscore allowed">
                        </div>
                        <div class="form-text">3-50 characters, letters, numbers and underscore only</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="form-text">Will be used for notifications and password recovery</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       required minlength="6">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" required minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        After creating your account, you'll be automatically logged in and redirected to the member dashboard.
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-join btn-lg">
                            <i class="fas fa-user-check me-2"></i>Create Member Account
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4">
                    <a href="../index.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
                <?php elseif (!$success && !$show_form): ?>
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h4>Invalid Join Link</h4>
                    <p class="mb-4">The join link is invalid or has expired. Please contact your manager for a new invitation link.</p>
                    <a href="../index.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i>Go to Home Page
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password validation
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords do not match");
                } else {
                    confirmPassword.setCustomValidity("");
                }
            }
            
            password.addEventListener('change', validatePasswords);
            confirmPassword.addEventListener('keyup', validatePasswords);
        });
    </script>
</body>
</html>