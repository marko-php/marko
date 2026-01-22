# Task 021: Unit Tests for Middleware

**Status**: completed
**Depends on**: 015
**Retry count**: 0

## Description
Create comprehensive unit tests for AuthMiddleware and GuestMiddleware.

## Context
- Test authorization/redirection behavior
- Test different guard types
- Test configurable redirects
- Note: Some tests may already exist from task 015

## Requirements (Test Descriptions)
- [x] `AuthMiddleware passes authenticated users`
- [x] `AuthMiddleware blocks unauthenticated for API`
- [x] `AuthMiddleware redirects unauthenticated for web`
- [x] `AuthMiddleware uses specified guard`
- [x] `GuestMiddleware passes unauthenticated`
- [x] `GuestMiddleware redirects authenticated`
- [x] `redirects use configured URLs`
- [x] `correct HTTP status codes returned`

## Acceptance Criteria
- All requirements have passing tests
- Both middlewares thoroughly tested
- HTTP responses verified

## Implementation Notes
Most tests already existed from task 015. Added one additional test for GuestMiddleware to verify guard parameter support.

**Test mapping to existing tests:**
- `AuthMiddleware passes authenticated users` -> "it allows authenticated users through"
- `AuthMiddleware blocks unauthenticated for API` -> "it returns 401 for API guard when unauthenticated"
- `AuthMiddleware redirects unauthenticated for web` -> "it redirects for web guard when unauthenticated"
- `AuthMiddleware uses specified guard` -> "it supports specifying guard via parameter"
- `GuestMiddleware passes unauthenticated` -> "GuestMiddleware allows unauthenticated users"
- `GuestMiddleware redirects authenticated` -> "GuestMiddleware redirects authenticated users"
- `redirects use configured URLs` -> "it supports configurable redirect URL" (GuestMiddleware) + "it redirects for web guard when unauthenticated" (AuthMiddleware)
- `correct HTTP status codes returned` -> Verified across all tests (401 for API, 302 for redirects, 200 for pass-through)

**New test added:**
- `it supports specifying guard via parameter` in GuestMiddlewareTest.php
