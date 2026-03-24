# Task 005: Write authentication README

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
The authentication package README is missing several sections that ReadmeTest expects. Add Configuration, Usage (with AuthManager/check/attempt examples), Guards, Middleware, and Events documentation based on actual package code.

## Context
- Related files:
  - `packages/authentication/README.md` — current README (has title, installation, quick example, docs link)
  - `packages/authentication/tests/ReadmeTest.php` — test expectations
  - `packages/authentication/src/` — source code
  - Has docs page at `docs/src/content/docs/packages/authentication.md`
- Test checks for specific strings (all case-sensitive `toContain` checks):
  - Configuration section: exactly `## Configuration` (NOT `## Config`), must mention `config/authentication.php`
  - Usage section: `## Usage`, must contain `AuthManager`, `check()`, `attempt(`
  - Guards: `Guard` interface, `SessionGuard`, `TokenGuard`
  - Middleware: `Middleware`, `AuthMiddleware`, `GuestMiddleware`
  - Events: `Event`, `LoginEvent`, `LogoutEvent`, `FailedLoginEvent`

## Requirements (Test Descriptions)
- [ ] `it README includes configuration examples` — section with config/authentication.php reference
- [ ] `it README includes usage examples` — AuthManager, check(), attempt() examples
- [ ] `it README documents guards` — Guard, SessionGuard, TokenGuard documentation
- [ ] `it README documents middleware` — AuthMiddleware, GuestMiddleware documentation
- [ ] `it README documents events` — LoginEvent, LogoutEvent, FailedLoginEvent documentation

## Acceptance Criteria
- All 5 requirements have passing tests
- README content accurately reflects actual package interfaces and classes
- Existing passing tests (README exists, installation instructions) continue to pass
