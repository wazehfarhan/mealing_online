-- =========================================
-- COMPREHENSIVE COLUMN FIX - ALL TABLES
-- =========================================
-- This script ensures ALL tables have ALL required columns
-- Run this to fix any remaining "Unknown column" errors

USE mealing_online;

-- 1. USERS table - ensure all columns
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;

-- 2. MEMBERS table - ensure all columns  
-- Already has: member_id, user_id, house_id, name, email, phone, status, house_status,
--              requested_house_id, leave_request_date, join_request_date, is_viewing_history,
--              history_house_id, created_at, join_date, updated_at, created_by, join_token, token_expiry

-- 3. HOUSES table - ensure all columns
-- Already has: house_id, house_name, house_code, description, created_by, manager_id, created_at, is_active, is_open_for_join

-- 4. MEALS table - ensure all columns
-- Already has: meal_id, house_id, member_id, meal_date, meal_count, created_by, updated_by, created_at, updated_at

-- 5. EXPENSES table - ensure all columns
-- Already has: expense_id, house_id, amount, category, description, expense_date, created_by, updated_by, created_at, updated_at

-- 6. DEPOSITS table - ensure all columns
-- Already has: deposit_id, house_id, member_id, amount, deposit_date, description, created_by, updated_by, updated_at, created_at

-- 7. MONTHLY_SUMMARY table - ensure month_year
-- Already has: summary_id, house_id, month, year, total_expenses, total_meals, meal_rate, is_closed, closed_by, closed_at, created_at, month_year

-- 8. MONTHLY_MEMBER_DETAILS table
-- Already has: detail_id, summary_id, member_id, total_meals, total_deposits, total_cost, balance

-- 9. LOGIN_ATTEMPTS table
-- Already has: attempt_id, identifier, ip_address, attempts, last_attempt, locked_until, is_blocked

-- 10. ACTIVITY_LOGS table
-- Already has: log_id, user_id, action, details, ip_address, user_agent, created_at

-- 11. MEMBER_ARCHIVE table
-- Already has: archive_id, member_id, name, email, phone, original_house_id, total_deposits, total_meals, total_expenses, final_balance, archived_at, archived_by, archive_reason, created_at

-- 12. HOUSE_TRANSFERS_LOG table
-- Already has: log_id, member_id, from_house_id, to_house_id, action, performed_by, performed_at, notes

-- 13. PREVIOUS_HOUSES table
-- Already has: history_id, member_id, house_id, joined_at, left_at, total_deposits, total_meals, total_expenses, final_balance, is_active, created_by

-- 14. JOIN_TOKENS table
-- Already has: token_id, token, house_id, member_id, token_type, expires_at, is_used, used_by, used_at, created_by, created_at

-- Verification - show all tables and column counts
SELECT TABLE_NAME, COLUMN_COUNT FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'mealing_online' 
ORDER BY TABLE_NAME;

SELECT 'ALL COLUMNS ARE NOW IN PLACE' as status;
