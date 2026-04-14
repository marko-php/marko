# Task 003: Update Database Package Feature Tests

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update feature tests in the database package that use inline entity classes with camelCase properties. After the core change, these properties produce snake_case column names, breaking assertions that reference the old camelCase column names.

## Context
- `packages/database/tests/Feature/EntityToMigrationWorkflowTest.php`:
  - `WorkflowUser` has `$isActive` with bare `#[Column]` — column becomes `is_active` instead of `isActive`
  - `WorkflowPost` has `$authorId` with bare `#[Column]` — column becomes `author_id` instead of `authorId`
  - Line 230: `->toContain('isActive')` must become `->toContain('is_active')`
  - Line 237: `->toContain('authorId')` must become `->toContain('author_id')`
  - Line 267: `new SchemaColumn(name: 'isActive', type: 'BOOLEAN')` must become `name: 'is_active'`
  - Line 289: `->toContain('isActive')` must become `->toContain('is_active')`
  - IMPORTANT: Search the entire file for ALL `SchemaColumn` and `SchemaTable` constructions that represent entity-derived schema. Any manually constructed `SchemaColumn` with a camelCase `name:` that represents what the entity would produce must also be updated (e.g., `name: 'isActive'` -> `name: 'is_active'`, `name: 'authorId'` -> `name: 'author_id'`). The "detects and generates migrations" test constructs expected schema objects that must match the new auto-converted names.

- `packages/database/tests/Feature/RepositoryCrudTest.php`:
  - `CrudProduct` has `$isAvailable` with bare `#[Column]` — column becomes `is_available` instead of `isAvailable`
  - Line 107: Mock storage uses `'isAvailable'` key — must become `'is_available'`
  - Lines 203-205: Mock storage arrays use `'isAvailable'` key — must become `'is_available'`
  - Line 228: `str_contains($sql, 'isAvailable = ?')` must become `str_contains($sql, 'is_available = ?')`
  - Line 370: Mock storage uses `'isAvailable'` key — must become `'is_available'`

## Requirements (Test Descriptions)
- [ ] `it uses snake_case column names in entity-to-migration workflow assertions`
- [ ] `it uses snake_case column names in SchemaColumn construction for diff tests`
- [ ] `it uses snake_case column names in mock storage arrays for CRUD tests`
- [ ] `it uses snake_case column names in SQL string matching for findBy tests`
- [ ] `it passes all feature tests after column name updates`

## Acceptance Criteria
- All tests in `packages/database/tests/Feature/` pass
- All `isActive` column references updated to `is_active`
- All `authorId` column references updated to `author_id`
- All `isAvailable` column references updated to `is_available`
- Mock connection storage arrays use snake_case keys for column names

## Implementation Notes
(Left blank - filled in by programmer during implementation)
