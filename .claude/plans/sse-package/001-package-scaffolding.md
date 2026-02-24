# Task 001: Package Scaffolding

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/sse` package directory structure with `composer.json`, `module.php`, `tests/Pest.php`, `SseException`, and add autoload entries to the root `composer.json`.

## Context
- Related files:
  - `packages/cors/composer.json` — reference for package composer.json structure
  - `packages/cors/module.php` — reference for minimal module.php
  - `packages/cors/src/Exceptions/CorsException.php` — reference for exception class
  - `packages/cors/tests/Pest.php` — reference for Pest config
  - `composer.json` (root) — needs `Marko\\Sse\\` autoload entries added
- Patterns to follow:
  - `type: "marko-module"` in composer.json
  - `extra.marko.module: true` in composer.json
  - Path repositories for `../core` and `../routing`
  - Exception extends `MarkoException` with `/** @noinspection PhpUnused */`
  - Minimal module.php: `return ['bindings' => []]`

## Requirements (Test Descriptions)
- [ ] `it has a valid composer.json with name marko/sse`
- [ ] `it has composer.json with type marko-module and extra.marko.module true`
- [ ] `it has PSR-4 autoloading configured for Marko\Sse namespace`
- [ ] `it requires marko/core and marko/routing as dependencies`
- [ ] `it has a module.php that returns array with bindings key`
- [ ] `it has SseException extending MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Package directory structure exists: `packages/sse/src/Exceptions/`, `packages/sse/tests/`
- Root `composer.json` has `Marko\\Sse\\` and `Marko\\Sse\\Tests\\` entries
- `composer dump-autoload` succeeds
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
