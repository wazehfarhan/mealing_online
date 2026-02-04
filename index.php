<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$functions = new Functions();
$page_title = "Meal Management System - Free Meal Tracking Solution";

// Check if database needs setup
$missing_tables = checkDatabaseTables();
if (!empty($missing_tables) && basename($_SERVER['PHP_SELF']) !== 'setup.php') {
    header("Location: setup.php");
    exit();
}

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'manager') {
        header("Location: manager/dashboard.php");
    } else {
        header("Location: member/dashboard.php");
    }
    exit();
}

// Get initial statistics
$stats = $functions->getSystemStats();
$developerInfo = $functions->getDeveloperInfo();

// Helper function to assign color classes to skills
function getSkillColorClass($skill) {
    $colors = [
        'C' => 'bg-c', 'C++' => 'bg-cpp', 'Java' => 'bg-java', 
        'C#' => 'bg-csharp', 'JavaScript' => 'bg-js', 'SQL' => 'bg-sql',
        'HTML' => 'bg-html', 'CSS' => 'bg-css', 'PHP' => 'bg-php',
        '.NET' => 'bg-dotnet', 'MySQL' => 'bg-mysql',
        'OOP' => 'bg-oop', 'Data Structures' => 'bg-ds',
        'Algorithms' => 'bg-algo', 'Database Management' => 'bg-db',
        'CRUD Operations' => 'bg-crud',
        'Full-Stack Web Development' => 'bg-fullstack',
        'AWS Cloud Engineering' => 'bg-aws',
        'AI & Machine Learning' => 'bg-ai'
    ];
    
    return $colors[$skill] ?? 'bg-secondary';
}

