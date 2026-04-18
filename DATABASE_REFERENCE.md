# 🗄️ COMPREHENSIVE DATABASE REFERENCE

## Mealing Online - Complete Database Schema

**Last Updated:** April 18, 2026  
**Database Name:** `mealing_online`  
**Total Tables:** 14  
**Total Columns:** 110+

---

## 📊 DATABASE TABLES & COLUMNS

### 1. **users** (User Accounts & Authentication)

| Column            | Type                                   | Key | Nullable | Default           | Purpose                           |
| ----------------- | -------------------------------------- | --- | -------- | ----------------- | --------------------------------- |
| user_id           | INT                                    | PK  | NO       | AUTO_INCREMENT    | Unique user identifier            |
| username          | VARCHAR(50)                            | UNI | NO       | -                 | Login username                    |
| email             | VARCHAR(100)                           | UNI | NO       | -                 | User email address                |
| password          | VARCHAR(255)                           | -   | NO       | -                 | Hashed password                   |
| role              | ENUM('super_admin','manager','member') | -   | NO       | member            | User role/permission level        |
| security_question | VARCHAR(255)                           | -   | YES      | -                 | Password recovery question        |
| security_answer   | VARCHAR(255)                           | -   | YES      | -                 | Password recovery answer (hashed) |
| house_id          | INT                                    | FK  | YES      | -                 | Associated house ID               |
| member_id         | INT                                    | -   | YES      | -                 | Associated member ID              |
| last_login        | DATETIME                               | -   | YES      | -                 | Last login timestamp              |
| created_at        | TIMESTAMP                              | -   | NO       | CURRENT_TIMESTAMP | Account creation time             |
| is_active         | TINYINT(1)                             | -   | YES      | 1                 | Account active status             |

**Relationships:**

- References: `houses` (house_id), `members` (member_id)
- Used in: Authentication, user management, access control

---

### 2. **members** (House Members)

| Column             | Type                                                                  | Key | Nullable | Default           | Purpose                          |
| ------------------ | --------------------------------------------------------------------- | --- | -------- | ----------------- | -------------------------------- |
| member_id          | INT                                                                   | PK  | NO       | AUTO_INCREMENT    | Unique member identifier         |
| user_id            | INT                                                                   | FK  | YES      | -                 | Associated user account          |
| house_id           | INT                                                                   | FK  | NO       | -                 | Current house ID                 |
| name               | VARCHAR(100)                                                          | -   | NO       | -                 | Member's full name               |
| email              | VARCHAR(100)                                                          | -   | YES      | -                 | Member's email                   |
| phone              | VARCHAR(20)                                                           | -   | YES      | -                 | Member's phone number            |
| status             | ENUM('active','inactive','left')                                      | IDX | NO       | active            | Member status in current house   |
| house_status       | ENUM('active','pending_leave','pending_join','left','house_inactive') | -   | YES      | active            | Status during house transfer     |
| requested_house_id | INT                                                                   | -   | YES      | -                 | Target house for transfer        |
| leave_request_date | DATETIME                                                              | -   | YES      | -                 | When leave was requested         |
| join_request_date  | DATETIME                                                              | -   | YES      | -                 | When join was requested          |
| is_viewing_history | TINYINT(1)                                                            | -   | YES      | 0                 | Viewing previous house data flag |
| history_house_id   | INT                                                                   | -   | YES      | -                 | House ID being viewed in history |
| join_token         | VARCHAR(100)                                                          | UNI | YES      | -                 | Token for invite links           |
| token_expiry       | DATETIME                                                              | -   | YES      | -                 | When invite link expires         |
| created_at         | TIMESTAMP                                                             | -   | NO       | CURRENT_TIMESTAMP | Member added date                |
| updated_at         | TIMESTAMP                                                             | -   | YES      | -                 | Last update timestamp            |

**Relationships:**

- References: `users` (user_id), `houses` (house_id, requested_house_id, history_house_id)
- Used in: Meal tracking, deposit management, house membership

**Critical Enums:**

- `status`: 'active' = current member, 'inactive' = suspended, 'left' = left house
- `house_status`: Used during transfers (pending_leave → approved → left)

---

### 3. **houses** (House Groups/Organizations)

