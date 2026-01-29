<?php
require_once '../config/database.php';

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'manager') {
        header("Location: ../manager/dashboard.php");
    } else {
        header("Location: ../member/dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Role - Meal Management System</title>
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
        .role-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .role-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        .role-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .role-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            height: 100%;
            transition: all 0.3s;
            cursor: pointer;
        }
        .role-option:hover {
            border-color: #3498db;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.1);
        }
        .role-icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
        }
        .btn-role {
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
            margin-top: 20px;
        }
        .btn-role:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="role-container">
        <div class="role-card">
            <div class="role-header">
                <h2><i class="fas fa-user-plus me-2"></i>Join Meal Management System</h2>
                <p class="text-muted">Select your role to continue</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="role-option">
                        <div class="role-icon text-primary">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h4>Manager</h4>
                        <p class="text-muted">Create and manage meal system, add members, track expenses, and generate reports.</p>
                        <p><small class="text-success"><i class="fas fa-check-circle me-1"></i>Full system control</small></p>
                        <p><small class="text-success"><i class="fas fa-check-circle me-1"></i>Add/Edit members</small></p>
                        <p><small class="text-success"><i class="fas fa-check-circle me-1"></i>Generate reports</small></p>
                        <form method="POST" action="register.php">
                            <input type="hidden" name="role" value="manager">
                            <button type="submit" class="btn btn-primary btn-role">
                                <i class="fas fa-user-plus me-2"></i>Register as Manager
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="role-option">
                        <div class="role-icon text-success">
                            <i class="fas fa-user"></i>
                        </div>
                        <h4>Member</h4>
                        <p class="text-muted">Join existing meal system, view your meals, check balance, and see monthly reports.</p>
                        <p><small class="text-success"><i class="fas fa-check-circle me-1"></i>View personal meals</small></p>
                        <p><small class="text-success"><i class="fas fa-check-circle me-1"></i>Check balance</small></p>
                        <p><small class="text-success"><i class="fas fa-check-circle me-1"></i>Monthly reports</small></p>
                        <form method="POST" action="join.php">
                            <input type="hidden" name="action" value="join">
                            <button type="submit" class="btn btn-success btn-role">
                                <i class="fas fa-sign-in-alt me-2"></i>Join as Member
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <p class="mb-3">Already have an account?</p>
                <a href="login.php" class="btn btn-outline-secondary">
                    <i class="fas fa-sign-in-alt me-2"></i>Login Here
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>