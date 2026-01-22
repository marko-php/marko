# Task 013: Authentication Events

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create authentication event classes: LoginEvent, LogoutEvent, FailedLoginEvent, PasswordResetEvent.

## Context
- Events dispatched during authentication lifecycle
- Allow observers to react to auth events (logging, etc.)
- Follow existing event pattern in core

## Requirements (Test Descriptions)
- [x] `it creates LoginEvent with user and guard name`
- [x] `it creates LoginEvent with remember flag`
- [x] `it creates LogoutEvent with user and guard name`
- [x] `it creates FailedLoginEvent with credentials and guard name`
- [x] `it creates FailedLoginEvent without exposing password`
- [x] `it creates PasswordResetEvent with user`
- [x] `all events are immutable`
- [x] `all events have getter methods`

## Acceptance Criteria
- All requirements have passing tests
- Events follow framework event patterns
- FailedLoginEvent does not expose passwords

## Implementation Notes
(Left blank - filled in by programmer during implementation)
