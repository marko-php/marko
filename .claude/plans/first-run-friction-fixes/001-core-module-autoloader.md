# Task 001: Extract a reusable module autoloader in marko/core

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
`Application::registerAutoloaders()` / `registerPsr4Autoloader()` are private and only run during a full app boot. Extract this logic into a reusable, standalone class so a lightweight caller (the test base class in Task 002) can register `app/*` and `modules/*` PSR-4 autoloaders **without** booting the container. `Application` then delegates to it, with no behavior change.

## Context
- Related files:
  - `packages/core/src/Application.php` (lines ~228-268: `registerAutoloaders`, `registerPsr4Autoloader`; uses `ModuleDiscovery`, `ManifestParser`, `DependencyResolver`, module `->source`/`->autoload`/`->path`)
  - `packages/core/src/Module/ModuleDiscovery.php`, `ManifestParser.php`, `ModuleManifest`
  - New: `packages/core/src/Module/ModuleAutoloader.php`
- Patterns to follow: existing `Module/` classes; constructor property promotion; `declare(strict_types=1)`; no final classes; full type declarations.
- The new class should accept a base path, discover non-vendor modules (`modules/`, `app/`) via the existing discovery, and `spl_autoload_register` a PSR-4 closure per namespace→path. Vendor modules are skipped (Composer already handles them). Registration must be idempotent (safe to call once per process; avoid double-registering the same namespace+path).
- **The class must run discovery itself** (`ModuleDiscovery::discoverInModules()` + `discoverInApp()` with a `ManifestParser`) — it CANNOT consume `Application::$modules`, because the lightweight caller (Task 002 `TestCase`) never boots `Application`. `Application::registerAutoloaders()` currently iterates the already-resolved `$this->modules`; when `Application` delegates to the new class it should pass its discovered/resolved non-vendor modules (or the base path) so behavior is unchanged. Keep `Application`'s existing post-resolution call site working.
- **Autoload source:** a module's PSR-4 map comes from its `composer.json` `autoload.psr-4` (see `ManifestParser`), NOT from `module.php`. Discovery also requires `composer.json` with `extra.marko.module: true`. So this only autoloads app/modules dirs that are real Marko modules with a PSR-4 entry — that constraint is inherent and correct; the empty-dirs test covers the no-module case.
- Running `DependencyResolver` is unnecessary for pure autoloader registration (order does not matter for `spl_autoload_register`); discovery alone suffices, which also avoids surfacing a resolver error during a test run.

## Requirements (Test Descriptions)
- [x] `it registers a psr-4 autoloader that resolves an app module class from its source file`
- [x] `it registers autoloaders for modules in the modules directory`
- [x] `it skips vendor modules because composer already autoloads them`
- [x] `it does not register the same namespace and path twice when called repeatedly`
- [x] `it resolves nothing and does not error when app and modules directories are empty`
- [x] `it leaves Application boot still able to autoload an app module class (regression)`

## Acceptance Criteria
- `Application` uses the extracted class; existing core tests still pass.
- New `ModuleAutoloader` is independently constructable without a container.
- All requirements have passing tests; lint clean; no coverage decrease.

## Implementation Notes

- Created `packages/core/src/Module/ModuleAutoloader.php` — standalone class that accepts `modulesPath`, `appPath`, and a `ManifestParser`; runs `ModuleDiscovery::discoverInModules()` + `discoverInApp()` then registers `spl_autoload` closures per PSR-4 map. Idempotency tracked via `$this->registered` (namespace+absolutePath key).
- Updated `Application::registerAutoloaders()` to delegate to `ModuleAutoloader` (added `use` import; removed the now-redundant private `registerPsr4Autoloader()` method).
- All 6 requirements covered by `packages/core/tests/Unit/Module/ModuleAutoloaderTest.php`.
- The DependencyResolverTest failure present in the suite is pre-existing from another task on this branch and unrelated to Task 001.
