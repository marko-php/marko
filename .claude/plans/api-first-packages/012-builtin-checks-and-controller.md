# Task 012: Built-in Health Checks and Controller

**Status**: pending
**Depends on**: 011
**Retry count**: 0

## Description
Implement built-in health checks for database, cache, filesystem connectivity and the HealthController that exposes the /health endpoint with aggregated results.

## Context
- Package: `packages/health/`
- Built-in checks test connectivity to infrastructure services
- Each check is optional — only activated when the corresponding interface is available in the container
- Study `packages/database/src/Contracts/ConnectionInterface.php` for database connectivity check
- Study `packages/cache/src/Contracts/CacheInterface.php` for cache availability check
- Study `packages/filesystem/src/Contracts/FilesystemInterface.php` for filesystem check
- Study `packages/blog/src/Controllers/Api/` for API controller + route attribute patterns
- HealthController aggregates all registered checks, returns JSON with overall status
- HTTP 200 for healthy/degraded, HTTP 503 for unhealthy

## Requirements (Test Descriptions)
- [ ] `it checks database connectivity via DatabaseHealthCheck`
- [ ] `it checks cache read/write via CacheHealthCheck`
- [ ] `it checks filesystem writability via FilesystemHealthCheck`
- [ ] `it aggregates all check results in HealthController`
- [ ] `it returns 200 with healthy status when all checks pass`
- [ ] `it returns 503 with unhealthy status when any critical check fails`
- [ ] `it exposes GET /health route via HealthController`

## Acceptance Criteria
- All requirements have passing tests
- Health checks in `src/Checks/` directory
- HealthController in `src/Controllers/HealthController.php`
- Each built-in check handles missing dependencies gracefully (skip if service unavailable)
- Response includes per-check details and overall aggregated status
- Duration is measured for each check
- Code follows code standards

## Implementation Notes
