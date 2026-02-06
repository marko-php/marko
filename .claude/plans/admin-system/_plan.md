# Plan: Admin System

## Created
2026-02-06

## Status
in_progress

## Objective
Build a modular, extensible admin system consisting of four new packages (`marko/admin`, `marko/admin-auth`, `marko/admin-panel`, `marko/admin-api`) and integrate blog admin functionality into the existing `marko/blog` package.

## Scope

### In Scope
- `marko/admin` - Contract layer: interfaces for admin sections, menus, dashboard widgets, permission declarations, admin route prefix configuration
- `marko/admin-auth` - Authorization layer: resource-based permissions, roles, role-permission assignment, admin guard configuration, AdminAuthMiddleware, user entity with role support, user provider, migrations for roles/permissions/user tables
- `marko/admin-panel` - Server-rendered admin UI: Latte templates, layout with sidebar navigation, dashboard page, login/logout pages, discovers all AdminSectionInterface implementations
- `marko/admin-api` - JSON API: RESTful endpoints exposing admin sections, permission-protected, coexists with admin-panel independently
- Blog admin integration - CRUD controllers for posts, authors, categories, tags, comments within the blog package, registered as an AdminSection
- Fix SessionGuard to use guard-scoped session keys (currently hardcoded `auth_user_id` causes conflicts with multiple session guards)

### Out of Scope
- Multi-tenancy/scope-aware permissions
- JavaScript frontend (Alpine.js, htmx, etc.)
- Admin dashboard widgets with real data (placeholder structure only - real widgets come when analytics/commerce arrive)
- Password reset flows
- Two-factor authentication
- Admin user self-registration
- Admin panel theming/skin system

## Success Criteria
- [ ] All four admin packages have complete test coverage
- [ ] Blog admin CRUD operations work through both panel and API
- [ ] Multiple packages can register AdminSections independently
- [ ] admin-panel and admin-api can coexist simultaneously
- [ ] admin-panel can be swapped via Preference without affecting section registrations
- [ ] Permissions are resource-based and composable
- [ ] SessionGuard supports guard-scoped session keys for multi-guard setups
- [ ] All tests passing
- [ ] Code follows project standards (strict types, constructor promotion, no final, no traits)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Fix SessionGuard guard-scoped session keys | - | completed |
| 002 | marko/admin contracts - AdminSectionInterface, menu, permissions | - | completed |
| 003 | marko/admin config and route prefix | 002 | completed |
| 004 | marko/admin discovery - AdminSectionDiscovery for attribute scanning | 002 | completed |
| 005 | marko/admin-auth - Permission and Role entities, migrations | 002 | completed |
| 006 | marko/admin-auth - User entity with AuthenticatableInterface + roles | 005 | completed |
| 007 | marko/admin-auth - PermissionRegistry and PermissionDiscovery | 005 | completed |
| 008 | marko/admin-auth - RoleRepository and PermissionRepository | 005, 006 | completed |
| 009 | marko/admin-auth - AdminUserProvider implementing UserProviderInterface | 006, 008 | completed |
| 010 | marko/admin-auth - AdminAuthMiddleware and RequiresPermission attribute | 007, 009 | completed |
| 011 | marko/admin-auth - AdminAuthConfig and module.php bindings | 009, 010 | completed |
| 012 | marko/admin-panel - Package skeleton, composer.json, AdminPanelConfig | 003 | completed |
| 013 | marko/admin-panel - Layout template and Latte views (base, login, dashboard) | 012 | completed |
| 014 | marko/admin-panel - AdminPanelController (dashboard, login, logout) | 011, 013 | completed |
| 015 | marko/admin-panel - Menu building and section rendering | 004, 014 | completed |
| 016 | marko/admin-api - Package skeleton, ApiResponse helpers | 003 | completed |
| 017 | marko/admin-api - AdminApiController (sections list, section detail) | 011, 016 | completed |
| 018 | marko/admin-api - API route registration and auth integration | 017 | completed |
| 019 | marko/blog admin - BlogAdminSection registration and permissions | 004, 007 | completed |
| 020 | marko/blog admin - PostAdminController (list, create, edit, delete) | 010, 019 | completed |
| 021 | marko/blog admin - Author/Category/Tag/Comment admin controllers | 020 | pending |
| 022 | marko/blog admin - Post admin Latte templates | 013, 020 | completed |
| 023 | marko/blog admin - Remaining admin Latte templates | 022 | pending |
| 024 | marko/blog admin - Blog admin API controllers | 018, 019 | pending |

## Architecture Notes

### Package Dependency Graph
```
marko/admin (contracts)
├── marko/admin-auth  → depends on: admin, auth, database
├── marko/admin-panel → depends on: admin, view
├── marko/admin-api   → depends on: admin, routing
└── marko/blog        → depends on: admin (for section registration)
```

### Key Design Decisions

1. **Guard-scoped session keys**: SessionGuard currently uses `auth_user_id`. Must change to `auth_{guardName}_user_id` so admin and web guards don't collide. This is a prerequisite fix.

2. **Permission model**: Resource-based strings like `blog.posts.create`. Declared via `#[AdminPermission]` attributes on AdminSection classes. Roles group permissions. Checked via `#[RequiresPermission]` attribute on controller methods + `AdminAuthMiddleware`.

3. **Section discovery**: `#[AdminSection]` attribute on classes implementing `AdminSectionInterface`. Discovered by scanning modules just like plugins/observers. Both admin-panel and admin-api consume these sections.

4. **Template resolution**: Admin panel templates use `admin-panel::` prefix. Blog admin templates use `blog::admin/` path. View module priority (vendor < modules < app) enables template overrides.

5. **API authentication**: Uses existing `TokenGuard` via auth package. Admin API configures a separate `admin-api` guard in auth config.

6. **Simultaneous panel + API**: Both packages independently consume `marko/admin` contracts. Neither knows about the other. They register their own routes and middleware.

### Session Key Fix Impact
The SessionGuard session key change from `auth_user_id` to `auth_{name}_user_id` is technically breaking. However, since there are no production deployments yet, this is safe. Tests will need updating.

## Risks & Mitigations
- **Session key change breaks existing auth tests**: Low risk since no production deployments. Update tests in same PR.
- **Admin package circular dependencies**: Mitigated by clean contract layer - feature packages depend only on `marko/admin`, never on `marko/admin-panel` or `marko/admin-api`.
- **Template override complexity**: Mitigated by existing `ModuleTemplateResolver` which already supports module priority resolution.
- **Permission explosion**: Mitigated by wildcard support in roles (e.g., `blog.*` grants all blog permissions).
