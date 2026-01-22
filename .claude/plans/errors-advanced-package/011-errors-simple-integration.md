# Task 011: Integration with marko/errors-simple

**Status**: pending
**Depends on**: 010
**Retry count**: 0

## Description
Ensure proper integration with marko/errors-simple formatters.

## Context
- Both packages coexist via preference selection
- Can fall back to errors-simple formatters
- Module declares preference

## Requirements (Test Descriptions)
- [ ] `module.php declares bindings`
- [ ] `it can use BasicHtmlFormatter as fallback`
- [ ] `it can use TextFormatter from errors-simple`
- [ ] `preference overrides ErrorHandlerInterface`
- [ ] `packages can coexist`

## Acceptance Criteria
- All requirements have passing tests
- Integration is seamless
- Preference system works

## Implementation Notes
(Left blank - filled in by programmer during implementation)
