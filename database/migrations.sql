-- =====================================
-- Meal Management System - Database Migrations
-- =====================================

-- ============================
-- Part 1: Modify ENUM columns
-- ============================

-- Update house_status ENUM in members table to include 'left' and 'house_inactive'
ALTER TABLE `members` MODIFY COLUMN `house_status` ENUM('active', 'pending_leave', 'pending_join', 'left', 'house_inactive') DEFAULT 'active';

-- ============================
-- Part 2: Create login_attempts table for rate limiting
-- ============================

CREATE TABLE IF NOT EXISTS `login_attempts` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================
-- Part 3: Add Database Indexes for Performance
-- ============================

-- Meals table indexes
ALTER TABLE `meals` ADD INDEX IF NOT EXISTS `idx_house_date_meal` (`house_id`, `meal_date`, `meal_count`);
ALTER TABLE `meals` ADD INDEX IF NOT EXISTS `idx_member_date` (`member_id`, `meal_date`);

-- Expenses table indexes  
ALTER TABLE `expenses` ADD INDEX IF NOT EXISTS `idx_house_date_amount` (`house_id`, `expense_date`, `amount`);

-- Deposits table indexes
ALTER TABLE `deposits` ADD INDEX IF NOT EXISTS `idx_member_date` (`member_id`, `deposit_date`);
ALTER TABLE `deposits` ADD INDEX IF NOT EXISTS `idx_house_date` (`house_id`, `deposit_date`);

-- Members table indexes
ALTER TABLE `members` ADD INDEX IF NOT EXISTS `idx_house_status` (`house_id`, `status`);
ALTER TABLE `members` ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`);

-- Users table indexes
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_username` (`username`);

-- Monthly summary table indexes
ALTER TABLE `monthly_summary` ADD INDEX IF NOT EXISTS `idx_house_month` (`house_id`, `month`, `year`);

-- Join tokens table indexes
ALTER TABLE `join_tokens` ADD INDEX IF NOT EXISTS `idx_token_expires` (`token`, `expires_at`);

-- ============================
-- Part 4: Verification
-- ============================
SELECT 'Migration completed successfully!' as status;
