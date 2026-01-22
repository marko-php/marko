# Task 009: TokenGuard Implementation

**Status**: completed
**Depends on**: 005, 006, 007
**Retry count**: 0

## Description
Create the TokenGuard class implementing GuardInterface for stateless API token authentication.

## Context
- Reads token from Authorization header (Bearer prefix)
- No session required (stateless)
- Ideal for API authentication

## Requirements (Test Descriptions)
- [x] `it implements GuardInterface`
- [x] `it extracts token from Authorization header`
- [x] `it strips Bearer prefix from token`
- [x] `it returns user for valid token`
- [x] `it returns null for invalid token`
- [x] `it returns true from check when token valid`
- [x] `it returns false from check when no token`
- [x] `it supports configurable header name`
- [x] `it supports configurable prefix`
- [x] `it is stateless (no session dependency)`

## Acceptance Criteria
- All requirements have passing tests
- Completely stateless operation
- Configurable header and prefix

## Implementation Notes
- TokenGuard implements GuardInterface for stateless API token authentication
- Token is extracted from request headers via `setHeaders()` method
- Uses `getTokenFromHeaders()` to parse the Authorization header and strip the Bearer prefix
- Retrieves user via UserProviderInterface's `retrieveByCredentials(['api_token' => $token])`
- Completely stateless - login() and logout() are no-ops
- Configurable header name (default: "Authorization") and prefix (default: "Bearer ")
- Caches the user for the duration of the request to avoid repeated provider lookups