| Column           | Type         | Key | Nullable | Default           | Purpose                             |
| ---------------- | ------------ | --- | -------- | ----------------- | ----------------------------------- |
| house_id         | INT          | PK  | NO       | AUTO_INCREMENT    | Unique house identifier             |
| house_name       | VARCHAR(100) | -   | NO       | -                 | House display name                  |
| house_code       | VARCHAR(20)  | UNI | NO       | -                 | Unique invite code (e.g., HM123ABC) |
| description      | TEXT         | -   | YES      | -                 | House description                   |
| created_by       | INT          | FK  | YES      | -                 | User who created house              |
| manager_id       | INT          | -   | YES      | -                 | Current house manager               |
| created_at       | TIMESTAMP    | -   | NO       | CURRENT_TIMESTAMP | House creation time                 |
| is_active        | TINYINT(1)   | -   | YES      | 1                 | House active status                 |
| is_open_for_join | TINYINT(1)   | -   | YES      | 1                 | Can new members join                |

**Relationships:**

- References: `users` (created_by, manager_id)
- Used by: `members`, `meals`, `expenses`, `deposits`, `monthly_summary`

---

### 4. **meals** (Meal Entries)

| Column     | Type          | Key | Nullable | Default           | Purpose                               |
| ---------- | ------------- | --- | -------- | ----------------- | ------------------------------------- |
| meal_id    | INT           | PK  | NO       | AUTO_INCREMENT    | Unique meal record ID                 |
| house_id   | INT           | FK  | NO       | -                 | House for this meal entry             |
| member_id  | INT           | FK  | NO       | -                 | Member eating                         |
| meal_date  | DATE          | IDX | NO       | -                 | Date of meal                          |
| meal_count | DECIMAL(10,2) | -   | YES      | 0.00              | Number of meals (e.g., 0.5, 1.0, 2.5) |
| created_by | INT           | FK  | YES      | -                 | User who recorded                     |
| updated_by | INT           | FK  | YES      | -                 | Last user to update                   |
| created_at | TIMESTAMP     | -   | NO       | CURRENT_TIMESTAMP | Record creation time                  |
| updated_at | TIMESTAMP     | -   | YES      | -                 | Last update time                      |

**Relationships:**

- References: `houses`, `members` (member_id), `users` (created_by, updated_by)
- Used for: Meal cost calculation, monthly billing

---

### 5. **expenses** (House Expenses)

| Column       | Type          | Key | Nullable | Default           | Purpose                                       |
| ------------ | ------------- | --- | -------- | ----------------- | --------------------------------------------- |
| expense_id   | INT           | PK  | NO       | AUTO_INCREMENT    | Unique expense record ID                      |
| house_id     | INT           | FK  | NO       | -                 | House for this expense                        |
| amount       | DECIMAL(10,2) | -   | NO       | -                 | Expense amount                                |
| category     | VARCHAR(50)   | -   | NO       | -                 | Expense category (e.g., Groceries, Utilities) |
| description  | TEXT          | -   | YES      | -                 | Expense details                               |
| expense_date | DATE          | IDX | NO       | -                 | Date of expense                               |
| created_by   | INT           | FK  | YES      | -                 | User who recorded                             |
| created_at   | TIMESTAMP     | -   | NO       | CURRENT_TIMESTAMP | Record creation time                          |
| updated_at   | TIMESTAMP     | -   | YES      | -                 | Last update time                              |

**Relationships:**

- References: `houses`, `users` (created_by)
- Used for: Monthly billing, expense tracking

---

### 6. **deposits** (Member Deposits/Payments)

| Column       | Type          | Key | Nullable | Default           | Purpose                  |
| ------------ | ------------- | --- | -------- | ----------------- | ------------------------ |
| deposit_id   | INT           | PK  | NO       | AUTO_INCREMENT    | Unique deposit record ID |
| house_id     | INT           | FK  | NO       | -                 | House for this deposit   |
| member_id    | INT           | FK  | NO       | -                 | Member making deposit    |
| amount       | DECIMAL(10,2) | -   | NO       | -                 | Deposit amount           |
| deposit_date | DATE          | IDX | NO       | -                 | Date of deposit          |
| description  | TEXT          | -   | YES      | -                 | Deposit description      |
| created_by   | INT           | FK  | YES      | -                 | User who recorded        |
| created_at   | TIMESTAMP     | -   | NO       | CURRENT_TIMESTAMP | Record creation time     |

**Relationships:**

- References: `houses`, `members`, `users` (created_by)
- Used for: Member balance tracking

---

### 7. **monthly_summary** (Monthly Financial Summary)

