# Task 001: Bootstrap `marko/scope` package skeleton

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/scope` package skeleton: `composer.json`, empty `module.php`, PSR-4 autoload, and src/tests directory layout matching existing Marko packages.

## Context
- Related files: `packages/scope/` (new), `packages/database/composer.json` (reference), `packages/cache/composer.json` (interface-package reference)
- Patterns to follow: Existing interface packages (`marko/database`, `marko/cache`, `marko/log`). See `.claude/module-development.md` and `.claude/architecture.md` § Package Architecture.

## Requirements (Test Descriptions)
- [x] `it has a valid composer.json with name marko/scope and extra.marko.module set to true`
- [x] `it requires PHP ^8.5, marko/core, marko/config, and marko/database in composer.json`
- [x] `it autoloads PSR-4 namespace Marko\Scope\ from packages/scope/src/`
- [x] `it autoloads PSR-4 test namespace Marko\Scope\Tests\ from packages/scope/tests/`
- [x] `it has a module.php returning array with empty bindings`
- [x] `it has no version field in composer.json`

## Required Dependencies
- `marko/core` — DI / plugin / module foundations.
- `marko/config` — `ConfigRepositoryInterface` is read by `PhpScopeRegistry`.
- `marko/database` — base `Entity` and the extender mechanism are required for the override-companion entity.

## Acceptance Criteria
- Package directory `packages/scope/` exists with `src/`, `tests/Unit/`, `tests/Feature/`, `composer.json`, `module.php`, `LICENSE`.
- `composer install --dry-run` from monorepo root succeeds.
- Lint passes for the new files.

## Implementation Notes
- Created `packages/scope/` with `src/`, `tests/Unit/`, `tests/Feature/`, `composer.json`, `module.php`, `LICENSE`, and `tests/Pest.php`.
- Package type is `marko-module` (matching `marko/cache` and `marko/log` patterns).
- `module.php` returns `['bindings' => []]` (empty bindings, following `packages/pagination/module.php` pattern).
- Registered the package in the monorepo root `composer.json`: added repository path entry, `require` entry, and `autoload-dev` PSR-4 entry.
- All 6 tests pass; lint clean with no changes needed.
