<?php
require_once 'config/database.php';

// First connect without selecting database
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);

if (!$conn) {
    die("Could not connect to MySQL: " . mysqli_connect_error());
}

// Create database if not exists
$create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!mysqli_query($conn, $create_db)) {
    die("Error creating database: " . mysqli_error($conn));
}

// Select database
if (!mysqli_select_db($conn, DB_NAME)) {
    die("Error selecting database: " . mysqli_error($conn));
}

// Read SQL file
$sql_file = 'database/meal_system.sql';
if (!file_exists($sql_file)) {
    die("SQL file not found: $sql_file");
}

$sql_content = file_get_contents($sql_file);

// Remove comments and split queries
$sql_content = preg_replace('/--.*$/m', '', $sql_content);
$queries = array_filter(array_map('trim', explode(';', $sql_content)));

$success_count = 0;
$error_count = 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Meal Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .setup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }
        .setup-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .log-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 20px 0;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .btn-setup {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-setup:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header text-center">
            <h2><i class="fas fa-database me-2"></i>Database Setup</h2>
            <p class="mb-0">Meal Management System Installation</p>
        </div>
        
        <div class="progress mb-4">
            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                 style="width: 50%">Setting up database...</div>
        </div>
        
        <div class="log-box">
            <?php
            // Execute queries
            foreach ($queries as $query) {
                if (!empty($query) && strlen($query) > 10) {
                    echo "<div class='info'>Executing: " . substr($query, 0, 100) . "...</div>";
                    
                    if (mysqli_query($conn, $query)) {
                        echo "<div class='success'>✓ Success</div>";
                        $success_count++;
                    } else {
                        $error_msg = mysqli_error($conn);
                        // Skip "already exists" errors
                        if (strpos($error_msg, 'already exists') === false) {
                            echo "<div class='error'>✗ Error: " . $error_msg . "</div>";
                            $error_count++;
                        } else {
                            echo "<div class='info'>✓ Already exists (skipped)</div>";
                        }
                    }
                    echo "<hr>";
                    
                    // Flush output buffer
                    ob_flush();
                    flush();
                    usleep(100000); // 0.1 second delay
                }
            }
            
            // Check if admin user exists
            $check_admin = mysqli_query($conn, "SELECT user_id FROM users WHERE username = 'admin'");
            if (mysqli_num_rows($check_admin) == 0) {
                // Create admin user with password: admin123
                $password = password_hash('admin123', PASSWORD_DEFAULT);
                $admin_sql = "INSERT INTO users (username, email, password, role, is_active) 
                              VALUES ('admin', 'admin@mealsystem.com', '$password', 'manager', 1)";
                
                if (mysqli_query($conn, $admin_sql)) {
                    echo "<div class='success'>✓ Created default admin user</div>";
                } else {
                    echo "<div class='error'>✗ Failed to create admin user: " . mysqli_error($conn) . "</div>";
                }
            } else {
                echo "<div class='info'>✓ Admin user already exists</div>";
            }
            ?>
        </div>
        
        <div class="setup-summary mt-4 p-3 rounded" 
             style="background-color: <?php echo $error_count > 0 ? '#f8d7da' : '#d4edda'; ?>">
            <h5>Setup Summary:</h5>
            <p>Successful queries: <strong class="success"><?php echo $success_count; ?></strong></p>
            <p>Errors: <strong class="<?php echo $error_count > 0 ? 'error' : 'success'; ?>"><?php echo $error_count; ?></strong></p>
            
            <?php if ($error_count == 0): ?>
            <div class="alert alert-success mt-3">
                <h4><i class="fas fa-check-circle me-2"></i>Setup Completed Successfully!</h4>
                <p class="mb-2"><strong>Default Login Credentials:</strong></p>
                <p class="mb-1">Username: <strong>admin</strong></p>
                <p class="mb-3">Password: <strong>admin123</strong></p>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Change the default password after first login!
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning mt-3">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>Setup Completed with Errors</h4>
                <p>Some tables might already exist. The system may still work properly.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-setup btn-lg me-3">
                <i class="fas fa-home me-2"></i>Go to Home
            </a>
            <a href="auth/login.php" class="btn btn-success btn-lg">
                <i class="fas fa-sign-in-alt me-2"></i>Login Now
            </a>
        </div>
        
        <div class="mt-4 text-center text-muted">
            <small>If you encounter issues, check your database credentials in config/database.php</small>
        </div>
    </div>
    
    <?php
    // Update progress bar
    echo "<script>
        document.querySelector('.progress-bar').style.width = '100%';
        document.querySelector('.progress-bar').textContent = 'Setup Complete';
    </script>";
    
    mysqli_close($conn);
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>