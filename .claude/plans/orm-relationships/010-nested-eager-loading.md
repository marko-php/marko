# Task 010: Nested Eager Loading — Dot-Notation Relationship Chains

**Status**: complete
**Depends on**: 006, 007
**Retry count**: 0

## Description
Add support for nested eager loading using dot notation (e.g., `'comments.author'`). When `with('comments.author')` is called, the loader first loads comments for all posts, then loads author for all loaded comments. This extends the RelationshipLoader to recursively resolve relationship chains.

## Context
- Modify: `packages/database/src/Entity/RelationshipLoader.php`
- Modify: `packages/database/src/Repository/Repository.php` (parse dot notation in with())
- Test file: `packages/database/tests/Entity/RelationshipLoaderNestedTest.php`
- Dot notation is parsed into a tree structure so that `with('comments.author', 'comments.tags')` loads comments once, then loads both author and tags on those comments
- Loading is breadth-first: load all level-1 relationships, then all level-2, etc.

## Requirements (Test Descriptions)

### Dot Notation Parsing
- [x] `it parses single-level relationship name`
- [x] `it parses two-level dot notation into parent and child`
- [x] `it parses three-level dot notation`
- [x] `it merges duplicate parent relationships from multiple dot paths`

### Nested Loading
- [x] `it loads nested BelongsTo on HasMany results`
- [x] `it loads nested HasMany on BelongsTo result`
- [x] `it loads multiple nested relationships on same parent`
- [x] `it loads three levels deep`

### Batch Optimization
- [x] `it batch loads nested relationships across all parent entities`
- [x] `it handles empty intermediate results without error`

## Acceptance Criteria
- All requirements have passing tests
- Dot notation parsed into a tree to deduplicate shared prefixes
- Each level of nesting uses batch loading (no N+1 at any level)
- Handles empty intermediate results (e.g., comments loaded but none have authors)
- Maximum depth is not artificially limited but documented
- Strict types, @throws tags

## Implementation Notes
- Added `RelationshipLoader::parseRelationshipTree(array $relationships): array` static method that converts flat dot-notation strings into a nested tree (e.g. `['comments.author', 'category']` → `['comments' => ['author' => []], 'category' => []]`)
- Added `RelationshipLoader::loadNested(array $entities, array $relationshipTree, EntityMetadata $parentMetadata): void` which loads top-level relationships and recursively processes children using reflection to collect the related entities
- Updated `Repository::with()` to validate only the top-level segment of dot-notation paths
- Updated `Repository::eagerLoadRelationships()` to parse pending relationships into a tree and call `loadNested()` instead of calling `load()` per relationship — this handles both flat and nested cases uniformly
- Each level of nesting is batch-loaded (single WHERE IN query per relationship) with no artificial depth limit
