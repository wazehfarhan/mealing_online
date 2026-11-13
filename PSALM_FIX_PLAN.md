# Mealing Online - Psalm Static Analysis Fix Plan

## 📊 **Total Issues: 168** (as documented in TODO.md)

| File | Issues | Priority |
|------|--------|----------|
| `includes/functions.php` | 96 | High |
| `includes/auth.php` | 59 | High |
| `manager/deposits.php` | 12 | Medium |
| `index.php` | 1 | Low |

---

## 🔍 **Issue Breakdown by Type**

### 1. **Missing Return Types** (~70 issues)
Functions missing explicit return type declarations.

**functions.php - Fix Examples:**
```php
// Before (line ~64):
public function getDashboardStats(?int $month = null, ?int $year = null, ?int $house_id = null): array

// Before (line ~868):
/**
 * @psalm-return array{breakdown: list<non-empty-array<string, float|int|null|string>>, total: float|int}
 */
public function getExpenseBreakdown(int $month, int $year, ?int $house_id = null): array

// Before (line ~898):
public function formatCurrency(float $amount): string { ... }

// Before (line ~903):
public function formatDate(string $date_string): string { ... }

// Before (line ~908):
public function formatDateTime(string $date_string): string { ... }
```

**auth.php - Fix Examples:**
```php
public function login(string $username, string $password): bool { ... }
public function getSecurityQuestions(): array { ... }
public function verifySecurityAnswer(string $email, string $answer): bool { ... }
public function generateResetToken(string $email): array { ... }
```

### 2. **Missing Parameter Types** (~47 issues)
Function parameters missing type declarations.

**functions.php - Fix Examples:**
```php
// All public methods should have typed parameters:
// - sanitize(mixed $input): mixed
// - validateEmail(string $email): bool|string  
// - validatePhone(string $phone): int
// - generateRandomString(int $length = 10): string
// - generateHouseCode(): string
```

**auth.php - Fix Examples:**
```php
// login(string $username, string $password): bool - already has types
// Other methods may be missing parameter types
```

### 3. **Missing Property Types** (~23 issues)
Class properties missing type declarations.

**auth.php - Fix:**
```php
// In Auth class:
private mysqli $conn;  // Add type to $conn property
private array $security_questions = [...]  // Add type to $security_questions
```

**functions.php - Fix:**
Various class properties need type declarations.

### 4. **Paradoxical Conditions** (~6 issues)
Logic errors where conditions can never be true.

**config/database.php** - mentioned in TODO.md Step 2
**functions.php** - countPendingRequests() cast issue

### 5. **MissingDocblockType** issues
Documentation blocks missing proper type information.

---

## 🛠️ **Fix Priority Order**

### **Phase 1: Critical (functions.php - 96 issues)**
1. Add return types to all public methods
2. Add parameter types to all public methods  
3. Add property type declarations to the Functions class
4. Fix ParadoxicalCondition in countPendingRequests()

### **Phase 2: High (auth.php - 59 issues)**
1. Add return types to all public methods
2. Add parameter types where missing
3. Add `private mysqli $conn;` property type
4. Add `private array $security_questions;` property type

### **Phase 3: Medium (manager/deposits.php - 12 issues)**
1. Add return types to public methods
2. Add parameter types where missing

### **Phase 4: Low (index.php - 1 issue)**
1. Add return type to getSkillColorClass() function

---

## 📝 **Specific Fixes for functions.php**

Based on the TODO.md steps:

1. **Step 1: Auto-fix MissingReturnType**
   ```bash
   # Temporarily enable MissingReturnType in psalm.xml
   # Run: psalm --alter --issues=MissingReturnType
   # Verify auto-fixed return types
   ```

2. **Step 2: Fix InvalidReturnType + ParadoxicalCondition**
   - Fix `functions.php` countPendingRequests() - cast MySQL result to int
   - Fix `config/database.php` ParadoxicalCondition

3. **Step 3: Add MissingParamType declarations**
   - Add parameter types to all auth.php methods
   - Add parameter types to all functions.php methods
   - Add parameter type to index.php getSkillColorClass()

4. **Step 4: Add MissingPropertyType declarations**
   - Add `mysqli` type to Auth::$conn
   - Add `array` type to Auth::$security_questions

5. **Step 5: Verification**
   - Run Psalm to verify all issues resolved
   - Confirm zero errors on target files

---

## 💡 **Recommended Approach**

1. **Start with Phase 1** - functions.php has the most issues (96)
2. **Use psalm --alter** to auto-fix return types
3. **Manually fix** parameter types and property types
4. **Test after each phase** to ensure no regressions
5. **Final verification** - run Psalm and confirm 0 errors

---

## 📋 **Quick Start Commands**

```bash
# 1. First, see current issues
cd /Applications/XAMPP/xamppfiles/htdocs/mealing_online
php -d phar.readonly=0 vendor/bin/psalm -c psalm.xml 2>&1 | grep -E "^(|[0-9]+ )*(error|Error)" | head -20

# 2. Auto-fix return types (if psalm config works)
# php -d phar.readonly=0 vendor/bin/psalm --alter --issues=MissingReturnType

# 3. Fix specific known issues manually:
# - Add return types to functions
# - Add parameter types to methods  
# - Add property types to classes
```

---

## ⚠️ **Environment Note**

Psalm configuration has path resolution issues in this XAMPP environment 
(node_modules not found). The fixes above can be applied manually based on 
the issue patterns identified. The TODO.md already documents the exact steps 
needed to resolve all 168 issues.

---
*Plan based on TODO.md analysis and code review of 4 target files.*