# Mealing Online - Code Issues Analysis

## 📊 **Psalm Static Analysis: 168 Total Issues**

Based on TODO.md and code review, issues are distributed across:

| File | Issues | Main Types |
|------|--------|------------|
| `includes/functions.php` | 96 | Missing return types (53), Missing parameter types (24), Missing property types (13), Paradoxical conditions (6) |
| `includes/auth.php` | 59 | Missing return types (31), Missing parameter types (18), Missing property types (10) |
| `manager/deposits.php` | 12 | Missing return types (7), Missing parameter types (5) |
| `index.php` | 1 | Missing return type (1) |

## 🔍 **Detailed Issue Breakdown**

### 1. **Missing Return Types** (~70 issues)
Functions missing explicit return type declarations:

**functions.php examples:**
- `getDashboardStats()` - returns `array` but no return type hint
- `getExpenseBreakdown()` - documented `@psalm-return` but no actual return type
- `formatCurrency()` - documented return type `string` but could be improved
- `getHouseMeals()` - documented `@psalm-return` but missing
- `getMembersWithoutUsers()` - documented `@psalm-return` but missing

**auth.php examples:**
- `login()` - returns `bool` but no return type
- `getSecurityQuestions()` - returns `array` but no return type
- `verifySecurityAnswer()` - returns `bool` but no return type
- `generateResetToken()` - returns `array` but no return type

### 2. **Missing Parameter Types** (~47 issues)
Functions missing parameter type declarations:

**functions.php examples:**
- `sanitize(mixed $input)` - should have specific types
- `validateEmail(string $email)` - partially fixed
- `validatePhone(string $phone)` - partially fixed
- `getDashboardStats(?int $month, ?int $year, ?int $house_id)` - some fixed
- Various other methods without type hints

**auth.php examples:**
- `login(string $username, string $password)` - partially fixed
- `generateResetToken(string $email)` - partially fixed

### 3. **Missing Property Types** (~23 issues)
Class properties missing type declarations:

**auth.php:**
- `$security_questions` array - should be `array` or specific type
- `$conn` mysqli connection - should be `private mysqli $conn`

**functions.php:**
- Various class properties without types

### 4. **Paradoxical Conditions** (~6 issues)
Logic errors where conditions can never be true:

**config/database.php** - mentioned in TODO.md
**functions.php** - countPendingRequests() cast issue

### 5. **MissingDocblockType** issues
Documentation blocks missing proper type information.

## 🛠️ **Recommended Fixes**

### Step 1: Auto-fix MissingReturnType
```bash
# Enable MissingReturnType in psalm.xml temporarily
# Run: psalm --alter --issues=MissingReturnType
```

### Step 2: Fix Specific Issues

**functions.php - Add return types:**
```php
// Example fixes:
public function sanitize(mixed $input): mixed { ... }
public function validateEmail(string $email): bool|string { ... }
public function validatePhone(string $phone): int { ... }
public function generateRandomString(int $length = 10): string { ... }
public function generateHouseCode(): string { ... }
```

**auth.php - Add property types:**
```php
// In Auth class:
private mysqli $conn;
private array $security_questions = [...];
```

**Add parameter types where missing:**
- Ensure all method parameters have type declarations
- Use `?` prefix for nullable types
- Use `mixed` for flexible types

### Step 3: Verification
After fixes, run Psalm to confirm zero errors.

## 📝 **Priority Order**

1. **Critical**: Missing return types on public methods
2. **High**: Missing parameter types on public methods  
3. **Medium**: Missing property type declarations
4. **Low**: Minor documentation improvements

The application is functionally complete, but these type improvements will enhance code quality, IDE support, and prevent future bugs.