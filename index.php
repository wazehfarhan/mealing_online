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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            padding: 10px 0;
            background: rgba(255, 255, 255, 0.98);
        }
        
        .hero-section {
            padding: 180px 0 120px;
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
        
        /* Developer Section */
        .developer-section {
            background: linear-gradient(135deg, 
                rgba(33, 37, 41, 0.95) 0%, 
                rgba(52, 58, 64, 0.95) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .developer-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%234e73df" fill-opacity="0.1" d="M0,224L48,213.3C96,203,192,181,288,181.3C384,181,480,203,576,202.7C672,203,768,181,864,165.3C960,149,1056,139,1152,149.3C1248,160,1344,192,1392,208L1440,224L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            background-position: center;
            opacity: 0.3;
        }
        
        .developer-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .developer-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .skill-badge {
            background: linear-gradient(45deg, var(--primary), var(--info));
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 5px;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .skill-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
        }
        
        .project-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 5px solid var(--primary);
        }
        
        .project-card:hover {
            transform: translateX(10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary), var(--success));
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -33px;
            top: 5px;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid white;
            box-shadow: 0 0 0 4px var(--primary);
        }
        
        .quote-box {
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            border-radius: 15px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .quote-box::before {
            content: '"';
            position: absolute;
            top: -30px;
            left: 20px;
            font-size: 120px;
            opacity: 0.2;
            font-family: serif;
        }
        
        .social-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            margin: 0 10px;
        }
        
        .social-icon:hover {
            transform: translateY(-5px);
            background: var(--primary);
            color: white;
            text-decoration: none;
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
        
        .typewriter {
            overflow: hidden;
            border-right: .15em solid var(--primary);
            white-space: nowrap;
            margin: 0 auto;
            animation: typing 3.5s steps(40, end), blink-caret .75s step-end infinite;
        }
        
        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }
        
        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: var(--primary) }
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-egg-fried me-2"></i>MealMaster
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-2 px-4" href="auth/register.php">Get Started</a>
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
                    <h1 class="display-4 fw-bold mb-4 animate__animated animate__fadeInUp">
                        <span class="gradient-text">Free Meal Management System</span>
                    </h1>
                    <p class="lead mb-4 animate__animated animate__fadeInUp animate__delay-1s">
                        Completely free solution for managing meals in hostels, messes, and shared houses.
                    </p>
                    <div class="d-flex gap-3 animate__animated animate__fadeInUp animate__delay-2s">
                        <a href="auth/register.php" class="btn btn-primary btn-lg px-4">
                            <i class="bi bi-lightning me-2"></i>Start Free Now
                        </a>
                        <a href="#developer" class="btn btn-outline-light btn-lg px-4">
                            <i class="bi bi-person-badge me-2"></i>Meet Developer
                        </a>
                    </div>
                    <p class="text-muted mt-4 animate__animated animate__fadeInUp animate__delay-3s">
                        <i class="bi bi-arrow-up-right-circle me-2"></i>
                        Join <span class="fw-bold"><?php echo $stats['total_houses'] ?? 0; ?>+</span> houses and 
                        <span class="fw-bold"><?php echo $stats['total_members'] ?? 0; ?>+</span> members already using MealMaster
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative floating-element">
                        <div class="display-1 text-center mb-4">🍽️</div>
                        <div class="position-absolute top-0 start-0 animate__animated animate__bounceIn">
                            <div class="bg-white rounded-circle p-3 shadow-lg">
                                <i class="bi bi-graph-up text-primary fs-3"></i>
                            </div>
                        </div>
                        <div class="position-absolute top-0 end-0 animate__animated animate__bounceIn animate__delay-1s">
                            <div class="bg-white rounded-circle p-3 shadow-lg">
                                <i class="bi bi-calculator text-success fs-3"></i>
                            </div>
                        </div>
                        <div class="position-absolute bottom-0 start-50 translate-middle-x animate__animated animate__bounceIn animate__delay-2s">
                            <div class="bg-white rounded-circle p-3 shadow-lg">
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
                    <button id="refreshAll" class="btn btn-sm btn-outline-primary ms-3 refresh-btn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </p>
            </div>
            
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-card animate__animated animate__fadeInUp">
                        <div class="stat-number" id="stat-houses"><?php echo $stats['total_houses'] ?? 0; ?></div>
                        <div class="stat-label">Active Houses</div>
                        <small class="text-muted" id="stat-houses-time">Updated just now</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card animate__animated animate__fadeInUp animate__delay-1s">
                        <div class="stat-number" id="stat-members"><?php echo $stats['total_members'] ?? 0; ?></div>
                        <div class="stat-label">Happy Members</div>
                        <small class="text-muted" id="stat-members-time">Updated just now</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card animate__animated animate__fadeInUp animate__delay-2s">
                        <div class="stat-number" id="stat-meals"><?php echo number_format($stats['today_meals'] ?? 0, 1); ?></div>
                        <div class="stat-label">Meals Today</div>
                        <small class="text-muted" id="stat-meals-time">Updated just now</small>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card animate__animated animate__fadeInUp animate__delay-3s">
                        <div class="stat-number" id="stat-money">৳<?php echo $total_money_formatted; ?></div>
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
                    <div class="text-center p-4 h-100">
                        <div class="feature-icon text-success mb-4">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h5 class="fw-bold">100% Free Forever</h5>
                        <p>No hidden fees, no subscriptions, no credit card required. Built to help communities, not for profit.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center p-4 h-100">
                        <div class="feature-icon text-warning mb-4">
                            <i class="bi bi-lightning-fill"></i>
                        </div>
                        <h5 class="fw-bold">Instant Setup</h5>
                        <p>Create your house and start tracking in under 2 minutes. No email verification or complex setup required.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center p-4 h-100">
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
                <h2 class="fw-bold text-white mb-3">Meet The Developer</h2>
                <p class="text-light">Built with passion by a software engineering student</p>
            </div>
            
            <div class="row mb-5">
                <div class="col-lg-4 mb-4">
                    <div class="developer-card p-4 h-100">
                        <div class="text-center mb-4">
                            <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <i class="bi bi-code-slash text-white fs-1"></i>
                            </div>
                            <h3 class="text-white mt-3"><?php echo $developerInfo['name']; ?></h3>
                            <p class="text-light"><?php echo $developerInfo['title']; ?></p>
                        </div>
                        <p class="text-light"><?php echo $developerInfo['bio']; ?></p>
                        
                        <div class="mt-4">
                            <h5 class="text-white mb-3">Contact</h5>
                            <p class="text-light mb-1">
                                <i class="bi bi-envelope me-2"></i>
                                <?php echo $developerInfo['contact']['email']; ?>
                            </p>
                            <p class="text-light mb-1">
                                <i class="bi bi-telephone me-2"></i>
                                <?php echo $developerInfo['contact']['phone']; ?>
                            </p>
                            <p class="text-light">
                                <i class="bi bi-geo-alt me-2"></i>
                                <?php echo $developerInfo['contact']['location']; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <div class="developer-card p-4 h-100">
                        <h4 class="text-white mb-4"><i class="bi bi-mortarboard me-2"></i>Education</h4>
                        <div class="timeline">
                            <div class="timeline-item">
                                <h5 class="text-white"><?php echo $developerInfo['education']['degree']; ?></h5>
                                <p class="text-light mb-1"><?php echo $developerInfo['education']['university']; ?></p>
                                <p class="text-light small"><?php echo $developerInfo['education']['semester']; ?> | ID: <?php echo $developerInfo['education']['student_id']; ?></p>
                            </div>
                            <div class="timeline-item">
                                <h5 class="text-white">Higher Secondary Certificate</h5>
                                <p class="text-light mb-1">Holy Land College</p>
                                <p class="text-light small">GPA: 5.00</p>
                            </div>
                        </div>
                        
                        <h4 class="text-white mt-4 mb-3"><i class="bi bi-tools me-2"></i>Skills</h4>
                        <div class="mb-3">
                            <?php foreach($developerInfo['skills'] as $skillType => $skills): ?>
                                <h6 class="text-white mb-2"><?php echo $skillType; ?>:</h6>
                                <div class="mb-3">
                                    <?php 
                                    $skillList = explode(', ', $skills);
                                    foreach($skillList as $skill): 
                                    ?>
                                        <span class="skill-badge"><?php echo trim($skill); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <div class="developer-card p-4 h-100">
                        <h4 class="text-white mb-4"><i class="bi bi-briefcase me-2"></i>Projects</h4>
                        
                        <?php foreach($developerInfo['projects'] as $project => $description): ?>
                            <div class="project-card mb-3">
                                <h5 class="fw-bold"><?php echo $project; ?></h5>
                                <p class="text-muted mb-0"><?php echo $description; ?></p>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="quote-box mt-4">
                            <p class="mb-0"><?php echo $developerInfo['quote']; ?></p>
                        </div>
                        
                        <div class="text-center mt-4">
                            <h6 class="text-white mb-3">Connect with me</h6>
                            <div>
                                <a href="<?php echo $developerInfo['profiles']['github']; ?>" class="social-icon" target="_blank">
                                    <i class="bi bi-github"></i>
                                </a>
                                <a href="<?php echo $developerInfo['profiles']['linkedin']; ?>" class="social-icon" target="_blank">
                                    <i class="bi bi-linkedin"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <p class="text-light mb-0">
                    <i class="bi bi-code-square me-2"></i>
                    This project is part of my journey to build meaningful software solutions
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">
                        <i class="bi bi-egg-fried me-2"></i>MealMaster
                    </h5>
                    <p class="text-muted">
                        Free meal management system for hostels, messes, and shared houses.
                        Built to solve real-world problems with modern web technologies.
                    </p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="auth/login.php" class="text-white text-decoration-none">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="auth/register.php" class="text-white text-decoration-none">
                                <i class="bi bi-person-plus me-2"></i>Register
                            </a>
                        </li>
                        <li>
                            <a href="#developer" class="text-white text-decoration-none">
                                <i class="bi bi-code-slash me-2"></i>About Developer
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">Technologies Used</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary">PHP</span>
                        <span class="badge bg-success">MySQL</span>
                        <span class="badge bg-info">Bootstrap 5</span>
                        <span class="badge bg-warning">JavaScript</span>
                        <span class="badge bg-danger">HTML5</span>
                        <span class="badge bg-secondary">CSS3</span>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4 pt-4 border-top border-secondary">
                <p class="text-muted small mb-0">
                    © <?php echo date('Y'); ?> MealMaster - Developed by <?php echo $developerInfo['name']; ?> | 
                    Version 1.0 | Built with ❤️ for the community
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
            
            // Typewriter effect for hero text
            const heroText = document.querySelector('.hero-section h1');
            if (heroText) {
                heroText.classList.add('typewriter');
            }
        });
        
        // Skill badges hover effect
        document.querySelectorAll('.skill-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
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
    </script>
</body>
</html>