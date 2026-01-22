# Task 007: PrettyHtmlFormatter Production vs Development

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Handle different output for production vs development environments.

## Context
- Development: Full details, syntax highlighting, request data
- Production: Generic error message, no sensitive details

## Requirements (Test Descriptions)
- [ ] `it shows full details in development mode`
- [ ] `it shows generic message in production mode`
- [ ] `it hides stack trace in production`
- [ ] `it hides request data in production`
- [ ] `it respects environment configuration`

## Acceptance Criteria
- All requirements have passing tests
- Production mode is safe/secure
- Development mode is helpful

## Implementation Notes
(Left blank - filled in by programmer during implementation)
