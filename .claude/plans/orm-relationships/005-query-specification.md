# Task 005: QuerySpecification Interface and matching() on Repository

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the `QuerySpecification` interface with a single `apply()` method, and add `matching()` to the `Repository` base class. Specifications are composable, named query objects that replace Eloquent scopes with explicit, testable query logic. The `matching()` method accepts variadic specifications and returns entities.

## Context
- New file: `packages/database/src/Query/QuerySpecification.php`
- Modify: `packages/database/src/Repository/Repository.php`
- Test files:
  - `packages/database/tests/Query/QuerySpecificationTest.php`
  - `packages/database/tests/Repository/RepositoryMatchingTest.php`
- Patterns to follow: existing `QueryBuilderInterface` for query method patterns
- `matching()` creates a query builder, applies all specifications, then executes and returns `EntityCollection`
- Specifications compose by sequential application (each modifies the builder in order)

## Requirements (Test Descriptions)

### QuerySpecification Interface
- [ ] `it defines apply method accepting QueryBuilderInterface`
- [ ] `it can be implemented with a simple where clause`
- [ ] `it can be implemented with constructor parameters for configuration`

### Repository matching()
- [ ] `it returns EntityCollection from matching with single specification`
- [ ] `it returns EntityCollection from matching with multiple specifications`
- [ ] `it applies specifications in order to query builder`
- [ ] `it returns empty collection when no entities match specifications`
- [ ] `it throws RepositoryException when query builder is not configured`

### Specification Composition
- [ ] `it composes two specifications filtering results cumulatively`
- [ ] `it allows specifications with constructor parameters`

## Acceptance Criteria
- All requirements have passing tests
- QuerySpecification is an interface with single `apply(QueryBuilderInterface $builder): void` method
- Repository::matching() accepts variadic `QuerySpecification` and returns `EntityCollection`
- matching() requires QueryBuilderFactoryInterface (throws RepositoryException if not configured)
- `matching()` must create its own `EntityCollection` from query builder results directly (using the same hydration pattern as `getEntities()`). Do NOT delegate to `findAll()`/`findBy()` since those still return `array` at this point in the task chain
- Strict types declared
- @throws tag on matching() for RepositoryException

## Implementation Notes
(Left blank - filled in by programmer during implementation)
