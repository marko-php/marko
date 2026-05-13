# Task 032: `ScopedOrderByFactory` service

**Status**: pending
**Depends on**: 016

## Description
A registered service that constructs `ScopedOrderBy` specs with the right dependencies wired. App code uses `$factory->create(Product::class, 'name', 'asc')` instead of dealing with constructor injection at the call site.

This isolates the spec from container-access concerns and makes test setup trivial.

## Context
- Related files: `packages/scope/src/Query/ScopedOrderByFactory.php` (new)
- Patterns to follow: Constructor-injected service. Registered as singleton in `marko/scope` `module.php`. Pulls `ScopeMetadataFactory`, `ScopeContext`, `ScopeSortRendererInterface` from the container exactly once at construction.

## Requirements (Test Descriptions)
- [ ] `it constructs a ScopedOrderBy with the entity class, property, and direction`
- [ ] `it injects ScopeMetadataFactory, ScopeContext, and ScopeSortRendererInterface into the spec`
- [ ] `it defaults direction to asc when omitted`
- [ ] `it is a readonly class with constructor-injected dependencies`

## Acceptance Criteria
- `readonly class ScopedOrderByFactory`.
- `create(string $entityClass, string $property, string $direction = 'asc'): ScopedOrderBy`.
- Registered as a singleton in `module.php` (update task 017 dependencies).

## Implementation Notes
(Left blank — filled in during implementation.)
