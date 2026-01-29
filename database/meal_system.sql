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

-- If you want to add updated_at column
ALTER TABLE expenses ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

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
(18, 450.00, 'Gas', '', '2026-01-29', 2, '2026-01-29 13:59:22', '2026-01-29 14:40:12');

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
  MODIFY `deposit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `meals`
--
ALTER TABLE `meals`
  MODIFY `meal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
