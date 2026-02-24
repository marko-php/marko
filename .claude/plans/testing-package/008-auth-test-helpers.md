# Task 008: Auth Test Helpers (FakeAuthenticatable, FakeUserProvider)

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create `FakeAuthenticatable` implementing `AuthenticatableInterface` and `FakeUserProvider` implementing `UserProviderInterface`. These replace the ad-hoc TestUser (52 lines) and TestUserProvider (57 lines) used in authentication tests.

## Context
- Related files:
  - `packages/authentication/src/AuthenticatableInterface.php` - user interface (6 methods: getAuthIdentifier, getAuthIdentifierName, getAuthPassword, getRememberToken, setRememberToken, getRememberTokenName)
  - `packages/authentication/src/Contracts/UserProviderInterface.php` - provider interface (5 methods: retrieveById, retrieveByCredentials, validateCredentials, retrieveByRememberToken, updateRememberToken)
  - `packages/authentication/tests/Integration/TestUser.php` - existing ad-hoc user (52 lines)
  - `packages/authentication/tests/Integration/TestUserProvider.php` - existing ad-hoc provider (57 lines)
- Location: `packages/testing/src/Fake/FakeAuthenticatable.php`, `packages/testing/src/Fake/FakeUserProvider.php`

## Requirements (Test Descriptions)
- [ ] `FakeAuthenticatable implements AuthenticatableInterface`
- [ ] `FakeAuthenticatable has configurable identifier, password, and remember token`
- [ ] `FakeAuthenticatable defaults to sensible values (id=1, password='hashed')`
- [ ] `FakeAuthenticatable tracks remember token changes`
- [ ] `FakeUserProvider implements UserProviderInterface`
- [ ] `FakeUserProvider returns configured user by ID`
- [ ] `FakeUserProvider returns null when user not found by ID`
- [ ] `FakeUserProvider validates credentials using configurable callback`
- [ ] `FakeUserProvider retrieves user by remember token`
- [ ] `FakeUserProvider tracks remember token updates`

## Acceptance Criteria
- All requirements have passing tests
- FakeAuthenticatable is configurable via constructor with sensible defaults
- FakeUserProvider accepts users and validation logic via constructor
- Both follow framework conventions (constructor property promotion, strict types)
- Code follows all code standards

## Implementation Notes
### FakeAuthenticatable Public API
```php
class FakeAuthenticatable implements AuthenticatableInterface
{
    public function __construct(
        private int|string $id = 1,
        private string $password = 'hashed-password',
        private ?string $rememberToken = null,
        private string $identifierName = 'id',
        private string $rememberTokenName = 'remember_token',
    ) {}

    public function getAuthIdentifier(): int|string;
    public function getAuthIdentifierName(): string;
    public function getAuthPassword(): string;
    public function getRememberToken(): ?string;
    public function setRememberToken(?string $token): void;
    public function getRememberTokenName(): string;
}
```

### FakeUserProvider Public API
```php
class FakeUserProvider implements UserProviderInterface
{
    /** Track last remember token update for assertions */
    public private(set) ?array $lastRememberTokenUpdate = null;

    /**
     * @param array<int|string, AuthenticatableInterface> $users Users keyed by their identifier
     * @param ?callable $credentialValidator Custom validation logic (receives user + credentials, returns bool)
     */
    public function __construct(
        private array $users = [],
        private ?callable $credentialValidator = null,
    ) {}

    public function retrieveById(int|string $identifier): ?AuthenticatableInterface;
    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface;
    public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool;
    public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface;
    public function updateRememberToken(AuthenticatableInterface $user, ?string $token): void;
}
```

Default credential validation: always returns true unless a custom validator is provided.
