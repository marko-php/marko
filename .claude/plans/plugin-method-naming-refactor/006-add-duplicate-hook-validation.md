# Task 006: Add Duplicate Hook Validation

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Add two levels of duplicate hook validation:

1. **Within a single plugin class** (in `PluginDiscovery::parsePluginClass()`): detect when two methods resolve to the same target method with the same timing (before or after). This can only happen with the `method:` param — without it, PHP already prevents duplicate method names.

2. **Across plugin classes** (in `PluginRegistry::register()`): detect when two different plugin classes register hooks for the same target method with the same timing AND the same sort order. This would cause non-deterministic execution order — a loud error per Marko's principles.

## Context
- Related files: `packages/core/src/Plugin/PluginDiscovery.php`, `packages/core/src/Plugin/PluginRegistry.php`, `packages/core/src/Exceptions/PluginException.php`
- Example intra-class violation: two methods both have `#[Before(method: 'save')]` — which one runs?
- Example cross-class violation: `ValidationPlugin` and `LoggingPlugin` both have `#[Before]` on `save()` with `sortOrder: 0` — non-deterministic order
- Add new static factory methods: `PluginException::duplicatePluginHook()` and `PluginException::conflictingSortOrder()`
- Intra-class validation runs during `parsePluginClass()`, after collecting all methods
- Cross-class validation runs during `register()`, checking against already-registered plugins

## Requirements (Test Descriptions)
- [x] `it throws PluginException when two before methods in same class target the same method`
- [x] `it throws PluginException when two after methods in same class target the same method`
- [x] `it allows before and after targeting the same method via method param in same class`
- [x] `it throws PluginException when two plugins have same timing, target method, and sort order`
- [x] `it allows two plugins with same timing and target method but different sort orders`
- [x] `it provides helpful error message with class and method names for intra-class conflict`
- [x] `it provides helpful error message with both plugin classes for cross-class conflict`

## Acceptance Criteria
- All requirements have passing tests
- Clear error messages tell developer which methods/classes conflict and how to fix it
- Intra-class: suggest using `method:` param with distinct names
- Cross-class: suggest changing one plugin's `sortOrder` to make ordering deterministic
- Code follows code standards and exception standards (message, context, suggestion)
