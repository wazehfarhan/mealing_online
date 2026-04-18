# Meal Management System - Security & Performance Overhaul TODO

## Priority 1: Critical Security Fixes ✅ Plan Approved

### ✅ 1.1 Database Credentials to .env

- ✅ Create `.env` and `.env.example`
- ✅ Update `config/database.php` to use `parse_ini_file()`
- ☐ Test connection (user copy .env)

### ✅ 1.2 Disable Error Display in Production

- ✅ Add `ENVIRONMENT=production` to .env
- ✅ Update `config/database.php` conditional error_reporting

### ✅ 1.3 CSRF Protection All Forms (base)

- ✅ Create `includes/csrf.php`

### [ ] 1.4 Fix SQL Injection includes/realtime.php

### [ ] 1.3 CSRF Protection All Forms

- Create `includes/csrf.php`
- Add CSRF token to ALL POST forms (add*\*.php, edit*\*.php)
- Add verification to all handlers

### [ ] 1.4 Fix SQL Injection includes/realtime.php

- Convert mysqli_query to prepared statements (lines 15-16, 46-48)

### [ ] 1.5 Login Rate Limiting

- Create `login_attempts` table
- Update `auth/login.php` with IP/username tracking

### [ ] 1.6 Remove Test Files

```
DELETE:
- member/test_leave.php
- manager/test_setup.php
- fix_database.php
- fix_member_status.php
- repair_database.php
```

### [ ] 1.7 Security Headers (.htaccess)

- Create `.htaccess` with X-Frame-Options, CSP, etc.

## Priority 2: Performance & Database

### [ ] 2.1 Database Indexes (SQL Migration)

```
ALTER TABLE meals ADD INDEX idx_house_date_meal (house_id, meal_date, meal_count);
ALTER TABLE expenses ADD INDEX idx_house_date_amount (house_id, expense_date, amount);
...
```

### [ ] 2.2 Fix ENUM house_status

```
ALTER TABLE members MODIFY house_status ENUM('active','pending_leave','pending_join','left','house_inactive') DEFAULT 'active';
```

### [ ] 2.3 Dashboard Cache

- Create `dashboard_cache` table
- Add `refreshDashboardCache()` to functions.php
- Update dashboard.php to use cache

### [ ] 2.4 Pagination (meals.php, expenses.php, deposits.php)

- Add LIMIT/OFFSET + page controls

## Priority 3: New Features

### [ ] 3.1 Email System

- `includes/email.php` + PHPMailer
- `email_queue` table

### [ ] 3.2 Activity Log

- `activity_log` table + logging functions

### [ ] 3.3 Bulk Meal Entry

- Create `manager/bulk_meals.php`

### [ ] 3.4 XLSX Export

- PhpSpreadsheet integration

## Priority 4: Bug Fixes & Quality

### [ ] 4.1-4.3 Bug fixes (category colors, api security, leave_house.php)

### [ ] 5.1-5.3 Refactor + .gitignore + session security

## Testing & Validation

### [ ] Test all forms (CSRF working)

### [ ] Verify SQLi fixed (static analysis)

### [ ] Performance: dashboard <200ms

### [ ] Security headers present

### [ ] No test files remain

**Progress: 0/28 items complete**

_Updated automatically as steps completed_
