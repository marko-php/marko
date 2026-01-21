# Task 013: Entity Metadata Parser and Schema Builder

**Status**: completed
**Depends on**: 012
**Retry count**: 0

## Description
Create the EntityMetadataFactory that parses entity classes and their attributes, and the SchemaBuilder that converts entity metadata into Schema value objects. This bridges user-defined entities to the internal schema representation.

## Context
- Related files: packages/database/src/Entity/EntityMetadataFactory.php, EntityMetadata.php, SchemaBuilder.php
- Patterns to follow: Reflection-based parsing, caching for performance
- Type inference from PHP types to database types

## Requirements (Test Descriptions)
- [x] `it creates EntityMetadata class to hold parsed attribute data`
- [x] `it creates EntityMetadataFactory to parse entity classes via reflection`
- [x] `it extracts #[Table] attribute for table name`
- [x] `it extracts #[Column] attributes from all public properties`
- [x] `it extracts #[Index] attributes from class`
- [x] `it infers column type from PHP property type (int→INT, string→VARCHAR, etc)`
- [x] `it infers nullable from nullable PHP type (?string)`
- [x] `it infers default from property initializer`
- [x] `it creates SchemaBuilder to convert EntityMetadata to Table value object`
- [x] `it discovers all entity classes with #[Table] attribute across modules`
- [x] `it caches parsed metadata for performance`
- [x] `it throws EntityException for invalid attribute combinations`
- [x] `it populates SchemaRegistry with all discovered tables`

## Acceptance Criteria
- All requirements have passing tests
- Type inference covers common PHP types
- Discovery follows module priority (app > modules > vendor)
- Performance is acceptable (metadata cached)

## Implementation Notes

### Files Created

**Entity Metadata Value Objects:**
- `packages/database/src/Entity/EntityMetadata.php` - Main metadata container with properties, columns, indexes, and helper methods
- `packages/database/src/Entity/ColumnMetadata.php` - Parsed column data (name, type, length, nullable, etc.)
- `packages/database/src/Entity/IndexMetadata.php` - Parsed index data (name, columns, unique)
- `packages/database/src/Entity/PropertyMetadata.php` - Property-level metadata for hydration

**Core Classes:**
- `packages/database/src/Entity/EntityMetadataFactory.php` - Parses entity classes via reflection, extracts attributes, infers types, caches results
- `packages/database/src/Entity/SchemaBuilder.php` - Converts EntityMetadata to Schema value objects (Table, Column, Index)
- `packages/database/src/Entity/EntityDiscovery.php` - Discovers entity classes with #[Table] across vendor/modules/app directories
- `packages/database/src/Schema/SchemaRegistry.php` - Central registry mapping table names to schemas, entity classes, and metadata

**Exception:**
- `packages/database/src/Exceptions/EntityException.php` - Entity-specific exceptions with helpful messages

### Type Inference
The factory infers database types from PHP types:
- `int` → `INT`
- `string` → `VARCHAR`
- `float` → `DECIMAL`
- `bool` → `BOOLEAN`
- `array` → `JSON`

Explicit types in `#[Column(type: 'TEXT')]` override inference.

### Caching
EntityMetadataFactory caches parsed metadata by class name. Use `clearCache()` to reset.

### Discovery Pattern
EntityDiscovery searches for Entity classes with #[Table] attribute:
- `discoverInVendor()` - vendor/*/*/src/Entity/
- `discoverInModules()` - modules/*/*/src/Entity/
- `discoverInApp()` - app/*/Entity/
- `discoverAll()` - All three paths combined

### Test Files
- `packages/database/tests/Entity/EntityMetadataTest.php`
- `packages/database/tests/Entity/EntityMetadataFactoryTest.php`
- `packages/database/tests/Entity/SchemaBuilderTest.php`
- `packages/database/tests/Entity/EntityDiscoveryTest.php`
- `packages/database/tests/Schema/SchemaRegistryTest.php`