// Format money
$total_money = $stats['total_money_managed'] ?? 0;
if ($total_money >= 1000000) {
    $total_money_formatted = number_format($total_money / 1000000, 1) . 'M';
} elseif ($total_money >= 1000) {
    $total_money_formatted = number_format($total_money / 1000, 1) . 'K';
} else {
    $total_money_formatted = number_format($total_money, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Management System - Free Meal Tracking Solution</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        :root {
            --primary: #4e73df;
            --secondary: #6c757d;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --dark: #5a5c69;
            --light: #f8f9fc;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            overflow-x: hidden;
            color: #333;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #2e59d9;
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            padding: 15px 0;
        }
        
        .navbar.scrolled {
            padding: 10px 0;
            background: rgba(255, 255, 255, 0.98);
        }
        
        .hero-section {
            padding: 160px 0 100px;
            background: linear-gradient(135deg, 
                rgba(78, 115, 223, 0.1) 0%, 
                rgba(28, 200, 138, 0.1) 50%, 
                rgba(54, 185, 204, 0.1) 100%);
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(78, 115, 223, 0.05) 0%, transparent 70%);
            animation: float 20s infinite linear;
        }
        
        @keyframes float {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* FIXED: Typewriter effect */
        .typewriter-container {
            display: inline-block;
            position: relative;
        }
        
        .typewriter-text {
            display: inline-block;
            overflow: hidden;
            border-right: 3px solid var(--primary);
            white-space: nowrap;
            margin: 0;
            letter-spacing: .15em;
            animation: typing 3.5s steps(40, end), blink-caret .75s step-end infinite;
        }
        
        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }
        
        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: var(--primary); }
        }
        
        /* Profile image styles */
        .profile-image-container {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary);
            background: linear-gradient(135deg, var(--primary), var(--info));
        }
        
        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .profile-image-container:hover .profile-img {
            transform: scale(1.1);
        }
        
        /* Fallback for missing images */
        .profile-img[src*="ui-avatars.com"] {
            object-fit: contain;
            padding: 10px;
        }
        
        /* Mobile responsiveness for profile image */
        @media (max-width: 768px) {
            .profile-image-container {
                width: 60px;
                height: 60px;
            }
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
            margin-bottom: 25px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--success));
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 10px;
            background: linear-gradient(90deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            color: var(--dark);
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .live-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: var(--success);
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { 
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(28, 200, 138, 0.7);
            }
            70% { 
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(28, 200, 138, 0);
            }
            100% { 
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(28, 200, 138, 0);
            }
        }
        
        .refresh-btn {
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            background: transparent;
            color: var(--primary);
        }
        
        .refresh-btn:hover {
            transform: rotate(180deg);
            color: var(--info);
        }
        
        .refresh-btn.loading {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Developer Section Improvements */
        .developer-section {
            background: linear-gradient(135deg, 
                rgba(33, 37, 41, 1) 0%, 
                rgba(52, 58, 64, 0.98) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .developer-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(78, 115, 223, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(28, 200, 138, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .developer-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: all 0.3s ease;
            height: 100%;
            color: #fff;
        }

        .developer-card:hover {
            transform: translateY(-5px);
            border-color: rgba(78, 115, 223, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        /* FIXED: Developer card text colors */
        .developer-card .text-dark {
            color: #f8f9fa !important;
        }
        
        .developer-card .text-muted {
            color: rgba(255, 255, 255, 0.7) !important;
        }
        
        /* FIXED: Project portfolio title visibility */
        .developer-card h4,
        .developer-card h5,
        .developer-card h6:not(.text-info) {
            color: white !important;
        }
        
        /* FIXED: Project card text in dark section */
        .developer-section .project-card h6 {
            color: #212529 !important;
        }
        
        .developer-section .project-card .text-muted {
            color: #6c757d !important;
        }

        /* Skill Badge Colors - FIXED FOR VISIBILITY */
        .skill-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white !important;
            transition: all 0.3s ease;
            border: none;
            display: inline-block;
            margin: 3px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .skill-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Specific skill colors - IMPROVED FOR VISIBILITY */
        .bg-c { background: linear-gradient(45deg, #A8B9CC, #6495ED); }
        .bg-cpp { background: linear-gradient(45deg, #00599C, #659AD2); }
        .bg-java { background: linear-gradient(45deg, #007396, #ED8B00); }
        .bg-csharp { background: linear-gradient(45deg, #239120, #68217A); }
        .bg-js { 
            background: linear-gradient(45deg, #F7DF1E, #F0DB4F); 
            color: #000 !important; 
            text-shadow: 0 1px 1px rgba(255,255,255,0.3);
        }
        .bg-sql { background: linear-gradient(45deg, #00758F, #F29111); }
        .bg-html { background: linear-gradient(45deg, #E34F26, #F06529); }
        .bg-css { background: linear-gradient(45deg, #1572B6, #33A9DC); }
        .bg-php { background: linear-gradient(45deg, #777BB4, #8993BE); }
        .bg-dotnet { background: linear-gradient(45deg, #512BD4, #68217A); }
        .bg-mysql { background: linear-gradient(45deg, #4479A1, #F29111); }
        .bg-oop { background: linear-gradient(45deg, #4CAF50, #8BC34A); }
        .bg-ds { background: linear-gradient(45deg, #FF5722, #FF9800); }
        .bg-algo { background: linear-gradient(45deg, #2196F3, #03A9F4); }
        .bg-db { background: linear-gradient(45deg, #673AB7, #9575CD); }
        .bg-crud { background: linear-gradient(45deg, #009688, #4DB6AC); }
        .bg-fullstack { background: linear-gradient(45deg, #FF6B6B, #4ECDC4); }
        .bg-aws { background: linear-gradient(45deg, #FF9900, #232F3E); }
        .bg-ai { background: linear-gradient(45deg, #FF4081, #7B1FA2); }

        /* Project Cards */
        .project-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            cursor: pointer;
            padding: 15px;
            margin-bottom: 10px;
        }

        .project-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            background: white;
        }

        .project-card h6 {
            color: #2c3e50 !important;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }

        .project-card .text-muted {
            color: #6c757d !important;
            line-height: 1.4;
            font-size: 0.85rem;
        }

        /* Quote Box */
        .quote-box {
            background: linear-gradient(135deg, 
                rgba(78, 115, 223, 0.2), 
                rgba(28, 200, 138, 0.2));
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            position: relative;
            padding: 20px;
            margin-top: 20px;
        }

        .quote-box::before {
            content: '"';
            position: absolute;
            top: -15px;
            left: 15px;
            font-size: 60px;
            opacity: 0.3;
            font-family: serif;
            color: white;
        }

        /* Contact Info */
        .bg-dark {
            background: rgba(0, 0, 0, 0.2) !important;
            border-radius: 8px;
        }

        .bg-dark p {
            color: rgba(255, 255, 255, 0.9) !important;
        }

        /* Badge colors for project domains */
        .badge {
            padding: 8px 12px;
            font-weight: 500;
            border-radius: 20px;
            margin: 2px;
            color: white !important;
        }

        /* Improved text visibility */
        .text-light {
            color: rgba(255, 255, 255, 0.9) !important;
        }

        .text-info {
            color: #4dc0ff !important;
        }

        /* Section headers */
        h4.text-white, h5.text-white, h6.text-white {
            color: white !important;
            font-weight: 600;
        }

        /* Gradient background for profile icon */
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary), var(--info));
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            display: inline-block;
            padding: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(78, 115, 223, 0.1), rgba(28, 200, 138, 0.1));
        }
        
        .floating-element {
            animation: floatElement 3s ease-in-out infinite;
        }
        
        @keyframes floatElement {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .gradient-text {
            background: linear-gradient(90deg, var(--primary), var(--success), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Social icons */
        .social-btn {
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Professional improvements */
        .hero-headline {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .hero-headline {
                font-size: 2.5rem;
            }
            
            .hero-section {
                padding: 120px 0 80px;
            }
            
            .typewriter-text {
                white-space: normal;
                border-right: none;
                animation: none;
            }
        }
        
        /* Professional card shadows */
        .professional-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 
                        0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .professional-shadow-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 
                        0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Smooth transitions */
        .smooth-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Improved button styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #2e59d9);
            border: none;
            padding: 12px 28px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2e59d9, var(--primary));
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(78, 115, 223, 0.3);
        }
        
        /* Enhanced footer */
        footer {
            background: linear-gradient(135deg, #2c3e50, #34495e) !important;
        }
        
        /* Statistics counter animation */
        .counter {
            display: inline-block;
            font-weight: 800;
        }
        
        /* Better mobile menu */
        .navbar-toggler {
            border: none;
            padding: 0.25rem 0.5rem;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        /* Ensure profile image doesn't stretch */
        .profile-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top professional-shadow" id="mainNav">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-egg-fried me-2"></i>MealMaster
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link smooth-transition" href="auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-2 px-4 smooth-transition" href="auth/register.php">Get Started</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-headline mb-4 animate__animated animate__fadeInUp">
                        <div class="typewriter-container">
                            <span class="typewriter-text gradient-text">Meal Management</span>
                        </div>
                        <div class="mt-2">Made Simple & Free</div>
                    </h1>
                    <p class="lead mb-4 animate__animated animate__fadeInUp animate__delay-1s">
                        Completely free solution for managing meals in hostels, messes, and shared houses.
                        Track expenses, manage members, and simplify meal planning.
                    </p>
                    <div class="d-flex flex-wrap gap-3 animate__animated animate__fadeInUp animate__delay-2s">
                        <a href="auth/register.php" class="btn btn-primary btn-lg px-4 smooth-transition">
                            <i class="bi bi-lightning me-2"></i>Start Free Now
                        </a>
                        <a href="#developer" class="btn btn-outline-dark btn-lg px-4 smooth-transition">
                            <i class="bi bi-person-badge me-2"></i>Meet Developer
                        </a>
                    </div>
                    <p class="text-muted mt-4 animate__animated animate__fadeInUp animate__delay-3s">
                        <i class="bi bi-arrow-up-right-circle me-2"></i>
                        Join <span class="fw-bold counter"><?php echo $stats['total_houses'] ?? 0; ?></span>+ houses and 
                        <span class="fw-bold counter"><?php echo $stats['total_members'] ?? 0; ?></span>+ members already using MealMaster
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative floating-element">
                        <div class="display-1 text-center mb-4">🍽️</div>
                        <div class="position-absolute top-0 start-0 animate__animated animate__bounceIn">
                            <div class="bg-white rounded-circle p-3 professional-shadow-lg smooth-transition">
                                <i class="bi bi-graph-up text-primary fs-3"></i>
                            </div>
                        </div>
                        <div class="position-absolute top-0 end-0 animate__animated animate__bounceIn animate__delay-1s">
                            <div class="bg-white rounded-circle p-3 professional-shadow-lg smooth-transition">
                                <i class="bi bi-calculator text-success fs-3"></i>
                            </div>
                        </div>
                        <div class="position-absolute bottom-0 start-50 translate-middle-x animate__animated animate__bounceIn animate__delay-2s">
                            <div class="bg-white rounded-circle p-3 professional-shadow-lg smooth-transition">
                                <i class="bi bi-people text-info fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3 gradient-text">Live System Statistics</h2>
                <p class="text-muted">
                    <span class="live-indicator"></span>
                    <span>Real-time Updates</span>
                    <button id="refreshAll" class="btn btn-sm btn-outline-primary ms-3 refresh-btn smooth-transition">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </p>
            </div>
            
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-card animate__animated animate__fadeInUp professional-shadow smooth-transition">
                        <div class="stat-number counter" id="stat-houses"><?php echo $stats['total_houses'] ?? 0; ?></div>
                        <div class="stat-label">Active Houses</div>
                        <small class="text-muted" id="stat-houses-time">Updated just now</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card animate__animated animate__fadeInUp animate__delay-1s professional-shadow smooth-transition">
                        <div class="stat-number counter" id="stat-members"><?php echo $stats['total_members'] ?? 0; ?></div>
                        <div class="stat-label">Happy Members</div>
                        <small class="text-muted" id="stat-members-time">Updated just now</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card animate__animated animate__fadeInUp animate__delay-2s professional-shadow smooth-transition">
                        <div class="stat-number counter" id="stat-meals"><?php echo number_format($stats['today_meals'] ?? 0, 1); ?></div>
                        <div class="stat-label">Meals Today</div>
                        <small class="text-muted" id="stat-meals-time">Updated just now</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card animate__animated animate__fadeInUp animate__delay-3s professional-shadow smooth-transition">
                        <div class="stat-number counter" id="stat-money">৳<?php echo $total_money_formatted; ?></div>
                        <div class="stat-label">Managed</div>
                        <small class="text-muted" id="stat-money-time">Updated just now</small>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p class="text-muted small">
                    Statistics update automatically. Last full update: <span id="lastUpdate"><?php echo date('h:i:s A'); ?></span>
                </p>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold gradient-text mb-3">Why Choose MealMaster?</h2>
                <p class="text-muted">Join thousands already managing their meals efficiently</p>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="text-center p-4 h-100 smooth-transition">
                        <div class="feature-icon text-success mb-4">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h5 class="fw-bold">100% Free Forever</h5>
                        <p>No hidden fees, no subscriptions, no credit card required. Built to help communities, not for profit.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center p-4 h-100 smooth-transition">
                        <div class="feature-icon text-warning mb-4">
                            <i class="bi bi-lightning-fill"></i>
                        </div>
                        <h5 class="fw-bold">Instant Setup</h5>
                        <p>Create your house and start tracking in under 2 minutes. No email verification or complex setup required.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center p-4 h-100 smooth-transition">
                        <div class="feature-icon text-primary mb-4">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5 class="fw-bold">Complete Privacy</h5>
                        <p>Your data stays private. We don't sell or share your information. Built with privacy-first principles.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Developer Section -->
    <section id="developer" class="developer-section py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-white mb-3 animate__animated animate__fadeInDown">Meet The Developer</h2>
                <p class="text-light mb-4 animate__animated animate__fadeInDown animate__delay-1s">Building scalable solutions with passion and precision</p>
                <div class="d-flex justify-content-center flex-wrap gap-3">
                    <a href="<?php echo $developerInfo['profiles']['github']; ?>" class="btn btn-outline-light btn-sm smooth-transition" target="_blank">
                        <i class="bi bi-github me-2"></i>GitHub
                    </a>
                    <a href="<?php echo $developerInfo['profiles']['linkedin']; ?>" class="btn btn-outline-light btn-sm smooth-transition" target="_blank">
                        <i class="bi bi-linkedin me-2"></i>LinkedIn
                    </a>
                    <button class="btn btn-outline-light btn-sm smooth-transition" onclick="showContactInfo()">
                        <i class="bi bi-envelope me-2"></i>Contact
                    </button>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Profile Card -->
                <div class="col-lg-4">
                    <div class="developer-card p-4 h-100 animate__animated animate__fadeInLeft smooth-transition">
                        <div class="d-flex align-items-center mb-4">
                            <div class="profile-image-container me-3">
                                <img src="image/farhan.png" alt="Farhan" class="profile-img" 
                                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($developerInfo['name']); ?>&background=4e73df&color=fff&size=70';">
                            </div>
                            <div>
                                <h4 class="text-white mb-1"><?php echo $developerInfo['name']; ?></h4>
                                <p class="text-light small mb-0"><?php echo $developerInfo['title']; ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="text-white mb-3"><i class="bi bi-person-badge me-2"></i>About Me</h5>
                            <p class="text-light" style="line-height: 1.6;">
                                <?php echo $developerInfo['bio']; ?> 
                                I enjoy solving real-world problems through clean, scalable, and efficient code.
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="text-white mb-3"><i class="bi bi-mortarboard me-2"></i>Education</h5>
                            <div class="bg-dark rounded p-3">
                                <h6 class="text-info mb-2"><?php echo $developerInfo['education']['degree']; ?></h6>
                                <p class="text-light small mb-1">
                                    <i class="bi bi-building me-1"></i>
                                    <?php echo $developerInfo['education']['university']; ?>
                                </p>
                                <p class="text-light small mb-1">
                                    <i class="bi bi-calendar me-1"></i>
                                    <?php echo $developerInfo['education']['semester']; ?>
                                </p>
                                <p class="text-light small">
                                    <i class="bi bi-id-card me-1"></i>
                                    ID: <?php echo $developerInfo['education']['student_id']; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="quote-box smooth-transition">
                            <i class="bi bi-quote text-white fs-1 opacity-25"></i>
                            <p class="text-white mb-0 fst-italic"><?php echo $developerInfo['quote']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Skills & Technologies -->
                <div class="col-lg-4">
                    <div class="developer-card p-4 h-100 animate__animated animate__fadeInUp smooth-transition">
                        <h4 class="text-white mb-4"><i class="bi bi-tools me-2"></i>Technical Stack</h4>
                        
                        <?php foreach($developerInfo['skills'] as $category => $skills): ?>
                            <div class="mb-4">
                                <h6 class="text-info mb-2">
                                    <i class="bi bi-chevron-right me-1"></i><?php echo $category; ?>
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php 
                                    $skillList = explode(', ', $skills);
                                    foreach($skillList as $skill): 
                                        $skill = trim($skill);
                                        $colorClass = getSkillColorClass($skill);
                                    ?>
                                        <span class="skill-badge <?php echo $colorClass; ?> smooth-transition">
                                            <?php echo $skill; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="mt-4">
                            <h5 class="text-white mb-3"><i class="bi bi-award me-2"></i>Career Vision</h5>
                            <div class="bg-dark rounded p-3">
                                <p class="text-light mb-0 small">
                                    To become a Professional Software Engineer specializing in 
                                    Full-Stack Web Development and Cloud Engineering, 
                                    contributing to impactful and scalable software solutions.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Projects Portfolio -->
                <div class="col-lg-4">
                    <div class="developer-card p-4 h-100 animate__animated animate__fadeInRight smooth-transition">
                        <h4 class="text-white mb-4">
                            <i class="bi bi-briefcase me-2"></i>Project Portfolio
                        </h4>
                        
                        <div class="row g-3" id="projectsContainer">
                            <?php 
                            $projectCount = 0;
                            foreach($developerInfo['projects'] as $project => $description): 
                                $projectCount++;
                                if($projectCount <= 6): // Show first 6 projects
                            ?>
                                <div class="col-12">
                                    <div class="project-card smooth-transition">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3">
                                                <i class="bi bi-folder text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-dark mb-1"><?php echo $project; ?></h6>
                                                <p class="text-muted small mb-0"><?php echo $description; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            
                            <?php if(count($developerInfo['projects']) > 6): ?>
                                <div class="col-12">
                                    <div class="text-center">
                                        <button class="btn btn-outline-light btn-sm smooth-transition" onclick="showAllProjects()">
                                            <i class="bi bi-three-dots me-1"></i>
                                            View <?php echo count($developerInfo['projects']) - 6; ?> More Projects
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4">
                            <h5 class="text-white mb-3"><i class="bi bi-building me-2"></i>Project Domains</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-primary smooth-transition">Desktop Applications</span>
                                <span class="badge bg-success smooth-transition">Web Development</span>
                                <span class="badge bg-info smooth-transition">Database Systems</span>
                                <span class="badge bg-warning smooth-transition">Management Systems</span>
                                <span class="badge bg-danger smooth-transition">CRUD Operations</span>
                                <span class="badge bg-secondary smooth-transition">Agriculture Tech</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Info Row -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="developer-card p-4 animate__animated animate__fadeInUp animate__delay-1s smooth-transition">
                        <div class="row align-items-center">
                            <div class="col-md-4 text-center mb-3 mb-md-0">
                                <h5 class="text-white mb-3"><i class="bi bi-geo-alt me-2"></i>Location</h5>
                                <p class="text-light mb-0">
                                    <i class="bi bi-geo-fill me-2"></i>
                                    <?php echo $developerInfo['contact']['location']; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-center mb-3 mb-md-0">
                                <h5 class="text-white mb-3"><i class="bi bi-envelope me-2"></i>Email</h5>
                                <p class="text-light mb-0">
                                    <i class="bi bi-envelope-fill me-2"></i>
                                    <?php echo $developerInfo['contact']['email']; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5 class="text-white mb-3"><i class="bi bi-telephone me-2"></i>Phone</h5>
                                <p class="text-light mb-0">
                                    <i class="bi bi-telephone-fill me-2"></i>
                                    <?php echo $developerInfo['contact']['phone']; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3 text-white">
                        <i class="bi bi-egg-fried me-2"></i>MealMaster
                    </h5>
                    <p class="text-light">
                        Free meal management system for hostels, messes, and shared houses.
                        Built to solve real-world problems with modern web technologies.
                    </p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3 text-white">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="auth/login.php" class="text-white text-decoration-none smooth-transition">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="auth/register.php" class="text-white text-decoration-none smooth-transition">
                                <i class="bi bi-person-plus me-2"></i>Register
                            </a>
                        </li>
                        <li>
                            <a href="#developer" class="text-white text-decoration-none smooth-transition">
                                <i class="bi bi-code-slash me-2"></i>About Developer
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3 text-white">Technologies Used</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary smooth-transition">PHP</span>
                        <span class="badge bg-success smooth-transition">MySQL</span>
                        <span class="badge bg-info smooth-transition">Bootstrap 5</span>
                        <span class="badge bg-warning smooth-transition">JavaScript</span>
                        <span class="badge bg-danger smooth-transition">HTML5</span>
                        <span class="badge bg-secondary smooth-transition">CSS3</span>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4 pt-4 border-top border-secondary">
                <p class="text-light small mb-0">
                    © <?php echo date('Y'); ?> MealMaster - Developed by <?php echo $developerInfo['name']; ?> | 
                    Version 1.0
                </p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript for animations and interactions -->
    <script>
        // Format numbers
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        // Format money
        function formatMoney(amount) {
            if (amount >= 1000000) {
                return '৳' + (amount / 1000000).toFixed(1) + 'M';
            } else if (amount >= 1000) {
                return '৳' + (amount / 1000).toFixed(1) + 'K';
            } else {
                return '৳' + formatNumber(amount.toFixed(2));
            }
        }
        
        // Format meals
        function formatMeals(meals) {
            return meals % 1 === 0 ? meals.toFixed(0) : meals.toFixed(1);
        }
        
        // Update a single statistic
        function updateStat(elementId, newValue, type = 'number') {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const currentValue = parseFloat(element.textContent.replace(/[^0-9.]/g, ''));
            const newValueNum = parseFloat(newValue);
            
            if (currentValue === newValueNum) return;
            
            let start = currentValue;
            const end = newValueNum;
            const duration = 1000;
            const startTime = performance.now();
            
            function animate(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const current = start + (end - start) * progress;
                
                if (type === 'money') {
                    element.textContent = formatMoney(current);
                } else if (type === 'meals') {
                    element.textContent = formatMeals(current);
                } else {
                    element.textContent = Math.round(current);
                }
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            }
            
            requestAnimationFrame(animate);
        }
        
        // Update timestamp
        function updateTimestamp(statId) {
            const timeElement = document.getElementById(statId + '-time');
            if (timeElement) {
                const now = new Date();
                timeElement.textContent = 'Updated ' + now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }
        }
        
        // Fetch updated statistics
        async function fetchStats() {
            try {
                const refreshBtn = document.getElementById('refreshAll');
                refreshBtn.classList.add('loading');
                
                const response = await fetch('api/get_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    updateStat('stat-houses', data.houses, 'number');
                    updateStat('stat-members', data.members, 'number');
                    updateStat('stat-meals', data.meals, 'meals');
                    updateStat('stat-money', data.money, 'money');
                    
                    updateTimestamp('stat-houses');
                    updateTimestamp('stat-members');
                    updateTimestamp('stat-meals');
                    updateTimestamp('stat-money');
                    
                    document.getElementById('lastUpdate').textContent = 
                        new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
                }
            } catch (error) {
                console.error('Error fetching stats:', error);
            } finally {
                const refreshBtn = document.getElementById('refreshAll');
                refreshBtn.classList.remove('loading');
            }
        }
        
        // Show contact information modal
        function showContactInfo() {
            const contactInfo = `
                <div class="text-center">
                    <div class="display-4 mb-3">👨‍💻</div>
                    <h5>Contact Information</h5>
                    <div class="mt-4">
                        <p>
                            <i class="bi bi-envelope-fill me-2 text-primary"></i>
                            <strong>Email:</strong> <?php echo $developerInfo['contact']['email']; ?>
                        </p>
                        <p>
                            <i class="bi bi-telephone-fill me-2 text-success"></i>
                            <strong>Phone:</strong> <?php echo $developerInfo['contact']['phone']; ?>
                        </p>
                        <p>
                            <i class="bi bi-geo-alt-fill me-2 text-info"></i>
                            <strong>Location:</strong> <?php echo $developerInfo['contact']['location']; ?>
                        </p>
                    </div>
                    <div class="mt-4">
                        <a href="mailto:<?php echo $developerInfo['contact']['email']; ?>" 
                           class="btn btn-primary me-2 smooth-transition">
                            <i class="bi bi-envelope me-1"></i> Send Email
                        </a>
                        <button class="btn btn-outline-secondary smooth-transition" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            `;
            
            showModal('Contact Developer', contactInfo);
        }

        // Show all projects modal
        function showAllProjects() {
            const projects = <?php echo json_encode($developerInfo['projects']); ?>;
            let projectsHTML = '<div class="row g-3">';
            
            Object.entries(projects).forEach(([project, description], index) => {
                projectsHTML += `
                    <div class="col-md-6">
                        <div class="card h-100 smooth-transition">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="bi bi-folder me-2"></i>${project}
                                </h6>
                                <p class="card-text text-muted small">${description}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            projectsHTML += '</div>';
            
            showModal('Project Portfolio', projectsHTML);
        }

        // Reusable modal function
        function showModal(title, content) {
            // Remove existing modal if any
            const existingModal = document.getElementById('dynamicModal');
            if (existingModal) existingModal.remove();
            
            // Create modal HTML
            const modalHTML = `
                <div class="modal fade" id="dynamicModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${title}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${content}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('dynamicModal'));
            modal.show();
        }
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('mainNav');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Fade-in animation on scroll
        function checkFadeIn() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('visible');
                }
            });
        }
        
        // Initial fade-in check
        window.addEventListener('scroll', checkFadeIn);
        window.addEventListener('load', checkFadeIn);
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Refresh button click
        document.getElementById('refreshAll').addEventListener('click', fetchStats);
        
        // Auto-refresh every 30 seconds
        setInterval(fetchStats, 30000);
        
        // Initial fetch after page load
        window.addEventListener('load', function() {
            setTimeout(fetchStats, 5000);
            
            // Animate counters on page load
            document.querySelectorAll('.counter').forEach(counter => {
                const target = parseInt(counter.textContent);
                if (!isNaN(target)) {
                    let current = 0;
                    const increment = target / 50;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            counter.textContent = target + '+';
                            clearInterval(timer);
                        } else {
                            counter.textContent = Math.floor(current) + '+';
                        }
                    }, 30);
                }
            });
            
            // Initialize animations for developer section
            const developerSection = document.getElementById('developer');
            if (developerSection) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const cards = entry.target.querySelectorAll('.animate__animated');
                            cards.forEach((card, index) => {
                                setTimeout(() => {
                                    card.style.opacity = '1';
                                }, index * 100);
                            });
                        }
                    });
                }, { threshold: 0.1 });
                
                observer.observe(developerSection);
            }
        });
        
        // Skill badges hover effect
        document.querySelectorAll('.skill-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Add floating animation to stats cards on hover
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Project cards click effect
        document.querySelectorAll('.project-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'translateX(5px)';
                setTimeout(() => {
                    this.style.transform = 'translateX(0)';
                }, 300);
            });
        });
    </script>
</body>
</html>