# Task 007: ComponentCollector

**Status**: completed
**Depends on**: 002, 004, 005, 006
**Retry count**: 0

## Description
Create the `ComponentCollector` class that discovers `#[Component]` attributes across all module classes and collects matching components for a given handle. This is the discovery engine — it scans classes via reflection (following the `RouteDiscovery` pattern), resolves handles, and returns a `ComponentCollection` for a specific page.

## Context
- Related files: `packages/routing/src/RouteDiscovery.php` (pattern to follow for attribute scanning and missing-class handling)
- Scans classes for `#[Component]` attributes using reflection
- Resolves class-reference handles (`[Controller::class, 'method']`) to string handles. This requires looking up the route path for the controller/method pair, so `ComponentCollector` needs access to `RouteCollection` to find the matching `RouteDefinition` and call `HandleResolver::generate()`.
- Collects components whose handles match the current page handle (using `HandleResolver::matches()`)
- `'default'` handle components are always included
- Handles gracefully missing classes from uninstalled Marko packages (same pattern as RouteDiscovery: catch `Error`, use `MarkoException::extractMissingClass()` + `MarkoException::inferPackageName()`)
- The layout component (root) is also discovered -- it has no `slot` but has `slots`

### Class Discovery
- `ComponentCollector` receives an array of class-strings to scan (e.g., collected from module manifests or a class scanner). Note: `RouteDiscovery::discoverInModule()` currently returns `[]` (stub) -- the actual filesystem scanning mechanism is not yet implemented there either.
- Primary method signature: `collect(array $classNames, string $handle): ComponentCollection` -- scans the given classes for `#[Component]` attributes, resolves handles, and returns a collection of components matching the given page handle.
- A secondary method `discoverFromClass(string $className): ?ComponentDefinition` handles single-class scanning (mirrors `RouteDiscovery::discoverFromClass()`).

## Requirements (Test Descriptions)
- [ ] `it discovers component attributes from a class`
- [ ] `it creates ComponentDefinition from discovered attributes`
- [ ] `it collects components matching an exact handle`
- [ ] `it collects components matching a handle prefix`
- [ ] `it always includes components with default handle`
- [ ] `it excludes components that do not match the handle`
- [ ] `it resolves class-reference handles to string handles`
- [ ] `it returns a ComponentCollection with all matching components`
- [ ] `it skips classes with attributes from uninstalled packages gracefully`
- [ ] `it discovers components targeting multiple handles`

## Acceptance Criteria
- All requirements have passing tests
- Follows RouteDiscovery pattern for attribute scanning
- Handles missing class errors gracefully (MarkoException::extractMissingClass pattern)
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
