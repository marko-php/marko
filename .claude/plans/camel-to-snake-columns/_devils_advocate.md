# Devil's Advocate Review: camel-to-snake-columns

## Critical (Must fix before building)

### C1. Task 005 missing authentication-token and media test updates (will break tests)

Task 005 lists updating admin-auth entity tests that check `$columnAttr->name` via reflection, but completely omits that `packages/authentication-token/tests/Entity/PersonalAccessTokenTest.php` and `packages/media/tests/Entity/MediaTest.php` have the exact same pattern. After removing explicit `name:` parameters from those entities, these tests will fail because `$column->name` becomes `null`.

**authentication-token test assertions that will break (6 assertions):**
- Line 38: `$tokenableTypeColumn->name->toBe('tokenable_type')`
- Line 45: `$tokenableIdColumn->name->toBe('tokenable_id')`
- Line 57: `$tokenHashColumn->name->toBe('token_hash')`
- Line 72: `$lastUsedAtColumn->name->toBe('last_used_at')`
- Line 79: `$expiresAtColumn->name->toBe('expires_at')`
- Line 86: `$createdAtColumn->name->toBe('created_at')`

**media test assertions that will break (4 assertions):**
- Line 45: `$originalFilenameColumn->name->toBe('original_filename')`
- Line 53: `$mimeTypeColumn->name->toBe('mime_type')`
- Line 88: `$createdAtColumn->name->toBe('created_at')`
- Line 96: `$updatedAtColumn->name->toBe('updated_at')`

**Fix:** Add these test files to task 005's scope with explicit instructions to update all `->name` assertions to `->toBeNull()` or remove them.

### C2. Task 004 massively underestimates scope (23 occurrences of `isActive` in RepositoryTest.php)

Task 004 says "search the file for `isActive` and update all column-name references to `is_active`" but does not convey the actual scope: there are 23 occurrences of `isActive` in `RepositoryTest.php` across mock storage arrays, SQL string assertions, findBy criteria, and comments. Many of these are in mock `ConnectionInterface` implementations with hardcoded column-name keys in return arrays. The worker needs to understand that ALL mock data arrays using `'isActive'` as a key need updating to `'is_active'`, plus SQL string matches like `str_contains($setClause, 'isActive')`.

The task also doesn't mention that comment on line 188 (`// - 'isActive' maps to 'isActive' column (no explicit name, uses property name)`) needs updating.

**Fix:** Add explicit list of affected areas in task 004 context.

### C3. Task 005 is too large for a single worker (7 entity files + 5 test files across 5 packages)

Task 005 spans 7 production entity files across 5 packages (admin-auth, media, webhook, authentication-token, database) plus test files in admin-auth, authentication-token, media, and database. This is a lot of files with subtle per-file differences. A single TDD worker would struggle with the breadth.

**Fix:** Split task 005 into two tasks: one for production entity cleanup (mechanical removal of redundant `name:` parameters) and one for test updates (which requires understanding what each test asserts about `$column->name`).

## Important (Should fix before building)

### I1. Task 001 "uses Column attribute name when specified" test becomes a no-op

The existing test at `EntityMetadataFactoryTest.php` line 172 uses `#[Column(name: 'user_id')]` on `$userId`. After the change, `userId` auto-converts to `user_id`, making the explicit name redundant -- the test passes whether or not the override mechanism works. Task 005 mentions this needs reworking, but this should be done in task 001 since task 001 is the one that needs to verify the override actually functions.

**Fix:** Move the requirement to fix this test from task 005 to task 001. Task 001 should modify this test to use a genuinely custom name (e.g., `#[Column(name: 'author')]` on `$userId`) so it validates the override mechanism.

### I2. Task 003 line references may be inaccurate

