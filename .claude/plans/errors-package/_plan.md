# Plan: Error Handling Packages

## Created
2026-01-20

## Status
in_progress

## Objective
Create `marko/errors` (interface package) and `marko/errors-simple` (reliable fallback implementation) to provide error handling infrastructure for the Marko framework.

## Scope

### In Scope
- `marko/errors`: Interface package defining contracts for error handling
  - `ErrorHandlerInterface` - contract for handling errors/exceptions
  - `ErrorReporterInterface` - optional contract for external reporting (Sentry, etc.)
  - `ErrorReport` - value object containing all error context
  - `Severity` - enum for error levels (error, warning, notice, deprecated)
- `marko/errors-simple`: Reliable fallback implementation
  - `SimpleErrorHandler` - implements ErrorHandlerInterface
  - `TextFormatter` - CLI output with ANSI colors
  - `BasicHtmlFormatter` - plain HTML output (xdebug-style)
  - PHP error/exception handler registration
  - Environment detection (CLI vs web, development vs production)
  - Code snippet extraction for context

### Out of Scope
- `marko/errors-advanced` (pretty Ignition-style UI) - shelved for future, needs templating
- External reporting implementations (Sentry, Bugsnag drivers)
- Logging integration (separate concern, will integrate via events)
- Custom error pages for production (application responsibility)

## Success Criteria
- [ ] `marko/errors` provides clean interfaces with no implementation
- [ ] `marko/errors-simple` has zero dependencies beyond core and errors
- [ ] Error handler auto-registers via module boot (no special bootstrap code)
- [ ] CLI errors display with ANSI colors and stack traces
- [ ] Web errors display basic HTML with stack trace and code context
- [ ] Development mode shows full details
- [ ] Production mode shows generic message, captures full details for logging
- [ ] ErrorReport captures: exception, stack trace, request context, code snippets
- [ ] Both packages have README.md documentation (conversational, no code)
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Severity enum | - | completed |
| 002 | ErrorReport value object | 001 | pending |
| 003 | ErrorHandlerInterface contract | 002 | pending |
| 004 | ErrorReporterInterface contract | 002 | pending |
| 005 | errors package setup | 001, 002, 003, 004 | pending |
| 006 | CodeSnippetExtractor | - | completed |
| 007 | TextFormatter for CLI | 001, 002, 006 | pending |
| 008 | BasicHtmlFormatter for web | 001, 002, 006 | pending |
| 009 | Environment detection | - | completed |
| 010 | SimpleErrorHandler | 003, 007, 008, 009 | pending |
| 011 | PHP error/exception registration | 010 | pending |
| 012 | errors-simple package setup | 005, 010, 011 | pending |
| 013 | Integration testing | 012 | pending |
| 014 | errors package README | 005 | pending |
| 015 | errors-simple package README | 012 | pending |

## Architecture Notes

### Interface/Implementation Split
Following the established pattern: `marko/errors` defines interfaces only, `marko/errors-simple` provides the implementation. This allows `marko/errors-advanced` to be added later as an alternative driver.

### Fallback Chain
`errors-simple` is designed to NEVER fail. It's the safety net that catches errors even in the error handler itself. This means:
- Zero dependencies beyond core (no templating, no external libraries)
- Graceful degradation if code snippets can't be read
- Plain text/HTML output that works everywhere

### MarkoException Integration
Existing `MarkoException` already has `context` and `suggestion` fields. `ErrorReport` will extract these when available, providing richer error messages for framework exceptions.

### Environment Detection
- CLI vs Web: Check `PHP_SAPI`
- Development vs Production: Check `MARKO_ENV` or `APP_ENV` environment variable (default: production for safety)

### Namespace Structure
```
Marko\Errors\                    # marko/errors
  Contracts\
    ErrorHandlerInterface
    ErrorReporterInterface
  ErrorReport
  Severity

Marko\Errors\Simple\             # marko/errors-simple
  SimpleErrorHandler
  Formatters\
    TextFormatter
    BasicHtmlFormatter
  CodeSnippetExtractor
  Environment
```

## Risks & Mitigations
- **Risk**: Error handler itself throws exception → **Mitigation**: Wrap all formatting in try/catch, fall back to basic string output
- **Risk**: File not readable for code snippets → **Mitigation**: Gracefully skip snippet, show file path only
- **Risk**: Memory exhaustion during error handling → **Mitigation**: Keep error handling minimal, no large allocations
