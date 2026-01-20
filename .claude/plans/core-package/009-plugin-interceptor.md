# Task 009: Plugin Interceptor (Method Wrapping)

**Status**: pending
**Depends on**: 003, 008
**Retry count**: 0

## Description
Create the plugin interceptor that wraps method calls with before/after plugin execution. When the container resolves a class that has plugins, it returns a proxy that applies the plugin chain.

## Context
- Location: `packages/core/src/Plugin/`
- Before plugins run before the method, can return early to short-circuit
- After plugins run after the method, receive and can modify the result
- The interceptor integrates with the container resolution

## Requirements (Test Descriptions)
- [ ] `it executes before plugins in sort order before target method`
- [ ] `it passes method arguments to before plugins`
- [ ] `it short-circuits when before plugin returns non-null value`
- [ ] `it executes target method when all before plugins return null`
- [ ] `it executes after plugins in sort order after target method`
- [ ] `it passes result and original arguments to after plugins`
- [ ] `it uses modified result from after plugin for next plugin`
- [ ] `it returns final result after all after plugins complete`
- [ ] `it handles methods with no plugins without overhead`
- [ ] `it injects plugin dependencies via container`
- [ ] `it creates proxy only for classes with registered plugins`

## Acceptance Criteria
- All requirements have passing tests
- Proxy generation is efficient (lazy or cached)
- Plugin chain respects sort order strictly
- Code follows strict types declaration

## Files to Create
```
packages/core/src/Plugin/
  PluginInterceptor.php     # Wraps method calls with plugin chain
  PluginProxy.php           # Proxy class that intercepts method calls
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