Task 003 references specific line numbers (230, 237, 267, 289) in `EntityToMigrationWorkflowTest.php`. Verified against actual file: line 230 contains `->toContain('isActive')` and line 237 contains `->toContain('authorId')`. These are correct. However, line 267 contains `new SchemaColumn(name: 'isActive', type: 'BOOLEAN')` which is correct, and line 289 contains `->toContain('isActive')` which is correct. Good.

However, the task misses an additional reference: the `SchemaColumn` construction and the `$addedColumnNames` assertion -- these are in the "detects and generates migrations for entity changes" test which constructs `SchemaTable` objects manually. Those manual `SchemaColumn` constructions with `name: 'isActive'` also need updating to `name: 'is_active'` for test consistency (they represent the "entity-defined schema" which would now produce snake_case).

**Fix:** Add explicit note to task 003 about updating ALL manually constructed `SchemaColumn`/`SchemaTable` objects that represent entity-derived schema.

### I3. WebhookAttempt has constructor-promoted properties with #[Column]

`WebhookAttempt` has `#[Column(name: 'webhook_url')]` and `#[Column(name: 'attempt_number')]` on constructor-promoted properties (lines 30, 34). Task 005 correctly identifies these need cleanup, but the worker should verify that `EntityMetadataFactory::parse()` actually processes constructor-promoted properties via `$reflection->getProperties(ReflectionProperty::IS_PUBLIC)`. PHP's ReflectionClass does return promoted properties from `getProperties()`, so this should work, but it is worth noting.

### I4. Task 003 and 004 could cause confusion about RepositoryCrudTest vs RepositoryTest scope

Task 003 covers `RepositoryCrudTest.php` (feature test) while task 004 covers `RepositoryTest.php` (unit test). Both have `isActive`/`isAvailable` references. The task descriptions are clear about which files belong where, but a worker could accidentally edit the wrong file. No change needed, just noting the risk.

## Minor (Nice to address)

### M1. Notification-database entity has a pre-existing inconsistency that this change fixes

`DatabaseNotification` has bare `#[Column]` on `$notifiableType`, `$notifiableId`, `$readAt`, `$createdAt`. Currently these produce camelCase column names (`notifiableType`, etc.), but the `DatabaseNotificationRepository` uses handwritten SQL with snake_case column names (`notifiable_type`, etc.) and manual hydration mapping `$row['notifiable_type']` to `$notification->notifiableType`. This is a pre-existing bug/inconsistency. After this change, the metadata will align with the SQL. No action needed since the repository doesn't use EntityMetadataFactory for queries, but worth noting that this change accidentally fixes a latent issue.

### M2. ColumnAttributeTest fixtures have camelCase properties (userId) but tests the attribute directly

`packages/database/tests/Attributes/ColumnAttributeTest.php` has fixture entities like `ForeignKeyReferenceTestEntity` with `$userId`. These test the Column attribute class directly via reflection, not the factory, so they won't be affected. No changes needed.

### M3. Documentation task (006) should note that `#[Column('author_email')]` on `$authorEmail` IS redundant

Task 006 says "Keep explicit names that DON'T match auto-conversion (e.g., `#[Column('author_email')]` on `$authorEmail`)" but then correctly notes in the last paragraph that `authorEmail` auto-converts to `author_email`, so it IS redundant. The earlier statement is misleading.

## Questions for the Team

### Q1. Should the `camelToSnakeCase` method be a standalone utility or private to EntityMetadataFactory?

The plan puts it as a private method on `EntityMetadataFactory`. If other parts of the framework ever need the same conversion (e.g., for table name conventions), it would need to be duplicated. A utility class in `Marko\Database\Support\Str` or similar could be reusable. However, YAGNI applies -- making it private is fine for now.

### Q2. What about the `$primaryKey` field in EntityMetadata?

Line 90 of `EntityMetadataFactory::parse()` sets `$primaryKey = $propertyName` (not column name). This is the PHP property name, used for things like `EntityMetadata::getPrimaryKeyProperty()`. This is correct -- the primary key identifier should be the property name. But the plan should verify no code path uses `$metadata->primaryKey` as a column name.
