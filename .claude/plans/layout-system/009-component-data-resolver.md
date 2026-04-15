# Task 009: ComponentDataResolver

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `ComponentDataResolver` class that resolves and invokes a component's `data()` method. Route parameters are injected by name into `data()` method arguments, using the same resolution and type-casting logic as `Router::resolveParameters()`. If the component class has no `data()` method, returns an empty array.

## Context
- Related files: `packages/routing/src/Router.php` lines 80-118 (resolveParameters pattern to follow)
- Route parameters are injected by name matching the method signature
- Type casting: int, float, bool (same as Router)
- Request object can be injected when type-hinted
- Components are instantiated via the container (constructor injection works)
- If `data()` method does not exist on the class, returns `[]`
- **Important:** `Router::resolveParameters()` and `Router::castToType()` are both `private` -- they cannot be called or inherited. Mirror the same logic (~40 lines) in `ComponentDataResolver`. This is acceptable because the component version is simpler: it only needs route params, Request injection, and default values. It does NOT need POST/query string fallback (components are not form handlers).

## Requirements (Test Descriptions)
- [ ] `it returns empty array when component has no data method`
- [ ] `it calls data method with no arguments when method has no parameters`
- [ ] `it injects route parameters by name into data method`
- [ ] `it casts route parameters to int when type-hinted`
- [ ] `it casts route parameters to float when type-hinted`
- [ ] `it casts route parameters to bool when type-hinted`
- [ ] `it injects Request object when type-hinted`
- [ ] `it uses default values when route parameter is not available`
- [ ] `it returns the array returned by the data method`

## Acceptance Criteria
- All requirements have passing tests
- Class is readonly
- Mirrors the same resolution logic pattern as Router (route params + Request injection + type casting + defaults). Does NOT include POST/query string fallback since components are not form handlers.
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
