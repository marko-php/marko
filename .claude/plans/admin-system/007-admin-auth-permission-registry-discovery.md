# Task 007: marko/admin-auth - PermissionRegistry and PermissionDiscovery

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Create the PermissionRegistry that collects all permissions declared by packages via `#[AdminPermission]` attributes. The PermissionDiscovery scans AdminSection classes for their `#[AdminPermission]` attributes and populates the registry. This is how the system knows what permissions exist across all installed packages.

## Context
- AdminPermission attributes are placed on AdminSection classes (from task 002)
- PermissionDiscovery works alongside AdminSectionDiscovery (task 004)
- PermissionRegistry stores all discovered permission definitions
- PermissionSyncer will later sync discovered permissions to the database (separate concern)
- Permissions support wildcard matching: `blog.*` matches `blog.posts.create`, `blog.posts.edit`, etc.
- Group is derived from the first segment of the permission key: `blog.posts.create` → group `blog`

## Requirements (Test Descriptions)
- [ ] `it registers permissions with key, label, and group`
- [ ] `it retrieves all registered permissions`
- [ ] `it retrieves permissions filtered by group`
- [ ] `it throws AdminAuthException when registering duplicate permission key`
- [ ] `it discovers permissions from AdminPermission attributes on AdminSection classes`
- [ ] `it derives group from first segment of permission key`
- [ ] `it supports wildcard permission matching with asterisk`
- [ ] `it matches blog.* against blog.posts.create`
- [ ] `it does not match blog.* against analytics.reports.view`
- [ ] `it matches single asterisk against any permission`

## Acceptance Criteria
- All requirements have passing tests
- Discovery follows existing discovery patterns
- Wildcard matching is clean and well-tested
- Code follows code standards
