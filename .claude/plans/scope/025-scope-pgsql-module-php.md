# Task 025: `marko/scope-pgsql` `module.php`

**Status**: pending
**Depends on**: 023, 031
**Retry count**: 0

## Description
Bind `ScopeSortRendererInterface` to `PgSqlScopeSortRenderer`. Mirror of task 021.

## Context
- Related files: `packages/scope-pgsql/module.php` (new)
- Patterns to follow: `packages/database-pgsql/module.php`. Mirror task 021's shape.

## Requirements (Test Descriptions)
- [ ] `it returns an array with bindings key`
- [ ] `it binds ScopeSortRendererInterface to PgSqlScopeSortRenderer`
- [ ] `it does not re-bind marko/scope interfaces`

## Acceptance Criteria
- Single binding in the file.
- Loads without error in a Marko bootstrap context.

## Implementation Notes
(Left blank — filled in during implementation.)
