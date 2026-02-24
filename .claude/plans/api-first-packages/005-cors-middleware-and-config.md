# Task 005: CORS Middleware and Config

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the marko/cors package with a configurable CORS middleware for cross-origin API access. This is a small, focused package that implements the CORS specification as a routing middleware.

## Context
- New package at `packages/cors/`
- Namespace: `Marko\Cors`
- Depends on: marko/core, marko/routing (for MiddlewareInterface, Request, Response), marko/config
- Study `packages/routing/src/Contracts/MiddlewareInterface.php` for middleware contract
- Study `packages/authentication/src/Middleware/AuthMiddleware.php` for middleware implementation pattern
- Study `packages/cache/config/cache.php` and `packages/cache/src/Config/CacheConfig.php` for config pattern
- CORS spec: preflight OPTIONS, Access-Control-Allow-Origin/Methods/Headers/Credentials/Max-Age
- Config file at `config/cors.php` with allowed_origins, allowed_methods, allowed_headers, etc.

## Requirements (Test Descriptions)
- [x] `it adds Access-Control-Allow-Origin header for allowed origins`
- [x] `it handles preflight OPTIONS requests with 204 No Content response`
- [x] `it rejects requests from origins not in allowed list`
- [x] `it reads CORS configuration from config/cors.php via CorsConfig`
- [x] `it supports wildcard star origin matching`
- [x] `it includes Access-Control-Allow-Credentials header when configured`
- [x] `it sets Access-Control-Max-Age header for preflight caching`

## Acceptance Criteria
- All requirements have passing tests
- Package scaffolding: composer.json, module.php, config/cors.php
- CorsMiddleware implements MiddlewareInterface
- CorsConfig reads from ConfigRepositoryInterface (no hardcoded defaults)
- CorsException follows loud errors pattern
- Code follows code standards (strict types, constructor promotion, no final)

## Implementation Notes
- Package created at `packages/cors/` with namespace `Marko\Cors`
- `CorsMiddleware` implements `MiddlewareInterface` — handles both preflight (OPTIONS, 204) and regular CORS requests
- `CorsConfig` reads all settings from `ConfigRepositoryInterface` via dot-notation keys (e.g. `cors.allowed_origins`)
- `CorsException` follows loud errors pattern (context + suggestion fields)
- `config/cors.php` reads from `$_ENV` with sensible defaults for methods/headers
- Wildcard `*` origin matching supported via `isOriginAllowed()` check
- Root `composer.json` updated with `Marko\Cors\` and `Marko\Cors\Tests\` namespaces
