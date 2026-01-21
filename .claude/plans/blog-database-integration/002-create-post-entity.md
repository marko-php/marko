# Task 002: Create Post Entity

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the Post entity class with proper database attributes defining the table schema. The entity uses `#[Table]` and `#[Column]` attributes to define its database structure, following the pattern established in the database package.

## Context
- Related files:
  - `packages/blog/src/Entities/Post.php` (to create)
  - `packages/database/src/Entity/Entity.php` (base class)
  - `packages/database/src/Attributes/Table.php`, `Column.php` (attributes)
  - `packages/database/tests/Repository/RepositoryTest.php` (example entity definition)
- Patterns to follow: Entity definition pattern from database package tests

## Requirements (Test Descriptions)
- [ ] `it extends the Entity base class`
- [ ] `it has Table attribute with posts table name`
- [ ] `it has id property with primaryKey and autoIncrement Column attributes`
- [ ] `it has title property with Column attribute`
- [ ] `it has slug property with Column attribute and unique constraint`
- [ ] `it has content property with Column attribute for TEXT type`
- [ ] `it has createdAt property with Column attribute mapping to created_at`
- [ ] `it has updatedAt property with Column attribute mapping to updated_at`
- [ ] `it uses nullable types for optional fields appropriately`

## Acceptance Criteria
- All requirements have passing tests
- Entity follows code standards (strict types, proper typing)
- Column mappings follow snake_case convention for database columns

## Implementation Notes
(Left blank - filled in by programmer during implementation)
