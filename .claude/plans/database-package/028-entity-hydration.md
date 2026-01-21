# Task 028: Entity Hydration

**Status**: pending
**Depends on**: 012, 006
**Retry count**: 0

## Description
Create the EntityHydrator that converts database rows into entity objects and vice versa. This handles type conversion, property mapping, and tracking entity state (new vs persisted).

## Context
- Related files: packages/database/src/Entity/EntityHydrator.php
- Patterns to follow: Uses EntityMetadata from Task 013
- Hydrator is separate from entity (Single Responsibility)

## Requirements (Test Descriptions)
- [ ] `it creates EntityHydrator class`
- [ ] `it hydrates entity from database row array`
- [ ] `it maps database columns to entity properties using metadata`
- [ ] `it handles snake_case to camelCase conversion`
- [ ] `it converts database types to PHP types (int, string, bool)`
- [ ] `it converts datetime strings to DateTimeImmutable`
- [ ] `it converts enum values to BackedEnum instances`
- [ ] `it handles nullable properties correctly`
- [ ] `it extracts entity data to row array for persistence`
- [ ] `it tracks whether entity is new (no ID) or persisted (has ID)`
- [ ] `it preserves original values for dirty checking`
- [ ] `it detects changed properties via isDirty()`

## Acceptance Criteria
- All requirements have passing tests
- Type conversion is accurate for common types
- Dirty checking enables efficient updates
- Handles null values properly

## Implementation Notes
(Left blank - filled in by programmer during implementation)
