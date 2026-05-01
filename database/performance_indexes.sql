-- Performance Indexes & ENUM Fixes (TODO.md P2.1-2.2)
-- Run: mysql -u root -p mealing_online < this_file.sql

-- Meals indexes (frequent range queries by house/date)
ALTER TABLE meals ADD INDEX idx_house_date (house_id, meal_date);
ALTER TABLE meals ADD INDEX idx_house_date_meal (house_id, meal_date, meal_count);
ALTER TABLE meals ADD INDEX idx_member_date (member_id, meal_date);

-- Expenses indexes  
ALTER TABLE expenses ADD INDEX idx_house_date_amount (house_id, expense_date, amount);
ALTER TABLE expenses ADD INDEX idx_date (expense_date);
ALTER TABLE expenses ADD INDEX idx_category (category);

-- Deposits indexes
ALTER TABLE deposits ADD INDEX idx_house_date (house_id, deposit_date);
ALTER TABLE deposits ADD INDEX idx_member_date (member_id, deposit_date);
ALTER TABLE deposits ADD INDEX idx_house_member (house_id, member_id);

-- Members indexes (status filtering, house lookup)
ALTER TABLE members ADD INDEX idx_status_house (status, house_id);
ALTER TABLE members ADD INDEX idx_house_status (house_id, house_status);
ALTER TABLE members ADD INDEX idx_user_house (user_id, house_id);

-- Houses indexes
ALTER TABLE houses ADD INDEX idx_manager (manager_id);
ALTER TABLE houses ADD INDEX idx_active (is_active);

-- Monthly summary indexes
ALTER TABLE monthly_summary ADD INDEX idx_house_year_month (house_id, year, month);

-- Activity logs indexes
ALTER TABLE activity_logs ADD INDEX idx_user_action_date (user_id, action, created_at);
ALTER TABLE activity_logs ADD INDEX idx_date (created_at);

-- Login attempts
ALTER TABLE login_attempts ADD INDEX idx_ip_date (ip_address, last_attempt);
ALTER TABLE login_attempts ADD INDEX idx_locked (locked_until);

-- Verify no duplicates before adding
-- SHOW INDEX FROM meals WHERE Key_name = 'idx_house_date'; etc.

-- Fix ENUM if missing values (TODO P2.2)
SET @current_enum = (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE TABLE_SCHEMA = 'mealing_online' 
                     AND TABLE_NAME = 'members' 
                     AND COLUMN_NAME = 'house_status');
SET @needed_enum = \"ENUM('active','pending_leave','pending_join','left','house_inactive') DEFAULT 'active'\";
UPDATE INFORMATION_SCHEMA.COLUMNS SET COLUMN_TYPE = @needed_enum 
WHERE TABLE_SCHEMA = 'mealing_online' AND TABLE_NAME = 'members' AND COLUMN_NAME = 'house_status' 
AND @current_enum != @needed_enum;
-- Note: Manual ALTER if needed: ALTER TABLE members MODIFY house_status ENUM(...) DEFAULT 'active';

-- Success message
SELECT 'All performance indexes added. Query SHOW INDEX FROM meals; to verify.' as status;

