# Task 004: F3 — Batch permission loading for all roles in one query

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`AdminUserProvider::loadRolesAndPermissions` runs on every authenticated admin request (via `retrieveById`, `retrieveByCredentials`, `retrieveByToken`) and loops `roleRepository->getPermissionsForRole($role->id)` once per role — N permission queries for a user with N roles. Add a batch method that fetches the permission set for all role ids in a single `WHERE rp.role_id IN (...)` query, and rewire the provider to use it.

## Context
- Related files:
  - `packages/admin-auth/src/AdminUserProvider.php` (`loadRolesAndPermissions` ~109-132 — the per-role loop; `readonly class`)
  - `packages/admin-auth/src/Repository/RoleRepositoryInterface.php` (`getPermissionsForRole(int): array` — add `getPermissionsForRoles`)
  - `packages/admin-auth/src/Repository/RoleRepository.php` (`getPermissionsForRole` ~100-119 — INNER JOIN `role_permissions rp` ... `WHERE rp.role_id = ?`; mirror its hydration via `metadataFactory->parse(Permission::class)`)
  - `packages/admin-auth/tests/Unit/AdminUserProviderTest.php` (`createMockRoleRepo` ~315 is a `readonly class implements RoleRepositoryInterface` — MUST add `getPermissionsForRoles` or the suite fatals; it takes a `$permissionsMap` keyed by role id, so the batch version should return the union across the requested ids)
  - `packages/admin-auth/tests/Unit/Repository/RoleRepositoryTest.php` (`createRoleMockConnectionWithHistory` ~191 records `['sql','bindings']` per `query()`/`execute()` — use this to assert one permissions query)
  - `packages/admin-auth/tests/Unit/Repository/RoleRepositoryInterfaceTest.php` (~line 19 reflection method-presence checks; extend `$expectedMethods` with `getPermissionsForRoles`)
- Patterns to follow:
  - Add `RoleRepositoryInterface::getPermissionsForRoles(array $roleIds): array` returning `array<Permission>` (deduplicated across roles), implemented with one `SELECT DISTINCT p.* FROM permissions p INNER JOIN role_permissions rp ON p.id = rp.permission_id WHERE rp.role_id IN (?, ?, ...)` where the placeholder count matches the role-id count.
  - **Empty `$roleIds` MUST short-circuit to `[]` with no query** — never emit `WHERE rp.role_id IN ()`, which is a SQL syntax error. Build the IN placeholder list from the (non-empty) role-id array and bind each id positionally.
  - `loadRolesAndPermissions` collects non-null role ids, calls `getPermissionsForRoles` once, and passes `array_unique` permission keys to `setRoles` exactly as before. Behavior must be identical for a user with zero roles (no permissions query) and for roles sharing permissions (deduped key set).
  - **Update every in-repo implementer of `RoleRepositoryInterface`** (`RoleRepository` + the `createMockRoleRepo` anonymous class) to satisfy the new method before running the suite.
  - Query-count assertions: `createRoleMockConnectionWithHistory` records each `query()` call; assert exactly one permissions query for N roles, that its SQL contains `IN (` with N placeholders, and that the resulting permission-key set is identical to the per-role loop.

## Requirements (Test Descriptions)
- [ ] `it loads the same permission set as the per-role implementation`
- [ ] `it deduplicates permission keys shared across roles`
- [ ] `it issues exactly one permissions query for a user with multiple roles`
- [ ] `it issues no permissions query when the user has no roles`
- [ ] `it returns an empty array from getPermissionsForRoles without querying when given no role ids`
- [ ] `it queries permissions with a WHERE role_id IN clause whose placeholder count matches the role ids`
- [ ] `it sets roles and unique permission keys on the authenticated user`
- [ ] `it defines getPermissionsForRoles on RoleRepositoryInterface`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
