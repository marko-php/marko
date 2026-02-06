# Task 016: marko/admin-api - Package Skeleton and ApiResponse Helpers

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Create the `marko/admin-api` package with its skeleton and JSON API response helpers. This package provides RESTful JSON endpoints for admin operations, independent of the admin panel.

## Context
- New package at `packages/admin-api/`
- Namespace: `Marko\AdminApi`
- Depends on: `marko/admin`, `marko/admin-auth`, `marko/routing`, `marko/auth`
- ApiResponse helpers: standardized JSON response format with `data`, `meta`, `errors` keys
- Pagination response helper for list endpoints
- Error response helper with status codes
- All API routes will use the `admin-api` token guard from auth config

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with admin, admin-auth, routing, auth dependencies`
- [ ] `it creates ApiResponse helper with success method returning data and meta`
- [ ] `it creates ApiResponse helper with error method returning errors array and status code`
- [ ] `it creates ApiResponse helper with paginated method including pagination meta`
- [ ] `it creates ApiResponse helper with notFound method returning 404`
- [ ] `it creates ApiResponse helper with forbidden method returning 403`
- [ ] `it creates ApiResponse helper with unauthorized method returning 401`
- [ ] `it has valid module.php with bindings`

## Acceptance Criteria
- All requirements have passing tests
- ApiResponse produces consistent JSON structure
- Package structure matches existing packages
- Code follows code standards
