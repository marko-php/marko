# Plan: marko/testing Package

## Created
2026-02-24

## Status
in_progress

## Objective
Build the `marko/testing` package providing reusable test fakes, assertion helpers, and Pest integration for all Marko framework packages and third-party modules. Then migrate the `marko/authentication` package tests as proof-of-concept to validate simplification.

## Scope

### In Scope
- Package scaffolding (composer.json, module.php, directory structure)
- FakeEventDispatcher with assertion methods
- FakeMailer with assertion methods
- FakeQueue with assertion methods
- FakeSession and FakeCookieJar (in-memory implementations)
- FakeLogger with assertion methods
- FakeConfigRepository with dot-notation and typed getters
- FakeAuthenticatable and FakeUserProvider for auth testing
- Custom Pest expectations for framework-specific assertions
- README following package standards
- Migration of `marko/authentication` tests to use `marko/testing`
- Before/after metrics comparison (lines of code, boilerplate reduction)

### Out of Scope
- TestApplication (full app bootstrap for integration tests) - future enhancement
- FakeNotificationChannel - can be added when notification tests need it
- Database testing utilities (DatabaseTestHelper already exists in marko/database)
- HTTP testing helpers (request simulation) - future enhancement
- Console testing helpers - future enhancement

## Success Criteria
- [ ] All `marko/testing` tests passing with >80% coverage
- [ ] All `marko/authentication` tests still passing after migration
- [ ] Measurable reduction in authentication test boilerplate (target: ~400+ lines eliminated)
- [ ] Code follows all project standards (strict types, no traits, multiline params, etc.)
- [ ] Each fake has assertion methods for verifying side effects
- [ ] README documents usage patterns for each fake

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding and base infrastructure | - | pending |
| 002 | FakeEventDispatcher | 001 | pending |
| 003 | FakeMailer | 001 | pending |
| 004 | FakeQueue | 001 | pending |
| 005 | FakeSession and FakeCookieJar | 001 | pending |
| 006 | FakeLogger | 001 | pending |
| 007 | FakeConfigRepository | 001 | pending |
| 008 | Auth test helpers (FakeAuthenticatable, FakeUserProvider) | 001 | pending |
| 009 | Pest custom expectations | 002, 003, 004, 005, 006, 007, 008 | pending |
| 010 | Migrate authentication tests | 002, 003, 004, 005, 006, 007, 008 | pending |
| 011 | README.md | 001, 002, 003, 004, 005, 006, 007, 008, 009, 010 | pending |

## Architecture Notes

### Design Principles
- **No traits** - All helpers are explicit classes instantiated directly
- **Assertion methods on fakes** - Each fake has `assertXxx()` methods for verifying side effects
- **Composable** - Fakes are independent; use only what you need
- **Implements real interfaces** - Each fake implements the corresponding package interface
- **Explicit over implicit** - No magic auto-wiring or global state

### Package Dependencies
`marko/testing` requires interface packages (lightweight, no drivers):
- `marko/core` (EventDispatcherInterface)
- `marko/config` (ConfigRepositoryInterface)
- `marko/mail` (MailerInterface)
- `marko/queue` (QueueInterface)
- `marko/session` (SessionInterface)
- `marko/log` (LoggerInterface)
- `marko/authentication` (AuthenticatableInterface, UserProviderInterface, CookieJarInterface)
- `pestphp/pest` (dev dependency for Pest expectations)

### Namespace
`Marko\Testing\` with PSR-4 from `src/`

### Directory Structure
```
packages/testing/
  src/
    Fake/
      FakeEventDispatcher.php
      FakeMailer.php
      FakeQueue.php
      FakeSession.php
      FakeCookieJar.php
      FakeLogger.php
      FakeConfigRepository.php
      FakeAuthenticatable.php
      FakeUserProvider.php
    Exceptions/
      AssertionFailedException.php
    Pest/
      Expectations.php
  config/
  tests/
    Unit/
      Fake/
    Pest.php
  composer.json
  module.php
  README.md
```

### Assertion Pattern
Each fake captures side effects and provides assertion methods:
```php
$fake = new FakeEventDispatcher();
// ... code that dispatches events ...
$fake->assertDispatched(UserLoggedIn::class);
$fake->assertNotDispatched(UserLoggedOut::class);
$fake->assertDispatchedCount(1);
```

Assertion failures throw `AssertionFailedException` extending `MarkoException` with message/context/suggestion.

## Risks & Mitigations
- **Interface changes**: Fakes implement current interfaces. If interfaces change, fakes break immediately (loud errors) - this is desirable.
- **Over-engineering fakes**: Keep fakes minimal - only implement what's needed for assertion/verification. Don't replicate real behavior.
- **Auth migration breaks tests**: Run auth tests after each migration step. The migration changes test infrastructure, not test logic.
