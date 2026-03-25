# Task 002: Refactor PluginDefinition, PluginDiscovery, and PluginRegistry

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update three tightly coupled classes atomically to avoid breaking the test suite between tasks:

1. **PluginDefinition**: Change `beforeMethods`/`afterMethods` from `array<string, int>` (keyed by plugin method name) to `array<string, array{pluginMethod: string, sortOrder: int}>` keyed by TARGET method name.
2. **PluginDiscovery**: Update `parsePluginClass()` to resolve target method names using the new convention -- if `#[Before]`/`#[After]` has a `method` parameter, use that as the target method name; otherwise, the plugin method's own name IS the target method name. Build the new `PluginDefinition` array structure accordingly.
3. **PluginRegistry**: Update `getSortedMethodsFor()` to match plugins by target method name directly (keys are already target method names), extract `sortOrder` from the nested array, and return the `pluginMethod` value for invocation.

These three changes must be made together because PluginDefinition is a value object consumed by both PluginDiscovery (producer) and PluginRegistry (consumer). Changing the structure in isolation would break all existing tests.

## Context
- Related files:
  - `packages/core/src/Plugin/PluginDefinition.php`
  - `packages/core/src/Plugin/PluginDiscovery.php`
  - `packages/core/src/Plugin/PluginRegistry.php`
- **PluginDefinition**: Value object, constructor property promotion only, `readonly class`
- **PluginDiscovery**: `parsePluginClass()` at lines 56-90 iterates methods, finds `#[Before]`/`#[After]` attributes, currently stores `$method->getName() => $sortOrder`. Change to: determine target method (from `method` param or method name), store as `$targetMethod => ['pluginMethod' => $methodName, 'sortOrder' => $sortOrder]`
- **PluginRegistry**: `getSortedMethodsFor()` at lines 86-115 currently does `$expectedMethodName = $type . ucfirst($targetMethod)` to match. Change to: keys are already target method names, compare key directly to `$targetMethod`, extract `pluginMethod` and `sortOrder` from the nested array value
- The `validatePluginMethods()` method in PluginDiscovery does NOT need changes -- it just checks for orphaned attributes

## Requirements (Test Descriptions)

### PluginDefinition
- [ ] `it creates PluginDefinition with target-method-keyed before methods`
- [ ] `it creates PluginDefinition with target-method-keyed after methods`
- [ ] `it creates PluginDefinition with empty method arrays by default`
- [ ] `it stores plugin method name separately from target method name`

### PluginDiscovery
- [ ] `it resolves target method from plugin method name when no method param`
- [ ] `it resolves target method from Before attribute method param`
- [ ] `it resolves target method from After attribute method param`
- [ ] `it builds PluginDefinition with correct target-to-plugin method mapping`
- [ ] `it handles mixed standard and explicit method params in same plugin`

### PluginRegistry
- [ ] `it matches before plugins by target method name directly`
- [ ] `it matches after plugins by target method name directly`
- [ ] `it returns plugin method name in result for invocation`
- [ ] `it returns correct plugin method name when method param differs from target`
- [ ] `it sorts matched methods by sortOrder ascending`
- [ ] `it returns empty array when no plugins match target method`

## Acceptance Criteria
- All requirements have passing tests
- PluginDefinition uses new array structure with accurate PHPDoc
- `parsePluginClass()` produces correct `PluginDefinition` with new array structure
- `getSortedMethodsFor()` no longer uses `before`/`after` prefix convention
- Code follows code standards

## Implementation Notes
- In PluginRegistry `getSortedMethodsFor()`, the iteration changes from:
  ```php
  foreach ($plugin->$methodsProperty as $methodName => $sortOrder) {
      $expectedMethodName = $type . ucfirst($targetMethod);
      if ($methodName === $expectedMethodName) { ... }
  }
  ```
  to:
  ```php
  foreach ($plugin->$methodsProperty as $targetMethodName => $entry) {
      if ($targetMethodName === $targetMethod) {
          $methods[] = [
              'pluginClass' => $plugin->pluginClass,
              'method' => $entry['pluginMethod'],
              'sortOrder' => $entry['sortOrder'],
          ];
      }
  }
  ```
- Test fixtures for PluginDiscovery tests need the new naming convention (e.g., `doSomething` instead of `beforeDoSomething`). The `createPluginClass` helper in PluginDiscoveryTest does NOT auto-generate prefixed names -- callers pass method names as array keys. Update the calling test code, not the helper itself.
