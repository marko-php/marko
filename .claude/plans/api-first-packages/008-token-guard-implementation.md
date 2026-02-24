# Task 008: TokenGuard Implementation

**Status**: pending
**Depends on**: 007
**Retry count**: 0

## Description
Implement the TokenGuard that authenticates API requests via Bearer tokens in the Authorization header. This guard implements the existing GuardInterface from marko/authentication.

## Context
- Package: `packages/authentication-token/`
- Study `packages/authentication/src/Contracts/GuardInterface.php` for the interface to implement
- Study `packages/authentication/src/Guard/SessionGuard.php` for existing guard implementation pattern
- Study `packages/authentication/src/Middleware/AuthMiddleware.php` for how guards are used
- TokenGuard extracts Bearer token from Authorization header, hashes it, looks up in repository
- TokenGuard is stateless — no session, no remember tokens
- The `attempt()` method is not applicable for token auth (tokens are pre-created); it should throw

## Requirements (Test Descriptions)
- [ ] `it implements GuardInterface from marko/authentication`
- [ ] `it extracts Bearer token from Authorization header`
- [ ] `it authenticates user by hashing token and looking up in repository`
- [ ] `it returns null user when no Authorization header is present`
- [ ] `it returns null user when token is not found or revoked`
- [ ] `it checks token abilities for fine-grained authorization`

## Acceptance Criteria
- All requirements have passing tests
- TokenGuard is in `src/Guard/TokenGuard.php`
- Uses TokenRepositoryInterface (not concrete) for token lookup
- Uses hashing to compare tokens (never stores plain text)
- Guard is registered in module.php bindings
- Code follows code standards

## Implementation Notes
