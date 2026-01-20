# Task 001: Severity Enum

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the Severity enum that represents different levels of PHP errors. This enum will be used throughout the error handling system to classify errors by their severity level.

## Context
- Related files: `packages/errors/src/Severity.php`
- Patterns to follow: PHP 8.1+ backed enums, similar to existing enums in core
- Must map to PHP's native error levels (E_ERROR, E_WARNING, etc.)

## Requirements (Test Descriptions)
- [ ] `it has an error case for fatal errors`
- [ ] `it has a warning case for warnings`
- [ ] `it has a notice case for notices`
- [ ] `it has a deprecated case for deprecation warnings`
- [ ] `it creates severity from PHP error level constant`
- [ ] `it returns error for E_ERROR and E_USER_ERROR`
- [ ] `it returns warning for E_WARNING and E_USER_WARNING`
- [ ] `it returns notice for E_NOTICE and E_USER_NOTICE`
- [ ] `it returns deprecated for E_DEPRECATED and E_USER_DEPRECATED`
- [ ] `it provides human readable label for each severity`
- [ ] `it provides ANSI color code for each severity`

## Acceptance Criteria
- All requirements have passing tests
- Enum is backed by string values for serialization
- Code follows project standards
