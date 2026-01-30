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

// Enable exception handling for mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Check if tables already exist
$check_tables = mysqli_query($conn, "SHOW TABLES LIKE 'houses'");
$tables_exist = mysqli_num_rows($check_tables) > 0;

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
        
        <?php if ($tables_exist): ?>
        <div class="alert alert-warning">
            <h4><i class="fas fa-exclamation-triangle me-2"></i>Database Already Exists</h4>
            <p>The database tables already exist. Running setup again may cause issues.</p>
        </div>
        
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-setup btn-lg me-3">
                <i class="fas fa-home me-2"></i>Go to Home
            </a>
            <a href="auth/login.php" class="btn btn-success btn-lg">
                <i class="fas fa-sign-in-alt me-2"></i>Login Now
            </a>
            <button type="button" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#resetModal">
                <i class="fas fa-redo me-2"></i>Reset Database
            </button>
        </div>
        
        <!-- Reset Modal -->
        <div class="modal fade" id="resetModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Reset Database</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This will delete all existing data and recreate the database structure.
                            This action cannot be undone!
                        </div>
                        <p>Are you sure you want to reset the database?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <a href="setup.php?reset=1" class="btn btn-danger">Yes, Reset Database</a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        
        <div class="progress mb-4">
            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                 style="width: 50%">Setting up database...</div>
        </div>
        
        <div class="log-box">
            <?php
            // Check if reset is requested
            $reset = isset($_GET['reset']) ? $_GET['reset'] : 0;
            
            if ($reset == 1 && $tables_exist) {
                // Drop all tables first
                echo "<div class='info'>Resetting database...</div>";
                
                // Disable foreign key checks
                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
                
                // Get all tables
                $tables_result = mysqli_query($conn, "SHOW TABLES");
                while ($table_row = mysqli_fetch_array($tables_result)) {
                    $table = $table_row[0];
                    $drop_query = "DROP TABLE IF EXISTS `$table`";
                    if (mysqli_query($conn, $drop_query)) {
                        echo "<div class='info'>Dropped table: $table</div>";
                    } else {
                        echo "<div class='error'>Failed to drop table $table: " . mysqli_error($conn) . "</div>";
                    }
                }
                
                // Re-enable foreign key checks
                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
                echo "<hr>";
            }
            
            // Read SQL file
            $sql_file = 'database/meal_system.sql';
            if (!file_exists($sql_file)) {
                die("<div class='error'>SQL file not found: $sql_file</div>");
            }

            $sql_content = file_get_contents($sql_file);
            
            // Remove comments
            $sql_content = preg_replace('/--.*$/m', '', $sql_content);
            $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
            
            // Split queries
            $queries = array_filter(array_map('trim', explode(';', $sql_content)));

            $success_count = 0;
            $error_count = 0;
            $skipped_count = 0;

            // Execute queries
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query) && strlen($query) > 10) {
                    // Skip comments and empty lines
                    if (strpos($query, '/*') === 0 || empty($query)) {
                        continue;
                    }
                    
                    echo "<div class='info'>Executing: " . substr($query, 0, 100) . "...</div>";
                    
                    try {
                        if (mysqli_query($conn, $query)) {
                            echo "<div class='success'>✓ Success</div>";
                            $success_count++;
                        }
                    } catch (Exception $e) {
                        $error_msg = $e->getMessage();
                        // Skip "already exists" errors
                        if (strpos($error_msg, 'already exists') !== false || 
                            strpos($error_msg, "Table '") !== false && strpos($error_msg, "' already exists") !== false) {
                            echo "<div class='info'>✓ Already exists (skipped)</div>";
                            $skipped_count++;
                        } else {
                            echo "<div class='error'>✗ Error: " . htmlspecialchars($error_msg) . "</div>";
                            $error_count++;
                        }
                    }
                    echo "<hr>";
                    
                    // Flush output buffer
                    ob_flush();
                    flush();
                    usleep(50000); // 0.05 second delay
                }
            }
            
            // Check if super admin user exists
            $check_superadmin = mysqli_query($conn, "SELECT user_id FROM users WHERE username = 'superadmin'");
            if (mysqli_num_rows($check_superadmin) == 0) {
                // Create superadmin user with password: password
                $password = password_hash('password', PASSWORD_DEFAULT);
                $superadmin_sql = "INSERT INTO users (username, email, password, role, is_active) 
                                   VALUES ('superadmin', 'superadmin@mealsystem.com', '$password', 'super_admin', 1)";
                
                if (mysqli_query($conn, $superadmin_sql)) {
                    echo "<div class='success'>✓ Created super admin user</div>";
                    $success_count++;
                } else {
                    echo "<div class='error'>✗ Failed to create super admin user: " . mysqli_error($conn) . "</div>";
                    $error_count++;
                }
            } else {
                echo "<div class='info'>✓ Super admin user already exists</div>";
                $skipped_count++;
            }
            
            // Check if admin user exists
            $check_admin = mysqli_query($conn, "SELECT user_id FROM users WHERE username = 'admin'");
            if (mysqli_num_rows($check_admin) == 0) {
                // Create admin user with password: admin123
                $password = password_hash('admin123', PASSWORD_DEFAULT);
                $admin_sql = "INSERT INTO users (username, email, password, role, is_active) 
                              VALUES ('admin', 'admin@mealsystem.com', '$password', 'manager', 1)";
                
                if (mysqli_query($conn, $admin_sql)) {
                    echo "<div class='success'>✓ Created admin user</div>";
                    $success_count++;
                } else {
                    echo "<div class='error'>✗ Failed to create admin user: " . mysqli_error($conn) . "</div>";
                    $error_count++;
                }
            } else {
                echo "<div class='info'>✓ Admin user already exists</div>";
                $skipped_count++;
            }
            
            // Check if default house exists
            $check_house = mysqli_query($conn, "SELECT house_id FROM houses WHERE house_code = 'MAIN001'");
            if (mysqli_num_rows($check_house) == 0) {
                // Create default house
                $house_sql = "INSERT INTO houses (house_name, house_code, description, created_by) 
                              VALUES ('Main House', 'MAIN001', 'Main living house for the system', 1)";
                
                if (mysqli_query($conn, $house_sql)) {
                    echo "<div class='success'>✓ Created default house</div>";
                    $success_count++;
                } else {
                    echo "<div class='error'>✗ Failed to create default house: " . mysqli_error($conn) . "</div>";
                    $error_count++;
                }
            } else {
                echo "<div class='info'>✓ Default house already exists</div>";
                $skipped_count++;
            }
            ?>
        </div>
        
        <div class="setup-summary mt-4 p-3 rounded" 
             style="background-color: <?php echo $error_count > 0 ? '#f8d7da' : '#d4edda'; ?>">
            <h5>Setup Summary:</h5>
            <p>Successful queries: <strong class="success"><?php echo $success_count; ?></strong></p>
            <p>Skipped (already exists): <strong class="info"><?php echo $skipped_count; ?></strong></p>
            <p>Errors: <strong class="<?php echo $error_count > 0 ? 'error' : 'success'; ?>"><?php echo $error_count; ?></strong></p>
            
            <?php if ($error_count == 0): ?>
            <div class="alert alert-success mt-3">
                <h4><i class="fas fa-check-circle me-2"></i>Setup Completed Successfully!</h4>
                <p class="mb-2"><strong>Default Login Credentials:</strong></p>
                <p class="mb-1"><strong>Super Admin:</strong> username: <strong>superadmin</strong>, password: <strong>password</strong></p>
                <p class="mb-3"><strong>Admin:</strong> username: <strong>admin</strong>, password: <strong>admin123</strong></p>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Change the default passwords after first login!
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning mt-3">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>Setup Completed with Errors</h4>
                <p>Some errors occurred during setup. Check the error messages above.</p>
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
        
        <?php endif; ?>
        
        <div class="mt-4 text-center text-muted">
            <small>If you encounter issues, check your database credentials in config/database.php</small>
        </div>
    </div>
    
    <?php
    if (!$tables_exist): 
    // Update progress bar
    echo "<script>
        document.querySelector('.progress-bar').style.width = '100%';
        document.querySelector('.progress-bar').textContent = 'Setup Complete';
    </script>";
    endif;
    
    mysqli_close($conn);
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>