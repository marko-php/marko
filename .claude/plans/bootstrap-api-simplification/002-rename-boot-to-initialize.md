# Task 002: Rename instance boot() to initialize()

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Rename the public instance method `boot()` to `initialize()` on the Application class. This frees up the `boot()` name for the static factory method (task 003). Update all callers: `bootstrap.php`, `CliKernel.php`, `ApplicationTest.php`, and `RoutingBootstrapperTest.php`.

## Context
- Related files: `packages/core/src/Application.php`, `packages/core/bootstrap.php`, `packages/core/tests/Unit/ApplicationTest.php`, `packages/routing/tests/RoutingBootstrapperTest.php`, `packages/cli/src/CliKernel.php`
- The current `boot()` is called in 5 locations:
  1. `bootstrap.php` line 37: `$app->boot()`
  2. Existing tests in `ApplicationTest.php` that call `$app->boot()` (29 instances)
  3. `RoutingBootstrapperTest.php` that calls `$app->boot()` (10 instances, including one in a `toThrow()` chain)
  4. `CliKernel.php` line 78: `$app->boot()`
  5. Module `boot` callbacks are called inside the method (line 144) — these are `$module->boot`, NOT `$app->boot()`, so no conflict — do NOT rename those
- The `router` property hook message says "Call boot() first." — update to "Router not available. Install marko/routing: composer require marko/routing"
- `initialize()` remains `public` (bootstrap.php needs to call it) and `void`

## Requirements (Test Descriptions)
- [ ] `it has an initialize() method that performs all discovery and wiring`
- [ ] `it no longer has a public boot() instance method`
- [ ] `it updates router property hook error message to "Router not available. Install marko/routing: composer require marko/routing"`
- [ ] `bootstrap.php calls initialize() instead of boot()`
- [ ] `CliKernel.php calls initialize() instead of boot()`
- [ ] `all existing Application tests pass with boot() renamed to initialize()`
- [ ] `all existing RoutingBootstrapperTest tests pass with boot() renamed to initialize()`

## Acceptance Criteria
- All requirements have passing tests
- `boot()` instance method renamed to `initialize()` everywhere
- `bootstrap.php` updated to call `$app->initialize()`
- All existing test assertions updated
- No behavioral changes — only the method name changes
- Code follows project standards

## Implementation Notes
This is a straightforward rename. Use find-and-replace across all five files:
1. `Application.php`: rename method `boot()` to `initialize()`, update the `@throws` docblock, update property hook error message to "Router not available. Install marko/routing: composer require marko/routing"
2. `bootstrap.php`: change `$app->boot()` to `$app->initialize()`
3. `ApplicationTest.php`: change all `$app->boot()` calls to `$app->initialize()`
4. `RoutingBootstrapperTest.php`: change all `$app->boot()` calls to `$app->initialize()` (10 instances, including one inside `expect(fn () => $app->boot())->toThrow(...)`)
5. `CliKernel.php`: change `$app->boot()` to `$app->initialize()` on line 78

The `@throws` docblock on the method itself references exception types — those don't change, just the method name.

Note: The `boot` property on `ModuleManifest` (`$module->boot`) is unrelated — it refers to module boot callbacks, not the Application method. Do NOT rename those. Also do NOT rename `$bootstrapper->boot()` in `discoverRoutes()` — that is `RoutingBootstrapper::boot()`, a different class entirely.
