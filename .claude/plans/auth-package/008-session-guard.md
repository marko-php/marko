# Task 008: SessionGuard Implementation

**Status**: completed
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
- [x] `it implements GuardInterface`
- [x] `it stores user ID in session on login`
- [x] `it retrieves user from session`
- [x] `it returns true from check when authenticated`
- [x] `it returns false from check when not authenticated`
- [x] `it returns true from guest when not authenticated`
- [x] `it returns user from user method when authenticated`
- [x] `it returns null from user when not authenticated`
- [x] `it attempts login with valid credentials`
- [x] `it fails attempt with invalid credentials`
- [x] `it logs out user and clears session`
- [x] `it regenerates session ID on login`
- [x] `it throws AuthException when session not available`

## Acceptance Criteria
- All requirements have passing tests
- Session fixation protection via regenerate
- Clear error when session package missing

## Implementation Notes
- Created SessionGuard at packages/auth/src/Guard/SessionGuard.php
- Test file at packages/auth/tests/Unit/Guard/SessionGuardTest.php
- Uses SessionInterface from packages/session/src/Contracts/SessionInterface.php
- Throws AuthException when session is not started
- Caches the authenticated user to avoid repeated provider lookups
- Also implemented id() and loginById() methods for completeness
- Added an extra test for guest() returning false when authenticated
