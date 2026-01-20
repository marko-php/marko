# Task 010: SimpleErrorHandler

**Status**: completed
**Depends on**: 003, 007, 008, 009
**Retry count**: 0

## Description
Create the SimpleErrorHandler class that implements ErrorHandlerInterface. This is the main error handler that coordinates error capture, formatting, and output. It must be rock-solid reliable as the fallback handler.

## Context
- Related files: `packages/errors-simple/src/SimpleErrorHandler.php`
- Patterns to follow: ErrorHandlerInterface contract
- Must never throw exceptions - wrap everything in try/catch

## Requirements (Test Descriptions)
- [ ] `it implements ErrorHandlerInterface`
- [ ] `it accepts Environment dependency for context detection`
- [ ] `it uses TextFormatter for CLI errors`
- [ ] `it uses BasicHtmlFormatter for web errors`
- [ ] `it creates ErrorReport from Throwable`
- [ ] `it creates ErrorReport from PHP error`
- [ ] `it converts PHP errors to ErrorException`
- [ ] `it outputs formatted error to stdout in CLI`
- [ ] `it outputs formatted error to response in web`
- [ ] `it respects error_reporting level`
- [ ] `it returns true from handleError when error is handled`
- [ ] `it shows full details in development mode`
- [ ] `it shows generic message in production mode`
- [ ] `it catches exceptions in formatters and falls back to plain text`
- [ ] `it sets HTTP 500 status code for web errors`
- [ ] `it clears output buffer before rendering error`

## Acceptance Criteria
- All requirements have passing tests
- Never throws exceptions
- Works in both CLI and web contexts
- Code follows project standards
