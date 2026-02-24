# Task 002: CsrfMiddleware -- CSRF Request Validation Middleware

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Implement CsrfMiddleware that validates CSRF tokens on state-changing HTTP requests (POST, PUT, PATCH, DELETE). Skips validation for safe methods (GET, HEAD, OPTIONS). Reads token from `_token` POST field or `X-CSRF-TOKEN` header. Throws CsrfTokenMismatchException on validation failure.

## Context
- Implements MiddlewareInterface from marko/routing
- Receives CsrfTokenManagerInterface via constructor injection
- Safe methods (GET, HEAD, OPTIONS) pass through without validation
- For state-changing requests, extracts token from:
  1. Request POST body `_token` field (form submissions)
  2. `X-CSRF-TOKEN` header (AJAX requests)
- If no token found or validation fails, throws CsrfTokenMismatchException
- On success, passes request to next handler in pipeline

## Requirements (Test Descriptions)
- [ ] `it implements MiddlewareInterface`
- [ ] `it passes GET requests through without validation`
- [ ] `it passes HEAD and OPTIONS requests through without validation`
- [ ] `it validates token from _token POST field on POST request`
- [ ] `it validates token from X-CSRF-TOKEN header on POST request`
- [ ] `it throws CsrfTokenMismatchException when token is missing on POST`
- [ ] `it throws CsrfTokenMismatchException when token is invalid on PUT PATCH DELETE`

## Acceptance Criteria
- CsrfMiddleware implements MiddlewareInterface
- Constructor accepts CsrfTokenManagerInterface
- Safe methods (GET, HEAD, OPTIONS) always pass through to next handler
- State-changing methods (POST, PUT, PATCH, DELETE) require valid token
- Token extracted from `_token` POST field first, then `X-CSRF-TOKEN` header as fallback
- Throws CsrfTokenMismatchException (not returns error response) -- let error handler deal with it
- All files have strict_types=1

## Implementation Notes

