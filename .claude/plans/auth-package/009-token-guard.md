# Task 009: TokenGuard Implementation

**Status**: pending
**Depends on**: 005, 006, 007
**Retry count**: 0

## Description
Create the TokenGuard class implementing GuardInterface for stateless API token authentication.

## Context
- Reads token from Authorization header (Bearer prefix)
- No session required (stateless)
- Ideal for API authentication

## Requirements (Test Descriptions)
- [ ] `it implements GuardInterface`
- [ ] `it extracts token from Authorization header`
- [ ] `it strips Bearer prefix from token`
- [ ] `it returns user for valid token`
- [ ] `it returns null for invalid token`
- [ ] `it returns true from check when token valid`
- [ ] `it returns false from check when no token`
- [ ] `it supports configurable header name`
- [ ] `it supports configurable prefix`
- [ ] `it is stateless (no session dependency)`

## Acceptance Criteria
- All requirements have passing tests
- Completely stateless operation
- Configurable header and prefix

## Implementation Notes
(Left blank - filled in by programmer during implementation)
