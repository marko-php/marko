# Task 005: UserProviderInterface

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Create the UserProviderInterface contract for fetching and validating users from storage.

## Context
- Related files: See interface design in _plan.md
- Provides abstraction over user storage (database, in-memory, etc.)
- Used by guards to retrieve and validate users

## Requirements (Test Descriptions)
- [ ] `it creates UserProviderInterface with retrieveById method`
- [ ] `it creates UserProviderInterface with retrieveByCredentials method`
- [ ] `it creates UserProviderInterface with validateCredentials method`
- [ ] `it creates UserProviderInterface with retrieveByRememberToken method`
- [ ] `it creates UserProviderInterface with updateRememberToken method`
- [ ] `retrieveById returns nullable AuthenticatableInterface`
- [ ] `retrieveByCredentials accepts array of credentials`
- [ ] `validateCredentials takes user and credentials array`

## Acceptance Criteria
- All requirements have passing tests
- Interface is fully typed
- Methods support password validation without exposing passwords

## Implementation Notes
(Left blank - filled in by programmer during implementation)
