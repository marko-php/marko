# Task 001: Add camelToSnakeCase and Update EntityMetadataFactory

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add a private `camelToSnakeCase(string $name): string` method to `EntityMetadataFactory` and update the `parse()` method to auto-convert property names to snake_case when no explicit `name:` is provided on the `#[Column]` attribute.

## Context
- Key file: `packages/database/src/Entity/EntityMetadataFactory.php`
- Line 68 is the critical change: `$columnName = $columnAttr->name ?? $propertyName;` becomes `$columnName = $columnAttr->name ?? $this->camelToSnakeCase($propertyName);`
- Test file: `packages/database/tests/Entity/EntityMetadataFactoryTest.php`
- The existing test "uses Column attribute name when specified" (line 172) tests explicit `name:` override — it should still pass
- Single-word properties like `$id`, `$name`, `$email` should be unaffected (already lowercase)

## Requirements (Test Descriptions)
- [ ] `it converts camelCase property names to snake_case column names automatically`
- [ ] `it preserves explicit Column name override when specified`
- [ ] `it handles single-word property names without change`
- [ ] `it handles consecutive uppercase letters correctly (userID becomes user_id)`
- [ ] `it handles leading uppercase sequences correctly (HTMLParser becomes html_parser)`
- [ ] `it updates the existing override test to use a genuinely custom name`

## Acceptance Criteria
- All requirements have passing tests
- `camelToSnakeCase` handles: `postId` -> `post_id`, `createdAt` -> `created_at`, `id` -> `id`, `HTMLParser` -> `html_parser`, `userID` -> `user_id`, `isActive` -> `is_active`
- Explicit `#[Column(name: 'custom')]` still takes priority over auto-conversion
- Existing EntityMetadataFactoryTest tests still pass (some may need column name assertion updates)
- The existing "uses Column attribute name when specified" test (line 172) MUST be updated to use a genuinely custom name that differs from what auto-conversion would produce (e.g., `#[Column(name: 'author')]` on `$userId` instead of `#[Column(name: 'user_id')]`), otherwise the test becomes a no-op that passes whether or not the override works

## Implementation Notes
(Left blank - filled in by programmer during implementation)
