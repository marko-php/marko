# Task 002: Ship Marko\Testing\TestCase (autoloaders only)

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Add `Marko\Testing\TestCase` to `marko/testing`. It extends PHPUnit's `TestCase` and, before tests run, registers `app/*` and `modules/*` PSR-4 autoloaders (via Task 001's `ModuleAutoloader`) so module/app classes resolve under bare `pest` with **zero** per-project Composer config (no classmap, no path repo). It must NOT boot the application, the container, or any driver — this is the lightweight base that makes the skill's `Pest.php.tmpl` (`use Marko\Testing\TestCase;`) correct.

## Context
- Related files:
  - `packages/testing/src/` (new `TestCase.php`), `packages/testing/composer.json` (already requires `marko/core`)
  - Task 001 `Marko\Core\Module\ModuleAutoloader`
  - Reference: framework packages currently use the default PHPUnit `TestCase` with `uses(...)` commented out (e.g. `marko/core/tests/Pest.php`)
- Patterns to follow: `declare(strict_types=1)`; no final classes; full type declarations.
- **Project-root detection:** the base class must locate the project root when tests run from the root *or* from a nested module dir (e.g. `app/home/tests/`). Walk up from the test/cwd location to the nearest ancestor containing `vendor/`. Register autoloaders once per process.
  - **Edge case — running inside the marko monorepo:** when `marko/testing`'s OWN suite runs from `packages/testing/`, the nearest ancestor with `vendor/` is the monorepo root, whose `app/` and `modules/` dirs are absent or unrelated. Detection must not error or mis-register in that layout; it should resolve to a usable root and no-op cleanly when no `app/`/`modules/` modules exist (covered by the empty-dirs requirement).
- **Registration timing:** autoloaders must be registered before any `App\*` / `modules` class is resolved during a test. Register from a place that runs before the test body executes (e.g. the constructor or `setUp()`), and ensure that placement still works when a test references an app-module class only inside the test closure (PHP resolves `use` imports lazily at first runtime reference, which is after `setUp()` — so `setUp()` registration is sufficient; do NOT rely on top-level file-scope class instantiation being safe).
- Do not add new runtime dependencies that pull in drivers; this class must work with no session/database/etc. driver installed.

## Requirements (Test Descriptions)
- [x] `it registers app module autoloaders so an App namespaced class resolves in tests`
- [x] `it resolves a class from a module in the modules directory`
- [x] `it locates the project root when running from a nested module tests directory`
- [x] `it does not require a session or database driver to be installed`
- [x] `it extends the phpunit test case so it works as a Pest base via uses()`
- [x] `it registers autoloaders only once across multiple test cases`
- [x] `it does not error and resolves nothing when run from a package dir whose root has no app or modules modules`
- [x] `it has registered the autoloaders by the time the test body runs so an App class resolves inside the test closure`

## Acceptance Criteria
- A Pest test using `uses(TestCase::class)` resolves an app-module class with no classmap/path-repo in the host project.
- No container/app boot occurs.
- All requirements have passing tests; lint clean; no coverage decrease.

## Implementation Notes

- `TestCase` extends `PHPUnit\Framework\TestCase`, aliases as `PhpUnitTestCase` in autoloads.
- `setUp()` calls `registerModuleAutoloaders()` which uses `ModuleAutoloader` from `marko/core`.
- `projectRoot()` walks up from `getcwd()` until it finds a directory containing `vendor/`.
- Registration is idempotent: `static array $registeredRoots` keyed by root path prevents duplicate `spl_autoload_register` calls within a process.
- When the monorepo root has no `app/` or `modules/` directories, `ModuleDiscovery` returns empty arrays and no autoloaders are registered (no-op).
- Test fixtures live at `packages/testing/tests/fixtures/project-root/` (with `app/home/` and `modules/blog/`) and `packages/testing/tests/fixtures/nested-project/` (with `app/accounts/`).
- Tests override `projectRoot()` via anonymous class subclasses with `public static string $root` properties, avoiding PHPUnit's `final __construct()` constraint.
