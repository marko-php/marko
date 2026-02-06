# Task 008: marko/admin-auth - RoleRepository and PermissionRepository

**Status**: complete
**Depends on**: 005, 006
**Retry count**: 0

## Description
Create repository interfaces and implementations for Role, Permission, and AdminUser entities. These provide CRUD operations and relationship loading (roles with permissions, users with roles).

## Context
- Follow repository patterns from blog: interface + implementation extending `Repository`
- RoleRepository needs: find, findBySlug, all, save, delete, getPermissionsForRole, syncPermissions
- PermissionRepository needs: find, findByKey, all, findByGroup, save, delete, syncFromRegistry
- AdminUserRepository needs: find, findByEmail, all, save, delete, getRolesForUser, syncRoles, isSlugUnique (for roles)
- `syncFromRegistry` on PermissionRepository is how declared permissions get written to DB
- Dispatch events: RoleCreated, RoleUpdated, RoleDeleted, AdminUserCreated, AdminUserUpdated

## Requirements (Test Descriptions)
- [x] `it creates RoleRepositoryInterface with find, findBySlug, all, save, delete methods`
- [x] `it creates RoleRepository extending Repository`
- [x] `it loads permissions for a role via getPermissionsForRole`
- [x] `it syncs permissions for a role via syncPermissions`
- [x] `it creates PermissionRepositoryInterface with find, findByKey, all, findByGroup methods`
- [x] `it creates PermissionRepository extending Repository`
- [x] `it syncs permissions from registry to database creating new and preserving existing`
- [x] `it creates AdminUserRepositoryInterface with find, findByEmail, all, save, delete methods`
- [x] `it creates AdminUserRepository extending Repository`
- [x] `it loads roles for a user via getRolesForUser`
- [x] `it syncs roles for a user via syncRoles`
- [x] `it dispatches events on role and user create/update/delete`

## Acceptance Criteria
- All requirements have passing tests
- Repository patterns match blog repositories exactly
- Interface/implementation split is clean
- Code follows code standards
