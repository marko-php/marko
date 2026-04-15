# Task 002: Update Database Package Unit Tests

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update unit tests in the database package that assert column names from parsed metadata. The core change causes camelCase property names to produce snake_case column names, so assertions checking column names need updating.

## Context
- `packages/database/tests/Entity/EntityMetadataFactoryTest.php` — Some test entities use camelCase properties without explicit names. The "extracts #[Column] attributes" test uses `$title`, `$content` (single-word, no change). The "extracts foreign key reference" test uses `$userId` without explicit name — its column will now be `user_id` instead of `userId`. But this test only checks `references`/`onDelete`/`onUpdate`, not the column name, so it should still pass.
- `packages/database/tests/Entity/SchemaBuilderTest.php` — The "builds ForeignKey objects from column references" test (line 138) expects FK name `fk_posts_userId` and columns `['userId']`. After the change, `$userId` (no explicit name) auto-converts to `user_id`, so expect `fk_posts_user_id` and `['user_id']`. The "preserves foreign key references" test (line 119) doesn't check column name directly — only references/onDelete/onUpdate — so it should pass.
- `packages/database/tests/Entity/EntityMetadataTest.php` — Manually constructs PropertyMetadata/ColumnMetadata, doesn't use the factory. No changes needed.
- `packages/database/tests/Entity/EntityHydratorTest.php` — Manually constructs metadata with snake_case column names. No changes needed.
- `packages/database/tests/Attributes/ColumnAttributeTest.php` — Tests the Column attribute class directly, not the factory. No changes needed.
- `packages/database/tests/Entity/EntityDiscoveryTest.php` — Uses `$id` only. No changes.
- `packages/database/tests/Schema/SchemaRegistryTest.php` — Uses single-word properties only. No changes.

## Requirements (Test Descriptions)
- [ ] `it generates FK name using snake_case column name (fk_posts_user_id)`
- [ ] `it uses snake_case column names in FK column arrays`
- [ ] `it preserves column name assertions for single-word properties`
- [ ] `it passes all existing EntityMetadataFactory tests after column name conversion`

## Acceptance Criteria
- All tests in `packages/database/tests/Entity/` pass
- All tests in `packages/database/tests/Schema/` pass
- All tests in `packages/database/tests/Attributes/` pass
- FK name in SchemaBuilderTest updated from `fk_posts_userId` to `fk_posts_user_id`
- FK columns in SchemaBuilderTest updated from `['userId']` to `['user_id']`

## Implementation Notes
(Left blank - filled in by programmer during implementation)
