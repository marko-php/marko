# Task 014: Event Dispatching in Guards

**Status**: completed
**Depends on**: 010, 013
**Retry count**: 0

## Description
Integrate event dispatching into guards for login, logout, and failed attempts.

## Context
- Guards dispatch events at appropriate lifecycle points
- Events enable logging, auditing, notifications
- Uses core event dispatcher

## Requirements (Test Descriptions)
- [x] `it dispatches LoginEvent on successful login`
- [x] `it dispatches LoginEvent on successful attempt`
- [x] `it dispatches LogoutEvent on logout`
- [x] `it dispatches FailedLoginEvent on failed attempt`
- [x] `it includes guard name in events`
- [x] `it includes remember flag in LoginEvent`
- [x] `event dispatching is optional (no error if dispatcher missing)`

## Acceptance Criteria
- All requirements have passing tests
- Events dispatched at correct lifecycle points
- Works with or without event dispatcher

## Implementation Notes
- Added optional `EventDispatcherInterface` dependency to `SessionGuard` constructor
- Created private helper methods for event dispatching: `dispatchLoginEvent()`, `dispatchLogoutEvent()`, `dispatchFailedLoginEvent()`
- Used nullsafe operator (`?->`) to make event dispatching optional
- LoginEvent dispatched after successful login with user, guard name, and remember flag
- LogoutEvent dispatched before clearing user from session, only if user was logged in
- FailedLoginEvent dispatched when user not found or credentials invalid
- All tests in new file: `packages/auth/tests/Unit/Guard/SessionGuardEventDispatchingTest.php`
