# Task 005: Rewrite PluginInterceptor

**Status**: pending
**Depends on**: 001, 002, 003, 004
**Retry count**: 0

## Description
Rewrite `PluginInterceptor::createProxy()` to use the new dual-strategy approach instead of returning a generic `PluginProxy`. The method now accepts both the original ID (interface/class requested) and resolved ID (concrete class after preference/binding resolution), uses `InterceptorClassGenerator` to create the appropriate interceptor class, and instantiates it.

## Context
- Related files:
  - `packages/core/src/Plugin/PluginInterceptor.php` — file to rewrite
  - `packages/core/src/Plugin/PluginInterception.php` (Task 001) — trait
  - `packages/core/src/Plugin/PluginInterceptedInterface.php` (Task 001) — marker interface
  - `packages/core/src/Plugin/InterceptorClassGenerator.php` (Task 002) — generator
  - `packages/core/src/Plugin/PluginRegistry.php` (Task 003) — interface-aware lookup
  - `packages/core/tests/Unit/Plugin/PluginInterceptorTest.php` — tests to update
- `PluginInterceptor` stays `readonly class` — the new `InterceptorClassGenerator` dependency is injected via constructor (the generator holds its own mutable cache)
- The constructor changes: add `InterceptorClassGenerator $generator` parameter
- The signature changes: `createProxy(string $targetClass, object $target)` becomes `createProxy(string $originalId, string $resolvedId, object $target)` where `$originalId` is the interface/class originally requested and `$resolvedId` is the concrete class after resolution
- When `$originalId` is an interface with plugins → interface wrapper strategy
- When `$resolvedId` is a non-readonly concrete class with plugins → subclass strategy
- When `$resolvedId` is a readonly concrete class with direct plugins → throw PluginException
- When neither has plugins → return original instance (no interception)

**Important note on wrapper strategy for concrete classes**: When the interface wrapper strategy is used, ALL methods of the interface must be generated as delegating stubs (not just plugged ones), because the wrapper holds a reference to the target rather than inheriting from it. Non-delegated methods would be undefined. For the subclass extension strategy, only plugged methods need overriding since non-plugged methods fall through to `parent::`.

### Decision Logic
1. Check if `$originalId` is an interface AND has plugins registered → **interface wrapper**
2. Else check if the resolved concrete class has plugins via `getEffectiveTargetClass()` → if effective target is an interface → **interface wrapper**; if effective target is the class itself → check readonly: if readonly → throw; else → **subclass extension**
3. Else → return `$target` unchanged

### Strategy Selection
1. If `$originalId` is an interface with plugins registered, OR the concrete class implements an interface that has plugins: use **interface wrapper strategy** (generate class implementing the interface, delegate all methods via wrapped target instance).
2. If the concrete class itself has plugins and no interface applies: if the class is NOT readonly, use **subclass extension strategy** (generate class extending the concrete class, override only plugged methods with `parent::` calls). If the class IS readonly, throw `PluginException::cannotInterceptReadonly()`.

### Interface Wrapper Instantiation
```php
$className = $generator->generateInterfaceWrapper($interfaceName);
return new $className($target, $effectiveTargetClass, $container, $registry);
```

### Subclass Instantiation
For concrete subclass strategy, the interceptor extends the concrete class. The target instance is already constructed by the container. The interceptor wraps it using the same delegation pattern (holds reference, delegates calls). The `initInterception()` is called after construction.

```php
$className = $generator->generateConcreteSubclass($resolvedId, $pluggedMethods);
$interceptor = new $className(/* same args as parent */);
// This is complex — for now, prefer interface wrapper when possible
```

## Requirements (Test Descriptions)

Rewrite tests in `packages/core/tests/Unit/Plugin/PluginInterceptorTest.php`. All existing behaviors must still pass.

- [ ] `it returns original instance when no plugins exist for the target`
- [ ] `it creates interceptor implementing interface when plugin targets an interface`
- [ ] `it creates interceptor that passes instanceof check for target interface`
- [ ] `it executes before plugins in sort order then calls target method`
- [ ] `it passes method arguments to before plugins`
- [ ] `it short-circuits when before plugin returns non-null non-array value`
- [ ] `it passes through to target method when before plugin returns null`
- [ ] `it modifies arguments when before plugin returns an array`
- [ ] `it throws PluginArgumentCountException when before plugin returns array with wrong count`
- [ ] `it chains argument modifications through multiple before plugins`
- [ ] `it executes after plugins in sort order after target method`
- [ ] `it passes result and arguments to after plugins`
- [ ] `it chains modified results through multiple after plugins`
- [ ] `it passes modified arguments from before plugins to after plugins`
- [ ] `it executes complete before-target-after flow`
- [ ] `it handles methods with no plugins on an intercepted class without overhead`
- [ ] `it injects plugin dependencies via container`
- [ ] `it calls plugin method by name returned from registry`
- [ ] `it executes plugins using explicit method param with different method names`
- [ ] `it finds plugins when interface is resolved via preferences to concrete class`
- [ ] `it exposes original target via getPluginTarget`
- [ ] `it throws PluginException for readonly concrete class with direct plugins`

## Acceptance Criteria
- All requirements have passing tests
- Every existing behavior from `PluginInterceptorTest.php` is preserved
- New `createProxy` signature: `createProxy(string $originalId, string $resolvedId, object $target): object`
- Interceptor objects satisfy `instanceof` for the target interface
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
