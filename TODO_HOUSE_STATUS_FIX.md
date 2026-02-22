# TODO: House Status Update for Members

## Task

When manager inactivates a house, members should know that the house is on the inactive mode.

## Implementation Plan

### Step 1: Modify manager/update_house.php

- When house is set to inactive, update all active members of that house to have a special status
- Added `house_inactive` status handling when house is deactivated
- Also handles reactivation (when house becomes active again)

### Step 2: Modify includes/header.php

- Check the house's is_active status in addition to member's house_status
- Display appropriate warning to members if their house is inactive
- Added prominent alert message for house inactive status

### Step 3: Modify member/dashboard.php

- Updated to display house_inactive status with red badge

### Step 4: Modify member/settings.php

- Updated to show house inactive status and offer option to join new house

### Step 5: Modify includes/transfer_functions.php

- Updated all functions to allow members with house_inactive status to join new houses

## Status

- [x] Step 1: Modify update_house.php
- [x] Step 2: Modify header.php
- [x] Step 3: Modify member/dashboard.php
- [x] Step 4: Modify member/settings.php
- [x] Step 5: Modify transfer_functions.php

## How it works:

1. When manager sets a house to inactive (unchecks "Active House"), all members with `house_status = 'active'` will have their status changed to `house_inactive`
2. Members will see a prominent warning banner on their dashboard
3. The sidebar will show "House Inactive" badge
4. Members can still view their history but are encouraged to join a new house
5. When house is reactivated, members' status is changed back to `active`
