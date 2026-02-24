# Task 009: Refactor admin-auth package tests to use marko/testing

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Replace GuardInterface stub, EventDispatcherInterface stub, and ConfigRepository stub in admin-auth tests. Add `marko/testing` to require-dev.

## Context
- Related files:
  - `packages/admin-auth/tests/Unit/Middleware/AdminAuthMiddlewareTest.php` — StubGuard (lines 48-111, name: 'admin')
  - `packages/admin-auth/tests/Unit/Repository/RepositoryEventsTest.php` — anonymous EventDispatcherInterface (lines 208-220, ~16 lines)
  - `packages/admin-auth/tests/Unit/Config/AdminAuthConfigTest.php` — anonymous ConfigRepositoryInterface (line 35)
  - `packages/admin-auth/composer.json` — add marko/testing to require-dev

### Replacements
| Old | New |
|---|---|
| `StubGuard` (name: 'admin', attempt: false) | `new FakeGuard(name: 'admin', attemptResult: false)` |
| Anonymous EventDispatcher (reference array) | `new FakeEventDispatcher()` |
| Anonymous ConfigRepository | `new FakeConfigRepository([...])` |

### EventDispatcher Pattern
```php
// BEFORE: 16-line anonymous class with &$events reference
$events = [];
$eventDispatcher = new class ($events) implements EventDispatcherInterface { ... };
// AFTER: assert via dispatched property
$eventDispatcher = new FakeEventDispatcher();
// ... after action ...
expect($eventDispatcher->dispatched)->toHaveCount(1);
```

## Requirements (Test Descriptions)
- [ ] `it uses FakeGuard instead of StubGuard in AdminAuthMiddlewareTest`
- [ ] `it uses FakeEventDispatcher instead of anonymous class in RepositoryEventsTest`
- [ ] `it uses FakeConfigRepository in AdminAuthConfigTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing admin-auth package tests pass unchanged
- StubGuard class removed
- Anonymous EventDispatcher and ConfigRepository stubs removed
- `marko/testing` added to `packages/admin-auth/composer.json` require-dev
- Run: `./vendor/bin/pest packages/admin-auth/tests/ --parallel`
