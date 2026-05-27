# Task 004: Pilot — add mutual conflict blocks to database-mysql and database-pgsql

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Add Composer `conflict` declarations to `marko/database-mysql` and `marko/database-pgsql` so they cannot be installed together. Each driver's `conflict` block must list every OTHER driver from `database/known-drivers.php` (currently just the one sibling). Note: `marko/database-readwrite` is NOT in this list — it's an add-on that coexists with both drivers.

## Context
- Files to modify:
  - `packages/database-mysql/composer.json` — add `"conflict": {"marko/database-pgsql": "*"}`
  - `packages/database-pgsql/composer.json` — add `"conflict": {"marko/database-mysql": "*"}`
- New test files (one per driver, to verify the conflict declaration):
  - `packages/database-mysql/tests/ComposerConflictTest.php`
  - `packages/database-pgsql/tests/ComposerConflictTest.php`
- Reference: `packages/view-twig/composer.json` already has this pattern (from the previous plan); mirror its structure.

**Important — verify current state first:** Check whether either composer.json already has a `conflict` block. If yes, extend it; if no, add it. Both packages currently lack `conflict` blocks (as of plan creation).

`marko/database-readwrite` does NOT get a conflict declaration — it's not in known-drivers.php and is intended to coexist with mysql or pgsql.

## Requirements (Test Descriptions)
For each driver (mysql and pgsql), in its respective test file:
- [ ] `it declares a Composer conflict with the sibling database driver`
- [ ] `it does not declare a conflict with marko/database-readwrite (add-on coexists)`
- [ ] `it uses wildcard version for the conflict declaration`

## Acceptance Criteria
- Both driver composer.json files include a `conflict` block listing the sibling driver with `*` version
- Neither lists `marko/database-readwrite` as a conflict
- New test files verify the conflict declarations exist and have the correct shape
- Both test files pass
- Code follows code standards
