# Task 003: CorsMiddleware -- CORS Handling Middleware

**Status**: done
**Depends on**: none
**Retry count**: 0

## Description
Implement CorsMiddleware that handles Cross-Origin Resource Sharing. Reads configuration for allowed origins, methods, headers, and max age. Handles preflight OPTIONS requests by returning a 204 No Content response with appropriate CORS headers. Adds CORS headers to all responses for allowed origins.

## Context
- Implements MiddlewareInterface from marko/routing
- Receives SecurityConfig via constructor injection for CORS settings
- Config keys: `security.cors.allowed_origins` (array), `security.cors.allowed_methods` (array), `security.cors.allowed_headers` (array), `security.cors.max_age` (int seconds)
- Preflight: If method is OPTIONS and request has `Origin` header, return 204 with CORS headers immediately (short-circuit)
- Normal requests: If request has `Origin` header matching allowed origins, add CORS headers to the response from next handler
- Wildcard `*` in allowed_origins means allow any origin
- Headers added: Access-Control-Allow-Origin, Access-Control-Allow-Methods, Access-Control-Allow-Headers, Access-Control-Max-Age (preflight only)

## Requirements (Test Descriptions)
- [ ] `it implements MiddlewareInterface`
- [ ] `it passes request through when no Origin header present`
- [ ] `it adds CORS headers for allowed origin`
- [ ] `it rejects request from disallowed origin`
- [ ] `it handles preflight OPTIONS request with 204 response`
- [ ] `it supports wildcard origin`
- [ ] `it includes configured allowed methods and headers in preflight response`

## Acceptance Criteria
- CorsMiddleware implements MiddlewareInterface
- Constructor accepts SecurityConfig
- Preflight OPTIONS with Origin header returns 204 with full CORS headers (short-circuits pipeline)
- Normal requests with matching Origin get CORS headers added to response
- Wildcard `*` origin matches any Origin header value
- Non-matching origins pass through without CORS headers (browser enforces rejection)
- Response construction follows the pattern of creating new Response with merged headers
- All files have strict_types=1

## Implementation Notes

