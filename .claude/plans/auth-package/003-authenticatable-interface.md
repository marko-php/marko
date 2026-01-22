# Task 003: AuthenticatableInterface and Trait

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the AuthenticatableInterface contract for entities that can be authenticated, and the Authenticatable trait providing default implementation.

## Context
- Related files: See interface design in _plan.md
- Interface defines contract for user entities (getAuthIdentifier, getAuthPassword, remember tokens)
- Trait provides default implementation for common patterns

## Requirements (Test Descriptions)
- [ ] `it creates AuthenticatableInterface with getAuthIdentifier method`
- [ ] `it creates AuthenticatableInterface with getAuthIdentifierName method`
- [ ] `it creates AuthenticatableInterface with getAuthPassword method`
- [ ] `it creates AuthenticatableInterface with getRememberToken method`
- [ ] `it creates AuthenticatableInterface with setRememberToken method`
- [ ] `it creates AuthenticatableInterface with getRememberTokenName method`
- [ ] `it creates Authenticatable trait implementing interface methods`
- [ ] `it returns id property as default identifier`
- [ ] `it returns password property as default password field`
- [ ] `it returns rememberToken property for remember token`

## Acceptance Criteria
- All requirements have passing tests
- Interface is fully typed with PHP 8.5 features
- Trait can be used in user entities

## Implementation Notes
(Left blank - filled in by programmer during implementation)
