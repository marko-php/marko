# Task 016: `ScopedOrderBy` QuerySpecification

**Status**: complete
**Depends on**: 008, 015, 029

## Description
A `QuerySpecification` that adds a scope-aware `ORDER BY` to a `RepositoryQueryBuilder`. The spec is a value object — its dependencies (renderer, metadata factory, scope context) are injected via constructor; do NOT pull from the container at apply-time. App code typically constructs the spec via the `ScopedOrderByFactory` service (task 032).

`apply()` builds a `ScopeSortExpression` from the metadata of the entity class associated with the builder, asks the injected renderer for SQL, and calls `$builder->orderByRaw($expression, $direction)` (added in task 029).

## Context
- Related files: `packages/scope/src/Query/ScopedOrderBy.php` (new)
- Patterns to follow: Existing `QuerySpecification` interface and existing specs in `packages/database/tests/Query/`. The builder param is `EntityQueryBuilderInterface` — `orderByRaw` must therefore exist on that interface too (provided by task 029 through `QueryBuilderInterface`).

## Requirements (Test Descriptions)
- [x] `it accepts a property name and optional direction defaulting to asc`
- [x] `it accepts ScopeMetadataFactory, ScopeContext, and ScopeSortRendererInterface in constructor`
- [x] `it builds a ScopeSortExpression by reading ScopeMetadata for the entity class on apply`
- [x] `it throws ScopeContextException when the property is not Scoped`
- [x] `it falls back to plain orderBy when no scope is active for any of the property's axes`
- [x] `it calls orderByRaw with the renderer-generated COALESCE expression when scope is active`
- [x] `it preserves direction asc or desc on the emitted ORDER BY`

## Acceptance Criteria
- Implements `Marko\Database\Query\QuerySpecification`.
- Constructor parameters: `string $property`, `string $direction`, `ScopeMetadataFactory $metadataFactory`, `ScopeContext $scopeContext`, `ScopeSortRendererInterface $scopeSortRenderer`, `string $entityClass`.
- `apply(EntityQueryBuilderInterface $builder): void` — pulls metadata, builds the expression, decides plain-vs-scoped, calls `orderBy` / `orderByRaw` accordingly.
- The spec class itself is **not** registered as a service; it is instantiated via the factory (task 032).

## Implementation Notes
- `ScopedOrderBy` implemented in `packages/scope/src/Query/ScopedOrderBy.php`.
- Added `ScopeContextException::propertyNotScoped()` factory method to `packages/scope/src/Exceptions/ScopeContextException.php`.
- Constructor parameter order: `property`, `scopeMetadataFactory`, `scopeContext`, `scopeSortRenderer`, `entityClass`, `direction` (with `direction` defaulting to `'asc'` as last optional param).
- `apply()` uses `ScopeContext::registry()->getHierarchy(axis)->walkUp(path)` to build the paths list for `ScopeSortExpression`.
- Falls back to `orderBy($property, strtoupper($direction))` when no axis in the property's metadata has an active scope.
- Calls `orderByRaw($sql, strtoupper($direction))` when scope paths exist.
