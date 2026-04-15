# Task 004: EntityMetadataFactory — Parse Relationship Attributes

**Status**: pending
**Depends on**: 002, 003
**Retry count**: 0

## Description
Extend `EntityMetadataFactory` to parse relationship attributes (`#[HasOne]`, `#[HasMany]`, `#[BelongsTo]`, `#[BelongsToMany]`) from entity classes and include them in `EntityMetadata`. Also extend `EntityMetadata` to hold relationship metadata.

## Context
- Modify: `packages/database/src/Entity/EntityMetadataFactory.php`
- Modify: `packages/database/src/Entity/EntityMetadata.php`
- Test file: `packages/database/tests/Entity/EntityMetadataFactoryRelationshipTest.php`
- The factory already parses `#[Column]`, `#[Table]`, `#[Index]` attributes — add relationship parsing in the same style
- Relationship properties must NOT also have `#[Column]` — they represent derived data, not stored columns
- EntityMetadata gains a `relationships` property: `array<string, RelationshipMetadata>` keyed by property name

## Requirements (Test Descriptions)

### EntityMetadata Extension
- [ ] `it includes relationships array in entity metadata`
- [ ] `it returns empty relationships array when entity has no relationships`
- [ ] `it provides getRelationship method to retrieve by property name`
- [ ] `it returns null from getRelationship for non-existent relationship`
- [ ] `it provides getRelationships method returning all relationships`

### Parsing HasOne
- [ ] `it parses HasOne attribute from entity property`
- [ ] `it extracts entity class from HasOne attribute`
- [ ] `it extracts foreign key from HasOne attribute`
- [ ] `it sets relationship type to HasOne`

### Parsing HasMany
- [ ] `it parses HasMany attribute from entity property`
- [ ] `it extracts entity class from HasMany attribute`
- [ ] `it extracts foreign key from HasMany attribute`
- [ ] `it sets relationship type to HasMany`

### Parsing BelongsTo
- [ ] `it parses BelongsTo attribute from entity property`
- [ ] `it extracts entity class from BelongsTo attribute`
- [ ] `it extracts foreign key from BelongsTo attribute`
- [ ] `it sets relationship type to BelongsTo`

### Parsing BelongsToMany
- [ ] `it parses BelongsToMany attribute from entity property`
- [ ] `it extracts entity class from BelongsToMany attribute`
- [ ] `it extracts pivot class from BelongsToMany attribute`
- [ ] `it extracts foreign key from BelongsToMany attribute`
- [ ] `it extracts related key from BelongsToMany attribute`
- [ ] `it sets relationship type to BelongsToMany`

### Validation
- [ ] `it skips properties without relationship attributes`
- [ ] `it parses entities with both columns and relationships`
- [ ] `it caches relationship metadata with entity metadata`

## Acceptance Criteria
- All requirements have passing tests
- EntityMetadata backward-compatible (relationships defaults to empty array)
- `EntityMetadataFactory::parse()` must pass the parsed `relationships` array to the `EntityMetadata` constructor (line ~139 in current code)
- Existing EntityMetadataFactory tests still pass
- No magic methods, strict types
- Relationship parsing uses same reflection pattern as column parsing

## Implementation Notes
(Left blank - filled in by programmer during implementation)
