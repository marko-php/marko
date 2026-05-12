# Task 010: marko/page-cache-file Package Scaffolding

**Status**: completed
**Depends on**: 005, 006
**Retry count**: 0

## Description
Create the `marko/page-cache-file` driver package: directory, `composer.json`, `module.php` binding `PageCacheInterface` to `FilePageCacheDriver`, `Pest.php`, and a `PackageStructureTest`. Add monorepo autoload entries. The driver class itself is a stub at this stage — task 011 implements it.

## Context
- Related files:
  - `packages/cache-file/composer.json` (template)
  - `packages/cache-file/module.php` (template — minimal `bindings` array)
  - `packages/cache-file/tests/Pest.php`
  - `packages/cache-file/tests/PackageStructureTest.php`
  - `composer.json` (root)
- Patterns to follow:
  - Namespace: `Marko\PageCache\File\`
  - Package name: `marko/page-cache-file`
  - Driver class location: `src/Driver/FilePageCacheDriver.php`
  - Dependencies: `marko/core`, `marko/config`, `marko/routing`, `marko/page-cache` (all `self.version`)
  - `module.php` binds `PageCacheInterface::class => FilePageCacheDriver::class`

## Requirements (Test Descriptions)
- [ ] `it has marko module flag in composer.json`
- [ ] `it declares correct PSR-4 autoloading namespace Marko\PageCache\File\\`
- [ ] `it depends on marko/page-cache`
- [ ] `it binds PageCacheInterface to FilePageCacheDriver in module.php`
- [ ] `it registers the package as a path repository in the root composer.json`
- [ ] `it declares marko/page-cache-file as a self.version requirement in the root composer.json`
- [ ] `it registers the package test autoload as Marko\PageCache\File\Tests\\ in the root composer.json autoload-dev`

## Acceptance Criteria
- `packages/page-cache-file/composer.json`, `module.php`, `tests/Pest.php`, `tests/PackageStructureTest.php` exist
- A stub `src/Driver/FilePageCacheDriver.php` exists implementing `PageCacheInterface` with all five methods throwing `LogicException('not implemented')` (placeholder; task 011 fills these in). This lets `module.php` reference a real class for the structure test.
- Root `composer.json` has the path repository entry, the `marko/page-cache-file: self.version` requirement, and `Marko\\PageCache\\File\\Tests\\` test autoload — all alphabetically ordered. Production autoload lives in the package's own `composer.json` (`Marko\\PageCache\\File\\` → `src/`).
- No `branch-alias` entries (verify against `packages/cache-file/composer.json`).
- `composer dump-autoload` succeeds

## Implementation Notes
(Left blank — filled in by programmer during implementation)
