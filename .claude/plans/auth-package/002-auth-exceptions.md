# Task 002: AuthException Hierarchy

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the exception hierarchy for authentication: AuthException (base), AuthenticationException, AuthorizationException, InvalidCredentialsException.

## Context
- Related files: packages/session/src/Exceptions/ (pattern to follow)
- All exceptions should include context and suggestion fields
- Follow "loud errors" philosophy

## Requirements (Test Descriptions)
- [ ] `it creates AuthException as base exception`
- [ ] `it creates AuthException with context and suggestion`
- [ ] `it creates AuthenticationException extending AuthException`
- [ ] `it creates AuthenticationException with factory method`
- [ ] `it creates AuthorizationException extending AuthException`
- [ ] `it creates AuthorizationException with factory method`
- [ ] `it creates InvalidCredentialsException extending AuthenticationException`
- [ ] `it creates InvalidCredentialsException with default message`

## Acceptance Criteria
- All requirements have passing tests
- Exception messages are helpful and actionable
- Factory methods create properly configured exceptions

## Implementation Notes
(Left blank - filled in by programmer during implementation)
