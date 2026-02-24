# Task 010: Migrate Authentication Tests

**Status**: pending
**Depends on**: 002, 003, 004, 005, 006, 007, 008
**Retry count**: 0

## Description
Migrate the `marko/authentication` package tests to use `marko/testing` fakes instead of ad-hoc test doubles. This is the proof-of-concept that validates the testing package makes tests significantly simpler. Track before/after metrics.

## Context
- Related files to modify:
  - `packages/authentication/composer.json` - add `marko/testing` as require-dev
  - `packages/authentication/tests/Integration/AuthFlowIntegrationTest.php` - uses TestUser, TestUserProvider, TestSession, TestCookieJar, TestEventDispatcher
  - `packages/authentication/tests/Integration/TestUser.php` - DELETE (52 lines, replaced by FakeAuthenticatable)
  - `packages/authentication/tests/Integration/TestUserProvider.php` - DELETE (57 lines, replaced by FakeUserProvider)
  - `packages/authentication/tests/Integration/TestSession.php` - DELETE (84 lines, replaced by FakeSession)
  - `packages/authentication/tests/Integration/TestCookieJar.php` - DELETE (36 lines, replaced by FakeCookieJar)
  - `packages/authentication/tests/Integration/TestEventDispatcher.php` - DELETE (28 lines, replaced by FakeEventDispatcher)
  - `packages/authentication/tests/Unit/AuthManagerTest.php` - contains inline TestConfigRepository (88 lines)
  - `packages/authentication/tests/Unit/Middleware/AuthMiddlewareTest.php` - contains duplicate MiddlewareTestConfigRepository (87 lines)
  - `packages/authentication/tests/Unit/Guard/SessionGuardTest.php` - may use inline stubs
  - `packages/authentication/tests/Unit/Guard/SessionGuardEventDispatchingTest.php` - may use inline event dispatcher
  - `packages/authentication/tests/Unit/Guard/TokenGuardTest.php` - may use inline stubs
  - `packages/authentication/tests/Unit/Config/AuthConfigTest.php` - may use inline config
  - `packages/authentication/tests/Unit/Token/RememberTokenManagerTest.php` - may use inline stubs
  - `packages/authentication/tests/Unit/Command/ClearTokensCommandTest.php` - may use inline stubs

## Requirements (Test Descriptions)
- [ ] `it replaces TestUser with FakeAuthenticatable in integration tests`
- [ ] `it replaces TestUserProvider with FakeUserProvider in integration tests`
- [ ] `it replaces TestSession with FakeSession in integration tests`
- [ ] `it replaces TestCookieJar with FakeCookieJar in integration tests`
- [ ] `it replaces TestEventDispatcher with FakeEventDispatcher in integration tests`
- [ ] `it replaces inline TestConfigRepository with FakeConfigRepository in unit tests`
- [ ] `it replaces duplicate MiddlewareTestConfigRepository with FakeConfigRepository`
- [ ] `all existing authentication tests still pass after migration`

## Acceptance Criteria
- ALL existing authentication tests pass (zero test failures)
- 5 ad-hoc test fixture files deleted (TestUser, TestUserProvider, TestSession, TestCookieJar, TestEventDispatcher)
- 2 inline ConfigRepository classes removed from unit tests
- `marko/testing` added as require-dev in authentication's composer.json
- Tests remain readable and follow testing conventions
- Measure and report: lines removed, files deleted, net reduction

## Implementation Notes
### Migration Strategy
1. First, add `marko/testing` to authentication's `require-dev`
2. Update imports in integration tests to use `Marko\Testing\Fake\*`
3. Replace constructor calls (TestUser → FakeAuthenticatable with equivalent params)
4. Delete the 5 fixture files from `tests/Integration/`
5. Replace inline ConfigRepository classes in unit tests with FakeConfigRepository
6. Run full authentication test suite to verify zero failures
7. Count before/after metrics

### Expected Metrics
Before:
- TestUser.php: 52 lines
- TestUserProvider.php: 57 lines
- TestSession.php: 84 lines
- TestCookieJar.php: 36 lines
- TestEventDispatcher.php: 28 lines
- TestConfigRepository (AuthManagerTest): ~88 lines
- MiddlewareTestConfigRepository (AuthMiddlewareTest): ~87 lines
- Total boilerplate: ~432 lines

After:
- All replaced by import statements (1 line each)
- Net reduction: ~400+ lines

### Compatibility Notes
- FakeAuthenticatable constructor defaults should match TestUser behavior
- FakeUserProvider constructor should accept same parameters as TestUserProvider
- FakeSession must implement same interface including $started property
- All assertion behavior in tests must be preserved exactly
