# Task 010: AuthManager

**Status**: pending
**Depends on**: 008, 009
**Retry count**: 0

## Description
Create the AuthManager class that manages multiple guards and resolves the current guard.

## Context
- Supports multiple guards (web, api, etc.)
- Resolves default guard from config
- Provides convenient proxy methods (check, user, attempt, etc.)

## Requirements (Test Descriptions)
- [ ] `it resolves default guard`
- [ ] `it resolves named guard`
- [ ] `it caches guard instances`
- [ ] `it proxies check to default guard`
- [ ] `it proxies user to default guard`
- [ ] `it proxies id to default guard`
- [ ] `it proxies attempt to default guard`
- [ ] `it proxies logout to default guard`
- [ ] `it creates session guard for session driver`
- [ ] `it creates token guard for token driver`
- [ ] `it throws for unknown guard driver`

## Acceptance Criteria
- All requirements have passing tests
- Guards are lazily instantiated
- Unknown drivers throw helpful errors

## Implementation Notes
(Left blank - filled in by programmer during implementation)
