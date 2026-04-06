# Devil's Advocate Review: plugin-interceptor-proxy-fix

## Critical (Must fix before building)

### C1. "No Traits" code standard directly violated (Task 001)

`.claude/code-standards.md` rule 14 states: **"No Traits - Use explicit composition instead. Traits hide where behavior comes from."** The entire plan revolves around a `PluginInterception` trait. This is the core architectural decision and it conflicts with project standards.

**However**, this is one of those rare cases where a trait is arguably justified: eval-generated classes cannot use composition in the traditional sense (they can't inject a helper object through a constructor they don't fully control in the subclass strategy). The trait is the mechanism by which generated code stays minimal.

**Fix**: Add an explicit note to task 001 and `_plan.md` acknowledging this is a deliberate, documented exception to rule 14, with rationale. The worker needs to know this is intentional, not an oversight they should "fix" by refactoring away from a trait.

### C2. Router.php uses `instanceof PluginProxy` -- no replacement pattern defined (Task 007)

`packages/routing/src/Router.php` (line 80) does:
```php
$reflectionTarget = $controller instanceof PluginProxy
    ? $controller->getPluginTarget()
    : $controller;
```

Task 007 says "Search for all references to `PluginProxy` across the codebase and update/remove them" but does NOT define what to replace this `instanceof` check with. Generated interceptor classes have no common base class or interface. Without a `PluginInterceptedInterface` or similar marker interface that provides `getPluginTarget()`, the Router has no way to detect an intercepted object.

**Fix**: Define a `PluginInterceptedInterface` with a `getPluginTarget(): object` method. Generated classes implement this interface. Router checks `instanceof PluginInterceptedInterface`. Add this to task 001 (the trait can provide the implementation) and task 002 (generated classes implement it). Update task 007 to explicitly list Router.php and the replacement pattern.

### C3. `PluginInterceptor` is `readonly class` -- cannot add mutable state (Task 005)

The current `PluginInterceptor` is declared `readonly class PluginInterceptor`. Task 005 rewrites it to use `InterceptorClassGenerator`, which caches generated classes. If the generator is injected, this is fine. But if the interceptor itself needs any mutable state (e.g., holding a reference to the generator), it cannot because `readonly` prevents property mutation after construction.

The plan should explicitly note that `PluginInterceptor` remains `readonly` by injecting the `InterceptorClassGenerator` through its constructor. The generator itself holds the cache. Task 005 must add `InterceptorClassGenerator` as a constructor dependency.

**Fix**: Add explicit note to task 005 that `PluginInterceptor` stays `readonly`, and `InterceptorClassGenerator` is injected via constructor. The generator (task 002) must NOT be readonly since it maintains a class name cache.

### C4. Container tests reference `PluginProxy::class` in 6+ assertions (Task 007)

`packages/core/tests/Unit/Container/ContainerTest.php` has multiple `toBeInstanceOf(PluginProxy::class)` assertions (lines 377, 389, 408, 428, 449, 472, 656). Task 007 mentions updating `PluginInterceptorTest` but does NOT mention `ContainerTest.php`. The worker building task 007 will miss these unless told.

**Fix**: Add `ContainerTest.php` to task 007's explicit file list and specify what the replacement assertion should be (e.g., check that the object implements `PluginInterceptedInterface` or implements the target interface).

## Important (Should fix before building)

### I1. Task 005 is self-contradictory about strategy (Task 005)

The description section of task 005 starts with the dual-strategy approach, then has three paragraphs that reverse direction:
- "Revised approach: Use the wrapper pattern for ALL cases"
- "Actually, even simpler: always use the interface wrapper strategy..."

This stream-of-consciousness will confuse the worker. The final decision needs to be clear and the earlier text removed.

**Fix**: Clean up task 005 to state the final decided strategy clearly, removing the deliberation trail.

### I2. `getEffectiveTargetClass` returns only ONE key but a class can implement multiple interfaces with plugins (Task 003)

`getEffectiveTargetClass(string $class): ?string` returns a single string. But what if a concrete class implements two interfaces that both have plugins registered? The method "prefers class-level plugins over interface-level plugins" but doesn't address the case where multiple interfaces have plugins. Only the first matching interface's plugins would be found.

**Fix**: Either document that only one interface is supported (and throw if multiple have plugins), or change the method to return an array of effective target classes. Given the complexity, recommending the simpler approach: document the limitation and throw a clear error for ambiguous cases.

### I3. Missing dependency: Task 006 depends on knowing `createProxy`'s new signature, but task 006 runs AFTER task 005 (Task 006)

