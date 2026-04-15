# Devil's Advocate Review: layout-component-discovery

## Critical (Must fix before building)

### C1. `DiscoveringComponentCollector` constructor design creates inner `ComponentCollector` directly, bypassing DI (Task 001)

The plan says `DiscoveringComponentCollector` "internally creates a `ComponentCollector` from `HandleResolver` + `RouteCollection`." This means the class does `new ComponentCollector(...)` inside its constructor or method. This violates the constructor injection pattern (code-standards rule #2: "WRONG - service locator / `Container::get()`"). More importantly, it creates a tight coupling -- if someone uses a Preference to replace `ComponentCollector`, the `DiscoveringComponentCollector` will ignore it because it `new`s the concrete class directly.

**Fix:** `DiscoveringComponentCollector` should receive a `ComponentCollector` (or `ComponentCollectorInterface`) via constructor injection instead of creating it internally. The constructor should be: `ModuleRepositoryInterface $moduleRepository`, `ClassFileParser $classFileParser`, `ComponentCollector $componentCollector`. The inner collector already has `HandleResolver` and `RouteCollection` -- no need to duplicate those dependencies.

However, there is a subtlety: `DiscoveringComponentCollector` implements `ComponentCollectorInterface`, and if it also receives `ComponentCollectorInterface` via DI, this creates a circular binding (`ComponentCollectorInterface` -> `DiscoveringComponentCollector` which needs `ComponentCollectorInterface`). The fix is to type-hint the inner dependency as the concrete `ComponentCollector` class, not the interface. The module.php binds the interface to `DiscoveringComponentCollector`, while `DiscoveringComponentCollector` injects the concrete `ComponentCollector` directly. The container can resolve `ComponentCollector` without the interface binding.

### C2. Plan says `DiscoveringComponentCollector` constructor takes `HandleResolver` and `RouteCollection` but those are only needed by the inner `ComponentCollector` (Task 001)

The plan specifies the constructor as: `ModuleRepositoryInterface $moduleRepository`, `ClassFileParser $classFileParser`, `HandleResolver $handleResolver`, `RouteCollection $routeCollection`. But `HandleResolver` and `RouteCollection` are only used to construct the inner `ComponentCollector`. If we fix C1 by injecting `ComponentCollector` directly, these two dependencies become unnecessary dead code in `DiscoveringComponentCollector`.

**Fix:** Update the constructor signature to: `ModuleRepositoryInterface $moduleRepository`, `ClassFileParser $classFileParser`, `ComponentCollector $componentCollector`. Remove `HandleResolver` and `RouteCollection` from the constructor.

### C3. Task 002 description contradicts itself about `LayoutProcessor` changes (Task 002)

The description says: "update `LayoutProcessor::process()` to stop passing an empty array to `collect()`" then later says "Actually, `LayoutProcessor` should keep passing `[]`" and "No change needed to `LayoutProcessor` itself." This is confusing for a worker. The worker may waste time trying to figure out which instruction to follow.

**Fix:** Remove the contradictory language. State clearly: "No changes to `LayoutProcessor` are needed. The `[]` first argument to `collect()` is semantically correct -- it means 'no additional classes beyond what was discovered.' The only change in this task is adding bindings to `module.php`."

## Important (Should fix before building)

### I1. Task 002 test requirements include bindings that may not exist yet (Task 002)

Task 002 lists test requirements for `HandleResolver` singleton binding and `LayoutResolver` singleton binding in `module.php`. But the current `module.php` is empty (`'bindings' => []`). The plan scope says this task adds the `ComponentCollectorInterface` binding, but also expects `HandleResolver`, `LayoutResolver`, and `LayoutProcessorInterface` bindings to be tested. If those bindings are also being added in this task, the task description should say so explicitly. If they already exist elsewhere, the tests will be redundant.

Looking at the current `module.php`, none of these bindings exist. They all need to be added. The task description only mentions the `ComponentCollectorInterface` binding explicitly. The worker needs to know they are adding ALL four bindings.

**Fix:** Update task 002 description to explicitly list all bindings being added, not just `ComponentCollectorInterface`.

### I2. `DiscoveringComponentCollector` should not be `readonly` but task 001 acceptance criteria says "Class is not readonly (has mutable internal collector)" which is correct, yet the plan's wiring section omits this (Task 001)

The acceptance criteria correctly notes this, but the plan's Architecture Notes / Wiring section says nothing about why. The `ComponentCollector` is `readonly`, but `DiscoveringComponentCollector` wraps it and may need mutable state. Actually, if `DiscoveringComponentCollector` just delegates to an injected `ComponentCollector`, it can be `readonly` -- all its properties are set in the constructor and never change. The acceptance criterion "Class is not readonly (has mutable internal collector)" is based on the assumption from the original design that it creates the inner collector internally.

**Fix:** With the C1 fix (injecting `ComponentCollector` via constructor), `DiscoveringComponentCollector` CAN be `readonly class` since all its dependencies are constructor-injected and immutable. Update acceptance criteria to: "Class should be `readonly` if all properties are constructor-injected (per code-standards rule #4)."

### I3. Task 001 does not specify the `collect()` method's reflection check pattern precisely enough (Task 001)

The task says "Uses reflection to check for `#[Component]` attribute (same as `RoutingBootstrapper::hasRouteAttributes()`)." But `RoutingBootstrapper::hasRouteAttributes()` checks method-level attributes on `Route`, while `#[Component]` is a class-level attribute (`Attribute::TARGET_CLASS`). The worker needs to check `ReflectionClass::getAttributes(Component::class)` not method attributes. The existing `ComponentCollector::discoverFromClass()` already does exactly this check (line 78). Since `DiscoveringComponentCollector` only needs to identify which classes HAVE the attribute (not parse it), it should use a simpler check than full `discoverFromClass()`.

**Fix:** Clarify in task 001 that the discovery scan only needs to check `(new ReflectionClass($className))->getAttributes(Component::class) !== []` to identify component classes. The full attribute parsing (template, slots, handles) happens in the inner `ComponentCollector::collect()` call. Alternatively, the discovering collector could skip the attribute pre-check entirely and just pass all loaded class names to the inner collector, which already returns `null` for non-component classes. This would be simpler but scan more classes.

### I4. Missing test for empty modules (no `src/` directory) (Task 001)

The `RoutingBootstrapper::findControllerClasses()` explicitly checks `if (!is_dir($srcPath))` and returns early. The task's test requirements don't include a test for modules with no `src/` directory. `ClassFileParser::findPhpFiles()` handles this internally (returns empty if `!is_dir()`), but an explicit test ensures the contract.

**Fix:** Add a test requirement: "it handles modules without src directories gracefully"

## Minor (Nice to address)

### M1. Performance: scanning all module files on every `collect()` call

The plan acknowledges this ("Module scanning happens per `collect()` call -- no caching yet") and says it matches route discovery behavior. However, route discovery happens once at boot time, while `collect()` is called per-request via `LayoutProcessor::process()`. This means every page request scans every PHP file in every module's `src/` directory. This is a meaningful performance difference.

Not a blocker since the plan explicitly defers caching, but worth noting that route discovery is not a perfect analogy -- routes are discovered once, components are discovered per-request.

### M2. The `$classNames` merge behavior is not obvious from the interface

`ComponentCollectorInterface::collect(array $classNames, string $handle)` doesn't document that `$classNames` might be "additional" classes merged with auto-discovered ones. From the interface's perspective, `$classNames` IS the complete list. The `DiscoveringComponentCollector` changes this semantic by merging discovered classes with the passed ones. This could surprise callers.

## Questions for the Team

### Q1. Should `DiscoveringComponentCollector` pre-filter by attribute or pass all classes to the inner collector?

Two approaches: (a) scan files, check for `#[Component]` attribute, collect matching class names, then pass to `ComponentCollector::collect()`. (b) scan files, pass ALL loaded class names to `ComponentCollector::collect()`, which already handles non-component classes by returning `null` from `discoverFromClass()`. Option (b) is simpler but processes more classes.

### Q2. Should `HandleResolver` and `LayoutResolver` be singletons or regular bindings?

Task 002 tests require them as singletons. Both are `readonly` stateless classes -- singleton vs transient makes no functional difference, only a minor performance one. The convention in other packages (e.g., `session/module.php`) is to use `singletons` for stateful shared services. These are stateless, so `bindings` might be more appropriate. However, since there is no reason to create multiple instances, `singletons` is also fine.
