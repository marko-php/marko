# Task 024: Loud-Fail Boot Check for Missing `marko/page-cache-entity`

**Status**: complete
**Depends on**: 019
**Retry count**: 0

## Description

Add a boot-time validator in `marko/page-cache` that detects when application code implements `IdentityInterface` but the `marko/page-cache-entity` bridge package is not installed. Without the bridge, implementing `IdentityInterface` is a silent no-op — entities declare identities, but nothing observes lifecycle events to actually purge them. This task makes that misconfiguration fail loudly at boot rather than at "stale data in production three weeks later."

## Context

- Related files:
  - `packages/page-cache/src/Boot/IdentityBridgeValidator.php` — new service that runs the check
  - `packages/page-cache/src/Exceptions/PageCacheException.php` — add `missingEntityBridge(string $offendingClass): self` factory
  - `packages/page-cache/module.php` — wire the validator into the `boot` callback
  - `packages/core/src/Discovery/ClassFileParser.php` — existing scanner; reuse to enumerate classes in user modules
  - `packages/core/src/Event/ObserverDiscovery.php` — reference pattern (it iterates `$modules`, scans each `$manifest->path . '/src'`, parses PHP files, reflects)
  - `packages/page-cache/tests/Unit/Boot/IdentityBridgeValidatorTest.php` — new
- Pattern to follow: `ObserverDiscovery::discover(array $modules)` — same loop shape (iterate manifests, scan `src/`, load class, reflect). The validator uses the same mechanism but checks `$reflection->implementsInterface(IdentityInterface::class)` instead of looking for the `Observer` attribute.

**Validator logic:**
1. Iterate `$modules` (the `ModuleManifest[]` available at boot — same source `ObserverDiscovery` uses)
2. **Skip `marko/*` vendor modules.** The check is for application code; we don't care if the framework's own packages contain `IdentityInterface` implementers (none currently do, but future framework code shouldn't trip the check). Match on `$manifest->name` starting with `marko/`.
3. For each remaining module, scan `src/` via `ClassFileParser`. Load each class and reflect.
4. For each class implementing `IdentityInterface`, check if `Marko\PageCache\Entity\IdentityPurger` exists via `class_exists()`. If not, throw `PageCacheException::missingEntityBridge($offendingClassName)` immediately on the first hit.
5. If no implementers found, the validator is a no-op — no overhead beyond the scan, and the scan reuses the same files `ObserverDiscovery` already loaded.

**Exception shape:**
```php
public static function missingEntityBridge(string $offendingClass): self
{
    return new self(
        message: "Class '$offendingClass' implements IdentityInterface but marko/page-cache-entity is not installed. The page cache will not be invalidated when this entity changes.",
        context: "Detected during marko/page-cache boot validation",
        suggestion: "Install the bridge package: composer require marko/page-cache-entity",
    );
}
```

**Wiring in `module.php`:**
```php
return [
    'boot' => function (
        IdentityBridgeValidator $validator,
        ModuleRegistry $modules,
    ): void {
        $validator->validate($modules->all());
    },
];
```

(The exact `ModuleRegistry` API may differ — match whatever `ObserverDiscovery` is fed. Confirm the injection signature against the existing boot wiring in `packages/core/src/Application.php` before writing.)

**Why scan rather than soft-check at request time:** Failing at boot, once, with the class name pinpointed, is far more debuggable than "auto-purge silently doesn't work in production." This trades a small one-time boot cost for a strong correctness guarantee.

**Why skip `marko/*` modules:** Future framework packages may legitimately define `IdentityInterface` implementations in test fixtures or examples. The check is targeted at user/app code where the silent-failure risk is real.

**Performance note.** `ObserverDiscovery` already walks every module's `src/` at boot, so adding an interface-check during the same walk is essentially free if implementations are colocated. The simplest implementation (a separate validator that re-walks) is acceptable — boot is not a hot path. A future optimization could fuse the walks if profiling suggests it matters.

**Caching consideration.** Marko does not currently have a discovery cache. If/when one is added, the validator should hook into the same cache so production boots don't repeat the scan. Out of scope for this task; document it as a future-cache touchpoint in implementation notes.

## Requirements (Test Descriptions)

- [x] `it passes validation when no app class implements IdentityInterface`
- [x] `it passes validation when an app class implements IdentityInterface and the bridge package is installed`
- [x] `it throws PageCacheException when an app class implements IdentityInterface and the bridge package is not installed`
- [x] `it names the offending class in the exception message`
- [x] `it suggests the composer require command in the exception suggestion`
- [x] `it ignores marko/* modules when scanning for IdentityInterface implementers`
- [x] `it stops scanning after finding the first offending class (fail-fast)`

## Acceptance Criteria

- All requirements have passing tests
- `IdentityBridgeValidator` is a `readonly class` with a single public method
- `PageCacheException::missingEntityBridge()` follows the `message`/`context`/`suggestion` triple
- The check runs only at module boot — no per-request overhead
- Tests fake the bridge-installed/uninstalled state by using a configurable "bridge class name" parameter on the validator, NOT by manipulating `class_exists()` (which is impossible to mock in PHP). The validator's constructor should accept a `string $bridgeClass = IdentityPurger::class` parameter for testability.
- When `marko/page-cache-entity` IS installed in the integration tests, validation passes (no regression in the full suite)

## Implementation Notes

- Created `packages/page-cache/src/Boot/IdentityBridgeValidator.php` as a `readonly class` with single public `validate(array $modules): void` method
- Added `PageCacheException::missingEntityBridge(string $offendingClass): self` static factory to `packages/page-cache/src/Exceptions/PageCacheException.php`
- Created `packages/page-cache/module.php` wiring the validator into boot via `ModuleRepositoryInterface` (the interface registered in the container, confirmed from `Application.php`)
- Constructor accepts `string $bridgeClass = IdentityPurger::class` for testability — tests pass `ClassFileParser::class` (a class that exists) for the "installed" scenario and `'NonExistentBridgeClass'` for the "not installed" scenario
- The validator follows the exact same `src/` scan pattern as `ObserverDiscovery`
- Tests run via Docker with PHP 8.5 (the local PHP 8.3 installation is missing the `php8.3-xml` package required by phpunit's XML config loading)