Task 006 modifies the Container to call `createProxy($originalId, $id, $instance)` with three arguments. This is correct dependency ordering (006 depends on 005). However, the task doesn't mention that the Container's `setPluginInterceptor` might also need updating if the interceptor constructor changes, and the container tests need to construct the interceptor with the new dependencies (generator).

**Fix**: Add note to task 006 that container tests constructing `PluginInterceptor` must now pass an `InterceptorClassGenerator` instance as well.

### I4. No `pest.php` configuration for Integration test directory (Task 008)

Task 008 creates tests in `packages/core/tests/Integration/Plugin/` but the Integration directory doesn't exist, and there may be no Pest configuration to discover tests there. The existing `pest.php` likely only configures `Unit` and `Feature` directories.

**Fix**: Add to task 008 that the worker must verify/update `pest.php` to include the `Integration` directory, or place the tests in `tests/Feature/Plugin/` instead (which is the conventional location for multi-class integration tests in this project).

### I5. Existing tests call `$proxy->doAction()` directly -- won't work with generated interceptors (Task 005)

Current tests do `$proxy = $interceptor->createProxy(SomeClass::class, new SomeClass())` then `$proxy->doAction()`. With `PluginProxy`, this works via `__call()`. With generated interceptor classes, `doAction()` will be a real method -- but only if the target class's methods are all reflected and generated as stubs. For concrete class targets (not interfaces), the plan says only plugged methods are overridden. But the current tests target concrete classes, not interfaces. If the concrete subclass strategy is used, `doAction()` IS a real method on the parent. But if the wrapper strategy is used for concrete classes too (per the "revised approach" in task 005), then ALL public methods need to be generated as stubs, not just plugged ones. This is a significant change from the architecture notes which say "Only overrides methods that have plugins."

**Fix**: Clarify in task 002 and task 005: for the wrapper strategy applied to concrete classes, ALL public methods of the class (not just plugged ones) must be generated as delegating stubs. Otherwise non-plugged method calls will fail.

### I6. `PluginProxy` is `readonly class` -- the plan already removes it, but the trait properties need to be mutable (Task 001)

The `PluginInterception` trait stores `$__target`, `$__targetClass`, `$__container`, `$__registry` via `__initInterception()`. These properties are set after construction (in the interface wrapper case, they're set in the generated constructor, which is fine). But in the subclass strategy, they're set post-construction. The trait's properties cannot be `readonly` if they're set via a method call after the constructor. The generated classes that use this trait must NOT be `readonly class`.

**Fix**: Add explicit note to task 001 that the trait properties are mutable (non-readonly), and to task 002 that generated classes must NOT use the `readonly` keyword.

## Minor (Nice to address)

### M1. Double-underscore prefix convention

The trait uses `__initInterception`, `__intercept`, `__interceptParent`, `__target`, `__targetClass`, etc. While these aren't PHP magic methods, the double-underscore prefix is conventionally reserved for magic methods. This could confuse developers. Consider a different prefix like `_marko_` or just regular names since these are on generated classes that developers won't interact with directly.

### M2. eval() debugging experience

The plan mentions stack traces will show eval'd code, mitigated by one-line delegations. Consider also including a `// Generated by Marko PluginInterceptor` comment in the eval'd code to help developers understand what they're seeing in stack traces.

### M3. Class name collisions in tests

Multiple test files define top-level classes like `ShortCircuitService`, `PassThroughService`, etc. The new test files (task 001, 002, 008) will also define fixtures at the top level. PHP doesn't allow duplicate class names. Workers need to use unique names or namespaces to avoid "Cannot declare class" errors when running the full test suite.

### M4. Performance: Reflection on every `hasPluginsForClassOrInterfaces` call (Task 003)

The new method uses `class_implements()` or reflection to discover interfaces every time it's called. This could be cached for repeated lookups on the same class.

## Questions for the Team

1. **Trait exception to code standards**: Is the team comfortable with a documented exception to the "No Traits" rule for this specific use case? The alternative would be to generate the full plugin chain logic inline in every generated class, which makes the generated code much larger and harder to maintain.

2. **Wrapper vs subclass for concrete targets**: The plan flip-flops on this. Should concrete class targets that are non-readonly use the subclass strategy (more efficient, IS-A relationship preserved) or the wrapper strategy (simpler, one code path)? The wrapper strategy for concrete classes means `instanceof` checks on the concrete class will fail unless the wrapper also extends it.

3. **Multiple interfaces with plugins**: If `ClassA implements InterfaceB, InterfaceC` and both `InterfaceB` and `InterfaceC` have plugins registered, what should happen? Merge all plugins? Throw an error? Pick the first?

4. **Integration test location**: Should integration tests go in `tests/Integration/` (new convention) or `tests/Feature/` (existing convention)?
