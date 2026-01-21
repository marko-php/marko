# Task 012: Entity Base Class and Core Attributes

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Create the Entity base class and the core attributes (#[Table], #[Column], #[Index], #[ForeignKey]) that define database schema through entity classes. This is the primary way users define their database structure.

## Context
- Related files: packages/database/src/Entity/Entity.php, packages/database/src/Attributes/
- Patterns to follow: PHP 8 attributes, Marko attribute patterns
- Entities ARE the schema - no separate schema files

## Requirements (Test Descriptions)
- [x] `it creates Entity base class that can be extended`
- [x] `it creates #[Table] attribute with table name parameter`
- [x] `it creates #[Column] attribute with optional name parameter`
- [x] `it supports #[Column] primaryKey parameter`
- [x] `it supports #[Column] autoIncrement parameter`
- [x] `it supports #[Column] length parameter for varchar`
- [x] `it supports #[Column] type parameter for explicit type override`
- [x] `it supports #[Column] unique parameter`
- [x] `it supports #[Column] default parameter`
- [x] `it supports #[Column] references parameter for foreign keys`
- [x] `it supports #[Column] onDelete and onUpdate parameters`
- [x] `it creates #[Index] attribute with name and columns`
- [x] `it supports #[Index] unique parameter`
- [x] `it creates #[ForeignKey] attribute for composite foreign keys`
- [x] `it allows multiple #[Index] attributes on a class`

## Acceptance Criteria
- All requirements have passing tests
- Attributes are intuitive and well-documented
- Entity base class is minimal (just marker/common methods)
- Attributes capture all schema information needed

## Implementation Notes
Implemented the Entity base class and core attributes for defining database schema through PHP classes:

**Files Created:**
- `packages/database/src/Entity/Entity.php` - Abstract base class for entities
- `packages/database/src/Attributes/Table.php` - Class-level attribute for table name
- `packages/database/src/Attributes/Column.php` - Property-level attribute with full schema support
- `packages/database/src/Attributes/Index.php` - Class-level repeatable attribute for indexes
- `packages/database/src/Attributes/ForeignKey.php` - Class-level repeatable attribute for composite FKs

**Column Attribute Parameters:**
- `name` - Custom column name (optional, defaults to property name)
- `primaryKey` - Mark as primary key
- `autoIncrement` - Auto-increment column
- `length` - VARCHAR length
- `type` - Explicit type override
- `unique` - Unique constraint
- `default` - Default value
- `references` - Foreign key reference (e.g., 'users.id')
- `onDelete` / `onUpdate` - Referential actions

**Design Decisions:**
- Entity is abstract but minimal (marker class)
- Column attribute placed on properties, not class
- Index and ForeignKey are IS_REPEATABLE for multiple per class
- All attributes are readonly for immutability
