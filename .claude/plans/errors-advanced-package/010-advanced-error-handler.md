# Task 010: AdvancedErrorHandler with Fallback Chain

**Status**: completed
**Depends on**: 005, 007
**Retry count**: 0

## Description
Create AdvancedErrorHandler that uses PrettyHtmlFormatter with fallback.

## Context
- Implements ErrorHandlerInterface
- Falls back to BasicHtmlFormatter on errors
- Handles CLI vs Web environments

## Requirements (Test Descriptions)
- [x] `it implements ErrorHandlerInterface`
- [x] `it uses PrettyHtmlFormatter for web`
- [x] `it uses TextFormatter for CLI`
- [x] `it falls back to BasicHtmlFormatter on error`
- [x] `it handles ErrorReport correctly`
- [x] `it catches formatter exceptions`

## Acceptance Criteria
- All requirements have passing tests
- Fallback chain is robust
- Never fails silently

## Implementation Notes
- Created AdvancedErrorHandler class implementing ErrorHandlerInterface
- Uses PrettyHtmlFormatter for web requests (with dark mode CSS)
- Uses TextFormatter for CLI requests
- Falls back to BasicHtmlFormatter when PrettyHtmlFormatter throws
- Added marko/errors-simple as a dependency for access to Environment, TextFormatter, BasicHtmlFormatter, and CodeSnippetExtractor
- handleException converts Throwable to ErrorReport and passes to handle()
- All formatter exceptions are caught and fallback is used
