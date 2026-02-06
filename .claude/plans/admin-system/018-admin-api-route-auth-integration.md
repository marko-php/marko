# Task 018: marko/admin-api - API Route Registration and Auth Integration

**Status**: done
**Depends on**: 017
**Retry count**: 0

## Description
Wire up the admin API routes, configure the token guard for API authentication, and ensure admin-api works alongside admin-panel without conflicts. Add API-specific config for token header and versioning.

## Context
- API uses a separate `admin-api` token guard (reads `Authorization: Bearer` header)
- Auth config needs `admin-api` guard entry: `['driver' => 'token', 'provider' => 'admin-users']`
- API routes should be discovered normally via routing attribute scanning
- AdminApi config: `admin-api.version` (default: `'v1'`), `admin-api.rate_limit` (default: 60)
- Ensure API routes and panel routes don't conflict (different prefixes: `/admin/api/v1/` vs `/admin/`)
- module.php bindings for AdminApiConfig

## Requirements (Test Descriptions)
- [x] `it creates AdminApiConfig with version and rate limit settings`
- [x] `it configures admin-api token guard for API authentication`
- [x] `it registers API routes under /admin/api/v1 prefix`
- [x] `it does not conflict with admin-panel routes`
- [x] `it returns JSON 401 for missing bearer token`
- [x] `it returns JSON 401 for invalid bearer token`
- [x] `it authenticates with valid bearer token and returns user`
- [x] `it has valid config/admin-api.php with default values`
- [x] `it has module.php with AdminApiConfig binding`

## Acceptance Criteria
- All requirements have passing tests
- API and panel routes coexist without conflicts
- Token-based auth works independently from session-based panel auth
- Code follows code standards
