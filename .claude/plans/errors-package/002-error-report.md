# Task 002: ErrorReport Value Object

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the ErrorReport value object that encapsulates all information about an error. This is the central data structure passed through the error handling system, containing the exception, stack trace, request context, and any extracted metadata.

## Context
- Related files: `packages/errors/src/ErrorReport.php`
- Patterns to follow: Immutable value objects with readonly properties
- Should extract `context` and `suggestion` from `MarkoException` when available

## Requirements (Test Descriptions)
- [ ] `it creates report from throwable with message and code`
- [ ] `it captures the throwable instance`
- [ ] `it captures the stack trace as array`
- [ ] `it captures the file and line where error occurred`
- [ ] `it captures the severity level`
- [ ] `it captures the timestamp of when error occurred`
- [ ] `it extracts context from MarkoException`
- [ ] `it extracts suggestion from MarkoException`
- [ ] `it returns empty context for non-MarkoException`
- [ ] `it returns empty suggestion for non-MarkoException`
- [ ] `it captures previous exception when present`
- [ ] `it provides unique identifier for the error report`
- [ ] `it is immutable after creation`

## Acceptance Criteria
- All requirements have passing tests
- Uses readonly properties for immutability
- Code follows project standards
