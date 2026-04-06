# Task 007: Remove PluginProxy and Update Imports

**Status**: complete
**Depends on**: 005, 006
**Retry count**: 0

## Description
Remove the now-unused `PluginProxy` class and update all references across the codebase. The `PluginProxy` has been replaced by generated interceptor classes that use the `PluginInterception` trait.

## Context
- Related files:
  - `packages/core/src/Plugin/PluginProxy.php` — DELETE this file
  - `packages/core/tests/Unit/Plugin/PluginInterceptorTest.php` — update `toBeInstanceOf(PluginProxy::class)` checks
  - `packages/core/tests/Unit/Container/ContainerTest.php` — has 6+ assertions using `toBeInstanceOf(PluginProxy::class)` (lines ~377, 389, 408, 428, 449, 472, 656) that must be updated
  - `packages/routing/src/Router.php` (line 80) — uses `instanceof PluginProxy` to unwrap plugin proxies for reflection; must change to `instanceof PluginInterceptedInterface`
- Search for ALL references to `PluginProxy` across the codebase and update/remove them
- Replace `instanceof PluginProxy` checks with `instanceof PluginInterceptedInterface` (from Task 001)
- Replace `toBeInstanceOf(PluginProxy::class)` assertions with `toBeInstanceOf(PluginInterceptedInterface::class)` in tests
- The `PluginInterceptorTest` currently asserts `toBeInstanceOf(PluginProxy::class)` — this should change to check `PluginInterceptedInterface::class`

## Requirements (Test Descriptions)

- [x] `it does not reference PluginProxy anywhere in the codebase after removal`
- [x] `it asserts interceptor implements PluginInterceptedInterface instead of checking for PluginProxy`
- [x] `it verifies PluginProxy class file no longer exists`
- [x] `it updates Router.php to use instanceof PluginInterceptedInterface`
- [x] `it updates ContainerTest.php assertions from PluginProxy to PluginInterceptedInterface`

## Acceptance Criteria
- `PluginProxy.php` deleted
- No remaining references to `PluginProxy` in source code or tests
- `Router.php` uses `instanceof PluginInterceptedInterface` instead of `instanceof PluginProxy`
- `ContainerTest.php` uses `PluginInterceptedInterface::class` in instanceof assertions
- All tests pass after removal
- Code follows code standards

## Implementation Notes
- Deleted `packages/core/src/Plugin/PluginProxy.php`
- Updated `packages/routing/src/Router.php`: replaced `use Marko\Core\Plugin\PluginProxy` with `use Marko\Core\Plugin\PluginInterceptedInterface`, and changed `instanceof PluginProxy` to `instanceof PluginInterceptedInterface`
- `PluginInterceptorTest.php` and `ContainerTest.php` were already using `PluginInterceptedInterface` (completed in task 005)
- Added `packages/core/tests/Unit/Plugin/PluginProxyRemovalTest.php` with 5 meta-tests verifying codebase state (vendor dirs excluded from grep)
