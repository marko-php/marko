# Task 012: Entity Base Class and Core Attributes

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Create the Entity base class and the core attributes (#[Table], #[Column], #[Index], #[ForeignKey]) that define database schema through entity classes. This is the primary way users define their database structure.

## Context
- Related files: packages/database/src/Entity/Entity.php, packages/database/src/Attributes/
- Patterns to follow: PHP 8 attributes, Marko attribute patterns
- Entities ARE the schema - no separate schema files

## Requirements (Test Descriptions)
- [ ] `it creates Entity base class that can be extended`
- [ ] `it creates #[Table] attribute with table name parameter`
- [ ] `it creates #[Column] attribute with optional name parameter`
- [ ] `it supports #[Column] primaryKey parameter`
- [ ] `it supports #[Column] autoIncrement parameter`
- [ ] `it supports #[Column] length parameter for varchar`
- [ ] `it supports #[Column] type parameter for explicit type override`
- [ ] `it supports #[Column] unique parameter`
- [ ] `it supports #[Column] default parameter`
- [ ] `it supports #[Column] references parameter for foreign keys`
- [ ] `it supports #[Column] onDelete and onUpdate parameters`
- [ ] `it creates #[Index] attribute with name and columns`
- [ ] `it supports #[Index] unique parameter`
- [ ] `it creates #[ForeignKey] attribute for composite foreign keys`
- [ ] `it allows multiple #[Index] attributes on a class`

## Acceptance Criteria
- All requirements have passing tests
- Attributes are intuitive and well-documented
- Entity base class is minimal (just marker/common methods)
- Attributes capture all schema information needed

## Implementation Notes
(Left blank - filled in by programmer during implementation)
