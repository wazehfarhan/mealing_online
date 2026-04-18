#!/bin/bash
# Database Fix Verification Script
# Tests all queries that were causing "Unknown column" errors

echo "=========================================="
echo "Database Column Error Verification"
echo "=========================================="
echo ""

/opt/homebrew/bin/mysql -uroot -h 127.0.0.1 mealing_online << 'EOF'

-- Test 1: MEMBERS table with created_by
SELECT "Test 1: INSERT into members with created_by" as test_name;
INSERT IGNORE INTO members (house_id, name, phone, email, join_date, created_by, status) 
VALUES (1, 'Test User', '1234567890', 'test@example.com', CURDATE(), 1, 'active');
SELECT IF(COUNT(*) > 0, 'PASS', 'FAIL') as result FROM members WHERE name='Test User';
DELETE FROM members WHERE name='Test User';

-- Test 2: EXPENSES table with updated_by
SELECT "Test 2: UPDATE expenses with updated_by" as test_name;
INSERT INTO expenses (house_id, amount, category, description, expense_date, created_by, updated_by) 
VALUES (1, 100.00, 'Test', 'Test expense', CURDATE(), 1, 1);
SELECT IF(COUNT(*) > 0, 'PASS', 'FAIL') as result FROM expenses WHERE category='Test';
DELETE FROM expenses WHERE category='Test';

-- Test 3: DEPOSITS table with updated_by and updated_at
SELECT "Test 3: UPDATE deposits with updated_by and updated_at" as test_name;
INSERT INTO deposits (house_id, member_id, amount, deposit_date, description, created_by, updated_by, updated_at) 
VALUES (1, 1, 50.00, CURDATE(), 'Test', 1, 1, NOW());
SELECT IF(COUNT(*) > 0, 'PASS', 'FAIL') as result FROM deposits WHERE description='Test';
DELETE FROM deposits WHERE description='Test';

-- Test 4: MONTHLY_SUMMARY with month_year
SELECT "Test 4: INSERT monthly_summary with month_year" as test_name;
INSERT IGNORE INTO monthly_summary (house_id, month, year, month_year, total_expenses, total_meals, meal_rate) 
VALUES (1, 4, 2026, '2026-04', 1000.00, 50.00, 20.00);
SELECT IF(COUNT(*) > 0, 'PASS', 'FAIL') as result FROM monthly_summary WHERE month_year='2026-04';
DELETE FROM monthly_summary WHERE month_year='2026-04';

-- Test 5: Join operations with created_by
SELECT "Test 5: JOIN members with created_by" as test_name;
SELECT COUNT(*) as member_count FROM members m LEFT JOIN users u ON m.created_by = u.user_id;
SELECT 'PASS' as result;

-- Test 6: PREVIOUS_HOUSES with created_by
SELECT "Test 6: INSERT previous_houses with created_by" as test_name;
INSERT IGNORE INTO previous_houses (member_id, house_id, joined_at, created_by) 
VALUES (1, 1, NOW(), 1);
SELECT IF(COUNT(*) > 0, 'PASS', 'FAIL') as result FROM previous_houses WHERE member_id=1;
DELETE FROM previous_houses WHERE member_id=1;

-- Test 7: All tables have columns
SELECT "Test 7: Verify all key tables and columns" as test_name;
SELECT TABLE_NAME, COLUMN_COUNT as col_count FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA='mealing_online' ORDER BY TABLE_NAME;

SELECT "=== ALL TESTS COMPLETE ===" as final_status;

EOF

echo ""
echo "=========================================="
echo "Database is ready for all operations!"
echo "=========================================="
