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

// Get real statistics from database
try {
    $conn = getConnection();
    
    // Get total houses
    $house_sql = "SELECT COUNT(*) as total FROM houses WHERE status = 'active'";
    $house_result = mysqli_query($conn, $house_sql);
    $house_data = mysqli_fetch_assoc($house_result);
    $total_houses = $house_data['total'] ?? 0;
    
    // Get total active members
    $member_sql = "SELECT COUNT(*) as total FROM members WHERE status = 'active'";
    $member_result = mysqli_query($conn, $member_sql);
    $member_data = mysqli_fetch_assoc($member_result);
    $total_members = $member_data['total'] ?? 0;
    
    // Get today's meals
    $today = date('Y-m-d');
    $meal_sql = "SELECT COALESCE(SUM(meal_count), 0) as total FROM meals WHERE meal_date = '$today'";
    $meal_result = mysqli_query($conn, $meal_sql);
    $meal_data = mysqli_fetch_assoc($meal_result);
    $today_meals = $meal_data['total'] ?? 0;
    
    // Get total money managed (sum of all deposits)
    $money_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM deposits";
    $money_result = mysqli_query($conn, $money_sql);
    $money_data = mysqli_fetch_assoc($money_result);
    $total_money = $money_data['total'] ?? 0;
    
    // Format total money
    if ($total_money >= 1000000) {
        $total_money_formatted = number_format($total_money / 1000000, 1) . 'M';
    } elseif ($total_money >= 1000) {
        $total_money_formatted = number_format($total_money / 1000, 1) . 'K';
    } else {
        $total_money_formatted = number_format($total_money);
    }
    
    mysqli_close($conn);
    
} catch (Exception $e) {
    // Fallback statistics if database query fails
    $total_houses = 0;
    $total_members = 0;
    $today_meals = 0;
    $total_money = 0;
    $total_money_formatted = '0';
}

