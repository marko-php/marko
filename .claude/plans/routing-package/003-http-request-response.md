# Task 003: HTTP Request/Response Value Objects

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create simple Request and Response value objects for HTTP handling. These are minimal implementations focused on what's needed for routing - not full PSR-7 implementations. Keep them simple and extend later if needed.

## Context
- Location: `packages/routing/src/Http/`
- Request wraps PHP superglobals ($_GET, $_POST, $_SERVER, etc.)
- Response represents HTTP response (status, headers, body)
- Keep simple - no PSR-7 compliance required initially
- Use readonly properties where appropriate

## Requirements (Test Descriptions)
- [ ] `Request::fromGlobals creates request from PHP superglobals`
- [ ] `Request returns method (GET, POST, etc.) from server vars`
- [ ] `Request returns path without query string`
- [ ] `Request returns query parameters from GET`
- [ ] `Request returns body parameters from POST`
- [ ] `Request returns specific header by name`
- [ ] `Request returns all headers`
- [ ] `Response accepts status code, headers, and body`
- [ ] `Response defaults to 200 status code`
- [ ] `Response::json creates JSON response with correct content-type`
- [ ] `Response::html creates HTML response with correct content-type`
- [ ] `Response::redirect creates redirect response with Location header`
- [ ] `Response::send outputs headers and body`

## Acceptance Criteria
- All requirements have passing tests
- Request is immutable (readonly properties)
- Response provides fluent interface for common responses
- No external dependencies

## Files to Create
```
packages/routing/src/Http/
  Request.php
  Response.php
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
