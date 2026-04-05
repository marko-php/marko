# Task 002: Wrap Resolved Instances with Plugin Proxy

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Modify `Container::resolve()` to wrap resolved instances with `PluginInterceptor::createProxy()` when a `PluginInterceptor` is available. This is the core change that makes plugins functional. The proxy wrapping should happen for autowired instances and closure bindings, but NOT for pre-registered instances (via `instance()`). Singleton caching must store the proxy, not the raw instance.

## Context
- Related files: `packages/core/src/Container/Container.php`, `packages/core/src/Plugin/PluginInterceptor.php`
- `PluginInterceptor::createProxy()` already returns the raw target when no plugins exist — no extra guard needed
- `resolve()` has three code paths that create instances:
  1. The `instances[]` early return (pre-registered and cached singletons) — should NOT be re-wrapped
  2. Closure bindings — returns early before end of method, so proxy wrapping must happen INSIDE this block before the return
  3. Autowiring — falls through to end of method, proxy wrapping can happen before the return
- **IMPORTANT**: The closure binding code path has an early `return` statement. Proxy wrapping placed "at the end" of `resolve()` will NEVER execute for closure bindings. You must add proxy wrapping in BOTH the closure binding block (before its return) AND the autowiring path (before its return). Alternatively, restructure to have a single exit point.
- The `$id` variable changes during resolution (preferences/bindings may change it). Pass the resolved concrete class name (post-preference/binding `$id`) as `targetClass` to `createProxy()`, NOT `$originalId`
- Pre-registered instances via `instance()` are returned from the `instances[]` cache before proxy wrapping code runs, so they are naturally excluded
- The `PluginInterceptor` is accessed via the setter added in Task 001 (`$this->pluginInterceptor`), which may be null

## Requirements (Test Descriptions)
- [ ] `it wraps resolved instance with plugin proxy when plugins are registered`
- [ ] `it returns raw instance when no plugins are registered for the class`
- [ ] `it caches the proxy as the singleton instance on subsequent resolves`
- [ ] `it wraps closure binding results with plugin proxy`
- [ ] `it does not wrap pre-registered instances from instance() method`
- [ ] `it applies plugin proxy after preference resolution`

## Acceptance Criteria
- All requirements have passing tests
- Existing ContainerTest tests continue to pass
- Code follows code standards
