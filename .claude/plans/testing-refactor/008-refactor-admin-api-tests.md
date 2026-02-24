# Task 008: Refactor admin-api package tests to use marko/testing

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Replace 2 GuardInterface stubs and ConfigRepository stubs in admin-api tests with FakeGuard and FakeConfigRepository. Add `marko/testing` to require-dev.

## Context
- Related files:
  - `packages/admin-api/tests/Unit/Controller/MeControllerTest.php` — MeStubGuard (lines 20-83, name: 'admin-api')
  - `packages/admin-api/tests/Unit/Controller/SectionControllerTest.php` — StubGuard (lines 67-130, name: 'admin-api')
  - `packages/admin-api/tests/Unit/Config/AdminApiConfigTest.php` — ConfigRepositoryInterface stub (line 22) + UserProviderInterface stubs (lines 224, 288)
  - `packages/admin-api/composer.json` — add marko/testing to require-dev

### Guard Replacements
Both stubs are identical with `attempt()` returning false:
| Old | New |
|---|---|
| `new MeStubGuard()` | `new FakeGuard(name: 'admin-api', attemptResult: false)` |
| `new StubGuard()` | `new FakeGuard(name: 'admin-api', attemptResult: false)` |

### Config + UserProvider Replacements
- Config stub → `new FakeConfigRepository([...])`
- UserProvider stubs at lines 224, 288 → check if `new FakeUserProvider(users: [...])` can replace them. If behaviors are too specific, leave as-is.

## Requirements (Test Descriptions)
- [ ] `it uses FakeGuard instead of MeStubGuard in MeControllerTest`
- [ ] `it uses FakeGuard instead of StubGuard in SectionControllerTest`
- [ ] `it uses FakeConfigRepository in AdminApiConfigTest`
- [ ] `it evaluates UserProvider stubs for FakeUserProvider replacement`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing admin-api package tests pass unchanged
- Both GuardInterface stubs removed
- ConfigRepository stub removed
- `marko/testing` added to `packages/admin-api/composer.json` require-dev
- Run: `./vendor/bin/pest packages/admin-api/tests/ --parallel`
