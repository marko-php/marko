# Task 001: Add FakeGuard to marko/testing

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create FakeGuard implementing GuardInterface with state tracking, configurable behavior, and assertion methods. Also add Pest expectations (`toHaveAttempted`, `toBeAuthenticated`). This eliminates 9 identical Guard stubs duplicated across 5 packages (~576 lines of boilerplate).

## Context
- Related files:
  - `packages/authentication/src/Contracts/GuardInterface.php` — the interface to implement
  - `packages/testing/src/Fake/FakeLogger.php` — follow this pattern for assertion methods
  - `packages/testing/src/Pest/Expectations.php` — add new expectations here
  - `packages/testing/src/Exceptions/AssertionFailedException.php` — add factory methods for guard assertions
  - `packages/testing/tests/Unit/Fake/FakeLoggerTest.php` — follow this test pattern
- Patterns to follow:
  - All fakes use `private(set)` on recording properties
  - Assertion methods throw `AssertionFailedException` with descriptive messages
  - Pest expectations accept the fake type and throw `InvalidArgumentException` for wrong types
  - Constructor uses property promotion
  - `clear()` method resets all state

### FakeGuard API Design
```php
class FakeGuard implements GuardInterface
{
    // Recording (private(set))
    public array $attempts = [];       // array of credential arrays
    public bool $logoutCalled = false;

    // Provider hook (required by interface)
    public ?UserProviderInterface $provider = null { set { $this->provider = $value; } }

    // Constructor
    public function __construct(
        private readonly string $name = 'test',
        private bool $attemptResult = true,
    ) {}

    // Setup helpers
    public function setUser(?AuthenticatableInterface $user): void
    public function setAttemptResult(bool $result): void

    // GuardInterface implementation
    public function check(): bool                              // user !== null
    public function guest(): bool                              // !check()
    public function user(): ?AuthenticatableInterface          // returns current user
    public function id(): int|string|null                      // user?->getAuthIdentifier()
    public function attempt(array $credentials): bool          // records in $attempts, returns $attemptResult
    public function login(AuthenticatableInterface $user): void // sets user
    public function loginById(int|string $id): ?AuthenticatableInterface // returns null
    public function logout(): void                             // sets logoutCalled, clears user
    public function getName(): string                          // returns $name

    // Assertions
    public function assertAuthenticated(): void
    public function assertGuest(): void
    public function assertAttempted(?callable $callback = null): void
    public function assertNotAttempted(): void
    public function assertLoggedOut(): void

    // Reset
    public function clear(): void
}
```

## Requirements (Test Descriptions)

### FakeGuard Core Behavior
- [ ] `it starts with no authenticated user`
- [ ] `it tracks user state via login and logout`
- [ ] `it records attempt calls with credentials`
- [ ] `it returns configurable attempt result`
- [ ] `it tracks logout calls`
- [ ] `it returns configured guard name`
- [ ] `it resets all state on clear`

### FakeGuard Assertions
- [ ] `it asserts authenticated when user is set`
- [ ] `it throws when asserting authenticated with no user`
- [ ] `it asserts guest when no user is set`
- [ ] `it throws when asserting guest with user set`
- [ ] `it asserts attempted when attempts were made`
- [ ] `it asserts attempted with callback filter`
- [ ] `it throws when asserting attempted with no attempts`
- [ ] `it asserts not attempted when no attempts exist`
- [ ] `it throws when asserting not attempted after attempt`
- [ ] `it asserts logged out when logout was called`
- [ ] `it throws when asserting logged out without logout call`

### Pest Expectations
- [ ] `it provides toHaveAttempted expectation`
- [ ] `it provides toBeAuthenticated expectation`
- [ ] `it rejects non-FakeGuard for toHaveAttempted`
- [ ] `it rejects non-FakeGuard for toBeAuthenticated`

### AssertionFailedException Factory Methods
- [ ] `it creates assertion for expected authenticated`
- [ ] `it creates assertion for unexpected guest`

## Acceptance Criteria
- All requirements have passing tests
- FakeGuard follows same patterns as other fakes (private(set), clear(), assertion methods)
- Pest expectations auto-loaded via Expectations.php
- Code follows code standards
