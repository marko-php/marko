# Task 003: SyntaxHighlighter

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Create the SyntaxHighlighter class for PHP code tokenization and coloring.

## Context
- Uses PHP's token_get_all() to tokenize code
- HTML escaping and span-based color coding
- No external dependencies

## Requirements (Test Descriptions)
- [x] `it highlights PHP code with spans`
- [x] `it escapes HTML entities`
- [x] `it identifies token types correctly`
- [x] `it handles invalid PHP code gracefully`
- [x] `it produces valid HTML output`
- [x] `it supports context lines around error`

## Acceptance Criteria
- All requirements have passing tests
- Output is valid HTML
- Handles edge cases gracefully

## Implementation Notes
- Created `SyntaxHighlighter` class in `packages/errors-advanced/src/SyntaxHighlighter.php`
- Uses PHP's `token_get_all()` for tokenization
- HTML escaping via `htmlspecialchars()` with ENT_QUOTES | ENT_HTML5
- Token type classification via match expression covering keywords, strings, variables, comments, numbers, tags, and identifiers
- `highlight()` method tokenizes and wraps tokens in span elements with CSS classes
- `highlightWithContext()` method extracts lines around an error line and highlights them
