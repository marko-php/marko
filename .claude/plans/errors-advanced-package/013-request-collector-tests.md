# Task 013: Unit Tests for RequestDataCollector

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Create comprehensive unit tests for RequestDataCollector.

## Context
- Test sensitive field masking
- Test various request scenarios

## Requirements (Test Descriptions)
- [x] `it masks password fields`
- [x] `it masks token fields`
- [x] `it masks api_key fields`
- [x] `it masks authorization headers`
- [x] `it masks cookies`
- [x] `it handles empty request data`
- [x] `it handles nested data`
- [x] `it preserves non-sensitive data`

## Acceptance Criteria
- All requirements have passing tests
- All sensitive patterns masked
- No security leaks

## Implementation Notes
- Added `token`, `secret`, and `session` to SENSITIVE_FIELD_PATTERNS
- Added cookie collection via `$_COOKIE` superglobal
- Implemented recursive masking for nested arrays in `maskSensitiveData()`
- All 15 RequestDataCollector tests pass (46 total in errors-advanced package)
