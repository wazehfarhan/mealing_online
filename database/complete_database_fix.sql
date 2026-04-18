-- =====================================
-- COMPREHENSIVE DATABASE FIX
-- =====================================
-- This script fixes all missing tables and columns
-- =====================================

-- Step 1: Add missing columns to members table
ALTER TABLE `members` ADD COLUMN `join_token` VARCHAR(100) UNIQUE DEFAULT NULL;
ALTER TABLE `members` ADD COLUMN `token_expiry` DATETIME DEFAULT NULL;

-- Step 2: Create member_archive table
CREATE TABLE IF NOT EXISTS `member_archive` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Create house_transfers_log table
CREATE TABLE IF NOT EXISTS `house_transfers_log` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Create previous_houses table
CREATE TABLE IF NOT EXISTS `previous_houses` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 5: Create join_tokens table
CREATE TABLE IF NOT EXISTS `join_tokens` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================
-- Verification
-- =====================================
SELECT 'Database Fixes Applied Successfully!' as status;
