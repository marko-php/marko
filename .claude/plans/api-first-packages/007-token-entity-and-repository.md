# Task 007: Token Entity and Repository Interface

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the marko/authentication-token package scaffolding with the PersonalAccessToken entity, repository interface, and exception classes. This establishes the data layer for stateless API authentication.

## Context
- New package at `packages/authentication-token/`
- Namespace: `Marko\AuthenticationToken`
- Depends on: marko/core, marko/authentication, marko/hashing, marko/database
- Study `packages/authentication/src/Contracts/` for GuardInterface, AuthenticatableInterface, UserProviderInterface
- Study `packages/blog/src/Entity/Post.php` for entity pattern with #[Table] and #[Column] attributes
- Study `packages/blog/src/Contracts/PostRepositoryInterface.php` for repository interface pattern
- Study `packages/authentication/src/Exceptions/` for auth exception patterns
- PersonalAccessToken entity: id, tokenable_type, tokenable_id, name, token_hash, abilities (JSON), last_used_at, expires_at, created_at
- Tokens are hashed (SHA-256) before storage; plain text only available at creation

## Requirements (Test Descriptions)
- [ ] `it defines PersonalAccessToken entity with table and column attributes`
- [ ] `it defines TokenRepositoryInterface with find, findByToken, create, and revoke methods`
- [ ] `it defines HasApiTokensInterface for entities that can have tokens`
- [ ] `it throws InvalidTokenException with context for malformed token format`
- [ ] `it throws ExpiredTokenException with context and suggestion for expired tokens`
- [ ] `it creates valid package scaffolding with composer.json, module.php, and config`

## Acceptance Criteria
- All requirements have passing tests
- Entity is in `src/Entity/PersonalAccessToken.php`
- Contracts are in `src/Contracts/`
- Exceptions are in `src/Exceptions/`
- Config at `config/authentication-token.php` with token_expiration, hash_algorithm settings
- composer.json depends on marko/core, marko/authentication, marko/hashing, marko/database
- Code follows code standards

## Implementation Notes
