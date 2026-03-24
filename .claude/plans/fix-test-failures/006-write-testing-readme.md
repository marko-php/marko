# Task 006: Write testing README

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
The testing package README is missing several sections that ReadmeTest expects. Add Overview, comprehensive Usage with code examples for each fake, API Reference, FakeGuard documentation, and Pest expectations documentation. Depends on task 003 because tests for Pest expectations must work first.

## Context
- Related files:
  - `packages/testing/README.md` — current README (has title, installation, available fakes, quick example, docs link)
  - `packages/testing/tests/ReadmeTest.php` — test expectations
  - `packages/testing/src/` — source code (9 fakes + FakeGuard + Expectations)
  - Has docs page at `docs/src/content/docs/packages/testing.md`
- Test checks for specific content:
  - `## Overview` section explaining the benefit
  - `## Usage` section with code examples for each fake (FakeEventDispatcher, FakeMailer, FakeQueue, FakeSession, FakeCookieJar, FakeLogger, FakeConfigRepository, FakeAuthenticatable, FakeUserProvider) in ```php blocks
  - `## API Reference` section listing methods: dispatch(), assertDispatched(), assertNotDispatched(), assertDispatchedCount(), assertSent(), assertNothingSent(), assertSentCount(), assertPushed(), assertNotPushed(), assertPushedCount(), assertNothingPushed(), assertLogged(), assertNothingLogged()
  - `### FakeGuard` subsection with: new FakeGuard, setUser, attempt usage example; assertAuthenticated(), assertGuest(), assertAttempted(), assertNotAttempted(), assertLoggedOut() methods
  - Pest expectations: `toHaveAttempted`, `toBeAuthenticated`

## Requirements (Test Descriptions)
- [ ] `it has an overview section explaining the benefit` — ## Overview section exists
- [ ] `it has a usage section with code examples for each fake` — ## Usage with all 9 fakes in php code blocks
- [ ] `it has an API reference section listing all public methods` — ## API Reference with assertion methods
- [ ] `it includes FakeGuard usage example with guard configuration` — FakeGuard example with new FakeGuard, setUser, attempt
- [ ] `it documents FakeGuard assertion methods` — assertAuthenticated, assertGuest, assertAttempted, assertNotAttempted, assertLoggedOut
- [ ] `it documents toHaveAttempted and toBeAuthenticated Pest expectations` — Pest expectations documented
- [ ] `it follows the Package README Standards from code-standards.md` — follows standards

## Acceptance Criteria
- All 7 requirements have passing tests
- README content accurately reflects actual Fake classes and their methods
- Existing passing tests continue to pass
