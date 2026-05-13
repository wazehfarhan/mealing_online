# Fix 168 Psalm Static Analysis Problems

## Files to Fix

- [ ] includes/auth.php (59 problems)
- [ ] includes/functions.php (96 problems)
- [ ] index.php (1 problem)
- [ ] manager/deposits.php (12 problems)

## Steps

### Step 1: Auto-fix MissingReturnType via Psalm --alter

- [ ] Temporarily enable MissingReturnType in psalm.xml
- [ ] Run `psalm --alter --issues=MissingReturnType`
- [ ] Verify auto-fixed return types

### Step 2: Fix InvalidReturnType + ParadoxicalCondition bugs

- [ ] Fix `functions.php` countPendingRequests() - cast MySQL result to int
- [ ] Fix `config/database.php` ParadoxicalCondition

### Step 3: Add MissingParamType declarations

- [ ] Add parameter types to all auth.php methods
- [ ] Add parameter types to all functions.php methods
- [ ] Add parameter type to index.php getSkillColorClass()
- [ ] Add parameter types to deposits.php functions

### Step 4: Add MissingPropertyType declarations

- [ ] Add `mysqli` type to Auth::$conn
- [ ] Add `array` type to Auth::$security_questions

### Step 5: Verification

- [ ] Run Psalm to verify all issues resolved
- [ ] Confirm zero errors on target files
