# Task 013: Integration Testing

**Status**: pending
**Depends on**: 012
**Retry count**: 0

## Description
Create integration tests that verify the error handling packages work correctly together and with the rest of the framework. Tests should cover real error scenarios in both CLI and web contexts.

## Context
- Related files: `packages/errors-simple/tests/Feature/`
- Must test actual PHP error/exception handling
- Test both development and production modes

## Requirements (Test Descriptions)
- [ ] `it handles thrown exception in CLI context`
- [ ] `it handles thrown exception in web context`
- [ ] `it handles PHP warning in CLI context`
- [ ] `it handles PHP warning in web context`
- [ ] `it handles MarkoException with context and suggestion`
- [ ] `it handles nested exception with previous`
- [ ] `it shows full details in development mode`
- [ ] `it hides details in production mode`
- [ ] `it extracts code snippet from error location`
- [ ] `it gracefully handles missing source file`
- [ ] `it can be resolved from container via interface`
- [ ] `it registers and unregisters cleanly`
- [ ] `it falls back to plain text when formatter fails`

## Acceptance Criteria
- All requirements have passing tests
- Tests cover realistic error scenarios
- Tests verify both CLI and web output
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
