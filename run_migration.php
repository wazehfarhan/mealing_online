<?php
/**
 * Database Migration Script for House Transfer System
 * Run this file to apply the database changes
 * This script handles already-existing columns/tables gracefully
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting House Transfer System Migration...\n\n";

// Load configuration
if (!file_exists(__DIR__ . '/.env')) {
    die("Error: .env file not found. Please copy .env.example to .env and configure it.\n");
}

$env_file = file_get_contents(__DIR__ . '/.env');
$env_array = [];
foreach (explode("\n", $env_file) as $line) {
    $line = trim($line);
    if (!$line || $line[0] === '#') continue;
    if (strpos($line, '=') === false) continue;
    list($key, $value) = explode('=', $line, 2);
    $env_array[trim($key)] = trim($value);
}

$DB_HOST = $env_array['DB_HOST'] ?? 'localhost';
$DB_USER = $env_array['DB_USER'] ?? 'root';
$DB_PASS = $env_array['DB_PASS'] ?? '';
$DB_NAME = $env_array['DB_NAME'] ?? 'meal_system';

// Use TCP connection instead of socket for better compatibility
if ($DB_HOST === 'localhost') {
    $DB_HOST = '127.0.0.1';
}

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, '', 3306);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . "\n");
}

echo "Connected to MySQL server.\n\n";

// Select the database
if (!mysqli_select_db($conn, $DB_NAME)) {
    die("Cannot select database: " . mysqli_error($conn) . "\n");
}

echo "Using database: $DB_NAME\n\n";

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return mysqli_num_rows($result) > 0;
}

// Function to check if table exists
function tableExists($conn, $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return mysqli_num_rows($result) > 0;
}

// Execute ALTER TABLE statements with column existence check
echo "Adding columns to houses table...\n";
if (!columnExists($conn, 'houses', 'is_open_for_join')) {
    $sql = "ALTER TABLE `houses` ADD COLUMN `is_open_for_join` TINYINT(1) DEFAULT 1 AFTER `is_active`";
    if (mysqli_query($conn, $sql)) {
        echo "✓ Added is_open_for_join to houses\n";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ is_open_for_join already exists in houses\n";
}

echo "\nAdding columns to members table...\n";
$member_columns = [
    'house_status' => "ADD COLUMN `house_status` ENUM('active', 'pending_leave', 'pending_join', 'left', 'house_inactive') DEFAULT 'active' AFTER `status`",
    'requested_house_id' => "ADD COLUMN `requested_house_id` INT(11) DEFAULT NULL AFTER `house_id`",
    'leave_request_date' => "ADD COLUMN `leave_request_date` DATETIME DEFAULT NULL AFTER `token_expiry`",
    'join_request_date' => "ADD COLUMN `join_request_date` DATETIME DEFAULT NULL AFTER `leave_request_date`",
    'is_viewing_history' => "ADD COLUMN `is_viewing_history` TINYINT(1) DEFAULT 0 AFTER `join_request_date`",
    'history_house_id' => "ADD COLUMN `history_house_id` INT(11) DEFAULT NULL AFTER `is_viewing_history`"
];

foreach ($member_columns as $column => $alter_stmt) {
    if (!columnExists($conn, 'members', $column)) {
        $sql = "ALTER TABLE `members` $alter_stmt";
        if (mysqli_query($conn, $sql)) {
            echo "✓ Added $column to members\n";
        } else {
            echo "✗ Error adding $column: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "✓ $column already exists in members\n";
    }
}

// Modify ENUM column to include 'left' and 'house_inactive' if they exist
echo "\nUpdating ENUM columns...\n";
$sql = "ALTER TABLE `members` MODIFY COLUMN `house_status` 
        ENUM('active', 'pending_leave', 'pending_join', 'left', 'house_inactive') DEFAULT 'active'";
if (mysqli_query($conn, $sql)) {
    echo "✓ Updated house_status ENUM\n";
} else {
    echo "ℹ house_status ENUM already correct or table doesn't exist\n";
}

echo "\nCreating new tables...\n";

// Create member_archive table
if (!tableExists($conn, 'member_archive')) {
    $sql = "CREATE TABLE IF NOT EXISTS `member_archive` (
        `archive_id` INT(11) NOT NULL AUTO_INCREMENT,
        `member_id` INT(11) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `phone` VARCHAR(20) DEFAULT NULL,
        `original_house_id` INT(11) NOT NULL,
        `total_deposits` DECIMAL(10,2) DEFAULT 0.00,
        `total_meals` DECIMAL(10,2) DEFAULT 0.00,
        `total_expenses` DECIMAL(10,2) DEFAULT 0.00,
        `final_balance` DECIMAL(10,2) DEFAULT 0.00,
        `archived_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        `archived_by` INT(11) DEFAULT NULL,
        `archive_reason` VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (`archive_id`),
        KEY `idx_original_house` (`original_house_id`),
        KEY `idx_member` (`member_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created member_archive table\n";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ member_archive table already exists\n";
}

// Create house_transfers_log table
if (!tableExists($conn, 'house_transfers_log')) {
    $sql = "CREATE TABLE IF NOT EXISTS `house_transfers_log` (
        `log_id` INT(11) NOT NULL AUTO_INCREMENT,
        `member_id` INT(11) NOT NULL,
        `from_house_id` INT(11) DEFAULT NULL,
        `to_house_id` INT(11) DEFAULT NULL,
        `action` ENUM('leave_requested', 'leave_approved', 'leave_rejected', 
                      'join_requested', 'join_approved', 'join_rejected',
                      'transferred', 'archived') NOT NULL,
        `performed_by` INT(11) NOT NULL,
        `performed_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        `notes` TEXT DEFAULT NULL,
        PRIMARY KEY (`log_id`),
        KEY `idx_member` (`member_id`),
        KEY `idx_from_house` (`from_house_id`),
        KEY `idx_to_house` (`to_house_id`),
        KEY `idx_action` (`action`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created house_transfers_log table\n";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ house_transfers_log table already exists\n";
}

// Create previous_houses table
if (!tableExists($conn, 'previous_houses')) {
    $sql = "CREATE TABLE IF NOT EXISTS `previous_houses` (
        `history_id` INT(11) NOT NULL AUTO_INCREMENT,
        `member_id` INT(11) NOT NULL,
        `house_id` INT(11) NOT NULL,
        `joined_at` DATETIME NOT NULL,
        `left_at` DATETIME DEFAULT NULL,
        `total_deposits` DECIMAL(10,2) DEFAULT 0.00,
        `total_meals` DECIMAL(10,2) DEFAULT 0.00,
        `total_expenses` DECIMAL(10,2) DEFAULT 0.00,
        `final_balance` DECIMAL(10,2) DEFAULT 0.00,
        `is_active` TINYINT(1) DEFAULT 0,
        PRIMARY KEY (`history_id`),
        KEY `idx_member` (`member_id`),
        KEY `idx_house` (`house_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created previous_houses table\n";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ previous_houses table already exists\n";
}

// Create join_tokens table
if (!tableExists($conn, 'join_tokens')) {
    $sql = "CREATE TABLE IF NOT EXISTS `join_tokens` (
        `token_id` INT(11) NOT NULL AUTO_INCREMENT,
        `token` VARCHAR(100) NOT NULL UNIQUE,
        `house_id` INT(11) NOT NULL,
        `member_id` INT(11) DEFAULT NULL,
        `token_type` ENUM('member_invite', 'house_transfer') DEFAULT 'member_invite',
        `expires_at` DATETIME NOT NULL,
        `is_used` TINYINT(1) DEFAULT 0,
        `used_by` INT(11) DEFAULT NULL,
        `used_at` DATETIME DEFAULT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`token_id`),
        KEY `idx_house` (`house_id`),
        KEY `idx_member` (`member_id`),
        KEY `idx_token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created join_tokens table\n";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ join_tokens table already exists\n";
}

// Create login_attempts table for rate limiting
if (!tableExists($conn, 'login_attempts')) {
    $sql = "CREATE TABLE IF NOT EXISTS `login_attempts` (
        `attempt_id` INT(11) NOT NULL AUTO_INCREMENT,
        `identifier` VARCHAR(255) NOT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `attempts` INT(11) DEFAULT 1,
        `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `locked_until` TIMESTAMP NULL,
        `is_blocked` TINYINT(1) DEFAULT 0,
        PRIMARY KEY (`attempt_id`),
        KEY `idx_identifier` (`identifier`),
        KEY `idx_ip_address` (`ip_address`),
        KEY `idx_locked` (`locked_until`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created login_attempts table\n";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "✓ login_attempts table already exists\n";
}

// Update existing data
echo "\nUpdating existing data...\n";

$sql = "UPDATE `members` SET house_status = 'active' WHERE status = 'active' AND house_status IS NULL";
if (mysqli_query($conn, $sql)) {
    $affected = mysqli_affected_rows($conn);
    echo "✓ Updated $affected members with active status\n";
}

$sql = "UPDATE `houses` SET is_open_for_join = 1 WHERE is_active = 1 AND is_open_for_join IS NULL";
if (mysqli_query($conn, $sql)) {
    $affected = mysqli_affected_rows($conn);
    echo "✓ Updated $affected houses with join status\n";
}

// Create views
echo "\nCreating views...\n";

$views = [
    "v_member_house_history" => "CREATE OR REPLACE VIEW `v_member_house_history` AS
SELECT 
    ph.history_id,
    ph.member_id,
    ph.house_id,
    h.house_name,
    h.house_code,
    ph.joined_at,
    ph.left_at,
    ph.total_deposits,
    ph.total_meals,
    ph.total_expenses,
    ph.final_balance,
    ph.is_active
FROM previous_houses ph
JOIN houses h ON ph.house_id = h.house_id
ORDER BY ph.joined_at DESC",
    
    "v_pending_requests" => "CREATE OR REPLACE VIEW `v_pending_requests` AS
SELECT 
    m.member_id,
    m.name,
    m.email,
    m.phone,
    m.house_id as current_house_id,
    h_current.house_name as current_house_name,
    h_current.house_code as current_house_code,
    m.requested_house_id,
    h_requested.house_name as requested_house_name,
    h_requested.house_code as requested_house_code,
    m.house_status,
    m.leave_request_date,
    m.join_request_date,
    CASE 
        WHEN m.house_status = 'pending_leave' THEN 'Leave Request'
        WHEN m.house_status = 'pending_join' THEN 'Join Request'
        ELSE 'Unknown'
    END as request_type
FROM members m
LEFT JOIN houses h_current ON m.house_id = h_current.house_id
LEFT JOIN houses h_requested ON m.requested_house_id = h_requested.house_id
WHERE m.house_status IN ('pending_leave', 'pending_join')"
];

foreach ($views as $view_name => $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "✓ Created/Updated view: $view_name\n";
    } else {
        echo "✗ Error creating $view_name: " . mysqli_error($conn) . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Migration completed successfully!\n";
echo str_repeat("=", 60) . "\n\n";

// Verification
echo "Verification:\n";
echo str_repeat("-", 40) . "\n";

// Check tables
$tables_to_check = ['member_archive', 'house_transfers_log', 'previous_houses', 'join_tokens'];
foreach ($tables_to_check as $table) {
    if (tableExists($conn, $table)) {
        echo "✓ Table '$table' exists\n";
    } else {
        echo "✗ Table '$table' NOT found\n";
    }
}

// Check columns
$columns_to_check = [
    'houses' => ['is_open_for_join'],
    'members' => ['house_status', 'requested_house_id', 'leave_request_date', 'join_request_date', 'is_viewing_history', 'history_house_id']
];

foreach ($columns_to_check as $table => $columns) {
    foreach ($columns as $column) {
        if (columnExists($conn, $table, $column)) {
            echo "✓ Column '$column' in '$table' exists\n";
        } else {
            echo "✗ Column '$column' in '$table' NOT found\n";
        }
    }
}

// Add database indexes for performance optimization
echo "\nAdding database indexes...\n";

$indexes = [
    "ALTER TABLE `meals` ADD INDEX IF NOT EXISTS `idx_house_date_meal` (`house_id`, `meal_date`, `meal_count`)",
    "ALTER TABLE `meals` ADD INDEX IF NOT EXISTS `idx_member_date` (`member_id`, `meal_date`)",
    "ALTER TABLE `expenses` ADD INDEX IF NOT EXISTS `idx_house_date_amount` (`house_id`, `expense_date`, `amount`)",
    "ALTER TABLE `deposits` ADD INDEX IF NOT EXISTS `idx_member_date` (`member_id`, `deposit_date`)",
    "ALTER TABLE `deposits` ADD INDEX IF NOT EXISTS `idx_house_date` (`house_id`, `deposit_date`)",
    "ALTER TABLE `members` ADD INDEX IF NOT EXISTS `idx_house_status` (`house_id`, `status`)",
    "ALTER TABLE `members` ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`)",
    "ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_username` (`username`)",
    "ALTER TABLE `monthly_summary` ADD INDEX IF NOT EXISTS `idx_house_month` (`house_id`, `month`, `year`)",
    "ALTER TABLE `join_tokens` ADD INDEX IF NOT EXISTS `idx_token_expires` (`token`, `expires_at`)"
];

foreach ($indexes as $index_sql) {
    if (mysqli_query($conn, $index_sql)) {
        // Index created successfully (or already exists)
    } else {
        if (strpos(mysqli_error($conn), 'Duplicate key name') === false) {
            echo "ℹ Index creation: " . mysqli_error($conn) . "\n";
        }
    }
}
echo "✓ Database indexes optimization complete\n";

echo "\n";
mysqli_close($conn);


