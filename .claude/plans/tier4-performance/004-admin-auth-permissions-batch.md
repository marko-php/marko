# Task 004: F3 — Batch permission loading for all roles in one query

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`AdminUserProvider::loadRolesAndPermissions` runs on every authenticated admin request (via `retrieveById`, `retrieveByCredentials`, and `retrieveByRememberToken` — NOT `retrieveByToken`, that method does not exist) and loops `roleRepository->getPermissionsForRole($role->id)` once per role — N permission queries for a user with N roles. Add a batch method that fetches the permission set for all role ids in a single `WHERE rp.role_id IN (...)` query, and rewire the provider to use it.

## Context
- Related files:
  - `packages/admin-auth/src/AdminUserProvider.php` (`loadRolesAndPermissions` ~109-132 — the per-role loop; `readonly class`)
  - `packages/admin-auth/src/Repository/RoleRepositoryInterface.php` (`getPermissionsForRole(int): array` — add `getPermissionsForRoles`)
  - `packages/admin-auth/src/Repository/RoleRepository.php` (`getPermissionsForRole` ~100-119 — INNER JOIN `role_permissions rp` ... `WHERE rp.role_id = ?`; mirror its hydration via `metadataFactory->parse(Permission::class)`. Note it declares `@throws EntityException` because `hydrator->hydrate()` throws it.)
  - `packages/admin-auth/tests/Unit/AdminUserProviderTest.php` (`createMockRoleRepo` ~315 is a `readonly class implements RoleRepositoryInterface` — MUST add `getPermissionsForRoles` or the suite fatals, and the class must remain `readonly`; it takes a `$permissionsMap` keyed by role id, so the batch version returns the deduplicated union across the requested ids. This is the only in-repo anonymous implementer of the interface besides `RoleRepository`.)
  - `packages/admin-auth/tests/Unit/Repository/RoleRepositoryTest.php` (`createRoleMockConnectionWithHistory` ~191 records `['sql','bindings']` per `query()`/`execute()`, already implements `driverName()` at line 256, and returns the SAME canned `$queryResult` for EVERY `query()` call — construct the permission rows accordingly when asserting the one-query / dedup behavior)
  - `packages/admin-auth/tests/Unit/Repository/RoleRepositoryInterfaceTest.php` (~line 19 reflection method-presence checks; extend `$expectedMethods` with `getPermissionsForRoles` — also update the test title at line 12 if it enumerates method names)
- Patterns to follow:
  - Add `RoleRepositoryInterface::getPermissionsForRoles(array $roleIds): array` (`@param array<int> $roleIds`, `@throws EntityException`) returning `array<Permission>` (deduplicated across roles), implemented with one `SELECT DISTINCT p.* FROM permissions p INNER JOIN role_permissions rp ON p.id = rp.permission_id WHERE rp.role_id IN (?, ?, ...)` where the placeholder count matches the role-id count.
  - Because `getPermissionsForRoles` hydrates Permissions, it throws `EntityException`. That propagates through `loadRolesAndPermissions` into `AdminUserProvider::retrieveById`/`retrieveByCredentials`/`retrieveByRememberToken` — add `@throws EntityException` to `getPermissionsForRoles` (interface + impl), `loadRolesAndPermissions`, and those three provider methods per code-standards rule 9. Any NEW inline anonymous `ConnectionInterface` stub must implement `driverName(): string`.
  - **Empty `$roleIds` MUST short-circuit to `[]` with no query** — never emit `WHERE rp.role_id IN ()`, which is a SQL syntax error. Build the IN placeholder list from the (non-empty) role-id array and bind each id positionally.
  - `loadRolesAndPermissions` collects non-null role ids, calls `getPermissionsForRoles` once, and passes `array_unique` permission keys to `setRoles` exactly as before. Behavior must be identical for a user with zero roles (no permissions query) and for roles sharing permissions (deduped key set).
  - **Update every in-repo implementer of `RoleRepositoryInterface`** (`RoleRepository` + the `createMockRoleRepo` anonymous class) to satisfy the new method before running the suite.
  - Query-count assertions: `createRoleMockConnectionWithHistory` records each `query()` call; assert exactly one permissions query for N roles, that its SQL contains `IN (` with N placeholders, and that the resulting permission-key set is identical to the per-role loop.

## Requirements (Test Descriptions)
- [x] `it loads the same permission set as the per-role implementation`
- [x] `it deduplicates permission keys shared across roles`
- [x] `it issues exactly one permissions query for a user with multiple roles`
- [x] `it issues no permissions query when the user has no roles`
- [x] `it returns an empty array from getPermissionsForRoles without querying when given no role ids`
- [x] `it queries permissions with a WHERE role_id IN clause whose placeholder count matches the role ids`
- [x] `it sets roles and unique permission keys on the authenticated user`
- [x] `it defines getPermissionsForRoles on RoleRepositoryInterface`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `getPermissionsForRoles(array $roleIds): array` to `RoleRepositoryInterface` with `@throws EntityException` and `@param array<int> $roleIds`
- Implemented in `RoleRepository` with `SELECT DISTINCT p.* FROM permissions p INNER JOIN role_permissions rp ON p.id = rp.permission_id WHERE rp.role_id IN (?, ...)` — placeholders built from count of role ids
- Empty `$roleIds` short-circuits to `[]` with no query issued (SQL syntax error protection)
- Rewired `AdminUserProvider::loadRolesAndPermissions` to collect non-null role ids and call `getPermissionsForRoles` once; `array_unique` on permission keys preserved
- Added `@throws EntityException` to `loadRolesAndPermissions`, `retrieveById`, `retrieveByCredentials`, `retrieveByRememberToken`
- Added `getPermissionsForRoles` to `createMockRoleRepo` anonymous readonly class in `AdminUserProviderTest` — returns deduplicated union across the requested ids
- Added `TrackingRoleRepo` named class in `AdminUserProviderTest` with `getPermissionsForRole` throwing (to assert it is never called) and `batchCallCount`/`batchRoleIdsReceived` tracking
- Updated `$expectedMethods` list in `RoleRepositoryInterfaceTest` to include `getPermissionsForRoles`; test title left unchanged (it does not enumerate all method names)
