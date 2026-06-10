# Task 003: API token optional expiry on create and guard enforcement

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Personal access tokens never expire: `TokenManager::createToken()` never sets `expiresAt` and `TokenGuard::resolveTokenEntity()` resolves a token by hash without checking expiry. The `PersonalAccessToken` entity already has a nullable `expiresAt` column and `ExpiredTokenException::forToken()` already exists. Accept an optional `expiresAt` when creating a token and reject expired tokens in the guard, while keeping the timing-safe SHA-256 lookup intact. `expiresAt` stays nullable with no forced default TTL — expiry is enforced only when set.

## Context
- Related files: `packages/authentication-token/src/Service/TokenManager.php` (createToken ~20-42), `packages/authentication-token/src/Guard/TokenGuard.php` (resolveTokenEntity ~52-69, user ~71-80, hasAbility ~110+), `packages/authentication-token/src/Entity/PersonalAccessToken.php` (`?string $expiresAt`), `packages/authentication-token/src/Exceptions/ExpiredTokenException.php` (`forToken()`), `packages/authentication-token/src/Contracts/TokenRepositoryInterface.php`, `packages/authentication-token/src/Contracts/NewAccessToken.php`
- Patterns to follow: additive optional nullable parameter (default null) — do not break existing callers; keep `hash('sha256', $rawToken)` + repository `findByToken` lookup; loud expiry handling via the existing `ExpiredTokenException`; null-safe `DateTimeImmutable` comparison.

## Requirements (Test Descriptions)
- [ ] `it stores the provided expiresAt on the created personal access token`
- [ ] `it leaves expiresAt null when no expiry is provided to createToken`
- [ ] `it resolves the user for a token whose expiresAt is null`
- [ ] `it resolves the user for a token whose expiresAt is in the future`
- [ ] `it treats a token whose expiresAt is in the past as unauthenticated and returns no user`
- [ ] `it returns false from hasAbility when the resolved token has expired`
- [ ] `it preserves the timing-safe SHA-256 hash lookup when resolving a token`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
