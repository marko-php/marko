# Task 008: BasicHtmlFormatter for Web

**Status**: pending
**Depends on**: 001, 002, 006
**Retry count**: 0

## Description
Create the BasicHtmlFormatter class that formats ErrorReport as basic HTML output. This is used when errors occur in web context, providing a simple but functional error page similar to xdebug output.

## Context
- Related files: `packages/errors-simple/src/Formatters/BasicHtmlFormatter.php`
- Must be self-contained - no external CSS, JS, or templates
- Inline styles only for reliability

## Requirements (Test Descriptions)
- [ ] `it returns valid HTML document`
- [ ] `it sets appropriate content type header constant`
- [ ] `it displays the exception class name`
- [ ] `it displays the error message`
- [ ] `it displays file and line number`
- [ ] `it displays formatted stack trace as table`
- [ ] `it displays code snippet with syntax highlighting`
- [ ] `it highlights the error line in code snippet`
- [ ] `it includes line numbers in code snippet`
- [ ] `it displays context when available from MarkoException`
- [ ] `it displays suggestion when available from MarkoException`
- [ ] `it displays previous exception when present`
- [ ] `it escapes HTML entities in error messages`
- [ ] `it escapes HTML entities in file paths`
- [ ] `it escapes HTML entities in code snippets`
- [ ] `it formats in development mode with full details`
- [ ] `it formats in production mode with generic message`
- [ ] `it uses inline styles for reliability`
- [ ] `it uses monospace font for code`

## Acceptance Criteria
- All requirements have passing tests
- HTML is valid and renders in all browsers
- No external dependencies (CSS, JS, images)
- Code follows project standards
