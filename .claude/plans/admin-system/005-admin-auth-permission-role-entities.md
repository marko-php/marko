# Task 005: marko/admin-auth - Permission and Role Entities, Migrations

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Create the `marko/admin-auth` package with Permission and Role entities, their interfaces, and database migrations. Permissions are resource-based strings (e.g., `blog.posts.create`). Roles group permissions together (e.g., `editor` role has `blog.posts.create`, `blog.posts.edit`).

## Context
- New package at `packages/admin-auth/`
- Namespace: `Marko\AdminAuth`
- Depends on: `marko/admin`, `marko/auth`, `marko/database`
- Follow entity patterns from blog: `#[Table]`, `#[Column]`, `#[Index]`, `#[ForeignKey]` attributes
- Tables: `roles`, `permissions`, `role_permissions` (junction)
- Permissions are strings declared by packages via `#[AdminPermission]` attributes and stored in DB for assignment
- Roles have: id, name, slug, description, is_super_admin (boolean), created_at, updated_at
- Permissions have: id, key (unique string like `blog.posts.create`), label, group (e.g., `blog`), created_at
- role_permissions: role_id, permission_id (junction, unique constraint)

## Requirements (Test Descriptions)
- [ ] `it creates Role entity with id, name, slug, description, isSuperAdmin, createdAt, updatedAt`
- [ ] `it creates RoleInterface with getter methods`
- [ ] `it creates Permission entity with id, key, label, group, createdAt`
- [ ] `it creates PermissionInterface with getter methods`
- [ ] `it creates RolePermission junction entity with roleId and permissionId`
- [ ] `it creates migration for roles table with correct columns and indexes`
- [ ] `it creates migration for permissions table with unique key column`
- [ ] `it creates migration for role_permissions junction table with unique constraint`
- [ ] `it has valid composer.json with admin, auth, and database dependencies`
- [ ] `it marks super admin role via isSuperAdmin boolean flag`

## Acceptance Criteria
- All requirements have passing tests
- Entity patterns match blog entities exactly
- Migrations follow blog migration patterns
- Code follows code standards
