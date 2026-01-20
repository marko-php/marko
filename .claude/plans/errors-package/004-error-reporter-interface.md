# Task 004: ErrorReporterInterface Contract

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Define the ErrorReporterInterface contract for sending error reports to external services (Sentry, Bugsnag, logging systems, etc.). This is separate from the handler to allow multiple reporters to receive the same error.

## Context
- Related files: `packages/errors/src/Contracts/ErrorReporterInterface.php`
- Patterns to follow: Existing interfaces in core
- This interface is optional - not all applications need external reporting

## Requirements (Test Descriptions)
- [ ] `it defines report method that accepts ErrorReport`
- [ ] `it defines report method that returns void`
- [ ] `it defines shouldReport method that accepts ErrorReport`
- [ ] `it defines shouldReport method that returns bool`

## Acceptance Criteria
- All requirements have passing tests
- Interface is minimal and focused
- Documented with PHPDoc explaining separation from handler
- Code follows project standards
