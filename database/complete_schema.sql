-- =====================================================================
-- Mealing Online - Consolidated Database Schema
-- =====================================================================
-- This file contains the COMPLETE schema for new installations.
-- It consolidates the base schema (meal_system.sql) with all migrations:
--   - migration_house_transfer.sql
--   - migrations.sql
--   - additional_fixes.sql
--   - dashboard_cache.sql
--   - complete_database_fix.sql
-- 
-- For EXISTING installations, run the individual migration files instead.
-- =====================================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `mealing_online`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `mealing_online`;

-- ========================================
-- 1. Houses Table
-- ========================================

CREATE TABLE IF NOT EXISTS `houses` (
  `house_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_name` varchar(100) NOT NULL,
  `house_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `is_open_for_join` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`house_id`),
  UNIQUE KEY `unique_house_code` (`house_code`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 2. Users Table
-- ========================================

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','manager','member') NOT NULL DEFAULT 'member',
  `house_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
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

-- ========================================
-- 3. Members Table
-- ========================================

CREATE TABLE IF NOT EXISTS `members` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `join_date` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `house_status` enum('active','pending_leave','pending_join','left','house_inactive') DEFAULT 'active',
  `requested_house_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `join_token` varchar(100) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `leave_request_date` datetime DEFAULT NULL,
  `join_request_date` datetime DEFAULT NULL,
  `is_viewing_history` tinyint(1) DEFAULT 0,
  `history_house_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`member_id`),
  UNIQUE KEY `join_token` (`join_token`),
  KEY `idx_status` (`status`),
  KEY `idx_house_status` (`house_id`, `house_status`),
  KEY `created_by` (`created_by`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 4. Expenses Table
-- ========================================

CREATE TABLE IF NOT EXISTS `expenses` (
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
  KEY `idx_house_date_amount` (`house_id`, `expense_date`, `amount`),
  KEY `created_by` (`created_by`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 5. Meals Table
-- ========================================

CREATE TABLE IF NOT EXISTS `meals` (
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
  KEY `idx_house_date_meal` (`house_id`, `meal_date`, `meal_count`),
  KEY `idx_member_date` (`member_id`, `meal_date`),
  KEY `created_by` (`created_by`),
  KEY `fk_meals_updated_by` (`updated_by`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 6. Deposits Table
-- ========================================

CREATE TABLE IF NOT EXISTS `deposits` (
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
  KEY `idx_member_date` (`member_id`, `deposit_date`),
  KEY `idx_house_date` (`house_id`, `deposit_date`),
  KEY `member_id` (`member_id`),
  KEY `created_by` (`created_by`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 7. Monthly Summary Table
-- ========================================

CREATE TABLE IF NOT EXISTS `monthly_summary` (
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

-- ========================================
-- 8. Monthly Member Details Table
-- ========================================

CREATE TABLE IF NOT EXISTS `monthly_member_details` (
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

-- ========================================
-- 9. Login Attempts Table (Rate Limiting)
-- ========================================

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `locked_until` timestamp NULL,
  `is_blocked` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`attempt_id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_locked` (`locked_until`),
  KEY `idx_ip_date` (`ip_address`, `last_attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 10. Activity Logs Table
-- ========================================

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user_action_date` (`user_id`, `action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 11. Dashboard Cache Table
-- ========================================

CREATE TABLE IF NOT EXISTS `dashboard_cache` (
  `cache_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `cache_data` longtext NOT NULL COMMENT 'JSON encoded stats',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`cache_id`),
  UNIQUE KEY `unique_house_month_year` (`house_id`, `month`, `year`),
  KEY `idx_house_expires` (`house_id`, `expires_at`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 12. Member Archive Table
-- ========================================

CREATE TABLE IF NOT EXISTS `member_archive` (
  `archive_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `original_house_id` int(11) NOT NULL,
  `total_deposits` decimal(10,2) DEFAULT 0.00,
  `total_meals` decimal(10,2) DEFAULT 0.00,
  `total_expenses` decimal(10,2) DEFAULT 0.00,
  `final_balance` decimal(10,2) DEFAULT 0.00,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_by` int(11) DEFAULT NULL,
  `archive_reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`archive_id`),
  KEY `idx_original_house` (`original_house_id`),
  KEY `idx_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 13. House Transfers Log Table
-- ========================================

CREATE TABLE IF NOT EXISTS `house_transfers_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `from_house_id` int(11) DEFAULT NULL,
  `to_house_id` int(11) DEFAULT NULL,
  `action` enum('leave_requested','leave_approved','leave_rejected',
                'join_requested','join_approved','join_rejected',
                'transferred','archived') NOT NULL,
  `performed_by` int(11) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_from_house` (`from_house_id`),
  KEY `idx_to_house` (`to_house_id`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 14. Previous Houses Table (Member History)
-- ========================================

CREATE TABLE IF NOT EXISTS `previous_houses` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `house_id` int(11) NOT NULL,
  `joined_at` datetime NOT NULL,
  `left_at` datetime DEFAULT NULL,
  `total_deposits` decimal(10,2) DEFAULT 0.00,
  `total_meals` decimal(10,2) DEFAULT 0.00,
  `total_expenses` decimal(10,2) DEFAULT 0.00,
  `final_balance` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`history_id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_house` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 15. Join Tokens Table
-- ========================================

CREATE TABLE IF NOT EXISTS `join_tokens` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(100) NOT NULL,
  `house_id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `token_type` enum('member_invite','house_transfer') DEFAULT 'member_invite',
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `used_by` int(11) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_house` (`house_id`),
  KEY `idx_member` (`member_id`),
  KEY `idx_token` (`token`),
  KEY `idx_token_expires` (`token`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Foreign Key Constraints
-- ========================================

-- Houses
ALTER TABLE `houses`
  ADD CONSTRAINT `fk_houses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

-- Users
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE SET NULL;

-- Members
ALTER TABLE `members`
  ADD CONSTRAINT `fk_members_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_members_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Expenses
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_expenses_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Meals
ALTER TABLE `meals`
  ADD CONSTRAINT `fk_meals_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_meals_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_meals_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_meals_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Deposits
ALTER TABLE `deposits`
  ADD CONSTRAINT `fk_deposits_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_deposits_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_deposits_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Monthly Summary
ALTER TABLE `monthly_summary`
  ADD CONSTRAINT `fk_summary_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_summary_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE;

-- Monthly Member Details
ALTER TABLE `monthly_member_details`
  ADD CONSTRAINT `fk_details_summary` FOREIGN KEY (`summary_id`) REFERENCES `monthly_summary` (`summary_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_details_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE;

-- ========================================
-- Triggers
-- ========================================

-- Trigger: Auto-update deposit timestamps
DELIMITER $$
CREATE TRIGGER `deposits_before_update`
BEFORE UPDATE ON `deposits`
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP();
END$$
DELIMITER ;

-- ========================================
-- Default Data
-- ========================================

-- Default super admin user (password: password)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `is_active`) VALUES
('superadmin', 'superadmin@mealsystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1);

-- Default house
INSERT INTO `houses` (`house_name`, `house_code`, `description`, `created_by`) VALUES
('Main House', 'MAIN001', 'Main living house for the system', 1);

-- Default manager user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `role`, `house_id`, `is_active`) VALUES
('admin', 'admin@mealsystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1, 1);

-- ========================================
-- Views
-- ========================================

-- Monthly report view
CREATE OR REPLACE VIEW `v_monthly_report` AS
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

-- Member details with house info view
CREATE OR REPLACE VIEW `v_member_details` AS
SELECT 
    m.member_id,
    m.house_id,
    m.name,
    m.phone,
    m.email,
    m.join_date,
    m.status,
    m.house_status,
    h.house_name,
    h.house_code
FROM members m
JOIN houses h ON m.house_id = h.house_id
WHERE h.is_active = 1;

-- Pending requests view
CREATE OR REPLACE VIEW `v_pending_requests` AS
SELECT 
    m.member_id,
    m.name,
    m.email,
    m.phone,
    m.house_id as current_house_id,
    h_current.house_name as current_house_name,
    m.requested_house_id,
    h_requested.house_name as requested_house_name,
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
WHERE m.house_status IN ('pending_leave', 'pending_join');

-- ========================================
-- Setup Complete
-- ========================================
SELECT 'Consolidated schema installed successfully!' as status;

COMMIT;
