# Task 008: RepositoryQueryBuilder — Add with() and matching() Support

**Status**: pending
**Depends on**: 005, 007
**Retry count**: 0

## Description
Enhance the existing `RepositoryQueryBuilder` to support `with()` for eager loading and `matching()` for query specifications. This allows the fluent query builder API to include relationship loading and specification composition in a single chain.

## Context
- Modify: `packages/database/src/Repository/RepositoryQueryBuilder.php`
- Test file: `packages/database/tests/Repository/RepositoryQueryBuilderEnhancedTest.php`
- Currently `RepositoryQueryBuilder` is readonly and delegates to `QueryBuilderInterface`
- It already has `getEntities()` and `firstEntity()` — these need to apply eager loading
- The `with()` method stores relationship names; `matching()` applies specifications to the underlying builder
- `getEntities()` return type changes from `array` to `EntityCollection`

## Requirements (Test Descriptions)

### with() on Query Builder
- [ ] `it accepts relationship names via with`
- [ ] `it loads relationships on getEntities results`
- [ ] `it loads relationships on firstEntity result`
- [ ] `it returns null from firstEntity without loading when no result`
- [ ] `it chains with and where clauses fluently`

### matching() on Query Builder
- [ ] `it applies single specification via matching`
- [ ] `it applies multiple specifications via matching`
- [ ] `it chains matching with where and orderBy`
- [ ] `it chains matching with with for relationships and specifications together`

### getEntities Return Type
- [ ] `it returns EntityCollection from getEntities`
- [ ] `it returns empty EntityCollection when no results`

## Acceptance Criteria
- All requirements have passing tests
- RepositoryQueryBuilder MUST drop `readonly` from the class declaration to support `with()`/`matching()` mutable state (the current class is `readonly class RepositoryQueryBuilder`). Use a clone-based immutable pattern or internal mutable arrays for storing relationship names and specifications
- getEntities() returns EntityCollection
- firstEntity() applies eager loading when relationships specified
- matching() applies specifications to underlying QueryBuilderInterface
- Existing RepositoryQueryBuilder tests still pass
- Strict types, @throws tags

## Implementation Notes
(Left blank - filled in by programmer during implementation)
