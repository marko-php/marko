# Task 022: Bootstrap `marko/scope-pgsql` package skeleton

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create the `marko/scope-pgsql` driver package skeleton following sibling-module conventions. Requires `marko/scope` and `marko/database-pgsql`.

## Context
- Related files: `packages/scope-pgsql/` (new), `packages/database-pgsql/composer.json` (reference)
- Patterns to follow: `.claude/sibling-modules.md` — class prefix `PgSql*`, namespace `Marko\Scope\PgSql\`. Mirror task 018 structure exactly.

## Requirements (Test Descriptions)
- [x] `it has a valid composer.json with name marko/scope-pgsql and extra.marko.module true`
- [x] `it requires marko/scope and marko/database-pgsql in composer.json`
- [x] `it autoloads PSR-4 namespace Marko\Scope\PgSql\ from packages/scope-pgsql/src/`
- [x] `it autoloads tests namespace Marko\Scope\PgSql\Tests\ from tests/`
- [x] `it has a module.php returning an array with bindings key`
- [x] `it has no version field in composer.json`

## Acceptance Criteria
- Directory layout matches `packages/database-pgsql/`.
- `composer install --dry-run` succeeds.

## Implementation Notes
- Created `packages/scope-pgsql/` directory with `src/` and `tests/` subdirectories
- Created `composer.json` following `database-pgsql` pattern with `marko/scope` and `marko/database-pgsql` dependencies
- Created `module.php` returning empty bindings array (skeleton, to be filled in task 025)
- Created `LICENSE` (MIT, Copyright Devtomic LLC) and `.gitattributes` for proper packaging
- Registered in root `composer.json`: repository path entry, require entry, autoload-dev entry for `Marko\Scope\PgSql\Tests\`
- Added `scope`, `scope-mysql`, `scope-pgsql` to GitHub issue templates (bug_report.yml and feature_request.yml)