| Column         | Type          | Key | Nullable | Default           | Purpose                        |
| -------------- | ------------- | --- | -------- | ----------------- | ------------------------------ |
| summary_id     | INT           | PK  | NO       | AUTO_INCREMENT    | Unique summary ID              |
| house_id       | INT           | FK  | NO       | -                 | House this summary is for      |
| month          | INT           | -   | YES      | -                 | Month number (1-12)            |
| year           | INT           | -   | YES      | -                 | Year (e.g., 2026)              |
| total_expenses | DECIMAL(10,2) | -   | YES      | 0.00              | Total house expenses for month |
| total_meals    | DECIMAL(10,2) | -   | YES      | 0.00              | Total meals for month          |
| meal_rate      | DECIMAL(10,2) | -   | YES      | 0.00              | Cost per meal                  |
| is_closed      | TINYINT(1)    | -   | YES      | 0                 | Monthly report finalized       |
| closed_by      | INT           | FK  | YES      | -                 | User who closed report         |
| closed_at      | DATETIME      | -   | YES      | -                 | When report was closed         |
| created_at     | TIMESTAMP     | -   | NO       | CURRENT_TIMESTAMP | Summary creation time          |

**Relationships:**

- References: `houses`, `users` (closed_by), `monthly_member_details`
- Used for: Monthly billing, reporting

---

### 8. **monthly_member_details** (Member Monthly Details)

| Column         | Type          | Key | Nullable | Default        | Purpose                            |
| -------------- | ------------- | --- | -------- | -------------- | ---------------------------------- |
| detail_id      | INT           | PK  | NO       | AUTO_INCREMENT | Unique detail record ID            |
| summary_id     | INT           | FK  | NO       | -              | Monthly summary reference          |
| member_id      | INT           | FK  | NO       | -              | Member this detail is for          |
| total_meals    | DECIMAL(10,2) | -   | YES      | 0.00           | Member's meal count for month      |
| total_deposits | DECIMAL(10,2) | -   | YES      | 0.00           | Member's deposits for month        |
| total_cost     | DECIMAL(10,2) | -   | YES      | 0.00           | Member's expense share             |
| balance        | DECIMAL(10,2) | -   | YES      | 0.00           | Member's balance (deposits - cost) |

**Relationships:**

- References: `monthly_summary`, `members`
- Used for: Individual member billing

---

### 9. **login_attempts** (Security - Brute Force Prevention)

| Column       | Type         | Key | Nullable | Default           | Purpose                      |
| ------------ | ------------ | --- | -------- | ----------------- | ---------------------------- |
| attempt_id   | INT          | PK  | NO       | AUTO_INCREMENT    | Unique attempt record ID     |
| identifier   | VARCHAR(255) | IDX | NO       | -                 | Username or IP being tracked |
| ip_address   | VARCHAR(45)  | IDX | YES      | -                 | Source IP address            |
| attempts     | INT          | -   | YES      | 1                 | Number of failed attempts    |
| last_attempt | TIMESTAMP    | -   | YES      | CURRENT_TIMESTAMP | Last attempt timestamp       |
| locked_until | TIMESTAMP    | IDX | YES      | -                 | When lockout expires         |
| is_blocked   | TINYINT(1)   | -   | YES      | 0                 | Permanently blocked flag     |

**Configuration:**

- Max attempts: 5 per 15 minutes
- Lockout duration: 15 minutes (900 seconds)
- Used in: `auth/login.php`, `includes/rate_limiter.php`

---

### 10. **activity_logs** (Activity Audit Trail)

| Column     | Type         | Key | Nullable | Default           | Purpose                                      |
| ---------- | ------------ | --- | -------- | ----------------- | -------------------------------------------- |
| log_id     | INT          | PK  | NO       | AUTO_INCREMENT    | Unique log entry ID                          |
| user_id    | INT          | FK  | NO       | -                 | User performing action                       |
| action     | VARCHAR(50)  | IDX | NO       | -                 | Action performed (e.g., 'login', 'add_meal') |
| details    | TEXT         | -   | YES      | -                 | Additional details/JSON                      |
| ip_address | VARCHAR(45)  | -   | YES      | -                 | Source IP address                            |
| user_agent | VARCHAR(255) | -   | YES      | -                 | Browser user agent                           |
| created_at | TIMESTAMP    | IDX | NO       | CURRENT_TIMESTAMP | Log timestamp                                |

**Relationships:**

- References: `users` (user_id)
- Used for: Audit trail, security monitoring

---

### 11. **member_archive** (Historical Member Data)

