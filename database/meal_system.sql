-- Create database if not exists
CREATE DATABASE IF NOT EXISTS meal_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE meal_system;

-- Users table (for authentication)
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('manager', 'member') NOT NULL DEFAULT 'member',
    member_id INT DEFAULT NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Members table
CREATE TABLE IF NOT EXISTS members (
    member_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    join_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT DEFAULT NULL,
    join_token VARCHAR(100) UNIQUE,
    token_expiry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meals table
CREATE TABLE IF NOT EXISTS meals (
    meal_id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    meal_date DATE NOT NULL,
    meal_count DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    UNIQUE KEY unique_member_meal (member_id, meal_date),
    INDEX idx_meal_date (meal_date),
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expenses table
CREATE TABLE IF NOT EXISTS expenses (
    expense_id INT PRIMARY KEY AUTO_INCREMENT,
    amount DECIMAL(10,2) NOT NULL,
    category ENUM('Rice', 'Fish', 'Meat', 'Vegetables', 'Gas', 'Internet', 'Utility', 'Others') NOT NULL DEFAULT 'Others',
    description TEXT,
    expense_date DATE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expense_date (expense_date),
    INDEX idx_category (category),
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deposits table
CREATE TABLE IF NOT EXISTS deposits (
    deposit_id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    deposit_date DATE NOT NULL,
    description VARCHAR(255),
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_deposit_date (deposit_date),
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly summary table
CREATE TABLE IF NOT EXISTS monthly_summary (
    summary_id INT PRIMARY KEY AUTO_INCREMENT,
    month_year DATE NOT NULL,
    total_meals DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_expenses DECIMAL(10,2) NOT NULL DEFAULT 0,
    meal_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_closed BOOLEAN DEFAULT FALSE,
    closed_by INT DEFAULT NULL,
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_month (month_year),
    FOREIGN KEY (closed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly member details
CREATE TABLE IF NOT EXISTS monthly_member_details (
    detail_id INT PRIMARY KEY AUTO_INCREMENT,
    summary_id INT NOT NULL,
    member_id INT NOT NULL,
    total_meals DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_deposits DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_member_summary (summary_id, member_id),
    FOREIGN KEY (summary_id) REFERENCES monthly_summary(summary_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Check if updated_at column exists, if not, add it
ALTER TABLE deposits 
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL 
AFTER created_at;

-- Or create the full table if needed
CREATE TABLE IF NOT EXISTS deposits (
    deposit_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    deposit_date DATE NOT NULL,
    description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Insert default manager (password: admin123)
-- Using password_hash('admin123', PASSWORD_DEFAULT)
INSERT IGNORE INTO users (username, email, password, role, is_active) VALUES 
('admin', 'admin@mealsystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1);

-- Create test members for demo
INSERT IGNORE INTO members (name, phone, email, join_date, status) VALUES
('John Doe', '01712345678', 'john@example.com', CURDATE(), 'active'),
('Jane Smith', '01898765432', 'jane@example.com', CURDATE(), 'active'),
('Bob Johnson', '01911223344', 'bob@example.com', CURDATE(), 'active');

-- Create test meals
INSERT IGNORE INTO meals (member_id, meal_date, meal_count) VALUES
(1, CURDATE(), 2.0),
(2, CURDATE(), 1.5),
(3, CURDATE(), 1.0),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 1.5),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 2.0);

-- Create test expenses
INSERT IGNORE INTO expenses (amount, category, description, expense_date, created_by) VALUES
(1500.00, 'Rice', 'Monthly rice purchase', CURDATE(), 1),
(800.00, 'Fish', 'Fish for 3 days', CURDATE(), 1),
(500.00, 'Gas', 'Cooking gas refill', CURDATE(), 1),
(1200.00, 'Vegetables', 'Weekly vegetables', CURDATE(), 1);

-- Create test deposits
INSERT IGNORE INTO deposits (member_id, amount, deposit_date, description) VALUES
(1, 2000.00, CURDATE(), 'Monthly deposit'),
(2, 2000.00, CURDATE(), 'Monthly deposit'),
(3, 2000.00, CURDATE(), 'Monthly deposit');