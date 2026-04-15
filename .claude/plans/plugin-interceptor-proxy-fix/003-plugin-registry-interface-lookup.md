# Task 003: PluginRegistry Interface-Aware Lookup

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Add a method to `PluginRegistry` that checks for plugins registered against a class AND all interfaces it implements. Currently, plugins are stored keyed by the exact target class string from the `#[Plugin]` attribute. When a plugin targets `HasherInterface` but the container resolves to `BcryptHasher`, the registry needs to find those plugins. This task adds a method that aggregates plugins from both the concrete class and its interfaces.

## Context
- Related files:
  - `packages/core/src/Plugin/PluginRegistry.php` — file to modify
  - `packages/core/tests/Unit/Plugin/PluginRegistryTest.php` — add tests here
- This does NOT change existing methods (`hasPluginsFor`, `getPluginsFor`, `getBeforeMethodsFor`, `getAfterMethodsFor`) — those continue to work by exact key
- New method: `hasPluginsForClassOrInterfaces(string $class): bool` and `getEffectiveTargetClass(string $class): ?string`
- The `getEffectiveTargetClass` method returns the registry key (interface or class name) that has plugins for the given class, checking the class itself first, then its interfaces

## Requirements (Test Descriptions)

Add tests to `packages/core/tests/Unit/Plugin/PluginRegistryTest.php`.

- [x] `it finds plugins when checking class that directly has plugins registered`
- [x] `it finds plugins when checking class whose interface has plugins registered`
- [x] `it returns false when class and none of its interfaces have plugins`
- [x] `it returns the direct class as effective target when class itself has plugins`
- [x] `it returns the interface as effective target when only the interface has plugins`
- [x] `it prefers class-level plugins over interface-level plugins when both exist`
- [x] `it checks all implemented interfaces not just the first one`
- [x] `it throws PluginException when multiple interfaces of a class have plugins registered`

## Acceptance Criteria
- All requirements have passing tests
- Existing `PluginRegistry` tests still pass unchanged
- New methods use reflection to discover interfaces of the given class
- When multiple interfaces of a class have plugins, throw `PluginException::ambiguousInterfacePlugins()` — Marko is loud about ambiguity rather than silently picking a winner
- No breaking changes to existing API
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
