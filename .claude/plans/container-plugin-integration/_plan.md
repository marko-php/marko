# Plan: Container Plugin Integration

## Created
2026-04-04

## Status
completed

## Objective
Wire the plugin interception system into `Container::resolve()` so that resolved objects are automatically wrapped with `PluginProxy` when plugins are registered for their class. This makes the entire plugin system functional end-to-end.

## Scope

### In Scope
- Container accepts a `PluginInterceptor` via setter method (not constructor — circular dependency with PluginInterceptor needing ContainerInterface)
- `resolve()` wraps instances with `PluginInterceptor::createProxy()` when plugins exist
- Singleton caching stores the proxy (not the raw instance)
- Closure bindings also get proxy-wrapped
- `instance()` pre-registered objects are NOT proxy-wrapped (they're user-provided)
- Application wires `PluginRegistry` and `PluginInterceptor` into Container
- Integration tests proving end-to-end: container resolve → method call → plugin fires
- Preferences + plugins work together correctly

### Out of Scope
- Around plugins (architecture doc explicitly excludes these)
- Plugin discovery changes (already working)
- PluginProxy or PluginInterceptor behavioral changes (already working)
- Performance caching/compilation of plugin metadata

## Success Criteria
- [ ] Container wraps resolved objects with plugin proxies when plugins are registered
- [ ] Singleton instances return the same proxy on subsequent resolves
- [ ] Preferences resolve to the correct class AND apply plugins for the original target
- [ ] Closure bindings are proxy-wrapped
- [ ] Pre-registered instances (via `instance()`) are NOT proxy-wrapped
- [ ] Plugin before/after methods fire when calling methods on container-resolved objects
- [ ] All existing Container and Plugin tests continue to pass
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add PluginInterceptor setter to Container | - | completed |
| 002 | Wrap resolved instances with plugin proxy | 001 | completed |
| 003 | Wire PluginInterceptor into Application bootstrap | 001, 002 | completed |
| 004 | Integration test: end-to-end plugin interception via container | 002 | completed |
| 005 | Prevent plugins from targeting plugin classes | - | completed |

## Architecture Notes

### Where proxy wrapping happens
The proxy wrapping must happen in BOTH return paths of `resolve()`:
1. Inside the closure binding block (before its early `return`)
2. In the autowiring path (before its `return` at end of method)

The closure binding code path has an early `return` statement — any code placed only at the end of `resolve()` will never execute for closure bindings. Both paths must wrap the instance AND cache the proxy (not the raw instance) for singletons.

### What class to check plugins for
When preferences are in play, pass the resolved concrete class name (post-preference `$id`) as `targetClass` to `createProxy()`, NOT `$originalId`. Example: if `FooInterface` -> `FooImpl` via preference, and a plugin targets `FooImpl`, pass `FooImpl` to `createProxy()`. Plugins target concrete classes with public methods, not interfaces.

### PluginInterceptor already handles the "no plugins" case
`PluginInterceptor::createProxy()` already checks `hasPluginsFor()` and returns the raw target if no plugins exist. So the container can unconditionally call `createProxy()` without an extra guard — the interceptor handles the no-op case.

### instance() exclusion
Objects registered via `instance()` are pre-built by the user (or by Application bootstrap). They should NOT be proxy-wrapped because:
1. They may already be proxied
2. The user explicitly provided them — wrapping would violate "explicit over implicit"
3. Framework services (Container itself, EventDispatcher, etc.) are registered this way

## Risks & Mitigations
- **Infinite recursion**: Plugin classes are resolved from the container, which could trigger proxy wrapping of the plugin itself. Mitigation: Task 005 adds explicit validation preventing plugins from targeting plugin classes. Additionally, `PluginInterceptor::createProxy()` returns the raw instance if no plugins exist.
- **Existing test breakage**: Container tests don't use PluginInterceptor. Mitigation: The `PluginInterceptor` is injected via setter (not constructor), so existing tests that never call `setPluginInterceptor()` pass unchanged.
- **Type mismatches**: `PluginProxy` uses `__call()` magic, so type checks like `instanceof` on the proxy will fail. This is an existing design characteristic of the proxy system, not new to this change.
