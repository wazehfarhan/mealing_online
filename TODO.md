# House Transfer System - Implementation Plan

## Status: IN PROGRESS

## Started: 2024

---

## Phase 1: Database Setup ✅ COMPLETED

- [x] 1.1. Run `run_migration.php` to execute database changes
- [x] 1.2. Verify all tables and columns were created successfully

## Phase 2: Helper Functions ✅ COMPLETED

- [x] 2.1. Add `getMemberHouseHistory($email, $house_code)` function
- [x] 2.2. Add `calculateHouseHistoryStats($member_id, $house_id)` function
- [x] 2.3. Add `generateJoinToken()` function
- [x] 2.4. Add `useJoinToken()` function
- [x] 2.5. Add `getIncomingJoinRequests()` function

## Phase 3: Member Pages - Leave Feature ✅ COMPLETED

- [x] 3.1. Create `member/leave_request.php` - Member leave request page
- [x] 3.2. Update `member/settings.php` - Add leave request UI

## Phase 4: Member Pages - Join Feature ✅ COMPLETED

- [x] 4.1. Create `member/join_request.php` - Member join request page
- [x] 4.2. Update `member/settings.php` - Add join request UI

## Phase 5: Manager Pages - Settings ✅ COMPLETED

- [x] 5.1. Update `manager/settings.php` - Toggle house join openness
- [x] 5.2. Update `manager/generate_link.php` - Generate join tokens and transfer tokens

## Phase 6: Navigation Updates

- [ ] 6.1. Update `member/dashboard.php` - Show current house status and quick links

## Phase 7: Testing & Verification

- [ ] 7.1. Test member can request leave
- [ ] 7.2. Test manager can approve/reject leave
- [ ] 7.3. Test member can request join new house
- [ ] 7.4. Test manager can approve cross-house transfer
- [ ] 7.5. Test historical data viewing
- [ ] 7.6. Verify data preservation

---

## Features Implemented:

### 1. Member Leave System ✅

- Member clicks "Leave House" in settings
- If no today's meals, request is submitted
- Manager approves/rejects in `approve_requests.php`
- On approval: member archived, user account disconnected

### Join System (via 2. Member Token) ✅

- Manager generates unique join token for member
- Member uses token to request joining new house
- Both current and new house managers must approve
- Previous house data preserved in archive
- Member transferred to new house

### 3. View Previous House Data ✅

- Member enters old house code in settings
- System verifies member was part of that house
- Member can view deposits, meals, expenses, balance
- Toggle between current and historical views

---

## File Changes Summary:

### New Files Created:

- `member/leave_request.php`
- `member/join_request.php`
- `member/view_house.php`

### Files Modified:

- `includes/functions.php` - Added helper functions
- `member/settings.php` - Added leave/join UI
- `member/dashboard.php` - Added status display
- `manager/settings.php` - Added toggle for house openness
- `manager/generate_link.php` - Added token generation
- `manager/members.php` - Added transfer status
- `includes/header.php` - Added navigation

### Database Changes:

- New tables: `member_archive`, `house_transfers_log`, `previous_houses`, `join_tokens`
- New columns in `houses`: `is_open_for_join`
- New columns in `members`: `house_status`, `requested_house_id`, `leave_request_date`, `join_request_date`, `is_viewing_history`, `history_house_id`
- New views: `v_member_house_history`, `v_pending_requests`
- New triggers: Auto-update request dates
