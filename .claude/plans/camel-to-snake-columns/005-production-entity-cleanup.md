# Task 005: Remove Redundant Names from Production Entities and Update Entity Tests

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Remove all explicit `#[Column(name: '...')]` parameters that are now redundant because the auto-conversion produces the same snake_case name. Update entity attribute tests in admin-auth, authentication-token, and media that check Column attribute `name` via reflection.

## Context
All explicit names in production entities match what the auto-conversion would produce. They are now unnecessary boilerplate.

**Entities to clean up:**

- `packages/admin-auth/src/Entity/AdminUser.php`:
  - `#[Column('remember_token')]` on `$rememberToken` → `#[Column]`
  - `#[Column('is_active', default: '1')]` on `$isActive` → `#[Column(default: '1')]`
  - `#[Column('created_at')]` on `$createdAt` → `#[Column]`
  - `#[Column('updated_at')]` on `$updatedAt` → `#[Column]`

- `packages/admin-auth/src/Entity/Role.php`:
  - `#[Column('is_super_admin', default: '0')]` on `$isSuperAdmin` → `#[Column(default: '0')]`
  - `#[Column('created_at')]` on `$createdAt` → `#[Column]`
  - `#[Column('updated_at')]` on `$updatedAt` → `#[Column]`

- `packages/admin-auth/src/Entity/Permission.php`:
  - `#[Column('created_at')]` on `$createdAt` → `#[Column]`

- `packages/admin-auth/src/Entity/RolePermission.php`:
  - `#[Column('role_id', references: 'roles.id', onDelete: 'CASCADE')]` on `$roleId` → `#[Column(references: 'roles.id', onDelete: 'CASCADE')]`
  - `#[Column('permission_id', references: 'permissions.id', onDelete: 'CASCADE')]` on `$permissionId` → `#[Column(references: 'permissions.id', onDelete: 'CASCADE')]`

- `packages/media/src/Entity/Media.php`:
  - `#[Column('original_filename', length: 255)]` on `$originalFilename` → `#[Column(length: 255)]`
  - `#[Column('mime_type', length: 100)]` on `$mimeType` → `#[Column(length: 100)]`
  - `#[Column('created_at')]` on `$createdAt` → `#[Column]`
  - `#[Column('updated_at')]` on `$updatedAt` → `#[Column]`

- `packages/media/src/Entity/MediaAttachment.php`:
  - `#[Column('media_id')]` on `$mediaId` → `#[Column]`
  - `#[Column('attachable_type', length: 255)]` on `$attachableType` → `#[Column(length: 255)]`
  - `#[Column('attachable_id', length: 255)]` on `$attachableId` → `#[Column(length: 255)]`

- `packages/webhook/src/Entity/WebhookAttempt.php`:
  - `#[Column(name: 'status_code')]` on `$statusCode` → `#[Column]`
  - `#[Column(name: 'response_body', type: 'TEXT')]` on `$responseBody` → `#[Column(type: 'TEXT')]`
  - `#[Column(name: 'error_message', type: 'TEXT')]` on `$errorMessage` → `#[Column(type: 'TEXT')]`
  - `#[Column(name: 'attempted_at')]` on `$attemptedAt` → `#[Column]`
  - `#[Column(name: 'webhook_url')]` on `$webhookUrl` → `#[Column]`
  - `#[Column(name: 'attempt_number')]` on `$attemptNumber` → `#[Column]`

- `packages/authentication-token/src/Entity/PersonalAccessToken.php`:
  - `#[Column('tokenable_type')]` on `$tokenableType` → `#[Column]`
  - `#[Column('tokenable_id')]` on `$tokenableId` → `#[Column]`
  - `#[Column('token_hash', length: 64)]` on `$tokenHash` → `#[Column(length: 64)]`
  - `#[Column('last_used_at')]` on `$lastUsedAt` → `#[Column]`
  - `#[Column('expires_at')]` on `$expiresAt` → `#[Column]`
  - `#[Column('created_at')]` on `$createdAt` → `#[Column]`

