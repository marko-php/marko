# Task 006: RelationshipLoader — Batch Eager Loading Service

**Status**: pending
**Depends on**: 003, 004
**Retry count**: 0

## Description
Create the `RelationshipLoader` service that performs batch eager loading of relationships for a set of entities. It uses `WHERE column IN (...)` queries to load all related entities in a single query per relationship, then assigns them to the parent entities' relationship properties. This is the core engine that prevents N+1 queries.

## Context
- New file: `packages/database/src/Entity/RelationshipLoader.php`
- Test file: `packages/database/tests/Entity/RelationshipLoaderTest.php`
- Dependencies: `EntityMetadataFactory`, `EntityHydrator`, `QueryBuilderFactoryInterface` (NOT `ConnectionInterface` -- use QueryBuilderFactoryInterface to create query builders with `whereIn()`, consistent with how Repository works)
- Uses reflection to set relationship properties on parent entities (same pattern as EntityHydrator)
- For HasOne/BelongsTo: assigns single entity or null to property
- For HasMany: assigns array of entities to property
- For BelongsToMany: deferred to task 009

## Requirements (Test Descriptions)

### BelongsTo Loading
- [ ] `it loads BelongsTo relationship for a single entity`
- [ ] `it batch loads BelongsTo relationship for multiple entities`
- [ ] `it sets BelongsTo property to null when related entity not found`
- [ ] `it deduplicates foreign key values in batch query`

### HasOne Loading
- [ ] `it loads HasOne relationship for a single entity`
- [ ] `it batch loads HasOne relationship for multiple entities`
- [ ] `it sets HasOne property to null when related entity not found`

### HasMany Loading
- [ ] `it loads HasMany relationship for a single entity`
- [ ] `it batch loads HasMany relationship for multiple entities`
- [ ] `it sets HasMany property to empty array when no related entities found`
- [ ] `it groups HasMany results by foreign key value`

### Batch Query Optimization
- [ ] `it executes single query for same relationship across multiple entities`
- [ ] `it skips loading when all foreign key values are null`
- [ ] `it loads multiple different relationships in separate queries`

### Entity Hydration
- [ ] `it hydrates related entities with correct types`
- [ ] `it sets related entity properties via reflection`

## Acceptance Criteria
- All requirements have passing tests
- Uses `WHERE column IN (?)` for batch loading (not individual queries per entity)
- Uses reflection to set relationship properties (same pattern as EntityHydrator)
- Properly handles null foreign keys (skips them)
- Deduplicates foreign key values to minimize query size
- Constructor injection with EntityMetadataFactory, EntityHydrator, QueryBuilderFactoryInterface
- Must call `metadataFactory->parse()` on the RELATED entity class (from `RelationshipMetadata::$relatedEntityClass`) to discover its table name and column mappings for hydration
- Must resolve the PARENT entity's primary key property (via parent `EntityMetadata`) to extract FK match values for HasOne/HasMany loading
- `foreignKey` in relationship attributes refers to a PROPERTY name, not a database column name. The loader must resolve property names to column names via `PropertyMetadata` (look up in the entity's `EntityMetadata::properties` map). For BelongsTo, the FK property is on the parent entity. For HasOne/HasMany, the FK property is on the related entity
- Strict types, no magic methods
- @throws tags on public methods

## Implementation Notes
(Left blank - filled in by programmer during implementation)
