# Task 011: Refactor authentication event and guard tests

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace remaining inline AuthenticatableInterface and UserProviderInterface stubs in authentication tests with FakeAuthenticatable and FakeUserProvider. The authentication package already has marko/testing in require-dev (was migrated previously), but some inline stubs remain.

## Context
- Related files:
  - `packages/authentication/tests/Event/LoginEventTest.php` — 2 anonymous AuthenticatableInterface (lines 9, 51)
  - `packages/authentication/tests/Event/LogoutEventTest.php` — 1 anonymous AuthenticatableInterface (line 9)
  - `packages/authentication/tests/Event/EventGettersTest.php` — 1 anonymous AuthenticatableInterface (line 12)
  - `packages/authentication/tests/Event/PasswordResetEventTest.php` — 1 anonymous AuthenticatableInterface (line 9)
  - `packages/authentication/tests/Unit/Guard/TokenGuardTest.php` — 4 anonymous AuthenticatableInterface (lines 39, 166, 295, 405) + 4 anonymous UserProviderInterface (lines 69, 121, 196, 325)
  - `packages/authentication/tests/Unit/Guard/SessionGuardTest.php` — 2 anonymous UserProviderInterface (lines 303, 403)
- marko/testing is already in require-dev (no composer.json change needed)

### Authenticatable Replacements
All inline stubs follow this pattern:
```php
$user = new class () implements AuthenticatableInterface {
    public function getAuthIdentifier(): int|string { return 42; }
    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthPassword(): string { return 'hashed'; }
    public function getRememberToken(): ?string { return null; }
    public function setRememberToken(?string $token): void {}
    public function getRememberTokenName(): string { return 'remember_token'; }
};
```
Replace with: `new FakeAuthenticatable(id: 42)`

### UserProvider Replacements
Most stubs return a specific user or null:
```php
$provider = new readonly class ($user) implements UserProviderInterface {
    public function retrieveById(int|string $id): ?AuthenticatableInterface { return $this->user; }
    // ...
};
```
Replace with: `new FakeUserProvider(users: [$userId => $user])`

**Caution:** Some UserProvider stubs have specific `retrieveByCredentials` or `validateCredentials` behavior. Verify FakeUserProvider's behavior matches before replacing. If a stub's behavior differs significantly from FakeUserProvider, leave it as-is.

## Requirements (Test Descriptions)
- [ ] `it uses FakeAuthenticatable in event tests (LoginEvent, LogoutEvent, EventGetters, PasswordResetEvent)`
- [ ] `it uses FakeAuthenticatable in TokenGuardTest where applicable`
- [ ] `it uses FakeUserProvider in TokenGuardTest where applicable`
- [ ] `it uses FakeUserProvider in SessionGuardTest where applicable`
- [ ] `it preserves all existing test assertions and behaviors`
- [ ] `it leaves stubs that need specific behaviors FakeUserProvider cannot provide`

## Acceptance Criteria
- All existing authentication package tests pass unchanged
- Inline stubs replaced where FakeAuthenticatable/FakeUserProvider are compatible
- No new test behaviors added (pure refactor)
- Run: `./vendor/bin/pest packages/authentication/tests/ --parallel`