- `packages/database/tests/Entity/EntityMetadataFactoryTest.php`:
  - Line 175: `#[Column(name: 'user_id')]` on `$userId` — this is now redundant. Remove `name:` parameter.
  - NOTE: The "uses Column attribute name when specified" test reworking is handled in task 001.

- `packages/database/tests/Entity/EntityHydratorTest.php`:
  - Line 25: `#[Column('email_address')]` on `$email` — NOT redundant (`email` -> `email`, not `email_address`). Keep as-is.

- `packages/database/tests/Repository/RepositoryTest.php`:
  - Line 32: `#[Column('email_address')]` on `$email` — NOT redundant. Keep as-is.

**Tests to update (check Column attribute `name` via reflection):**

- `packages/admin-auth/tests/Unit/Entity/AdminUserTest.php`:
  - Line 67: `expect($rememberTokenColumn->name)->toBe('remember_token')` — after removal, `$columnAttr->name` will be `null`. Update test to check `null` or remove assertion (the factory handles the conversion, not the attribute).
  - Line 72: `expect($isActiveColumn->name)->toBe('is_active')` — same issue. After removal of explicit name from `#[Column(default: '1')]`, `name` is `null`.

- `packages/admin-auth/tests/Unit/Entity/PermissionTest.php`:
  - Line 117: `expect($columnAttribute->name)->toBe('created_at')` — same issue.

- `packages/admin-auth/tests/Unit/Entity/RoleTest.php`:
  - Lines 151, 165: `expect($columnAttribute->name)->toBe('created_at')` and `'updated_at'` — same issue.

- `packages/authentication-token/tests/Entity/PersonalAccessTokenTest.php`:
  - Line 38: `$tokenableTypeColumn->name->toBe('tokenable_type')` — will be `null` after removal. Update to `->toBeNull()` or remove.
  - Line 45: `$tokenableIdColumn->name->toBe('tokenable_id')` — same issue.
  - Line 57: `$tokenHashColumn->name->toBe('token_hash')` — same issue.
  - Line 72: `$lastUsedAtColumn->name->toBe('last_used_at')` — same issue.
  - Line 79: `$expiresAtColumn->name->toBe('expires_at')` — same issue.
  - Line 86: `$createdAtColumn->name->toBe('created_at')` — same issue.

- `packages/media/tests/Entity/MediaTest.php`:
  - Line 45: `$originalFilenameColumn->name->toBe('original_filename')` — will be `null` after removal. Update to `->toBeNull()` or remove.
  - Line 53: `$mimeTypeColumn->name->toBe('mime_type')` — same issue.
  - Line 88: `$createdAtColumn->name->toBe('created_at')` — same issue.
  - Line 96: `$updatedAtColumn->name->toBe('updated_at')` — same issue.

## Requirements (Test Descriptions)
- [ ] `it removes redundant explicit Column name from admin-auth entities`
- [ ] `it removes redundant explicit Column name from media entities`
- [ ] `it removes redundant explicit Column name from webhook entity`
- [ ] `it removes redundant explicit Column name from authentication-token entity`
- [ ] `it updates admin-auth entity tests to not assert explicit Column attribute name`
- [ ] `it updates authentication-token entity test to not assert explicit Column attribute name`
- [ ] `it updates media entity test to not assert explicit Column attribute name`
- [ ] `it passes all tests across affected packages after cleanup`

## Acceptance Criteria
- No redundant explicit `name:` parameters remain in production entity `#[Column]` attributes
- Admin-auth entity tests updated to reflect that Column attribute `name` is now `null` (auto-derived by factory)
- Authentication-token entity test updated to reflect that Column attribute `name` is now `null` (auto-derived by factory)
- Media entity test updated to reflect that Column attribute `name` is now `null` (auto-derived by factory)
- All tests pass across: database, admin-auth, media, webhook, authentication-token, notification-database packages

## Implementation Notes
(Left blank - filled in by programmer during implementation)
