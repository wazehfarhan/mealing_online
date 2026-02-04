<?php
session_start();
require_once '../config/database.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'manager') {
        header("Location: ../manager/dashboard.php");
    } else {
        header("Location: ../member/dashboard.php");
    }
    exit();
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token invalid. Please try again.";
    } else {
        if (isset($_POST['role']) && $_POST['role'] === 'manager') {
            header("Location: register.php");
            exit();
        } elseif (isset($_POST['action']) && $_POST['action'] === 'join') {
            header("Location: ../member/join.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="description" content="Choose your role - Meal Management System">
    <title>Choose Role - Meal Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
            --manager-color: #3498db;
            --member-color: #27ae60;
            --light-bg: #f8f9fa;
            --card-shadow: 0 20px 60px rgba(0,0,0,0.25);
            --mobile-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        * {
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
                display: block;
            }
        }
        
        .role-container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .role-card {
            background: white;
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        @media (max-width: 768px) {
            .role-card {
                border-radius: 16px;
                box-shadow: var(--mobile-shadow);
            }
        }
        
        .role-header {
            background: var(--secondary-gradient);
            color: white;
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        @media (max-width: 768px) {
            .role-header {
                padding: 30px 20px;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .role-header {
                padding: 40px 30px;
            }
        }
        
        .role-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100%;
            background: linear-gradient(transparent, rgba(0,0,0,0.1));
            pointer-events: none;
        }
        
        .role-header h1 {
            font-weight: 800;
            margin-bottom: 10px;
            font-size: 2.5rem;
            letter-spacing: -0.5px;
            position: relative;
            z-index: 1;
        }
        
        @media (max-width: 768px) {
            .role-header h1 {
                font-size: 1.8rem;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .role-header h1 {
                font-size: 2rem;
            }
        }
        
        .role-header p {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 0;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }
        
        @media (max-width: 768px) {
            .role-header p {
                font-size: 1rem;
            }
        }
        
        .role-body {
            padding: 50px 40px;
        }
        
        @media (max-width: 768px) {
            .role-body {
                padding: 30px 20px;
            }
        }
        
        @media (min-width: 769px) and (max-width: 1024px) {
            .role-body {
                padding: 40px 30px;
            }
        }
        
        .role-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        @media (max-width: 768px) {
            .role-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
        
        .role-option {
            border: 2px solid #e9ecef;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        @media (max-width: 768px) {
            .role-option {
                padding: 25px;
                border-radius: 16px;
            }
        }
        
        .role-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: transparent;
            transition: all 0.4s;
        }
        
        .role-option:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
        }
        
        @media (max-width: 768px) {
            .role-option:hover {
                transform: translateY(-4px);
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            }
        }
        
        .role-option.manager-option:hover {
            border-color: var(--manager-color);
        }
        
        .role-option.manager-option:hover::before {
            background: var(--manager-color);
        }
        
        .role-option.member-option:hover {
            border-color: var(--member-color);
        }
        
        .role-option.member-option:hover::before {
            background: var(--member-color);
        }
        
        .role-icon-wrapper {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, var(--light-bg), #fff);
            transition: all 0.4s;
        }
        
        @media (max-width: 768px) {
            .role-icon-wrapper {
                width: 80px;
                height: 80px;
                margin-bottom: 20px;
            }
        }
        
        .role-option:hover .role-icon-wrapper {
            transform: scale(1.1) rotate(5deg);
        }
        
        .role-option.manager-option .role-icon-wrapper {
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.2);
        }
        
        .role-option.member-option .role-icon-wrapper {
            box-shadow: 0 10px 30px rgba(39, 174, 96, 0.2);
        }
        
        .role-icon {
            font-size: 3.5rem;
            transition: all 0.4s;
        }
        
        @media (max-width: 768px) {
            .role-icon {
                font-size: 2.5rem;
            }
        }
        
        .role-option:hover .role-icon {
            transform: scale(1.1);
        }
        
        .role-icon.manager-icon {
            color: var(--manager-color);
            background: linear-gradient(135deg, var(--manager-color), #2980b9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .role-icon.member-icon {
            color: var(--member-color);
            background: linear-gradient(135deg, var(--member-color), #219653);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .role-option h3 {
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.75rem;
            color: #2c3e50;
        }
        
        @media (max-width: 768px) {
            .role-option h3 {
                font-size: 1.4rem;
            }
        }
        
        .role-option p {
            color: #6c757d;
            margin-bottom: 25px;
            font-size: 1.05rem;
            line-height: 1.6;
            flex-grow: 1;
        }
        
        @media (max-width: 768px) {
            .role-option p {
                font-size: 0.95rem;
                margin-bottom: 20px;
            }
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 25px 0;
            text-align: left;
            flex-grow: 1;
        }
        
        @media (max-width: 768px) {
            .feature-list {
                margin: 20px 0;
            }
        }
        
        .feature-list li {
            padding: 8px 0;
            padding-left: 32px;
            position: relative;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        @media (max-width: 768px) {
            .feature-list li {
                padding-left: 28px;
                font-size: 0.9rem;
                padding: 6px 0;
            }
        }
        
        .feature-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            top: 8px;
            width: 22px;
            height: 22px;
            background: #27ae60;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .btn-role {
            padding: 14px 35px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.4s;
            width: 100%;
            margin-top: auto;
            position: relative;
            overflow: hidden;
            border: none;
            font-size: 1.1rem;
            min-height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .btn-role {
                padding: 12px 25px;
                min-height: 48px;
                font-size: 1rem;
                border-radius: 10px;
            }
        }
        
        .btn-role::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .btn-role:active::after {
            animation: ripple 0.6s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(40, 40);
                opacity: 0;
            }
        }
        
        .btn-role:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .btn-role:active {
            transform: translateY(0);
        }
        
        .btn-manager {
            background: linear-gradient(135deg, var(--manager-color), #2980b9);
            color: white;
        }
        
        .btn-member {
            background: linear-gradient(135deg, var(--member-color), #219653);
            color: white;
        }
        
        .role-note {
            background: var(--light-bg);
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 20px;
            border-left: 4px solid #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .role-note {
                padding: 12px 16px;
                margin-top: 15px;
            }
        }
        
        .role-separator {
            display: flex;
            align-items: center;
            margin: 50px 0;
            color: #6c757d;
            position: relative;
        }
        
        @media (max-width: 768px) {
            .role-separator {
                margin: 30px 0;
            }
        }
        
        .role-separator::before,
        .role-separator::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, #dee2e6, transparent);
        }
        
        .role-separator span {
            padding: 0 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .quick-actions {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 35px;
            margin-top: 40px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        @media (max-width: 768px) {
            .quick-actions {
                padding: 25px 20px;
                margin-top: 30px;
                border-radius: 16px;
            }
        }
        
        .quick-actions h5 {
            font-weight: 700;
            margin-bottom: 15px;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }
        
        .quick-actions h5 i {
            margin-right: 10px;
            width: 24px;
        }
        
        .quick-actions p {
            color: #6c757d;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .quick-actions .btn {
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .quick-actions .btn {
                min-height: 44px;
                font-size: 0.95rem;
            }
        }
        
        .step-guide {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 35px;
            margin-top: 30px;
            border-left: 4px solid var(--manager-color);
            animation: slideIn 0.5s ease-out;
            display: none;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .step-guide {
                padding: 25px 20px;
                border-radius: 16px;
                margin-top: 25px;
            }
        }
        
        .step-guide h5 {
            font-weight: 700;
            margin-bottom: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
        }
        
        .step-guide h5 i {
            margin-right: 10px;
            color: var(--manager-color);
        }
        
        .step-guide ol {
            padding-left: 20px;
            margin-bottom: 0;
        }
        
        .step-guide ol li {
            padding: 8px 0;
            line-height: 1.6;
        }
        
        .step-guide .alert {
            border-radius: 12px;
            border: none;
            margin-top: 20px;
            padding: 16px 20px;
            background: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--manager-color);
        }
        
        @media (max-width: 768px) {
            .step-guide .alert {
                padding: 14px 16px;
                margin-top: 15px;
            }
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            padding: 0 20px;
        }
        
        @media (max-width: 768px) {
            .footer {
                margin-top: 30px;
                font-size: 0.85rem;
            }
        }
        
        /* Landscape mode adjustments */
        @media screen and (max-height: 700px) and (orientation: landscape) {
            .role-container {
                margin: 20px auto;
            }
            
            .role-header {
                padding: 25px 30px;
            }
            
            .role-body {
                padding: 30px;
            }
        }
        
        /* Small mobile devices */
        @media screen and (max-width: 360px) {
            .role-option {
                padding: 20px 15px;
            }
            
            .role-icon-wrapper {
                width: 70px;
                height: 70px;
            }
            
            .role-icon {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="role-container">
        <div class="role-card">
            <div class="role-header">
                <h1><i class="fas fa-utensils me-2"></i>Meal Management System</h1>
                <p class="lead mb-0">Choose how you want to join our community</p>
            </div>
            
            <div class="role-body">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="role-grid">
                    <!-- Manager Option -->
                    <div class="role-option manager-option" role="button" tabindex="0" 
                         onclick="document.getElementById('managerForm').submit()"
                         onkeypress="if(event.key === 'Enter') document.getElementById('managerForm').submit()">
                        <div class="role-icon-wrapper">
                            <div class="role-icon manager-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        </div>
                        
                        <h3>Create as Manager</h3>
                        <p class="text-muted">Start a new meal management system for your house or organization.</p>
                        
                        <ul class="feature-list">
                            <li>Create and manage your own house</li>
                            <li>Add and manage members</li>
                            <li>Track daily meals and expenses</li>
                            <li>Record member deposits</li>
                            <li>Generate detailed monthly reports</li>
                            <li>Calculate individual balances</li>
                        </ul>
                        
                        <form method="POST" action="" id="managerForm" style="display: none;">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="role" value="manager">
                        </form>
                        
                        <button type="button" onclick="document.getElementById('managerForm').submit()" 
                                class="btn btn-manager btn-role">
                            <i class="fas fa-plus-circle me-2"></i>Create New House
                        </button>
                        
                        <div class="role-note">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Ideal for house owners, mess managers, or team leaders
                            </small>
                        </div>
                    </div>
                    
                    <!-- Member Option -->
                    <div class="role-option member-option" role="button" tabindex="0"
                         onclick="document.getElementById('memberForm').submit()"
                         onkeypress="if(event.key === 'Enter') document.getElementById('memberForm').submit()">
                        <div class="role-icon-wrapper">
                            <div class="role-icon member-icon">
                                <i class="fas fa-user-friends"></i>
                            </div>
                        </div>
                        
                        <h3>Join as Member</h3>
                        <p class="text-muted">Join an existing meal system using an invitation link.</p>
                        
                        <ul class="feature-list">
                            <li>Join existing house using invitation link</li>
                            <li>View your personal meal records</li>
                            <li>Check your deposits and balance</li>
                            <li>See monthly expense reports</li>
                            <li>Track your monthly calculations</li>
                            <li>Update your personal profile</li>
                        </ul>
                        
                        <form method="POST" action="" id="memberForm" style="display: none;">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="join">
                        </form>
                        
                        <button type="button" onclick="document.getElementById('memberForm').submit()" 
                                class="btn btn-member btn-role">
                            <i class="fas fa-sign-in-alt me-2"></i>Join with Invitation Link
                        </button>
                        
                        <div class="role-note">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                You need an invitation link from your house manager
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="role-separator">
                    <span>Already have an account?</span>
                </div>
                
                <div class="quick-actions">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h5><i class="fas fa-sign-in-alt"></i>Existing Users</h5>
                            <p class="text-muted">If you already have an account, login to access your dashboard.</p>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Your Account
                            </a>
                        </div>
                        <div class="col-lg-6">
                            <h5><i class="fas fa-question-circle"></i>Need Help?</h5>
                            <p class="text-muted">Contact your house manager or check our documentation.</p>
                            <div class="d-grid">
                                <button class="btn btn-outline-secondary" onclick="toggleHelpGuide()">
                                    <i class="fas fa-question me-2"></i>How It Works
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="step-guide" id="helpGuide">
                    <h5><i class="fas fa-lightbulb"></i>How to Join as a Member</h5>
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
        
        <div class="footer">
            <p class="mb-0">
                <i class="fas fa-copyright me-1"></i>
                <?php echo date('Y'); ?> Meal Management System. All rights reserved.
            </p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile detection
        const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
        
        // Toggle help guide
        function toggleHelpGuide() {
            const guide = document.getElementById('helpGuide');
            if (guide.style.display === 'block') {
                guide.style.display = 'none';
            } else {
                guide.style.display = 'block';
                setTimeout(() => {
                    guide.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            }
        }
        
        // Initialize tooltips for mobile
        if (isMobile) {
            document.addEventListener('DOMContentLoaded', function() {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        }
        
        // Touch feedback for role options
        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('touchstart', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            option.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
        
        // Better hover effects for non-touch devices
        if (!isMobile) {
            document.querySelectorAll('.role-option').forEach(option => {
                option.addEventListener('mouseenter', function() {
                    const icon = this.querySelector('.role-icon i');
                    if (icon) {
                        icon.style.transform = 'scale(1.15) rotate(5deg)';
                    }
                });
                
                option.addEventListener('mouseleave', function() {
                    const icon = this.querySelector('.role-icon i');
                    if (icon) {
                        icon.style.transform = '';
                    }
                });
            });
        }
        
        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Focus management for accessibility
        document.addEventListener('DOMContentLoaded', function() {
            // Set focus to first role option for keyboard users
            const firstRoleOption = document.querySelector('.role-option');
            if (firstRoleOption && !isMobile) {
                firstRoleOption.focus();
            }
            
            // Auto-hide alert after 5 seconds
            setTimeout(() => {
                const alert = document.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        });
        
        // Handle keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('helpGuide').style.display = 'none';
            }
        });
    </script>
</body>
</html>