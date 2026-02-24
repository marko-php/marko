# Task 009: Token Management Service

**Status**: pending
**Depends on**: 008
**Retry count**: 0

## Description
Implement the TokenManager service for creating, revoking, and pruning personal access tokens. This is the primary API for application code to manage tokens.

## Context
- Package: `packages/authentication-token/`
- TokenManager orchestrates TokenRepositoryInterface and hashing
- Creating a token returns a NewAccessToken value object containing the plain-text token (only time it's available)
- Token hash uses SHA-256 for fast lookup (not bcrypt — tokens are random, not user-chosen)
- Study `packages/authentication/src/Service/` for service class patterns
- Study `packages/hashing/src/Contracts/PasswordHasherInterface.php` — note: we use hash('sha256') directly for tokens, not the password hasher (which is bcrypt and too slow for token lookup)

## Requirements (Test Descriptions)
- [ ] `it creates a new personal access token with SHA-256 hashed storage`
- [ ] `it returns NewAccessToken value object with plain-text token at creation time`
- [ ] `it assigns abilities array to created token`
- [ ] `it revokes a token by its id`
- [ ] `it revokes all tokens for a given user`

## Acceptance Criteria
- All requirements have passing tests
- TokenManager is in `src/Service/TokenManager.php`
- NewAccessToken value object is in `src/Value/NewAccessToken.php`
- Plain-text token is cryptographically random (random_bytes)
- Token expiration is configurable via config
- Code follows code standards

## Implementation Notes
