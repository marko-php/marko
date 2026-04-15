# Task 003: RelationshipMetadata — Value Object for Parsed Relationships

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the `RelationshipMetadata` readonly value object that holds parsed relationship information extracted from entity attributes. This is the relationship equivalent of `PropertyMetadata` — a structured, cacheable representation of relationship configuration. Also create the `RelationshipType` enum.

## Context
- New files:
  - `packages/database/src/Entity/RelationshipMetadata.php`
  - `packages/database/src/Entity/RelationshipType.php`
- Test file: `packages/database/tests/Entity/RelationshipMetadataTest.php`
- Patterns to follow: `packages/database/src/Entity/PropertyMetadata.php` (readonly value object with constructor promotion)
- RelationshipType is a BackedEnum like `packages/database/src/Schema/IndexType.php`

## Requirements (Test Descriptions)

### RelationshipType Enum
- [ ] `it defines HasOne relationship type`
- [ ] `it defines HasMany relationship type`
- [ ] `it defines BelongsTo relationship type`
- [ ] `it defines BelongsToMany relationship type`

### RelationshipMetadata Value Object
- [ ] `it creates metadata for a HasOne relationship`
- [ ] `it creates metadata for a HasMany relationship`
- [ ] `it creates metadata for a BelongsTo relationship`
- [ ] `it creates metadata for a BelongsToMany relationship with pivot class`
- [ ] `it stores the property name the relationship is defined on`
- [ ] `it stores the related entity class`
- [ ] `it stores the foreign key column name`
- [ ] `it stores the related key for BelongsToMany`
- [ ] `it stores the pivot class for BelongsToMany`
- [ ] `it returns null for pivot class on non-BelongsToMany relationships`
- [ ] `it returns null for related key on non-BelongsToMany relationships`
- [ ] `it identifies single-result relationships via isSingular`
- [ ] `it identifies collection relationships via isCollection`

## Acceptance Criteria
- All requirements have passing tests
- RelationshipMetadata is readonly with constructor promotion
- RelationshipType is a string-backed enum
- isSingular() returns true for HasOne and BelongsTo
- isCollection() returns true for HasMany and BelongsToMany
- Strict types declared
- No default values on required parameters

## Implementation Notes
(Left blank - filled in by programmer during implementation)
