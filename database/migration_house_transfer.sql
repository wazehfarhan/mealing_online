-- House Transfer System - Database Migration
-- This script adds the necessary columns and tables for house transfer functionality

USE meal_system;

-- ============================================
-- Phase 1: Add columns to existing tables
-- ============================================

-- Add is_open_for_join column to houses table
-- This allows managers to control whether their house can be joined
ALTER TABLE `houses` 
ADD COLUMN `is_open_for_join` TINYINT(1) DEFAULT 1 AFTER `is_active`;

-- Add status column to members table for transfer tracking
-- Values: 'active', 'pending_leave', 'pending_join', 'inactive'
ALTER TABLE `members` 
ADD COLUMN `house_status` ENUM('active', 'pending_leave', 'pending_join') DEFAULT 'active' AFTER `status`;

-- Add requested_house_id column for join requests
ALTER TABLE `members` 
ADD COLUMN `requested_house_id` INT(11) DEFAULT NULL AFTER `house_id`;

-- Add dates for request tracking
ALTER TABLE `members` 
ADD COLUMN `leave_request_date` DATETIME DEFAULT NULL AFTER `token_expiry`,
ADD COLUMN `join_request_date` DATETIME DEFAULT NULL AFTER `leave_request_date`;

-- Add is_viewing_history column for viewing past house data
ALTER TABLE `members` 
ADD COLUMN `is_viewing_history` TINYINT(1) DEFAULT 0 AFTER `join_request_date`,
ADD COLUMN `history_house_id` INT(11) DEFAULT NULL AFTER `is_viewing_history`;

-- ============================================
-- Phase 2: Create new tables
-- ============================================

-- Table: member_archive
-- Stores historical data of members who left houses
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

-- Table: house_transfers_log
-- Logs all house transfer actions for audit trail
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

-- Table: previous_houses
-- Tracks all houses a member has been part of
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

-- Table: join_tokens
-- Manages tokens for joining existing houses (not new member registration)
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

-- ============================================
-- Phase 3: Update existing data
-- ============================================

-- Set all existing active members to have house_status = 'active'
UPDATE `members` SET house_status = 'active' WHERE status = 'active';

-- Set is_open_for_join = 1 for all existing houses
UPDATE `houses` SET is_open_for_join = 1 WHERE is_active = 1;

-- ============================================
-- Phase 4: Create Views for reporting
-- ============================================

-- View: v_member_house_history
-- Shows all houses a member has been part of
CREATE OR REPLACE VIEW `v_member_house_history` AS
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
ORDER BY ph.joined_at DESC;

-- View: v_pending_requests
-- Shows all pending house transfer requests
CREATE OR REPLACE VIEW `v_pending_requests` AS
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
WHERE m.house_status IN ('pending_leave', 'pending_join');

-- ============================================
-- Phase 5: Create Triggers for automation
-- ============================================

-- Trigger: Auto-update leave_request_date when status changes to pending_leave
DELIMITER $$
CREATE TRIGGER `members_before_update_leave`
BEFORE UPDATE ON `members`
FOR EACH ROW
BEGIN
    IF OLD.house_status != 'pending_leave' AND NEW.house_status = 'pending_leave' THEN
        SET NEW.leave_request_date = NOW();
    END IF;
    
    IF OLD.house_status != 'pending_join' AND NEW.house_status = 'pending_join' THEN
        SET NEW.join_request_date = NOW();
    END IF;
END$$
DELIMITER ;

-- Trigger: Clear request dates when status changes back to active
DELIMITER $$
CREATE TRIGGER `members_before_update_active`
BEFORE UPDATE ON `members`
FOR EACH ROW
BEGIN
    IF NEW.house_status = 'active' AND OLD.house_status != 'active' THEN
        SET NEW.leave_request_date = NULL;
        SET NEW.join_request_date = NULL;
        SET NEW.requested_house_id = NULL;
    END IF;
END$$
DELIMITER ;

-- ============================================
-- Phase 6: Insert sample data for testing
-- ============================================

-- Insert a sample join token for testing (will be used by managers)
-- This is just an example - managers will generate their own tokens
-- INSERT INTO `join_tokens` (`token`, `house_id`, `token_type`, `expires_at`, `created_by`) 
-- VALUES (SHA2('TESTTOKEN123', 256), 1, 'member_invite', DATE_ADD(NOW(), INTERVAL 7 DAY), 1);

-- ============================================
-- Verification Queries
-- ============================================

-- Check if columns were added successfully
-- SELECT column_name FROM information_schema.columns 
-- WHERE table_schema = 'meal_system' AND table_name = 'houses';

-- SELECT column_name FROM information_schema.columns 
-- WHERE table_schema = 'meal_system' AND table_name = 'members';

-- Check if new tables were created
-- SHOW TABLES LIKE 'member_archive';
-- SHOW TABLES LIKE 'house_transfers_log';
-- SHOW TABLES LIKE 'previous_houses';
-- SHOW TABLES LIKE 'join_tokens';

-- Check views exist
-- SHOW FULL TABLES FROM meal_system WHERE Table_type = 'VIEW' AND Tables_in_meal_system LIKE 'v_%';

COMMIT;

