# Task 015: AuthMiddleware

**Status**: pending
**Depends on**: 006, 010
**Retry count**: 0

## Description
Create AuthMiddleware for protecting routes and GuestMiddleware for redirecting authenticated users.

## Context
- AuthMiddleware protects routes requiring authentication
- Returns 401/403 or redirects based on guard type
- GuestMiddleware redirects authenticated users (e.g., from login page)

## Requirements (Test Descriptions)
- [ ] `it allows authenticated users through`
- [ ] `it blocks unauthenticated users`
- [ ] `it returns 401 for API guard when unauthenticated`
- [ ] `it redirects for web guard when unauthenticated`
- [ ] `it supports specifying guard via parameter`
- [ ] `it uses default guard when not specified`
- [ ] `GuestMiddleware allows unauthenticated users`
- [ ] `GuestMiddleware redirects authenticated users`
- [ ] `it supports configurable redirect URL`

## Acceptance Criteria
- All requirements have passing tests
- Different behavior for API vs web guards
- Configurable redirect destinations

## Implementation Notes
(Left blank - filled in by programmer during implementation)
