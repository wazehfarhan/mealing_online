<?php
// header.php - SAFE FIXED VERSION

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// FIXED: Check both logged_in and user_id for compatibility
$is_logged_in = false;

// Method 1: Check logged_in (your original method)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $is_logged_in = true;
}

// Method 2: Check user_id (what update_profile.php uses)
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $is_logged_in = true;
    // Set logged_in for backward compatibility
    if (!isset($_SESSION['logged_in'])) {
        $_SESSION['logged_in'] = true;
    }
}

// If not logged in by either method, redirect to login
if (!$is_logged_in) {
    header("Location: ../auth/login.php");
    exit();
}

// Check if user has a house (for house-dependent pages)
$excluded_pages = ['setup_house.php', 'logout.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (!in_array($current_page, $excluded_pages)) {
    if (!isset($_SESSION['house_id'])) {
        // Check database for house_id
        require_once __DIR__ . '/../config/database.php';
        $conn = getConnection();
        
        $sql = "SELECT house_id FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            if ($row['house_id']) {
                $_SESSION['house_id'] = $row['house_id'];
            } else {
                // No house, redirect to setup
                header("Location: setup_house.php");
                exit();
            }
        } else {
            // User not found in database, logout
            session_destroy();
            header("Location: ../auth/login.php");
            exit();
        }
        
        mysqli_close($conn);
    }
}

$page_title = isset($page_title) ? $page_title : 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' | ' . (defined('SITE_NAME') ? SITE_NAME : 'Meal System'); ?></title>
    <link rel="icon" type="image/png" href="../image/icon.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --purple-color: #6f42c1;
            --orange-color: #fd7e14;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, #1a2530 100%);
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: fixed;
            width: 250px;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        
        .sidebar .nav-link {
            color: #bdc3c7;
            padding: 12px 20px;
            margin: 2px 0;
            border-radius: 5px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(52, 152, 219, 0.2);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 0;
            min-height: 100vh;
        }
        
        .navbar-top {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 30px;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .content-wrapper {
            padding: 30px;
        }
        
        .stat-card {
            border-radius: 10px;
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .card-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .content-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .page-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 0;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .alert {
            border: none;
            border-radius: 8px;
        }
        
        .balance-positive {
            color: var(--success-color);
            font-weight: bold;
        }
        
        .balance-negative {
            color: var(--danger-color);
            font-weight: bold;
        }
        
        .badge {
            padding: 6px 12px;
            font-weight: 500;
        }
        
        /* CUSTOM BADGE COLORS - ADDED FOR CONSISTENCY */
        .badge.bg-purple {
            background-color: var(--purple-color) !important;
            color: white !important;
        }
        .badge.bg-orange {
            background-color: var(--orange-color) !important;
            color: white !important;
        }
        .badge.bg-light {
            color: #212529 !important; /* Dark text for light background */
        }
        
        /* Make all badges have white text by default (except light) */
        .badge.bg-primary,
        .badge.bg-secondary,
        .badge.bg-success,
        .badge.bg-danger,
        .badge.bg-warning,
        .badge.bg-info,
        .badge.bg-dark,
        .badge.bg-purple,
        .badge.bg-orange {
            color: white !important;
        }
        
        /* Optional: Add purple and orange for other elements too */
        .bg-purple { background-color: var(--purple-color) !important; }
        .bg-orange { background-color: var(--orange-color) !important; }
        .text-purple { color: var(--purple-color) !important; }
        .text-orange { color: var(--orange-color) !important; }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar d-flex flex-column">
        <div class="p-4">
            <h4 class="text-white mb-0">
                <i class="fas fa-utensils me-2"></i>
                <?php echo isset($_SESSION['role']) && $_SESSION['role'] === 'manager' ? 'Manager Panel' : 'Member Panel'; ?>
            </h4>
            <small class="text-muted">
                <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?>
                <?php if (isset($_SESSION['role'])): ?>
                <span class="badge bg-<?php echo $_SESSION['role'] === 'manager' ? 'primary' : 'success'; ?> text-white ms-2">
                    <?php echo $_SESSION['role']; ?>
                </span>
                <?php endif; ?>
            </small>
        </div>
        
        <ul class="nav flex-column px-3 flex-grow-1">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager'): ?>
            <!-- Manager Menu -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'members.php' ? 'active' : ''; ?>" 
                   href="members.php">
                    <i class="fas fa-users"></i> Members
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'meals.php' ? 'active' : ''; ?>" 
                   href="meals.php">
                    <i class="fas fa-utensil-spoon"></i> Meals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>" 
                   href="expenses.php">
                    <i class="fas fa-money-bill-wave"></i> Expenses
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'deposits.php' ? 'active' : ''; ?>" 
                   href="deposits.php">
                    <i class="fas fa-wallet"></i> Deposits
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                   href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'generate_link.php' ? 'active' : ''; ?>" 
                   href="generate_link.php">
                    <i class="fas fa-link"></i> Generate Join Link
                </a>
            </li>
            <?php else: ?>
            <!-- Member Menu -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" 
                   href="profile.php">
                    <i class="fas fa-user"></i> My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : ''; ?>" 
                   href="report.php">
                    <i class="fas fa-file-alt"></i> My Report
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Common Menu -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" 
                   href="settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
        </ul>
        
        <div class="p-4 border-top border-dark">
            <a class="nav-link text-danger" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
            <div class="text-center text-muted mt-3">
                <small>&copy; <?php echo date('Y'); ?> Meal System</small>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <nav class="navbar-top">
            <div class="d-flex justify-content-between align-items-center w-100">
                <div>
                    <h4 class="page-title mb-0"><?php echo $page_title; ?></h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><a class="dropdown-item" href="../auth/change_password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <div class="content-wrapper">
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>