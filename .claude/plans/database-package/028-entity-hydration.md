# Task 028: Entity Hydration

**Status**: completed
**Depends on**: 012, 006
**Retry count**: 0

## Description
Create the EntityHydrator that converts database rows into entity objects and vice versa. This handles type conversion, property mapping, and tracking entity state (new vs persisted).

## Context
- Related files: packages/database/src/Entity/EntityHydrator.php
- Patterns to follow: Uses EntityMetadata from Task 013
- Hydrator is separate from entity (Single Responsibility)

## Requirements (Test Descriptions)
- [x] `it creates EntityHydrator class`
- [x] `it hydrates entity from database row array`
- [x] `it maps database columns to entity properties using metadata`
- [x] `it handles snake_case to camelCase conversion`
- [x] `it converts database types to PHP types (int, string, bool)`
- [x] `it converts datetime strings to DateTimeImmutable`
- [x] `it converts enum values to BackedEnum instances`
- [x] `it handles nullable properties correctly`
- [x] `it extracts entity data to row array for persistence`
- [x] `it tracks whether entity is new (no ID) or persisted (has ID)`
- [x] `it preserves original values for dirty checking`
- [x] `it detects changed properties via isDirty()`

## Acceptance Criteria
- All requirements have passing tests
- Type conversion is accurate for common types
- Dirty checking enables efficient updates
- Handles null values properly

## Implementation Notes
Implemented EntityHydrator for converting database rows to entity objects and vice versa:

**Files Created:**
- `packages/database/src/Entity/EntityHydrator.php` - Main hydrator class

**Files Updated:**
- `packages/database/src/Entity/EntityMetadata.php` - Added `primaryKey` and `properties` fields, plus helper methods for column/property mapping

**Key Features:**
- `hydrate()` - Creates entity instances from database rows using reflection
- `extract()` - Converts entity to row array for persistence
- `isNew()` - Checks if entity has null primary key (not yet persisted)
- `isDirty()` / `getDirtyProperties()` - Dirty checking via original value comparison
- `getOriginalValues()` - Returns values at hydration time

**Type Conversions Supported:**
- Scalar types: int, float, string, bool
- DateTimeImmutable from datetime strings
- BackedEnum from string/int values
- Null handling for nullable properties

**Design Decisions:**
- Uses WeakMap for original value storage (automatic cleanup when entity is GC'd)
- Reflection-based property access for clean entity classes
- Column-to-property mapping via EntityMetadata for snake_case/camelCase conversion
