# Task 021: Unit Tests for Middleware

**Status**: pending
**Depends on**: 015
**Retry count**: 0

## Description
Create comprehensive unit tests for AuthMiddleware and GuestMiddleware.

## Context
- Test authorization/redirection behavior
- Test different guard types
- Test configurable redirects

## Requirements (Test Descriptions)
- [ ] `AuthMiddleware passes authenticated users`
- [ ] `AuthMiddleware blocks unauthenticated for API`
- [ ] `AuthMiddleware redirects unauthenticated for web`
- [ ] `AuthMiddleware uses specified guard`
- [ ] `GuestMiddleware passes unauthenticated`
- [ ] `GuestMiddleware redirects authenticated`
- [ ] `redirects use configured URLs`
- [ ] `correct HTTP status codes returned`

## Acceptance Criteria
- All requirements have passing tests
- Both middlewares thoroughly tested
- HTTP responses verified

## Implementation Notes
(Left blank - filled in by programmer during implementation)
