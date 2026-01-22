# Task 010: AdvancedErrorHandler with Fallback Chain

**Status**: pending
**Depends on**: 005, 007
**Retry count**: 0

## Description
Create AdvancedErrorHandler that uses PrettyHtmlFormatter with fallback.

## Context
- Implements ErrorHandlerInterface
- Falls back to BasicHtmlFormatter on errors
- Handles CLI vs Web environments

## Requirements (Test Descriptions)
- [ ] `it implements ErrorHandlerInterface`
- [ ] `it uses PrettyHtmlFormatter for web`
- [ ] `it uses TextFormatter for CLI`
- [ ] `it falls back to BasicHtmlFormatter on error`
- [ ] `it handles ErrorReport correctly`
- [ ] `it catches formatter exceptions`

## Acceptance Criteria
- All requirements have passing tests
- Fallback chain is robust
- Never fails silently

## Implementation Notes
(Left blank - filled in by programmer during implementation)
