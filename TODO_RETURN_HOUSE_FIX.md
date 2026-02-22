# Fix: Return to Current House Not Working

## Task List

- [x] 1. Add return handler in member/settings.php to clear viewing history flags
- [x] 2. Test the fix

## Issue Description

When a member is viewing history of a previous house and clicks "Return to Current House" button, the action doesn't work because:

1. The button links to `settings.php?return=1`
2. But settings.php doesn't handle this parameter

## Solution

Add code to handle the return parameter in settings.php:

- Check if `$_GET['return'] == 1`
- Clear `is_viewing_history` and `history_house_id` in database
- Clear session variables
- Redirect to dashboard.php

## Changes Made

- Added handler in member/settings.php (lines 17-31) to process the return parameter
