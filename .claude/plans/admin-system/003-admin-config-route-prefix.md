# Task 003: marko/admin Config and Route Prefix

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Add configuration support to `marko/admin` for the admin route prefix and other admin settings. The route prefix defaults to `/admin` and is configurable. Also create the AdminConfig class wrapping ConfigRepositoryInterface.

## Context
- Related files: `packages/admin/config/admin.php`, `packages/admin/src/Config/AdminConfig.php`
- Follow BlogConfig pattern from `packages/blog/src/Config/BlogConfig.php`
- Config key: `admin.route_prefix` (default: `/admin`)
- Config key: `admin.name` (default: `'Admin'`)
- Validate route prefix starts with `/` (follow `InvalidRoutePrefixException` pattern from blog)
- Add module.php with bindings

## Requirements (Test Descriptions)
- [ ] `it provides default admin route prefix of /admin`
- [ ] `it provides configurable admin route prefix from config`
- [ ] `it throws InvalidAdminConfigException when route prefix does not start with slash`
- [ ] `it provides default admin name of Admin`
- [ ] `it provides configurable admin name from config`
- [ ] `it has valid config/admin.php with default values`
- [ ] `it binds AdminConfigInterface to AdminConfig in module.php`

## Acceptance Criteria
- All requirements have passing tests
- Config follows existing config patterns (BlogConfig, AuthConfig)
- module.php has correct bindings
- Code follows code standards
