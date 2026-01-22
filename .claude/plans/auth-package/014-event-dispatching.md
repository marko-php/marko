# Task 014: Event Dispatching in Guards

**Status**: pending
**Depends on**: 010, 013
**Retry count**: 0

## Description
Integrate event dispatching into guards for login, logout, and failed attempts.

## Context
- Guards dispatch events at appropriate lifecycle points
- Events enable logging, auditing, notifications
- Uses core event dispatcher

## Requirements (Test Descriptions)
- [ ] `it dispatches LoginEvent on successful login`
- [ ] `it dispatches LoginEvent on successful attempt`
- [ ] `it dispatches LogoutEvent on logout`
- [ ] `it dispatches FailedLoginEvent on failed attempt`
- [ ] `it includes guard name in events`
- [ ] `it includes remember flag in LoginEvent`
- [ ] `event dispatching is optional (no error if dispatcher missing)`

## Acceptance Criteria
- All requirements have passing tests
- Events dispatched at correct lifecycle points
- Works with or without event dispatcher

## Implementation Notes
(Left blank - filled in by programmer during implementation)