| Column            | Type          | Key | Nullable | Default           | Purpose                           |
| ----------------- | ------------- | --- | -------- | ----------------- | --------------------------------- |
| archive_id        | INT           | PK  | NO       | AUTO_INCREMENT    | Unique archive record ID          |
| member_id         | INT           | -   | NO       | -                 | Archived member ID                |
| name              | VARCHAR(100)  | -   | NO       | -                 | Member name at archive            |
| email             | VARCHAR(100)  | -   | YES      | -                 | Member email                      |
| phone             | VARCHAR(20)   | -   | YES      | -                 | Member phone                      |
| original_house_id | INT           | IDX | NO       | -                 | House member left                 |
| total_deposits    | DECIMAL(10,2) | -   | YES      | 0.00              | Final deposits                    |
| total_meals       | DECIMAL(10,2) | -   | YES      | 0.00              | Final meal count                  |
| total_expenses    | DECIMAL(10,2) | -   | YES      | 0.00              | Final expense share               |
| final_balance     | DECIMAL(10,2) | -   | YES      | 0.00              | Final balance                     |
| archived_at       | TIMESTAMP     | -   | NO       | CURRENT_TIMESTAMP | Archive time                      |
| archived_by       | INT           | FK  | YES      | -                 | User who archived                 |
| archive_reason    | VARCHAR(255)  | -   | YES      | -                 | Why archived (e.g., 'left_house') |

**Used for:** Preserving member history when leaving house

---

### 12. **house_transfers_log** (House Transfer Audit Trail)

| Column        | Type      | Key | Nullable | Default           | Purpose                  |
| ------------- | --------- | --- | -------- | ----------------- | ------------------------ |
| log_id        | INT       | PK  | NO       | AUTO_INCREMENT    | Unique log entry ID      |
| member_id     | INT       | IDX | NO       | -                 | Member being transferred |
| from_house_id | INT       | IDX | YES      | -                 | Source house             |
| to_house_id   | INT       | IDX | YES      | -                 | Destination house        |
| action        | ENUM(...) | IDX | NO       | -                 | Transfer action type     |
| performed_by  | INT       | -   | NO       | -                 | User performing action   |
| performed_at  | TIMESTAMP | -   | NO       | CURRENT_TIMESTAMP | Action timestamp         |
| notes         | TEXT      | -   | YES      | -                 | Additional notes         |

**Valid Actions:**

- 'leave_requested', 'leave_approved', 'leave_rejected'
- 'join_requested', 'join_approved', 'join_rejected'
- 'transferred', 'archived'

**Used for:** Tracking all house transfer requests and approvals

---

### 13. **previous_houses** (Member's House History)

| Column         | Type          | Key | Nullable | Default        | Purpose                   |
| -------------- | ------------- | --- | -------- | -------------- | ------------------------- |
| history_id     | INT           | PK  | NO       | AUTO_INCREMENT | Unique history record ID  |
| member_id      | INT           | IDX | NO       | -              | Member                    |
| house_id       | INT           | IDX | NO       | -              | House from history        |
| joined_at      | DATETIME      | -   | NO       | -              | When member joined        |
| left_at        | DATETIME      | -   | YES      | -              | When member left          |
| total_deposits | DECIMAL(10,2) | -   | YES      | 0.00           | Total deposits in house   |
| total_meals    | DECIMAL(10,2) | -   | YES      | 0.00           | Total meals in house      |
| total_expenses | DECIMAL(10,2) | -   | YES      | 0.00           | Total expense share       |
| final_balance  | DECIMAL(10,2) | -   | YES      | 0.00           | Final balance             |
| is_active      | TINYINT(1)    | -   | YES      | 0              | Currently active in house |

**Used for:** Viewing past house history

---

### 14. **join_tokens** (Invite Link Management)

| Column     | Type                                   | Key | Nullable | Default           | Purpose                        |
| ---------- | -------------------------------------- | --- | -------- | ----------------- | ------------------------------ |
| token_id   | INT                                    | PK  | NO       | AUTO_INCREMENT    | Unique token ID                |
| token      | VARCHAR(100)                           | UNI | NO       | -                 | Unique invite token (URL-safe) |
| house_id   | INT                                    | IDX | NO       | -                 | House being invited to         |
| member_id  | INT                                    | IDX | YES      | -                 | Member if for specific person  |
| token_type | ENUM('member_invite','house_transfer') | -   | YES      | member_invite     | Token type                     |
| expires_at | DATETIME                               | -   | NO       | -                 | When token expires             |
| is_used    | TINYINT(1)                             | -   | YES      | 0                 | Token used flag                |
| used_by    | INT                                    | -   | YES      | -                 | User who used token            |
| used_at    | DATETIME                               | -   | YES      | -                 | When token was used            |
| created_by | INT                                    | IDX | YES      | -                 | User who created token         |
| created_at | TIMESTAMP                              | -   | NO       | CURRENT_TIMESTAMP | Token creation time            |

**Used for:** Generating invite links in `member/join.php`

