# Task 010: Refactor admin-panel package tests to use marko/testing

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Replace 2 GuardInterface stubs, 2 AuthenticatableInterface stubs, and ConfigRepository stub across admin-panel tests. Depends on task 001 for FakeGuard. LoginStubView and LoginStubAdminConfig stay (domain-specific).

## Context
- Related files:
  - `packages/admin-panel/tests/Unit/Controller/LoginControllerTest.php` — LoginStubGuard (lines 46-125), LoginStubAdminUser (lines 147-188)
  - `packages/admin-panel/tests/Unit/Controller/DashboardControllerTest.php` — DashboardStubGuard (lines 110-173), StubAdminUser (lines 176-217)
  - `packages/admin-panel/tests/Unit/Config/AdminPanelConfigTest.php` — anonymous ConfigRepositoryInterface (line 13)
  - `packages/admin-panel/composer.json` — add marko/testing to require-dev

### Guard Replacements
| Old | New |
|---|---|
| `LoginStubGuard` (name: 'admin', has attempt tracking) | `new FakeGuard(name: 'admin')` |
| `DashboardStubGuard` (name: 'admin', attempt: false) | `new FakeGuard(name: 'admin', attemptResult: false)` |

### Authenticatable Replacements
| Old | New |
|---|---|
| `LoginStubAdminUser(id: 1, name: 'Admin', email: 'admin@example.com')` | `new FakeAuthenticatable(id: 1)` |
| `StubAdminUser(id: 1, name: 'Admin User', email: 'admin@example.com')` | `new FakeAuthenticatable(id: 1)` |

**Note:** StubAdminUser has a `getName()` method not in AuthenticatableInterface. Check if tests use this — if so, may need a small named stub with just `getName()` that extends or wraps FakeAuthenticatable.

### Property Changes for LoginStubGuard
| Old | New |
|---|---|
| `$guard->lastAttemptedCredentials` | `end($guard->attempts)` or `$guard->attempts[0]` |
| `$guard->logoutCalled` | `$guard->logoutCalled` (same) |
| `$guard->setAttemptResult(true)` | `$guard->setAttemptResult(true)` (same) |

### What Stays
- `LoginStubView` — implements ViewInterface (domain-specific, only 1 occurrence)
- `LoginStubAdminConfig` — implements AdminConfigInterface (domain-specific)

## Requirements (Test Descriptions)
- [ ] `it uses FakeGuard instead of LoginStubGuard in LoginControllerTest`
- [ ] `it uses FakeGuard instead of DashboardStubGuard in DashboardControllerTest`
- [ ] `it uses FakeAuthenticatable instead of LoginStubAdminUser in LoginControllerTest`
- [ ] `it uses FakeAuthenticatable instead of StubAdminUser in DashboardControllerTest`
- [ ] `it uses FakeConfigRepository in AdminPanelConfigTest`
- [ ] `it preserves all credential tracking and logout assertions`

## Acceptance Criteria
- All existing admin-panel package tests pass unchanged
- Guard and Authenticatable stubs removed (4 classes total)
- LoginStubView and LoginStubAdminConfig untouched
- `marko/testing` added to `packages/admin-panel/composer.json` require-dev
- Run: `./vendor/bin/pest packages/admin-panel/tests/ --parallel`
