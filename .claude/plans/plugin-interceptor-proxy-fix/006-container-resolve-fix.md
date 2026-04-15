# Task 006: Container Resolve Fix

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Fix `Container::resolve()` to pass both the original ID (interface/class requested) and the resolved ID (concrete class after preference/binding resolution) to `PluginInterceptor::createProxy()`. Currently only `$id` (which may have been mutated by preferences) is passed, causing plugins registered against interfaces to be missed when resolved via preferences.

## Context
- Related files:
  - `packages/core/src/Container/Container.php` — file to modify (lines 136-137 and 206-207)
  - `packages/core/tests/Unit/Container/ContainerTest.php` — may need new tests
- Currently `createProxy($id, $instance)` is called at two points in `resolve()`:
  1. Line 137: After closure binding resolution — `$id` is the binding key (may be interface)
  2. Line 207: After auto-wiring — `$id` is the concrete class (preferences already resolved)
- Both need to pass `$originalId` (captured at line 118) along with the current `$id`
- The new signature is: `createProxy($originalId, $resolvedId, $instance)`

### For closure bindings (line 137):
- `$originalId` = original request (before preference resolution)
- `$id` = after preference resolution (could be same as originalId, or concrete class)
- Pass: `createProxy($originalId, $id, $instance)`

### For auto-wired classes (line 207):
- `$originalId` = original request
- `$id` = concrete class name after preference + binding resolution
- Pass: `createProxy($originalId, $id, $instance)`

## Requirements (Test Descriptions)

Tests go in `packages/core/tests/Unit/Container/ContainerTest.php` (or appropriate existing test file).

- [ ] `it passes original interface ID and resolved class ID to plugin interceptor for closure bindings`
- [ ] `it passes original interface ID and resolved class ID to plugin interceptor for preference-resolved classes`
- [ ] `it passes same ID for both when resolving a concrete class directly`
- [ ] `it applies plugin interception when interface has plugins and is resolved via preference`
- [ ] `it applies plugin interception when interface has plugins and is resolved via closure binding`

## Acceptance Criteria
- All requirements have passing tests
- Existing container tests still pass
- Both `createProxy` call sites in `Container::resolve()` pass `$originalId` and `$id`
- Plugins registered against interfaces are found regardless of resolution path (binding or preference)
- Container tests that construct `PluginInterceptor` directly must now also pass an `InterceptorClassGenerator` instance (constructor changed in Task 005)
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
