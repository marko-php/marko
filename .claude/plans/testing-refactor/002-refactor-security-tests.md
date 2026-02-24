# Task 002: Refactor security package tests to use marko/testing

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace hand-rolled SessionInterface and ConfigRepositoryInterface stubs in security tests with FakeSession and FakeConfigRepository from marko/testing. Add `marko/testing` to require-dev.

## Context
- Related files:
  - `packages/security/tests/Unit/CsrfTokenManagerTest.php` — inline `createStubSession()` returning anonymous SessionInterface (~70 lines)
  - `packages/security/tests/Unit/SecurityConfigTest.php` — inline ConfigRepositoryInterface stub
  - `packages/security/tests/Unit/CorsMiddlewareTest.php` — inline ConfigRepositoryInterface stub
  - `packages/security/tests/Unit/SecurityHeadersMiddlewareTest.php` — inline ConfigRepositoryInterface stub
  - `packages/security/composer.json` — add marko/testing to require-dev
- Replacements:
  - Session stub → `new FakeSession()`, pre-populate with `->set()` calls
  - Config stubs → `new FakeConfigRepository(['key' => 'value', ...])`

## Requirements (Test Descriptions)
- [ ] `it uses FakeSession instead of inline session stub in CsrfTokenManagerTest`
- [ ] `it uses FakeConfigRepository instead of inline config stub in SecurityConfigTest`
- [ ] `it uses FakeConfigRepository instead of inline config stub in CorsMiddlewareTest`
- [ ] `it uses FakeConfigRepository instead of inline config stub in SecurityHeadersMiddlewareTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing security package tests pass unchanged
- All inline stubs removed
- `marko/testing` added to `packages/security/composer.json` require-dev
- No new test behaviors added (pure refactor)
- Run: `./vendor/bin/pest packages/security/tests/ --parallel`
