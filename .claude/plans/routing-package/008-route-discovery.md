# Task 008: Route Discovery

**Status**: completed
**Depends on**: 004, 006, 007
**Retry count**: 0

## Description
Create the RouteDiscovery class that scans controller classes in modules to find methods with route attributes. It builds RouteDefinition objects from discovered routes, combining class-level and method-level middleware.

## Context
- Location: `packages/routing/src/`
- Scans modules for classes with route attributes on methods
- Controller classes don't need a marker attribute - any class with routed methods is a controller
- Combines class #[Middleware] with method #[Middleware]
- Returns array of RouteDefinition objects

## Requirements (Test Descriptions)
- [ ] `it discovers routes in module src directories`
- [ ] `it finds methods with Get attribute`
- [ ] `it finds methods with Post attribute`
- [ ] `it finds methods with Put attribute`
- [ ] `it finds methods with Patch attribute`
- [ ] `it finds methods with Delete attribute`
- [ ] `it creates RouteDefinition for each discovered route`
- [ ] `it extracts path from route attribute`
- [ ] `it extracts controller class name`
- [ ] `it extracts method name`
- [ ] `it combines class-level middleware with method-level middleware`
- [ ] `it applies class middleware before method middleware`
- [ ] `it skips methods with DisableRoute attribute`
- [ ] `it handles multiple routes in same controller`

## Acceptance Criteria
- All requirements have passing tests
- Discovery handles nested directories
- Middleware order is deterministic (class first, then method)
- Skips disabled routes cleanly

## Files to Create
```
packages/routing/src/
  RouteDiscovery.php
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
