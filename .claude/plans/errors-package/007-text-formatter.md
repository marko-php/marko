# Task 007: TextFormatter for CLI

**Status**: completed
**Depends on**: 001, 002, 006
**Retry count**: 0

## Description
Create the TextFormatter class that formats ErrorReport as colored terminal output. This is used when errors occur in CLI context, providing a readable stack trace with ANSI colors.

## Context
- Related files: `packages/errors-simple/src/Formatters/TextFormatter.php`
- Patterns to follow: CLI output in core Command/Output
- Must work without ANSI colors when terminal doesn't support them

## Requirements (Test Descriptions)
- [ ] `it formats error message with severity color`
- [ ] `it displays the exception class name`
- [ ] `it displays the error message`
- [ ] `it displays file and line number`
- [ ] `it displays formatted stack trace`
- [ ] `it displays code snippet around error line`
- [ ] `it highlights the error line in code snippet`
- [ ] `it includes line numbers in code snippet`
- [ ] `it displays context when available from MarkoException`
- [ ] `it displays suggestion when available from MarkoException`
- [ ] `it displays previous exception when present`
- [ ] `it detects ANSI support and disables colors when not available`
- [ ] `it can be forced to disable colors`
- [ ] `it truncates very long file paths for readability`
- [ ] `it formats in development mode with full details`
- [ ] `it formats in production mode with minimal output`

## Acceptance Criteria
- All requirements have passing tests
- Output is readable in standard 80-column terminal
- Colors use standard ANSI escape codes
- Code follows project standards
