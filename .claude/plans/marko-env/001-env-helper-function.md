# Task 001: Create env() Helper Function

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the global `env()` helper function that retrieves environment variables with optional default values and automatic type coercion for common string patterns (true, false, null, empty).

## Context
- Related files: `packages/env/src/functions.php` (new file)
- Patterns to follow: Simple global function, similar to Laravel's env() but simpler
- This function will be autoloaded via Composer's `files` autoload before bootstrap

## Requirements (Test Descriptions)
- [ ] `it returns environment variable value when set`
- [ ] `it returns default value when environment variable not set`
- [ ] `it returns null when not set and no default provided`
- [ ] `it converts string true to boolean true`
- [ ] `it converts string false to boolean false`
- [ ] `it converts string null to null`
- [ ] `it converts string empty to empty string`
- [ ] `it handles parenthesized values like (true) and (false)`
- [ ] `it is case insensitive for boolean and null conversions`
- [ ] `it checks both $_ENV and getenv() for value`
- [ ] `it preserves string values that are not special keywords`
- [ ] `it preserves numeric strings as strings`

## Acceptance Criteria
- All requirements have passing tests
- Function is declared in global namespace
- Function has proper type hints and return type (mixed)
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
