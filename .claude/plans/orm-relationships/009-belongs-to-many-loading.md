# Task 009: BelongsToMany Eager Loading — Pivot Entity Resolution

**Status**: pending
**Depends on**: 006
**Retry count**: 0

## Description
Add `BelongsToMany` support to `RelationshipLoader`. Many-to-many relationships require querying through a pivot entity table: first load pivot rows matching the parent foreign keys, then load the related entities matching the pivot's related keys. The pivot entity is a first-class Entity with its own metadata.

## Context
- Modify: `packages/database/src/Entity/RelationshipLoader.php`
- Test file: `packages/database/tests/Entity/RelationshipLoaderBelongsToManyTest.php`
- BelongsToMany uses a pivot Entity class (e.g., PostTag) defined via `#[BelongsToMany(pivot: PostTag::class)]`
- Loading strategy:
  1. Get pivot table metadata from EntityMetadataFactory
  2. Query pivot table: `SELECT * FROM pivot_table WHERE foreign_key IN (...)`
  3. Collect related IDs from pivot rows
  4. Query related table: `SELECT * FROM related_table WHERE id IN (...)`
  5. Map related entities back to parents through pivot rows
- Pivot entities are standard entities — they have their own #[Table], #[Column] attributes

## Requirements (Test Descriptions)

### BelongsToMany Loading
- [ ] `it loads BelongsToMany relationship for a single entity`
- [ ] `it batch loads BelongsToMany relationship for multiple entities`
- [ ] `it sets BelongsToMany property to empty array when no related entities found`
- [ ] `it resolves through pivot table using two queries`
- [ ] `it correctly maps related entities back to parent entities`

### Pivot Entity Handling
- [ ] `it reads pivot table name from pivot entity metadata`
- [ ] `it uses foreign key column to query pivot table`
- [ ] `it uses related key column to query related table`
- [ ] `it handles pivot entities with extra columns`

### Batch Optimization
- [ ] `it deduplicates related IDs across parents in batch query`
- [ ] `it handles parents with no pivot rows`

## Acceptance Criteria
- All requirements have passing tests
- BelongsToMany uses exactly 2 queries: one for pivot table, one for related table
- Pivot entities are parsed via EntityMetadataFactory (standard entity metadata)
- Related entities are properly hydrated and assigned to parent properties
- Handles empty results gracefully (empty array, not null)
- Strict types, @throws tags

## Implementation Notes
(Left blank - filled in by programmer during implementation)
