# Task 015: AuthMiddleware

**Status**: completed
**Depends on**: 006, 010
**Retry count**: 0

## Description
Create AuthMiddleware for protecting routes and GuestMiddleware for redirecting authenticated users.

## Context
- AuthMiddleware protects routes requiring authentication
- Returns 401/403 or redirects based on guard type
- GuestMiddleware redirects authenticated users (e.g., from login page)

## Requirements (Test Descriptions)
- [x] `it allows authenticated users through`
- [x] `it blocks unauthenticated users`
- [x] `it returns 401 for API guard when unauthenticated`
- [x] `it redirects for web guard when unauthenticated`
- [x] `it supports specifying guard via parameter`
- [x] `it uses default guard when not specified`
- [x] `GuestMiddleware allows unauthenticated users`
- [x] `GuestMiddleware redirects authenticated users`
- [x] `it supports configurable redirect URL`

## Acceptance Criteria
- All requirements have passing tests
- Different behavior for API vs web guards
- Configurable redirect destinations

## Implementation Notes
Implemented two middleware classes:

1. **AuthMiddleware** (`packages/auth/src/Middleware/AuthMiddleware.php`):
   - Protects routes requiring authentication
   - Supports specifying guard via constructor parameter
   - Uses default guard when not specified
   - API guards (TokenGuard) return JSON 401 responses
   - Web guards return 401 or redirect if `redirectTo` is configured

2. **GuestMiddleware** (`packages/auth/src/Middleware/GuestMiddleware.php`):
   - Allows unauthenticated users through to the next handler
   - Redirects authenticated users to configurable URL
   - Supports specifying guard via constructor parameter

Both implement `MiddlewareInterface` from the routing package.
