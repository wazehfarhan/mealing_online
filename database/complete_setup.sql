-- =====================================
-- Complete Meal Management System Database Setup
-- =====================================

-- ========================================
-- 1. Create Users Table
-- ========================================

CREATE TABLE IF NOT EXISTS `users` (
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
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 2. Create Members Table
-- ========================================

CREATE TABLE IF NOT EXISTS `members` (
  `member_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `house_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active', 'inactive','left') NOT NULL DEFAULT 'active',
  `house_status` enum('active', 'pending_leave', 'pending_join', 'left', 'house_inactive') DEFAULT 'active',
  `requested_house_id` int(11) DEFAULT NULL,
  `leave_request_date` datetime DEFAULT NULL,
  `join_request_date` datetime DEFAULT NULL,
  `is_viewing_history` tinyint(1) DEFAULT 0,
  `history_house_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`member_id`),
  KEY `house_id` (`house_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 3. Create Meals Table
-- ========================================

CREATE TABLE IF NOT EXISTS `meals` (
  `meal_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `meal_date` date NOT NULL,
  `meal_count` decimal(10,2) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`meal_id`),
  KEY `house_id` (`house_id`),
  KEY `member_id` (`member_id`),
  KEY `meal_date` (`meal_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 4. Create Expenses Table
-- ========================================

CREATE TABLE IF NOT EXISTS `expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`expense_id`),
  KEY `house_id` (`house_id`),
  KEY `expense_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 5. Create Deposits Table
-- ========================================

CREATE TABLE IF NOT EXISTS `deposits` (
  `deposit_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `deposit_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`deposit_id`),
  KEY `house_id` (`house_id`),
  KEY `member_id` (`member_id`),
  KEY `deposit_date` (`deposit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 6. Create Monthly Summary Table
-- ========================================

CREATE TABLE IF NOT EXISTS `monthly_summary` (
  `summary_id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `month` int(2) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `total_expenses` decimal(10,2) DEFAULT 0,
  `total_meals` decimal(10,2) DEFAULT 0,
  `meal_rate` decimal(10,2) DEFAULT 0,
  `is_closed` tinyint(1) DEFAULT 0,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`summary_id`),
  KEY `house_id` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 7. Create Monthly Member Details Table  
-- ========================================

CREATE TABLE IF NOT EXISTS `monthly_member_details` (
  `detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `summary_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `total_meals` decimal(10,2) DEFAULT 0,
  `total_deposits` decimal(10,2) DEFAULT 0,
  `total_cost` decimal(10,2) DEFAULT 0,
  `balance` decimal(10,2) DEFAULT 0,
  PRIMARY KEY (`detail_id`),
  KEY `summary_id` (`summary_id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 8. Create Login Attempts Table (Rate Limiting)
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
  KEY `idx_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- 9. Add Database Indexes for Performance
-- ========================================

ALTER TABLE `meals` ADD KEY `idx_house_date_meal` (`house_id`, `meal_date`, `meal_count`);
ALTER TABLE `meals` ADD KEY `idx_member_date` (`member_id`, `meal_date`);

ALTER TABLE `expenses` ADD KEY `idx_house_date_amount` (`house_id`, `expense_date`, `amount`);

ALTER TABLE `deposits` ADD KEY `idx_member_date` (`member_id`, `deposit_date`);
ALTER TABLE `deposits` ADD KEY `idx_house_date` (`house_id`, `deposit_date`);

ALTER TABLE `members` ADD KEY `idx_house_status` (`house_id`, `status`);
ALTER TABLE `members` ADD KEY `idx_user_id` (`user_id`);

ALTER TABLE `users` ADD KEY `idx_username` (`username`);

ALTER TABLE `monthly_summary` ADD KEY `idx_house_month` (`house_id`, `month`, `year`);

-- ========================================
-- Setup Complete
-- ========================================
SELECT 'Database setup completed successfully!' as status;
