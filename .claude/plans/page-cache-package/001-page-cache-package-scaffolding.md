# Task 001: marko/page-cache Package Scaffolding

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/page-cache` package directory structure with `composer.json`, `Pest.php`, and a `PackageStructureTest`. Add autoload entries to the root monorepo `composer.json`. No `module.php` is needed for an interface package.

## Context
- Related files:
  - `packages/cache/composer.json` (template for composer.json)
  - `packages/cache/tests/Pest.php` (template)
  - `packages/cache/tests/PackageStructureTest.php` (template)
  - `composer.json` (root — add autoload + branch alias entries)
- Patterns to follow:
  - Namespace: `Marko\PageCache\`
  - Package name: `marko/page-cache`
  - PHP 8.5, declare(strict_types=1)
  - `extra.marko.module: true`
  - Dependencies: `marko/core`, `marko/config`, `marko/routing` (the middleware needs `Request`/`Response`/`RouteMatcherInterface`)
  - Dev dependencies: `pestphp/pest`, `marko/testing`

## Requirements (Test Descriptions)
- [ ] `it has marko module flag in composer.json`
- [ ] `it declares correct PSR-4 autoloading namespace Marko\PageCache\\`
- [ ] `it depends on marko/core, marko/config, and marko/routing`
- [ ] `it registers the package test autoload in the root composer.json autoload-dev`
- [ ] `it registers the package as a path repository in the root composer.json`
- [ ] `it declares marko/page-cache as a self.version requirement in the root composer.json`

## Acceptance Criteria
- `packages/page-cache/composer.json` exists with name `marko/page-cache`
- `packages/page-cache/tests/Pest.php` exists
- `packages/page-cache/tests/PackageStructureTest.php` covers the four requirements above
- Root `composer.json` has the new package's path repository entry under `repositories[]` (kept alphabetically ordered with other `packages/...` entries)
- Root `composer.json` has `marko/page-cache: self.version` in `require` (kept alphabetically ordered)
- Root `composer.json` has `Marko\\PageCache\\Tests\\` in `autoload-dev.psr-4` mapped to `packages/page-cache/tests/` (kept alphabetically ordered). Production autoload is NOT added at the root — each package's own `composer.json` declares its `autoload.psr-4` and Composer aggregates from path repos.
- No `branch-alias` entries (this monorepo does not use them — verify against existing `packages/cache/composer.json`)
- `composer dump-autoload` succeeds

## Implementation Notes
(Left blank — filled in by programmer during implementation)
