# Task 001: CORS wildcard+credentials hardening and Vary: Origin

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`CorsMiddleware` reflects any `Origin` into `Access-Control-Allow-Origin` and unconditionally emits `Access-Control-Allow-Credentials: true` when credentials are supported, even when the allowlist is `*`. This is the classic credentialed-wildcard vulnerability and omits the required `Vary: Origin` header. Refuse the wildcard+credentials combination loudly (or never emit credentials with `*`) and add `Vary: Origin` whenever the origin is reflected.

## Context
- Related files: `packages/cors/src/Middleware/CorsMiddleware.php` (handle ~18-60 — preflight OPTIONS branch ~28-44 emits NO credentials, normal-request branch ~46-59 emits credentials, neither adds `Vary: Origin`; isOriginAllowed ~62-71), `packages/cors/src/Config/CorsConfig.php` (`supportsCredentials()`, `allowedOrigins()`), `packages/cors/src/Exceptions/CorsException.php` (bare `extends MarkoException` — NO factory yet; add a static factory), `packages/cors/config/cors.php`, `packages/cors/tests/CorsMiddlewareTest.php`
- Patterns to follow: loud errors via `CorsException` static factory (`MarkoException` message/context/suggestion); config getters throw `ConfigNotFoundException` (no fallbacks); existing `CorsMiddlewareTest` style.
- **Guard placement (VERIFIED):** the wildcard+credentials rejection must run BEFORE the `OPTIONS` branch (~28) — otherwise a credentialed preflight slips through the OPTIONS path unguarded. Add `Vary: Origin` in BOTH the preflight response (~39-43) and the normal reflected response (~55-59), since today neither sets it.

## Requirements (Test Descriptions)
- [x] `it throws a CorsException when allowed origins contain a wildcard and credentials are supported`
- [x] `it never emits Access-Control-Allow-Credentials together with a wildcard Access-Control-Allow-Origin`
- [x] `it reflects an explicitly allowed origin and emits Access-Control-Allow-Credentials true when credentials are supported`
- [x] `it adds a Vary: Origin header when reflecting an allowed origin on a normal request`
- [x] `it adds a Vary: Origin header on the preflight OPTIONS response when the origin is allowed`
- [x] `it leaves the response unchanged and adds no CORS headers when the Origin is not allowed`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `CorsException::wildcardWithCredentials()` static factory in `packages/cors/src/Exceptions/CorsException.php`
- Guard placed in `CorsMiddleware::handle()` before the OPTIONS branch, so credentialed preflight requests also fail loudly
- `Vary: Origin` added to both the preflight response (OPTIONS branch) and the normal reflected-origin response
- Requirements 2, 3, and 6 passed immediately — the existing code already handled those cases correctly
- 13 total tests pass (7 original + 6 new)
