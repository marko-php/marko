# Task 021: `marko/scope-mysql` `module.php`

**Status**: pending
**Depends on**: 019, 030
**Retry count**: 0

## Description
Bind `ScopeSortRendererInterface` to `MySqlScopeSortRenderer`. This is the only wiring the driver package needs; the rest of `marko/scope`'s bindings already cover everything.

## Context
- Related files: `packages/scope-mysql/module.php` (new)
- Patterns to follow: `packages/database-mysql/module.php` for driver-package wiring style.

## Requirements (Test Descriptions)
- [ ] `it returns an array with bindings key`
- [ ] `it binds ScopeSortRendererInterface to MySqlScopeSortRenderer`
- [ ] `it does not re-bind marko/scope interfaces`

## Acceptance Criteria
- Single binding in the file.
- Loads without error in a Marko bootstrap context.

## Implementation Notes
(Left blank — filled in during implementation.)
