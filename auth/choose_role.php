<?php
session_start();
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['role']) && $_POST['role'] === 'manager') {
        header("Location: register.php");
        exit();
    } elseif (isset($_POST['action']) && $_POST['action'] === 'join') {
        header("Location: ../member/join.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Role - Meal Management System</title>
    <link rel="icon" type="image/png" href="../image/icon.png">
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
            max-width: 900px;
            margin: 0 auto;
        }
        .role-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .role-header {
            background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .role-body {
            padding: 40px;
        }
        .role-option {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            height: 100%;
            transition: all 0.4s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .role-option:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: transparent;
            transition: all 0.3s;
        }
        .role-option:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .role-option.manager-option:hover {
            border-color: #3498db;
            background: linear-gradient(to bottom, rgba(52, 152, 219, 0.03), transparent);
        }
        .role-option.manager-option:hover:before {
            background: #3498db;
        }
        .role-option.member-option:hover {
            border-color: #27ae60;
            background: linear-gradient(to bottom, rgba(39, 174, 96, 0.03), transparent);
        }
        .role-option.member-option:hover:before {
            background: #27ae60;
        }
        .role-icon {
            font-size: 4rem;
            margin-bottom: 25px;
            transition: transform 0.3s;
        }
        .role-option:hover .role-icon {
            transform: scale(1.1);
        }
        .role-icon.manager-icon {
            color: #3498db;
        }
        .role-icon.member-icon {
            color: #27ae60;
        }
        .btn-role {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 50px;
            transition: all 0.3s;
            width: 100%;
            margin-top: 25px;
            position: relative;
            overflow: hidden;
        }
        .btn-role:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
        }
        .btn-role:hover:before {
            left: 100%;
        }
        .btn-role:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            text-align: left;
        }
        .feature-list li {
            padding: 5px 0;
            padding-left: 30px;
            position: relative;
        }
        .feature-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #27ae60;
            font-weight: bold;
        }
        .role-separator {
            display: flex;
            align-items: center;
            margin: 40px 0;
            color: #6c757d;
        }
        .role-separator:before,
        .role-separator:after {
            content: '';
            flex: 1;
            height: 1px;
            background: #dee2e6;
        }
        .role-separator span {
            padding: 0 15px;
        }
        .quick-actions {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
        }
        .step-guide {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
            border-left: 4px solid #3498db;
        }
    </style>
</head>
<body>
    <div class="role-container">
        <div class="role-card">
            <div class="role-header">
                <h1><i class="fas fa-utensils me-2"></i>Meal Management System</h1>
                <p class="lead mb-0">Join our community for better meal management</p>
            </div>
            
            <div class="role-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="role-option manager-option">
                            <div class="role-icon manager-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h3>Create as Manager</h3>
                            <p class="text-muted mb-4">Start a new meal management system for your house or organization.</p>
                            
                            <ul class="feature-list">
                                <li>Create and manage your own house</li>
                                <li>Add and manage members</li>
                                <li>Track daily meals and expenses</li>
                                <li>Record member deposits</li>
                                <li>Generate detailed monthly reports</li>
                                <li>Calculate individual balances</li>
                            </ul>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="role" value="manager">
                                <button type="submit" class="btn btn-primary btn-role">
                                    <i class="fas fa-plus-circle me-2"></i>Create New House
                                </button>
                            </form>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Ideal for house owners, mess managers, or team leaders
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="role-option member-option">
                            <div class="role-icon member-icon">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <h3>Join as Member</h3>
                            <p class="text-muted mb-4">Join an existing meal system using an invitation link.</p>
                            
                            <ul class="feature-list">
                                <li>Join existing house using invitation link</li>
                                <li>View your personal meal records</li>
                                <li>Check your deposits and balance</li>
                                <li>See monthly expense reports</li>
                                <li>Track your monthly calculations</li>
                                <li>Update your personal profile</li>
                            </ul>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="join">
                                <button type="submit" class="btn btn-success btn-role">
                                    <i class="fas fa-sign-in-alt me-2"></i>Join with Invitation Link
                                </button>
                            </form>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    You need an invitation link from your house manager
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="role-separator">
                    <span>Already have an account?</span>
                </div>
                
                <div class="quick-actions">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-sign-in-alt me-2"></i>Existing Users</h5>
                            <p class="text-muted mb-3">If you already have an account, login to access your dashboard.</p>
                            <a href="login.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Your Account
                            </a>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-question-circle me-2"></i>Need Help?</h5>
                            <p class="text-muted mb-3">Contact your house manager or check our documentation.</p>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-secondary" onclick="showHelp()">
                                    <i class="fas fa-question me-2"></i>How It Works
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="step-guide" id="helpGuide" style="display: none;">
                    <h5><i class="fas fa-lightbulb me-2"></i>How to Join as a Member</h5>
                    <ol class="mt-3">
                        <li><strong>Get Invitation Link:</strong> Ask your house manager for an invitation link</li>
                        <li><strong>Click the Link:</strong> The link will take you to the registration page</li>
                        <li><strong>Enter Token:</strong> If you have just the token, go to Join page and enter it</li>
                        <li><strong>Create Account:</strong> Fill in your username, email, and password</li>
                        <li><strong>Access Dashboard:</strong> Once registered, you'll access your member dashboard</li>
                    </ol>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Note:</strong> If you don't have an invitation link, you must contact your house manager to get one.
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4 text-white">
            <p class="mb-0">
                <i class="fas fa-copyright me-1"></i>
                <?php echo date('Y'); ?> Meal Management System. All rights reserved.
            </p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showHelp() {
            const helpGuide = document.getElementById('helpGuide');
            if (helpGuide.style.display === 'none') {
                helpGuide.style.display = 'block';
                setTimeout(() => {
                    helpGuide.scrollIntoView({ behavior: 'smooth' });
                }, 100);
            } else {
                helpGuide.style.display = 'none';
            }
        }
        
        // Add click effect to role options
        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('click', function(e) {
                // Don't trigger if clicking on form elements
                if (!e.target.closest('form') && !e.target.closest('a')) {
                    const form = this.querySelector('form');
                    if (form) {
                        form.submit();
                    }
                }
            });
            
            // Add keyboard support
            option.setAttribute('tabindex', '0');
            option.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    const form = this.querySelector('form');
                    if (form) {
                        form.submit();
                    }
                }
            });
        });
        
        // Animate role icons on hover
        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('mouseenter', function() {
                const icon = this.querySelector('.role-icon i');
                if (icon) {
                    icon.style.transform = 'scale(1.2)';
                    icon.style.transition = 'transform 0.3s ease';
                }
            });
            
            option.addEventListener('mouseleave', function() {
                const icon = this.querySelector('.role-icon i');
                if (icon) {
                    icon.style.transform = 'scale(1)';
                }
            });
        });
        
        // Focus management for accessibility
        document.addEventListener('DOMContentLoaded', function() {
            const firstRoleOption = document.querySelector('.role-option');
            if (firstRoleOption) {
                firstRoleOption.focus();
            }
        });
    </script>
</body>
</html>