-- =====================================
-- ADDITIONAL DATABASE FIXES
-- =====================================

-- Step 1: Add missing manager_id column to houses table
ALTER TABLE `houses` ADD COLUMN `manager_id` INT(11) DEFAULT NULL AFTER `created_by`;
ALTER TABLE `houses` ADD KEY `idx_manager` (`manager_id`);

-- Step 2: Create activity_logs table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `log_id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`log_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Additional Database Fixes Applied!' as status;
