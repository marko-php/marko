# Task 005: PrettyHtmlFormatter Core Structure

**Status**: completed
**Depends on**: 003, 004
**Retry count**: 0

## Description
Create the core PrettyHtmlFormatter class structure.

## Context
- Implements FormatterInterface from marko/errors
- Uses SyntaxHighlighter and RequestDataCollector
- Self-contained HTML with embedded CSS

## Requirements (Test Descriptions)
- [x] `it implements FormatterInterface`
- [x] `it formats ErrorReport to HTML`
- [x] `it includes exception message`
- [x] `it includes file and line number`
- [x] `it includes code snippet`
- [x] `it embeds CSS in HTML output`
- [x] `it produces valid HTML document`

## Acceptance Criteria
- All requirements have passing tests
- Output is valid, self-contained HTML
- No external CSS dependencies

## Implementation Notes
- Created `FormatterInterface` in `marko/errors` package at `packages/errors/src/Contracts/FormatterInterface.php`
- Created `PrettyHtmlFormatter` at `packages/errors-advanced/src/PrettyHtmlFormatter.php`
- PrettyHtmlFormatter uses constructor property promotion with optional SyntaxHighlighter dependency
- Uses SyntaxHighlighter.highlightWithContext() for code snippet display
- Embeds minimal CSS directly in HTML for self-contained output
- HTML escapes all user-provided content to prevent XSS