// Prepare stats array
$stats = [
    'total_houses' => $total_houses,
    'total_members' => $total_members,
    'today_meals' => $today_meals,
    'total_money' => $total_money,
    'total_money_formatted' => $total_money_formatted
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Completely Free Meal Management System for hostels, messes, and shared houses. Track meals, calculate costs, manage expenses efficiently.">
    <meta name="keywords" content="free meal management, hostel management, mess management, expense tracking, meal tracking">
    <meta name="author" content="MealMaster - Developed by Single Developer">
    
    <title><?php echo $page_title; ?></title>
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="100% Free meal management solution for hostels, messes, and shared houses. No email confirmation needed!">
    <meta property="og:type" content="website">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #2e59d9;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            color: #333;
            line-height: 1.6;
        }
        
        /* Free Badge */
        .free-badge {
            background: linear-gradient(135deg, #1cc88a, #17a673);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(28, 200, 138, 0.3);
        }
        
        /* Navigation */
        .navbar {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary) !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar-brand i {
            font-size: 2rem;
            color: var(--primary);
        }
        
        .nav-link {
            color: var(--dark) !important;
            font-weight: 500;
            margin: 0 5px;
            padding: 8px 16px !important;
            border-radius: 50px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover,
        .nav-link.active {
            color: var(--primary) !important;
            background: rgba(78, 115, 223, 0.1);
        }
        
        /* Hero Section */
        .hero-section {
            padding: 160px 0 100px;
            background: linear-gradient(135deg, rgba(78, 115, 223, 0.1), rgba(118, 75, 162, 0.1));
            position: relative;
            overflow: hidden;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
            color: #2e3a59;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            color: var(--secondary);
            margin-bottom: 30px;
            max-width: 600px;
        }
        
        /* Free Guarantee Box */
        .free-guarantee {
            background: white;
            border-left: 5px solid var(--success);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .free-guarantee i {
            color: var(--success);
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        /* Stats Section */
        .stats-section {
            padding: 80px 0;
            background: white;
        }
        
        .stat-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: 15px;
            background: var(--light);
            border: 2px solid rgba(78, 115, 223, 0.1);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 25px rgba(78, 115, 223, 0.1);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: var(--secondary);
            font-size: 1rem;
        }
        
        .stat-note {
            font-size: 0.8rem;
            color: var(--secondary);
            margin-top: 10px;
            font-style: italic;
        }
        
        /* Features Section */
        .features-section {
            padding: 100px 0;
            background: var(--light);
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            text-align: center;
            color: #2e3a59;
        }
        
        .section-subtitle {
            color: var(--secondary);
            text-align: center;
            margin-bottom: 60px;
            font-size: 1.1rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            background: var(--primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
        }
        
        .feature-icon i {
            font-size: 1.8rem;
            color: white;
        }
        
        .feature-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #2e3a59;
        }
        
        .feature-description {
            color: var(--secondary);
            font-size: 0.95rem;
        }
        
        /* Privacy Section */
        .privacy-section {
            padding: 100px 0;
            background: white;
        }
        
        .privacy-card {
            background: var(--light);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            border: 2px dashed var(--primary);
        }
        
        .privacy-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        /* CTA Section */
        .cta-section {
            padding: 100px 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            text-align: center;
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
        }
        
        .cta-subtitle {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(78, 115, 223, 0.3);
        }
        
        .btn-outline-light {
            border: 2px solid white;
        }
        
        .btn-outline-light:hover {
            background: white;
            color: var(--primary);
        }
        
        /* Footer */
        .footer {
            background: #2e3a59;
            color: white;
            padding: 80px 0 30px;
        }
        
        .developer-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .developer-avatar {
            width: 100px;
            height: 100px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 auto 20px;
            color: white;
        }
        
        .developer-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .developer-title {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
        }
        
        .contact-details {
            margin-top: 20px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .contact-item i {
            width: 20px;
            color: var(--primary);
        }
        
        .footer-links h5 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: white;
        }
        
        .footer-links ul {
            list-style: none;
            padding: 0;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        .copyright {
            text-align: center;
            padding-top: 40px;
            margin-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.2rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .hero-section {
                padding: 140px 0 60px;
            }
        }
        
        @media (max-width: 576px) {
            .hero-title {
                font-size: 1.8rem;
            }
            
            .cta-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-egg-fried"></i>
                MealMaster
            </a>
            <span class="free-badge d-none d-md-inline-flex">
                <i class="bi bi-check-circle"></i> 100% Free
            </span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#privacy">Privacy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-2" href="auth/register.php">
                            <i class="bi bi-person-plus me-2"></i>Get Started
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <span class="free-badge mb-3 animate__animated animate__pulse">
                            <i class="bi bi-check-circle"></i> Completely Free Forever
                        </span>
                        <h1 class="hero-title">Free Meal Management System</h1>
                        <p class="hero-subtitle">
                            A completely free solution for managing meals in hostels, messes, and shared houses. 
                            No hidden fees, no subscriptions, no email confirmation needed!
                        </p>
                        <div class="hero-buttons">
                            <a href="auth/register.php" class="btn btn-primary me-3">
                                <i class="bi bi-lightning me-2"></i>Start Free Now
                            </a>
                            <a href="#features" class="btn btn-outline-primary">
                                <i class="bi bi-info-circle me-2"></i>Learn More
                            </a>
                        </div>
                        
                        <div class="free-guarantee">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-shield-check"></i>
                                <h6 class="mb-0">No Email Confirmation Needed</h6>
                            </div>
                            <p class="mb-0 text-muted">Sign up instantly. No email verification required. Start managing your meals immediately!</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center mt-4 mt-lg-0">
                        <img src="https://cdn-icons-png.flaticon.com/512/3448/3448609.png" 
                             alt="Free Meal Management" 
                             class="img-fluid animate__animated animate__fadeInRight"
                             style="max-width: 100%; height: auto;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Real-time Statistics</h2>
                <p class="section-subtitle">
                    These numbers represent actual data from our users. Your data stays private and only you control it.
                </p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_houses']; ?></div>
                        <div class="stat-label">Active Houses</div>
                        <div class="stat-note">Using our free service</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_members']; ?></div>
                        <div class="stat-label">Happy Members</div>
                        <div class="stat-note">Managing their meals</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['today_meals'], 1); ?></div>
                        <div class="stat-label">Meals Tracked Today</div>
                        <div class="stat-note">Across all houses</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-number">৳<?php echo $stats['total_money_formatted']; ?></div>
                        <div class="stat-label">Successfully Managed</div>
                        <div class="stat-note">Total deposits tracked</div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <p class="text-muted">
                    <small>
                        <i class="bi bi-info-circle me-1"></i>
                        All statistics are calculated from actual user data in real-time. 
                        We only track what's necessary for the system to function.
                    </small>
                </p>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Everything You Need, Completely Free</h2>
                <p class="section-subtitle">
                    No limitations, no premium features, just a complete meal management solution
                </p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-calculator"></i>
                        </div>
                        <h3 class="feature-title">Automatic Calculations</h3>
                        <p class="feature-description">
                            Meal rates, costs, and balances are calculated automatically. No more manual spreadsheet work.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-file-text"></i>
                        </div>
                        <h3 class="feature-title">Detailed Reports</h3>
                        <p class="feature-description">
                            Generate monthly reports with expense breakdowns, meal statistics, and financial summaries.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="feature-title">Member Management</h3>
                        <p class="feature-description">
                            Easily add members, track their meals and deposits, and manage house membership.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <h3 class="feature-title">Expense Tracking</h3>
                        <p class="feature-description">
                            Track all house expenses by category. Know exactly where your money is going.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h3 class="feature-title">Real-time Dashboard</h3>
                        <p class="feature-description">
                            See your house statistics, member balances, and meal counts in real-time.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-printer"></i>
                        </div>
                        <h3 class="feature-title">Print & Export</h3>
                        <p class="feature-description">
                            Print reports or export data for record keeping. Everything you need is included.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Privacy Section -->
    <section id="privacy" class="privacy-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="privacy-card">
                        <div class="privacy-icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h2 class="section-title">Your Data, Your Control</h2>
                        <p class="section-subtitle">
                            We believe in simple, transparent, and private meal management
                        </p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6 mb-3">
                                <div class="text-center p-3">
                                    <i class="bi bi-envelope-x fs-1 text-primary mb-3"></i>
                                    <h5>No Email Confirmation</h5>
                                    <p class="text-muted">Sign up and start using immediately. No email verification required.</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-center p-3">
                                    <i class="bi bi-database-check fs-1 text-primary mb-3"></i>
                                    <h5>Only What You Provide</h5>
                                    <p class="text-muted">We only store the data you enter. No unnecessary information collection.</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-center p-3">
                                    <i class="bi bi-credit-card fs-1 text-primary mb-3"></i>
                                    <h5>No Payment Information</h5>
                                    <p class="text-muted">Completely free. We never ask for credit cards or payment details.</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-center p-3">
                                    <i class="bi bi-person-check fs-1 text-primary mb-3"></i>
                                    <h5>You Own Your Data</h5>
                                    <p class="text-muted">Your meal and expense data belongs to you. We're just providing the tools.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Transparency Promise:</strong> This system is developed by a single developer who believes in creating useful, 
                            free tools. The only data collected is what you enter for meal management purposes.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="text-center">
                <h2 class="cta-title">Start Managing Your Meals Today</h2>
                <p class="cta-subtitle">
                    Join hundreds of houses already using our completely free meal management system. 
                    No hidden fees, no email confirmation, just simple meal management.
                </p>
                <div class="cta-buttons mt-4">
                    <a href="auth/register.php" class="btn btn-primary btn-lg me-3">
                        <i class="bi bi-lightning me-2"></i>Get Started Free
                    </a>
                    <a href="auth/login.php" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </a>
                </div>
                <p class="mt-4" style="opacity: 0.9; font-size: 0.95rem;">
                    <i class="bi bi-shield-check me-2"></i>100% Free • No Email Confirmation • Your Data Stays Private
                </p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="developer-info">
                        <div class="developer-avatar">
                            <?php echo substr("Developer", 0, 1); ?>
                        </div>
                        <h3 class="developer-name">Single Developer Project</h3>
                        <p class="developer-title">MealMaster - Free Meal Management System</p>
                        <p style="color: rgba(255, 255, 255, 0.8);">
                            This system was developed by a single developer passionate about creating useful, 
                            free tools for community management. No team, no company, just one person building something helpful.
                        </p>
                        
                        <div class="contact-details">
                            <h5 style="color: white; margin-bottom: 15px;">Contact the Developer</h5>
                            <div class="contact-item">
                                <i class="bi bi-person"></i>
                                <span>Status: Actively Maintaining</span>
                            </div>
                            <div class="contact-item">
                                <i class="bi bi-code-slash"></i>
                                <span>Tech: PHP, MySQL, Bootstrap</span>
                            </div>
                            <div class="contact-item">
                                <i class="bi bi-calendar-check"></i>
                                <span>Started: 2023</span>
                            </div>
                            <div class="contact-item">
                                <i class="bi bi-heart"></i>
                                <span>Motivation: Help Communities</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <div class="footer-links">
                        <h5>System</h5>
                        <ul>
                            <li><a href="#home">Home</a></li>
                            <li><a href="#features">Features</a></li>
                            <li><a href="#privacy">Privacy</a></li>
                            <li><a href="auth/login.php">Login</a></li>
                            <li><a href="auth/register.php">Register</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <div class="footer-links">
                        <h5>Resources</h5>
                        <ul>
                            <li><a href="docs/user_guide.php">User Guide</a></li>
                            <li><a href="docs/faq.php">FAQ</a></li>
                            <li><a href="docs/tips.php">Tips & Tricks</a></li>
                            <li><a href="docs/troubleshooting.php">Troubleshooting</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <div class="footer-links">
                        <h5>Project Info</h5>
                        <ul>
                            <li><a href="about_project.php">About This Project</a></li>
                            <li><a href="changelog.php">Changelog</a></li>
                            <li><a href="roadmap.php">Future Plans</a></li>
                            <li><a href="contribute.php">Want to Contribute?</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="alert alert-primary text-center" style="background: rgba(255,255,255,0.1); border: none; color: white;">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> This is a personal project developed to help communities manage their meals efficiently. 
                        It's completely free and will remain free forever. Your data is only used for meal management purposes.
                    </div>
                </div>
            </div>
            
            <div class="copyright">
                <p>
                    &copy; <?php echo date('Y'); ?> MealMaster - Free Meal Management System. 
                    Developed and maintained by a single developer. 
                    <br class="d-block d-md-none">
                    <span class="d-none d-md-inline">|</span> 
                    Version 1.0 | Last Updated: <?php echo date('F Y'); ?>
                </p>
                <p class="mt-2" style="font-size: 0.8rem;">
                    <i class="bi bi-heart-fill text-danger"></i> 
                    Built with passion for helping communities manage their meals better.
                </p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Smooth scrolling
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
        
        // Update active nav link
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= (sectionTop - 100)) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
        
        // Animate stats on scroll
        function animateCounter(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                element.textContent = value;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }
        
        // Observe stats section
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Get all stat numbers and animate them
                    document.querySelectorAll('.stat-number').forEach(stat => {
                        const currentValue = parseInt(stat.textContent.replace(/[^0-9]/g, ''));
                        if (!isNaN(currentValue) && currentValue > 0) {
                            animateCounter(stat, 0, currentValue, 2000);
                        }
                    });
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        // Start observing stats section
        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            observer.observe(statsSection);
        }
        
        // Free badge animation
        const freeBadge = document.querySelector('.free-badge');
        if (freeBadge) {
            setInterval(() => {
                freeBadge.classList.toggle('animate__pulse');
            }, 3000);
        }
    </script>
</body>
</html>