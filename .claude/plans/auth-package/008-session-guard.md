# Task 008: SessionGuard Implementation

**Status**: pending
**Depends on**: 005, 006, 007
**Retry count**: 0

## Description
Create the SessionGuard class implementing GuardInterface for session-based authentication.

## Context
- Related files: packages/session/ (session dependency)
- Stores user ID in session after login
- Regenerates session ID on login (prevents session fixation)
- Throws error if session package not installed

## Requirements (Test Descriptions)
- [ ] `it implements GuardInterface`
- [ ] `it stores user ID in session on login`
- [ ] `it retrieves user from session`
- [ ] `it returns true from check when authenticated`
- [ ] `it returns false from check when not authenticated`
- [ ] `it returns true from guest when not authenticated`
- [ ] `it returns user from user method when authenticated`
- [ ] `it returns null from user when not authenticated`
- [ ] `it attempts login with valid credentials`
- [ ] `it fails attempt with invalid credentials`
- [ ] `it logs out user and clears session`
- [ ] `it regenerates session ID on login`
- [ ] `it throws AuthException when session not available`

## Acceptance Criteria
- All requirements have passing tests
- Session fixation protection via regenerate
- Clear error when session package missing

## Implementation Notes
(Left blank - filled in by programmer during implementation)
