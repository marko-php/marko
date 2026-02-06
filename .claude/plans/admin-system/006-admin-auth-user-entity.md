# Task 006: marko/admin-auth - AdminUser Entity with AuthenticatableInterface

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Create the AdminUser entity that implements `AuthenticatableInterface` from the auth package. This is the same user table concept - admin users are regular users with roles assigned. The entity includes role relationship loading and permission checking.

## Context
- Related files: `packages/auth/src/AuthenticatableInterface.php`
- AdminUser implements `AuthenticatableInterface` so it works with existing guards
- Table: `admin_users` with id, email, password, name, remember_token, is_active, created_at, updated_at
- Junction table: `admin_user_roles` (user_id, role_id)
- AdminUser has methods to check permissions via loaded roles
- `hasPermission(string $key): bool` - checks if any role grants the permission (or if user has super_admin role)
- `hasRole(string $slug): bool` - checks if user has a specific role
- `getRoles(): array` - returns loaded roles

## Requirements (Test Descriptions)
- [ ] `it creates AdminUser entity implementing AuthenticatableInterface`
- [ ] `it has email, password, name, rememberToken, isActive properties`
- [ ] `it returns auth identifier as id`
- [ ] `it returns auth identifier name as id`
- [ ] `it returns auth password from password property`
- [ ] `it supports remember token get and set`
- [ ] `it creates migration for admin_users table with correct columns and indexes`
- [ ] `it creates migration for admin_user_roles junction table`
- [ ] `it checks hasPermission against loaded roles`
- [ ] `it returns true for any permission when user has super admin role`
- [ ] `it checks hasRole by role slug`
- [ ] `it returns false for hasPermission when no roles loaded`

## Acceptance Criteria
- All requirements have passing tests
- AdminUser implements AuthenticatableInterface correctly
- Entity patterns match existing blog entities
- Code follows code standards
