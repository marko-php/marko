# Plan: Bootstrap API Simplification

## Created
2026-03-25

## Status
completed

## Objective
Simplify the Marko bootstrap API so that `public/index.php` can be reduced from 6+ lines to 2 meaningful lines: `$app = Application::boot(dirname(__DIR__))` + `$app->handleRequest()`. Maintain backwards compatibility with the existing `bootstrap.php` closure.

## Scope

### In Scope
- Fix Router hard type dependency in Application.php (routing is optional, core must not break without it)
- Rename instance method `boot()` to `initialize()` (internal), reclaim `boot()` for the static factory
- Add static `Application::boot(string $basePath)` factory method that infers vendor/modules/app paths, calls `initialize()`, and returns the Application instance
- Add `Application::handleRequest()` method for the request→route→response→send lifecycle
- Move env loading from `bootstrap.php` into `Application::initialize()` (DRY); simplify `bootstrap.php`
- Full test coverage for all changes

### Out of Scope
- Changing the existing constructor signature
- Modifying `ProjectPaths` (it already infers paths from basePath)
- Updating docs pages (that's a separate follow-up plan)
- Creating the skeleton project (separate follow-up plan)
- CLI entry point (`marko` command) — only web request handling

## Success Criteria
- [ ] `Application::boot('/path/to/project')` creates and initializes an Application with correct paths
- [ ] `$app->handleRequest()` handles full request lifecycle (create request, route, send response)
- [ ] `marko/core` can be loaded without `marko/routing` installed (no fatal on class load)
- [ ] Existing `bootstrap.php` closure still works (updated to call `initialize()`)
- [ ] All existing tests pass (updated for rename)
- [ ] New tests cover `boot()`, `handleRequest()`, and Router type safety

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Fix Router hard type dependency — use `?object` with docblocks | - | pending |
| 002 | Rename instance `boot()` to `initialize()` | - | pending |
| 003 | Add static `Application::boot()` factory method | 001, 002 | pending |
| 004 | Add `Application::handleRequest()` method | 001, 003 | pending |
| 005 | Move env loading into `initialize()` and simplify `bootstrap.php` | 002, 003 | pending |

## Architecture Notes
- `Application::boot()` is a static factory that infers paths using `ProjectPaths` conventions: `$basePath/vendor`, `$basePath/modules`, `$basePath/app`. It returns `self`.
- The renamed instance method `initialize()` remains `void`. `boot()` calls `initialize()` internally and returns the instance.
- Router property types change from `?Router` / `Router` to `?object` / `object` with `@var` docblocks, so `marko/core` can be loaded without `marko/routing`.
- `handleRequest()` depends on routing being installed. If `$this->_router === null`, throw a clear RuntimeException BEFORE touching any routing classes.
- Env loading moves into `initialize()` so both entry paths get it. Guard with `class_exists(EnvLoader::class)`. Derive basePath via `dirname($this->vendorPath)`.

## Risks & Mitigations
- **Routing is optional**: `handleRequest()` must check router availability and throw a helpful error. The check must happen BEFORE calling `Request::fromGlobals()`.
- **Rename ripple**: `boot()` → `initialize()` rename touches Application.php, bootstrap.php, and existing tests. Task 002 handles all of these atomically.
- **Env loading moved**: After refactor, `initialize()` does it. Since env loading is removed from `bootstrap.php`, double-loading is not a concern. `EnvLoader::load()` is also idempotent.
