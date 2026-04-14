# Task 007: Repository with() — Integrate Eager Loading

**Status**: pending
**Depends on**: 001, 006
**Retry count**: 0

## Description
Add `with()` method to the `Repository` base class that specifies which relationships to eager load. The `with()` returns a scoped repository proxy (or sets internal state) so that subsequent `find()`, `findAll()`, `findBy()`, and `findOneBy()` calls automatically eager-load the specified relationships after the primary query.

## Context
- Modify: `packages/database/src/Repository/Repository.php`
- Modify: `packages/database/src/Repository/RepositoryInterface.php` (add with() to interface? or keep on concrete only — decision: keep on concrete Repository since RepositoryInterface is the minimal contract)
- Test file: `packages/database/tests/Repository/RepositoryWithTest.php`
- The `with()` method stores relationship names, then find/findAll/findBy methods pass loaded entities through `RelationshipLoader`
- Repository constructor gains optional `RelationshipLoader` parameter
- `with()` returns a new repository instance or a fluent builder to avoid shared mutable state across calls

## Requirements (Test Descriptions)

### with() Method
- [ ] `it returns a new instance from with to avoid shared state`
- [ ] `it accepts variadic string relationship names`
- [ ] `it chains with find to load BelongsTo relationship`
- [ ] `it chains with find to load HasMany relationship`
- [ ] `it chains with find to load HasOne relationship`
- [ ] `it chains with findAll to load relationships on all entities`
- [ ] `it chains with findBy to load relationships on matched entities`
- [ ] `it chains with findOneBy to load relationships on single entity`

### Eager Loading Integration
- [ ] `it passes loaded entities to RelationshipLoader`
- [ ] `it returns EntityCollection from findAll with relationships loaded`
- [ ] `it returns null from find when entity not found without loading relationships`
- [ ] `it loads multiple relationships when multiple names specified`

### Eager Validation
- [ ] `it throws RepositoryException when with is called with unknown relationship name`
- [ ] `it validates all relationship names against entity metadata before cloning`

### Without RelationshipLoader
- [ ] `it works without RelationshipLoader when no with is called`
- [ ] `it throws RepositoryException when with is called but RelationshipLoader is not configured`

## Acceptance Criteria
- All requirements have passing tests
- `with()` must use the `clone` pattern (not `new static(...)` construction) to avoid re-running constructor validation and metadata parsing. The cloned instance carries over all existing state; only the pending relationship names differ. Use PHP 8.5 `clone($this) with { ... }` or traditional clone with property mutation
- Repository constructor gains optional `?RelationshipLoader $relationshipLoader = null` as the last parameter (after `$eventDispatcher`)
- `with()` must check for null `RelationshipLoader` and throw `RepositoryException` BEFORE cloning
- RelationshipLoader is a concrete class, so it will be autowired by the DI container automatically -- no explicit binding needed
- Repository needs a non-readonly mutable property (e.g., `private array $pendingRelationships = []`) to store relationship names from `with()`. This means Repository already has mixed mutability (it has `protected readonly` properties plus the mutable `$metadata`), so adding another mutable property is consistent
- Existing Repository tests still pass (backward compatible)
- find/findAll/findBy/findOneBy return EntityCollection where appropriate
- Strict types, @throws tags

## Implementation Notes
(Left blank - filled in by programmer during implementation)
