# Task 013: Authentication Events

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create authentication event classes: LoginEvent, LogoutEvent, FailedLoginEvent, PasswordResetEvent.

## Context
- Events dispatched during authentication lifecycle
- Allow observers to react to auth events (logging, etc.)
- Follow existing event pattern in core

## Requirements (Test Descriptions)
- [ ] `it creates LoginEvent with user and guard name`
- [ ] `it creates LoginEvent with remember flag`
- [ ] `it creates LogoutEvent with user and guard name`
- [ ] `it creates FailedLoginEvent with credentials and guard name`
- [ ] `it creates FailedLoginEvent without exposing password`
- [ ] `it creates PasswordResetEvent with user`
- [ ] `all events are immutable`
- [ ] `all events have getter methods`

## Acceptance Criteria
- All requirements have passing tests
- Events follow framework event patterns
- FailedLoginEvent does not expose passwords

## Implementation Notes
(Left blank - filled in by programmer during implementation)
