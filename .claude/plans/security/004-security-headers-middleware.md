# Task 004: SecurityHeadersMiddleware -- Security Headers Middleware

**Status**: done
**Depends on**: none
**Retry count**: 0

## Description
Implement SecurityHeadersMiddleware that adds standard security headers to every response. All header values are configurable via SecurityConfig. Passes request to next handler, then adds security headers to the response.

## Context
- Implements MiddlewareInterface from marko/routing
- Receives SecurityConfig via constructor injection
- Adds these headers to every response:
  - `X-Content-Type-Options` (default: `nosniff`)
  - `X-Frame-Options` (default: `SAMEORIGIN`)
  - `X-XSS-Protection` (default: `1; mode=block`)
  - `Strict-Transport-Security` (default: `max-age=31536000; includeSubDomains`)
  - `Referrer-Policy` (default: `strict-origin-when-cross-origin`)
  - `Content-Security-Policy` (default: `default-src 'self'`)
- Each header can be individually configured or disabled (empty string = omit header)
- Config keys under `security.headers.*`

## Requirements (Test Descriptions)
- [ ] `it implements MiddlewareInterface`
- [ ] `it adds all six security headers to response`
- [ ] `it uses configured header values from SecurityConfig`
- [ ] `it omits headers with empty string config value`
- [ ] `it preserves existing response headers`
- [ ] `it preserves response body and status code`

## Acceptance Criteria
- SecurityHeadersMiddleware implements MiddlewareInterface
- Constructor accepts SecurityConfig
- All six security headers added with their configured values
- Headers with empty string value are omitted (allows disabling individual headers)
- Existing response headers are preserved (merged, not replaced)
- Response body and status code pass through unchanged
- Response construction follows pattern of creating new Response with merged headers
- All files have strict_types=1

## Implementation Notes