---

## 🔑 FOREIGN KEY RELATIONSHIPS

```
users
├── house_id → houses.house_id
├── member_id → members.member_id
└── created_at references

houses
├── created_by → users.user_id
└── manager_id → users.user_id

members
├── user_id → users.user_id
├── house_id → houses.house_id
├── requested_house_id → houses.house_id
└── history_house_id → houses.house_id

meals
├── house_id → houses.house_id
├── member_id → members.member_id
├── created_by → users.user_id
└── updated_by → users.user_id

expenses
├── house_id → houses.house_id
└── created_by → users.user_id

deposits
├── house_id → houses.house_id
├── member_id → members.member_id
└── created_by → users.user_id

monthly_summary
└── house_id → houses.house_id

monthly_member_details
├── summary_id → monthly_summary.summary_id
└── member_id → members.member_id

activity_logs
└── user_id → users.user_id

member_archive
└── original_house_id → houses.house_id

house_transfers_log
└── member_id → members.member_id

previous_houses
├── member_id → members.member_id
└── house_id → houses.house_id

join_tokens
└── house_id → houses.house_id
```

---

## 📈 INDEXES FOR PERFORMANCE

### Critical Indexes (Search/Filter):

- `users.username` - UNIQUE
- `users.email` - UNIQUE
- `houses.house_code` - UNIQUE
- `members.join_token` - UNIQUE
- `join_tokens.token` - UNIQUE

### Speed Indexes (Frequently Queried):

- `houses.created_by`
- `houses.manager_id`
- `members.house_id`
- `members.user_id`
- `members.status`
- `meals.house_id`
- `meals.member_id`
- `meals.meal_date`
- `expenses.house_id`
- `expenses.expense_date`
- `deposits.house_id`
- `deposits.member_id`
- `deposits.deposit_date`
- `monthly_summary.house_id`
- `activity_logs.user_id`
- `activity_logs.action`
- `activity_logs.created_at`
- `login_attempts.identifier`
- `login_attempts.ip_address`
- `login_attempts.locked_until`

---

## ⚠️ COMMON ERROR SCENARIOS & SOLUTIONS

### Error 1: "Unknown column 'join_token'"

**Solution:** Ensure members table has join_token column

```sql
ALTER TABLE members ADD COLUMN join_token VARCHAR(100) UNIQUE DEFAULT NULL;
```

### Error 2: "Unknown column 'manager_id' in 'where' clause"

**Solution:** Add manager_id to houses table

```sql
ALTER TABLE houses ADD COLUMN manager_id INT(11) DEFAULT NULL;
```

### Error 3: "Table 'mealing_online.activity_logs' doesn't exist"

**Solution:** Run `database/additional_fixes.sql` to create the table

### Error 4: "House status 'left' or 'house_inactive' not valid"

**Solution:** Ensure members.house_status ENUM includes all 5 values:

```sql
ALTER TABLE members MODIFY COLUMN house_status
ENUM('active','pending_leave','pending_join','left','house_inactive');
```

### Error 5: "Duplicate entry for house_code"

**Solution:** Unique constraint on houses.house_code - use unique codes only

---

## 🔧 MAINTENANCE TASKS

### Regular Backups

```bash
mysqldump -uroot mealing_online > backup_$(date +%Y%m%d).sql
```

### Check Database Integrity

```sql
CHECK TABLE users, members, houses, meals, expenses, deposits;
REPAIR TABLE users, members, houses, meals, expenses, deposits;
```

### Clear Old Login Attempts

```sql
DELETE FROM login_attempts WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

### Archive Old Member Data

```sql
INSERT INTO member_archive SELECT * FROM members WHERE status = 'left' AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

## ✅ VERIFICATION CHECKLIST

- [x] All 14 tables created
- [x] All columns with correct types
- [x] Primary keys defined
- [x] Foreign key references set
- [x] Unique constraints applied
- [x] Indexes created for performance
- [x] ENUM values complete
- [x] Timestamps with defaults
- [x] join_token column added to members
- [x] manager_id column added to houses
- [x] activity_logs table created
- [x] house_transfers_log created
- [x] previous_houses table created
- [x] member_archive table created
- [x] join_tokens table created
- [x] login_attempts table for security

---

## 📝 FINAL STATUS

**Database Schema:** ✅ COMPLETE  
**All Tables:** ✅ 14/14 CREATED  
**All Columns:** ✅ 110+ VERIFIED  
**Integrity:** ✅ READY FOR PRODUCTION  
**Last Audit:** April 18, 2026

The database is now fully configured and ready for the application to run without column/table errors!
