# Task 007: Refactor authorization package tests to use marko/testing

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Replace 3 GuardInterface stubs and 1 ConfigRepository stub in authorization tests with FakeGuard and FakeConfigRepository. Add `marko/testing` to require-dev.

## Context
- Related files:
  - `packages/authorization/tests/Unit/GateTest.php` — StubGuard (lines 17-80, name: 'test')
  - `packages/authorization/tests/Unit/GatePolicyIntegrationTest.php` — IntegrationStubGuard (lines 49-112, name: 'integration-test')
  - `packages/authorization/tests/Unit/Middleware/AuthorizationMiddlewareTest.php` — MiddlewareStubGuard (lines 41-104, name: 'middleware-test')
  - `packages/authorization/tests/Unit/Config/AuthorizationConfigTest.php` — StubConfigRepository (lines 11-94, 84 lines — named class with nested key resolution)
  - `packages/authorization/composer.json` — add marko/testing to require-dev

### Guard Replacements
All 3 stubs are identical except for `getName()` return value. All have `attempt()` returning false.
| Old | New |
|---|---|
| `new StubGuard()` | `new FakeGuard(name: 'test', attemptResult: false)` |
| `new IntegrationStubGuard()` | `new FakeGuard(name: 'integration-test', attemptResult: false)` |
| `new MiddlewareStubGuard()` | `new FakeGuard(name: 'middleware-test', attemptResult: false)` |

### Config Replacement
StubConfigRepository uses nested array key resolution (dot-notation traversal). FakeConfigRepository uses flat dot-notation keys. Need to adjust test data format:
```php
// BEFORE: nested array
new StubConfigRepository(['authorization' => ['policies' => [...]]])
// AFTER: flat keys
new FakeConfigRepository(['authorization.policies' => [...]])
```

## Requirements (Test Descriptions)
- [ ] `it uses FakeGuard instead of StubGuard in GateTest`
- [ ] `it uses FakeGuard instead of IntegrationStubGuard in GatePolicyIntegrationTest`
- [ ] `it uses FakeGuard instead of MiddlewareStubGuard in AuthorizationMiddlewareTest`
- [ ] `it uses FakeConfigRepository instead of StubConfigRepository in AuthorizationConfigTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing authorization package tests pass unchanged
- All 3 GuardInterface stub classes and StubConfigRepository removed
- `marko/testing` added to `packages/authorization/composer.json` require-dev
- Run: `./vendor/bin/pest packages/authorization/tests/ --parallel`
