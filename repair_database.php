<?php
require_once 'config/database.php';

$conn = getConnection();

echo "<h2>Database Repair Utility</h2>";
echo "<style>body { font-family: monospace; padding: 20px; } .success { color: green; } .error { color: red; } .info { color: blue; }</style>";

// List of tables to check/repair
$tables = [
    'users' => "
        CREATE TABLE IF NOT EXISTS users (
            user_id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('manager', 'member') NOT NULL,
            member_id INT DEFAULT NULL,
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'members' => "
        CREATE TABLE IF NOT EXISTS members (
            member_id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            email VARCHAR(100),
            join_date DATE NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_by INT,
            join_token VARCHAR(100) UNIQUE,
            token_expiry DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
    // Add other tables similarly
];

foreach ($tables as $table_name => $create_sql) {
    echo "<div class='info'>Checking table: $table_name</div>";
    
    // Check if table exists
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");
    
    if (mysqli_num_rows($result) == 0) {
        echo "<div class='error'>Table $table_name doesn't exist. Creating...</div>";
        
        if (mysqli_query($conn, $create_sql)) {
            echo "<div class='success'>✓ Table $table_name created successfully</div>";
        } else {
            echo "<div class='error'>✗ Failed to create $table_name: " . mysqli_error($conn) . "</div>";
        }
    } else {
        echo "<div class='success'>✓ Table $table_name exists</div>";
        
        // Check and repair table
        $repair = mysqli_query($conn, "REPAIR TABLE `$table_name`");
        if ($repair) {
            echo "<div class='info'>Table $table_name repaired (if needed)</div>";
        }
    }
    echo "<hr>";
}

// Check and create admin user
echo "<div class='info'>Checking admin user...</div>";
$result = mysqli_query($conn, "SELECT user_id FROM users WHERE username = 'admin'");
if (mysqli_num_rows($result) == 0) {
    echo "<div class='error'>Admin user not found. Creating...</div>";
    
    $admin_sql = "INSERT INTO users (username, email, password, role) 
                  VALUES ('admin', 'admin@mealsystem.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager')";
    
    if (mysqli_query($conn, $admin_sql)) {
        echo "<div class='success'>✓ Admin user created successfully</div>";
        echo "<div class='info'>Default password: admin123</div>";
    } else {
        echo "<div class='error'>✗ Failed to create admin user: " . mysqli_error($conn) . "</div>";
    }
} else {
    echo "<div class='success'>✓ Admin user exists</div>";
}

echo "<hr>";
echo "<h3>Repair Complete</h3>";
echo "<p><a href='index.php'>Go to Home Page</a> | <a href='setup.php'>Run Full Setup</a></p>";

mysqli_close($conn);
?>