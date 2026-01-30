-- Meal Management System Database Schema
-- Version: 1.0
-- Created: 2026-01-28

-- Drop database if exists (optional - uncomment if needed)
-- DROP DATABASE IF EXISTS proper_system;

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS meal_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Use the database
USE meal_system;

-- --------------------------------------------------------
-- Table structure for table `houses`
-- --------------------------------------------------------

CREATE TABLE `houses` (
  `house_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_name` varchar(100) NOT NULL,
  `house_code` varchar(20) NOT NULL UNIQUE,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`house_id`),
  UNIQUE KEY `unique_house_code` (`house_code`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','manager','member') NOT NULL DEFAULT 'member',
  `house_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `members`
-- --------------------------------------------------------

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `join_date` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `join_token` varchar(100) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `join_token` (`join_token`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `expenses`
-- --------------------------------------------------------

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` enum('Rice','Fish','Meat','Vegetables','Gas','Internet','Utility','Others') NOT NULL DEFAULT 'Others',
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`expense_id`),
  KEY `idx_expense_date` (`expense_date`),
  KEY `idx_category` (`category`),
  KEY `created_by` (`created_by`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `meals`
-- --------------------------------------------------------

CREATE TABLE `meals` (
  `meal_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `meal_date` date NOT NULL,
  `meal_count` decimal(4,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`meal_id`),
  UNIQUE KEY `unique_member_meal` (`house_id`, `member_id`, `meal_date`),
  KEY `idx_meal_date` (`meal_date`),
  KEY `created_by` (`created_by`),
  KEY `fk_meals_updated_by` (`updated_by`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `deposits`
-- --------------------------------------------------------

CREATE TABLE `deposits` (
  `deposit_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `deposit_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`deposit_id`),
  KEY `idx_deposit_date` (`deposit_date`),
  KEY `member_id` (`member_id`),
  KEY `created_by` (`created_by`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `monthly_summary`
-- --------------------------------------------------------

CREATE TABLE `monthly_summary` (
  `summary_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `month_year` date NOT NULL,
  `total_meals` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(10,2) NOT NULL DEFAULT 0.00,
  `meal_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_closed` tinyint(1) DEFAULT 0,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`summary_id`),
  UNIQUE KEY `unique_month` (`house_id`, `month_year`),
  KEY `closed_by` (`closed_by`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `monthly_member_details`
-- --------------------------------------------------------

CREATE TABLE `monthly_member_details` (
  `detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `summary_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `total_meals` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_deposits` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`detail_id`),
  UNIQUE KEY `unique_member_summary` (`summary_id`, `member_id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Foreign Key Constraints
-- --------------------------------------------------------

-- Houses table constraints
ALTER TABLE `houses`
  ADD CONSTRAINT `houses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

-- Users table constraints
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE SET NULL;

-- Members table constraints
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `members_ibfk_2` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Expenses table constraints
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Meals table constraints
ALTER TABLE `meals`
  ADD CONSTRAINT `fk_meals_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `meals_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meals_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `meals_ibfk_3` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Deposits table constraints
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deposits_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `deposits_ibfk_3` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Monthly summary table constraints
ALTER TABLE `monthly_summary`
  ADD CONSTRAINT `monthly_summary_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `monthly_summary_ibfk_2` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Monthly member details table constraints
ALTER TABLE `monthly_member_details`
  ADD CONSTRAINT `monthly_member_details_ibfk_1` FOREIGN KEY (`summary_id`) REFERENCES `monthly_summary` (`summary_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `monthly_member_details_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE;

-- --------------------------------------------------------
-- Insert default super admin user
-- Password: password (hashed with bcrypt)
-- --------------------------------------------------------

INSERT INTO `users` (`username`, `email`, `password`, `role`, `is_active`) VALUES
('superadmin', 'superadmin@mealsystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1);

-- --------------------------------------------------------
-- Insert default house for testing
-- --------------------------------------------------------

INSERT INTO `houses` (`house_name`, `house_code`, `description`, `created_by`) VALUES
('Main House', 'MAIN001', 'Main living house for the system', 1);

-- --------------------------------------------------------
-- Insert default admin user
-- Password: admin123 (hashed with bcrypt)
-- --------------------------------------------------------

INSERT INTO `users` (`username`, `email`, `password`, `role`, `house_id`, `is_active`) VALUES
('admin', 'admin@mealsystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1, 1);

-- --------------------------------------------------------
-- Database triggers (optional)
-- --------------------------------------------------------

-- Trigger to update updated_at timestamp for deposits
DELIMITER $$
CREATE TRIGGER `deposits_before_update`
BEFORE UPDATE ON `deposits`
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP();
END$$
DELIMITER ;

-- --------------------------------------------------------
-- Create database user for the application
-- Note: This part might need adjustment based on your hosting environment
-- --------------------------------------------------------

-- Uncomment and modify these lines if you want to create a dedicated database user
/*
CREATE USER 'meal_app'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL PRIVILEGES ON proper_system.* TO 'meal_app'@'localhost';
FLUSH PRIVILEGES;
*/

-- --------------------------------------------------------
-- Create views for reporting (optional)
-- --------------------------------------------------------

-- View for monthly report
CREATE VIEW `v_monthly_report` AS
SELECT 
    ms.summary_id,
    ms.house_id,
    DATE_FORMAT(ms.month_year, '%Y-%m') as month_year,
    ms.total_meals,
    ms.total_expenses,
    ms.meal_rate,
    COUNT(DISTINCT mmd.member_id) as total_members,
    SUM(mmd.balance) as total_balance
FROM monthly_summary ms
LEFT JOIN monthly_member_details mmd ON ms.summary_id = mmd.summary_id
GROUP BY ms.summary_id, ms.house_id, ms.month_year, ms.total_meals, ms.total_expenses, ms.meal_rate;

-- View for member details with house information
CREATE VIEW `v_member_details` AS
SELECT 
    m.member_id,
    m.house_id,
    m.name,
    m.phone,
    m.email,
    m.join_date,
    m.status,
    h.house_name,
    h.house_code
FROM members m
JOIN houses h ON m.house_id = h.house_id
WHERE h.is_active = 1;

COMMIT;