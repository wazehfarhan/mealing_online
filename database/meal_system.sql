-- Create database if not exists
CREATE DATABASE IF NOT EXISTS meal_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE meal_system;

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 29, 2026 at 07:41 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `meal_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `deposit_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `deposit_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`deposit_id`, `member_id`, `amount`, `deposit_date`, `description`, `created_by`, `created_at`, `updated_at`) VALUES
(16, 12, 100.00, '2026-01-20', '', 2, '2026-01-29 14:53:39', NULL),
(17, 11, 2500.00, '2026-01-25', '', 2, '2026-01-29 14:56:22', '2026-01-29 14:56:45'),
(18, 12, 1000.00, '2026-01-22', '', 2, '2026-01-29 17:06:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` enum('Rice','Fish','Meat','Vegetables','Gas','Internet','Utility','Others') NOT NULL DEFAULT 'Others',
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `amount`, `category`, `description`, `expense_date`, `created_by`, `created_at`, `updated_at`) VALUES
(13, 100.00, 'Meat', '', '2026-01-29', 2, '2026-01-29 13:58:38', '2026-01-29 14:39:11'),
(14, 300.00, 'Vegetables', '', '2026-01-29', 2, '2026-01-29 13:58:45', '2026-01-29 14:39:11'),
(15, 200.00, 'Utility', '', '2026-01-29', 2, '2026-01-29 13:58:52', '2026-01-29 14:39:11'),
(16, 150.00, 'Vegetables', '', '2026-01-29', 2, '2026-01-29 13:58:59', '2026-01-29 14:39:11'),
(17, 1200.00, 'Internet', '', '2026-01-29', 2, '2026-01-29 13:59:14', '2026-01-29 14:39:11'),
(18, 450.00, 'Gas', '', '2026-01-29', 2, '2026-01-29 13:59:22', '2026-01-29 14:40:12'),
(19, 1000.00, 'Rice', '', '2026-01-18', 2, '2026-01-29 17:06:45', '2026-01-29 17:06:45');

-- --------------------------------------------------------

--
-- Table structure for table `meals`
--

CREATE TABLE `meals` (
  `meal_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `meal_date` date NOT NULL,
  `meal_count` decimal(4,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meals`
--

INSERT INTO `meals` (`meal_id`, `member_id`, `meal_date`, `meal_count`, `created_at`, `created_by`, `updated_by`, `updated_at`) VALUES
(25, 12, '2026-01-11', 3.00, '2026-01-29 14:51:20', 2, NULL, NULL),
(26, 11, '2026-01-11', 3.00, '2026-01-29 14:51:20', 2, NULL, NULL),
(27, 12, '2026-01-14', 1.00, '2026-01-29 14:51:29', 2, NULL, NULL),
(28, 11, '2026-01-14', 2.00, '2026-01-29 14:51:29', 2, NULL, NULL),
(29, 12, '2026-01-15', 1.00, '2026-01-29 14:51:39', 2, NULL, '2026-01-29 14:51:53'),
(30, 11, '2026-01-15', 1.00, '2026-01-29 14:51:39', 2, NULL, '2026-01-29 14:51:53'),
(31, 12, '2026-01-22', 3.00, '2026-01-29 17:07:05', 2, NULL, NULL),
(32, 11, '2026-01-22', 2.00, '2026-01-29 17:07:05', 2, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `join_date` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `join_token` varchar(100) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`member_id`, `name`, `phone`, `email`, `join_date`, `status`, `created_by`, `join_token`, `token_expiry`, `created_at`) VALUES
(11, 'jubaida', '1122', 'jubaida@gmail.com', '2026-01-29', 'active', 2, 'ebe13471237d3a87e06fa8ba458c9ea33a2b2f5275df799b12979197ac24b545', '2026-02-05 15:48:27', '2026-01-29 14:48:27'),
(12, 'anik', '3344', 'anik@gmail.com', '2026-01-22', 'active', 2, '2c202c3a610b0a1f3d3a26df8c84dd12c6f568699104868cf82a2afbb77e42fe', '2026-02-05 15:48:39', '2026-01-29 14:48:39');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_member_details`
--

CREATE TABLE `monthly_member_details` (
  `detail_id` int(11) NOT NULL,
  `summary_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `total_meals` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_deposits` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_summary`
--

CREATE TABLE `monthly_summary` (
  `summary_id` int(11) NOT NULL,
  `month_year` date NOT NULL,
  `total_meals` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(10,2) NOT NULL DEFAULT 0.00,
  `meal_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_closed` tinyint(1) DEFAULT 0,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('manager','member') NOT NULL DEFAULT 'member',
  `member_id` int(11) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `member_id`, `last_login`, `created_at`, `is_active`) VALUES
(1, 'admin', 'admin@mealsystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', NULL, NULL, '2026-01-28 21:22:25', 1),
(2, 'farhan', 'wzullah.farhan@gmail.com', '$2y$10$eBdStkTyHSYajZB7SDcg8eOHjOxYV/pp5trlziWIgAeJaKfDpSJk.', 'manager', NULL, '2026-01-29 17:24:04', '2026-01-28 22:18:51', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`deposit_id`),
  ADD KEY `idx_deposit_date` (`deposit_date`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `idx_expense_date` (`expense_date`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `meals`
--
ALTER TABLE `meals`
  ADD PRIMARY KEY (`meal_id`),
  ADD UNIQUE KEY `unique_member_meal` (`member_id`,`meal_date`),
  ADD KEY `idx_meal_date` (`meal_date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_meals_updated_by` (`updated_by`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `join_token` (`join_token`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `monthly_member_details`
--
ALTER TABLE `monthly_member_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD UNIQUE KEY `unique_member_summary` (`summary_id`,`member_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `monthly_summary`
--
ALTER TABLE `monthly_summary`
  ADD PRIMARY KEY (`summary_id`),
  ADD UNIQUE KEY `unique_month` (`month_year`),
  ADD KEY `closed_by` (`closed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `deposit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `meals`
--
ALTER TABLE `meals`
  MODIFY `meal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `monthly_member_details`
--
ALTER TABLE `monthly_member_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_summary`
--
ALTER TABLE `monthly_summary`
  MODIFY `summary_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deposits_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `meals`
--
ALTER TABLE `meals`
  ADD CONSTRAINT `fk_meals_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `meals_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meals_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `monthly_member_details`
--
ALTER TABLE `monthly_member_details`
  ADD CONSTRAINT `monthly_member_details_ibfk_1` FOREIGN KEY (`summary_id`) REFERENCES `monthly_summary` (`summary_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `monthly_member_details_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_summary`
--
ALTER TABLE `monthly_summary`
  ADD CONSTRAINT `monthly_summary_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;








