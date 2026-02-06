# Task 017: marko/admin-api - AdminApiController (Sections List, Section Detail)

**Status**: completed
**Depends on**: 011, 016
**Retry count**: 0

## Description
Create the core admin API controllers that expose admin section metadata via JSON. These endpoints let API consumers discover what admin sections are available and what permissions they require.

## Context
- Controllers follow existing patterns with route attributes
- API routes prefixed with `/admin/api/v1` (configurable from admin config route prefix + `/api/v1`)
- Uses TokenGuard-based authentication via admin-api guard
- AdminAuthMiddleware for permission checking
- `GET /admin/api/v1/sections` - list all sections the user has access to
- `GET /admin/api/v1/sections/{id}` - get section detail with menu items
- `GET /admin/api/v1/me` - get current user info with permissions
- Uses ApiResponse helpers from task 016

## Requirements (Test Descriptions)
- [ ] `it returns list of admin sections on GET /admin/api/v1/sections`
- [ ] `it filters sections by user permissions`
- [ ] `it returns section detail with menu items on GET /admin/api/v1/sections/{id}`
- [ ] `it returns 404 when section not found`
- [ ] `it returns current user info with roles and permissions on GET /admin/api/v1/me`
- [ ] `it returns 401 when not authenticated`
- [ ] `it uses ApiResponse format for all responses`
- [ ] `it applies AdminAuthMiddleware to all routes`

## Acceptance Criteria
- All requirements have passing tests
- API responses follow consistent ApiResponse format
- Controllers use proper route attributes
- Authentication via token guard
- Code follows code standards
