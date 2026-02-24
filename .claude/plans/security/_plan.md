# Plan: Security Middleware

## Created
2026-02-24

## Status
done

## Objective
Build `marko/security` -- security middleware package providing CSRF protection, CORS handling, and standard security headers. Single package (not interface/driver split) since there is only one sensible implementation for each middleware.

## Scope
### In Scope
- CsrfTokenManager for token generation (via EncryptorInterface), storage (via SessionInterface), and validation
- CsrfMiddleware that validates CSRF tokens on state-changing requests (POST, PUT, PATCH, DELETE)
- CorsMiddleware with configurable origins, methods, headers, max age, and preflight handling
- SecurityHeadersMiddleware adding configurable security headers (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Strict-Transport-Security, Referrer-Policy, Content-Security-Policy)
- SecurityConfig wrapping ConfigRepositoryInterface for all three middleware
- SecurityException base class with CsrfTokenMismatchException subclass
- config/security.php with sensible defaults for all settings

### Out of Scope
- CSRF double-submit cookie pattern (session-based only)
- Content Security Policy nonce/hash generation
- CORS credential handling
- Rate limiting (separate package: marko/rate-limiting)
- Authentication/authorization (separate packages: marko/auth, marko/authorization)

## Success Criteria
- All three middleware implement MiddlewareInterface
- CsrfMiddleware skips safe methods, validates token from body or header
- CorsMiddleware handles preflight OPTIONS requests and adds CORS headers
- SecurityHeadersMiddleware adds all six security headers with configurable values
- CsrfTokenMismatchException follows three-part exception pattern
- >90% test coverage on all middleware and token manager

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | CsrfTokenManager -- token generation, storage, validation | - | done |
| 002 | CsrfMiddleware -- CSRF request validation middleware | 001 | done |
| 003 | CorsMiddleware -- CORS handling middleware | - | done |
| 004 | SecurityHeadersMiddleware -- security headers middleware | - | done |
| 005 | SecurityConfig, module.php, composer.json, config file | 001, 002, 003, 004 | done |

## Architecture Notes
- Namespace: `Marko\Security`
- Package: `packages/security/`
- Dependencies: marko/core, marko/config, marko/routing, marko/session, marko/encryption
- CsrfTokenManager uses EncryptorInterface to generate cryptographically random tokens (encrypt a random value)
- Token stored in session under key `_csrf_token`
- CsrfMiddleware checks `_token` POST field first, then `X-CSRF-TOKEN` header
- CorsMiddleware short-circuits with 204 No Content for preflight OPTIONS requests
- All config values read through SecurityConfig (wraps ConfigRepositoryInterface)
- Response is readonly, so middleware must construct new Response objects with merged headers

## Risks & Mitigations
| Risk | Mitigation |
|------|------------|
| Session not started when CSRF middleware runs | Document that session middleware must run before CSRF middleware in the pipeline |
| CORS wildcard origin combined with credentials | Out of scope for v1; document limitation |
| Response immutability complicates header merging | Construct new Response with merged headers array (same pattern as RateLimitMiddleware) |
