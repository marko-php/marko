# Task 011: marko/admin-auth - AdminAuthConfig and module.php Bindings

**Status**: pending
**Depends on**: 009, 010
**Retry count**: 0

## Description
Create the AdminAuthConfig class and complete the module.php with all DI bindings for the admin-auth package. Configure the admin guard in auth config. Create events for role/user lifecycle. Wire everything together.

## Context
- AdminAuthConfig wraps ConfigRepositoryInterface for admin-auth settings
- Config keys: `admin-auth.guard` (default: `'admin'`), `admin-auth.super_admin_role` (default: `'super-admin'`)
- module.php binds all interfaces to implementations
- Auth config needs an `admin` guard entry: `['driver' => 'session', 'provider' => 'admin-users']`
- Events follow blog event patterns: RoleCreated, RoleUpdated, RoleDeleted, AdminUserCreated, AdminUserUpdated, AdminUserDeleted, PermissionsSynced

## Requirements (Test Descriptions)
- [ ] `it creates AdminAuthConfig with guard name and super admin role slug`
- [ ] `it binds AdminUserRepositoryInterface to AdminUserRepository in module.php`
- [ ] `it binds RoleRepositoryInterface to RoleRepository in module.php`
- [ ] `it binds PermissionRepositoryInterface to PermissionRepository in module.php`
- [ ] `it binds AdminUserProvider as factory with password hasher dependency in module.php`
- [ ] `it creates RoleCreated, RoleUpdated, RoleDeleted events`
- [ ] `it creates AdminUserCreated, AdminUserUpdated, AdminUserDeleted events`
- [ ] `it creates PermissionsSynced event dispatched after registry sync`
- [ ] `it has valid config/admin-auth.php with default values`
- [ ] `it has module.php with all required bindings`

## Acceptance Criteria
- All requirements have passing tests
- Config follows existing config patterns
- Events follow existing event patterns
- module.php bindings are complete and correct
- Code follows code standards
