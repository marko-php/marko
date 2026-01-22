# Task 007: PrettyHtmlFormatter Production vs Development

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Handle different output for production vs development environments.

## Context
- Development: Full details, syntax highlighting, request data
- Production: Generic error message, no sensitive details

## Requirements (Test Descriptions)
- [x] `it shows full details in development mode`
- [x] `it shows generic message in production mode`
- [x] `it hides stack trace in production`
- [x] `it hides request data in production`
- [x] `it respects environment configuration`

## Acceptance Criteria
- All requirements have passing tests
- Production mode is safe/secure
- Development mode is helpful

## Implementation Notes
Added environment-aware formatting to PrettyHtmlFormatter:

1. **Constructor parameter**: Added `environment` parameter (defaults to 'development')

2. **Production mode** (`formatProduction`): Returns minimal HTML with generic "An error occurred" message, no sensitive details like:
   - Exception message
   - File paths and line numbers
   - Stack traces
   - Request data
   - Code snippets

3. **Development mode** (`formatDevelopment`): Full error details including:
   - Complete exception message
   - File location and line number
   - Syntax-highlighted code snippets
   - Full stack trace
   - Request method and URI

4. **Environment check**: `isProduction()` method checks if environment equals 'production'
