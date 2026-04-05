# Devil's Advocate Review: container-plugin-integration

## Critical (Must fix before building)

### C1. Circular dependency makes Option C impossible (Task 003)

Task 003 describes Option C as "cleanest": create `PluginRegistry` and `PluginInterceptor` before Container, pass to Container constructor. But `PluginInterceptor`'s constructor requires `ContainerInterface`:

```php
readonly class PluginInterceptor
{
    public function __construct(
        private ContainerInterface $container,
        private PluginRegistry $registry,
    ) {}
}
```

You cannot create `PluginInterceptor` before `Container` exists, because it needs the Container. And you cannot pass `PluginInterceptor` to Container's constructor because it does not exist yet.

**Fix**: Option A (setter method) is the only viable approach. Task 001 must add a `setPluginInterceptor()` method to Container. Task 003 must use Option A: create Container first, create PluginInterceptor with the Container reference, then call `$container->setPluginInterceptor($interceptor)`. This also means Container cannot use `readonly` on the `$pluginInterceptor` property.

### C2. Closure binding early return bypasses end-of-method proxy wrapping (Task 002)

Task 002 says "proxy wrapping should happen at the end of `resolve()`, just before returning." But the closure binding code path returns early at line 132:

```php
if ($binding instanceof Closure) {
    $instance = $binding($this);
    if (isset($this->shared[$originalId])) {
        $this->instances[$originalId] = $instance;
    }
    return $instance;  // <-- EARLY RETURN, skips any code at end of method
}
```

Any proxy wrapping placed "at the end" of `resolve()` will never execute for closure bindings. The plan's architecture note ("all code paths get wrapped") is incorrect for the current structure.

**Fix**: Task 002 must explicitly handle proxy wrapping in BOTH the closure binding return path AND the autowiring return path. The task description must specify that proxy wrapping code needs to be added in two places (or the resolve method must be restructured to have a single exit point).

### C3. Task 004 missing dependency on Task 003 (Task 004)

Task 004 says "Depends on: 002" but its description says "These tests validate that the system works as a whole." The integration tests that wire `PluginRegistry` + `PluginInterceptor` + `Container` together require the setter/wiring mechanism from Task 003. Without Task 003, there is no way to inject `PluginInterceptor` into Container (since the constructor approach requires the circular dependency fix).

Actually, on closer inspection, Task 004 could manually construct all pieces in the test without needing Application wiring. The tests create a Container, create a PluginInterceptor with that Container, and inject it via the setter from Task 001. This works without Task 003. However, the dependency should still be explicit since Task 004 needs the setter method from Task 001 AND the proxy wrapping logic from Task 002.

**Fix**: Task 004's dependency is correct (depends on 002, which depends on 001), but the task context section must clarify that tests should manually wire components using the setter from Task 001, not rely on Application bootstrap.

## Important (Should fix before building)

### I1. Line number references will be stale (Task 002)

Task 002 references specific line numbers from the current Container.php (line 160-191, line 126-132, line 106-108). After Task 001 adds a new constructor parameter and potentially a setter method, all these line numbers will shift. Workers building Task 002 will be confused by wrong line references.

**Fix**: Replace line number references with descriptive code path names: "the `instances[]` early return", "the closure binding block", "the autowiring block", "the singleton caching block."

### I2. Setter method violates `readonly` expectations (Task 001)

The `PluginInterceptor` property on Container cannot be `readonly` since it must be set after construction via a setter. Container class itself is not `readonly` (it has mutable `$shared`, `$instances`, `$bindings` arrays), so this is fine structurally. But the task must explicitly state this property is nullable and set via setter, not via constructor.

**Fix**: Task 001 must specify: add a `private ?PluginInterceptor $pluginInterceptor = null` property (not constructor-promoted) and a `setPluginInterceptor(PluginInterceptor $interceptor): void` method. Add a test requirement: "it accepts PluginInterceptor via setter method."

### I3. No test for plugin on interface-requested class via preference (Task 004)

The plan's architecture notes discuss preferences + plugins, but the test requirement "it fires plugins on preference-resolved objects" is vague. The specific scenario to test: register a preference `InterfaceA` -> `ConcreteB`, register a plugin targeting `ConcreteB`, resolve `InterfaceA` from container, call a method, verify plugin fires. This is the critical cross-cutting test.

**Fix**: Add explicit setup instructions to Task 004 for the preference + plugin test case.

### I4. `createProxy` targetClass parameter needs clarification (Task 002)

When preferences are in play, `$id` changes from the original interface to the concrete class during resolution. The `createProxy()` call should use `$id` (the resolved concrete class), not `$originalId` (the requested interface). The plan's architecture note says "plugins target concrete classes with public methods" which is correct, but Task 002 doesn't explicitly specify which variable to pass as `targetClass`.

**Fix**: Task 002 requirements should explicitly state: "pass the resolved concrete class name (post-preference `$id`) as `targetClass` to `createProxy()`, not the original requested `$id`."

## Minor (Nice to address)

### M1. No test for plugin class resolution causing recursion

The plan mentions infinite recursion risk under "Risks & Mitigations" but no test validates that resolving a plugin class from within `PluginProxy::__call()` doesn't cause infinite proxy wrapping. If a plugin class itself has plugins registered against it, the Container would try to proxy-wrap the plugin, which would create another proxy, etc. This is an edge case but worth a test.

### M2. `PluginProxy` uses `__call()` which code standards forbid

Code standards say "No magic methods -- Avoid `__get`, `__set`, `__call`, `__callStatic`." `PluginProxy` relies on `__call()` by design. This is pre-existing and out of scope for this plan, but worth noting the tension.

### M3. No `bind()` method in ContainerInterface

Container has `bind()` as a public method but it's not on `ContainerInterface`. This means code that only has `ContainerInterface` (like `PluginProxy` which holds `ContainerInterface`) cannot call `bind()`. Not an issue for this plan specifically, but worth noting.

## Questions for the Team

1. **Should `setPluginInterceptor()` be on `ContainerInterface`?** Currently, the interface only has `singleton()`, `instance()`, `call()`, and the PSR methods. If the setter is only called during Application bootstrap, it could stay on the concrete `Container` class only. But if other code needs to set interceptors, it should be on the interface.

2. **Should proxy wrapping apply to `call()` resolved dependencies?** Currently only `resolve()` is being modified. But `call()` also resolves dependencies via `$this->resolve()`. Since `resolve()` is the internal method and `call()` delegates to it, this should be covered. But worth confirming.

3. **Should there be a guard against double-proxying?** If someone calls `container->get(Foo::class)` and stores the proxy, then later registers it via `container->instance('foo', $proxy)`, and another resolve path hits it -- could it get double-wrapped? The `instance()` exclusion prevents this for pre-registered instances, but worth thinking about.
