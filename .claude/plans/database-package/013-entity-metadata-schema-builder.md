# Task 013: Entity Metadata Parser and Schema Builder

**Status**: pending
**Depends on**: 012
**Retry count**: 0

## Description
Create the EntityMetadataFactory that parses entity classes and their attributes, and the SchemaBuilder that converts entity metadata into Schema value objects. This bridges user-defined entities to the internal schema representation.

## Context
- Related files: packages/database/src/Entity/EntityMetadataFactory.php, EntityMetadata.php, SchemaBuilder.php
- Patterns to follow: Reflection-based parsing, caching for performance
- Type inference from PHP types to database types

## Requirements (Test Descriptions)
- [ ] `it creates EntityMetadata class to hold parsed attribute data`
- [ ] `it creates EntityMetadataFactory to parse entity classes via reflection`
- [ ] `it extracts #[Table] attribute for table name`
- [ ] `it extracts #[Column] attributes from all public properties`
- [ ] `it extracts #[Index] attributes from class`
- [ ] `it infers column type from PHP property type (int→INT, string→VARCHAR, etc)`
- [ ] `it infers nullable from nullable PHP type (?string)`
- [ ] `it infers default from property initializer`
- [ ] `it creates SchemaBuilder to convert EntityMetadata to Table value object`
- [ ] `it discovers all entity classes with #[Table] attribute across modules`
- [ ] `it caches parsed metadata for performance`
- [ ] `it throws EntityException for invalid attribute combinations`
- [ ] `it populates SchemaRegistry with all discovered tables`

## Acceptance Criteria
- All requirements have passing tests
- Type inference covers common PHP types
- Discovery follows module priority (app > modules > vendor)
- Performance is acceptable (metadata cached)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
