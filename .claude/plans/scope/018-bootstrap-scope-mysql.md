# Task 018: Bootstrap `marko/scope-mysql` package skeleton

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the `marko/scope-mysql` driver package skeleton following sibling-module conventions. Requires `marko/scope` and `marko/database-mysql`.

## Context
- Related files: `packages/scope-mysql/` (new), `packages/database-mysql/composer.json` (reference)
- Patterns to follow: `.claude/sibling-modules.md` — class prefix `MySql*`, namespace `Marko\Scope\MySql\`.

## Requirements (Test Descriptions)
- [ ] `it has a valid composer.json with name marko/scope-mysql and extra.marko.module true`
- [ ] `it requires marko/scope and marko/database-mysql in composer.json`
- [ ] `it autoloads PSR-4 namespace Marko\Scope\MySql\ from packages/scope-mysql/src/`
- [ ] `it autoloads tests namespace Marko\Scope\MySql\Tests\ from tests/`
- [ ] `it has a module.php returning an array with bindings key`
- [ ] `it has no version field in composer.json`

## Acceptance Criteria
- Directory layout matches existing driver packages (`packages/database-mysql/`).
- `composer install --dry-run` succeeds.

## Implementation Notes
(Left blank — filled in during implementation.)
