# Task 010: AuthManager

**Status**: completed
**Depends on**: 008, 009
**Retry count**: 0

## Description
Create the AuthManager class that manages multiple guards and resolves the current guard.

## Context
- Supports multiple guards (web, api, etc.)
- Resolves default guard from config
- Provides convenient proxy methods (check, user, attempt, etc.)

## Requirements (Test Descriptions)
- [x] `it resolves default guard`
- [x] `it resolves named guard`
- [x] `it caches guard instances`
- [x] `it proxies check to default guard`
- [x] `it proxies user to default guard`
- [x] `it proxies id to default guard`
- [x] `it proxies attempt to default guard`
- [x] `it proxies logout to default guard`
- [x] `it creates session guard for session driver`
- [x] `it creates token guard for token driver`
- [x] `it throws for unknown guard driver`

## Acceptance Criteria
- All requirements have passing tests
- Guards are lazily instantiated
- Unknown drivers throw helpful errors

## Implementation Notes
- Created AuthManager at packages/auth/src/AuthManager.php
- Test file at packages/auth/tests/Unit/AuthManagerTest.php
- AuthManager supports multiple guards (session, token)
- Guards are lazily instantiated and cached
- Proxy methods (check, user, id, attempt, logout) delegate to default guard
- Unknown guard drivers throw AuthException with helpful error message
- Updated TokenGuard to support configurable name parameter for proper guard naming
