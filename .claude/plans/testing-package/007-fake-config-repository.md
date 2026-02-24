# Task 007: FakeConfigRepository

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create a `FakeConfigRepository` that implements `ConfigRepositoryInterface` from `marko/config`. It stores configuration values in a flat array with dot-notation support and provides typed getters. This replaces the `TestConfigRepository` class that is duplicated across authentication test files (~176 lines of duplication).

## Context
- Related files:
  - `packages/config/src/ConfigRepositoryInterface.php` - interface to implement (9 methods: get, has, getString, getInt, getBool, getFloat, getArray, all, withScope)
  - `packages/config/src/Exceptions/ConfigNotFoundException.php` - exception to throw for missing keys
  - `packages/authentication/tests/Unit/AuthManagerTest.php` - contains TestConfigRepository (88 lines)
  - `packages/authentication/tests/Unit/Middleware/AuthMiddlewareTest.php` - contains MiddlewareTestConfigRepository (87 lines, duplicate)
- Location: `packages/testing/src/Fake/FakeConfigRepository.php`

## Requirements (Test Descriptions)
- [ ] `it implements ConfigRepositoryInterface`
- [ ] `it stores and retrieves values using dot notation`
- [ ] `it checks if key exists with has()`
- [ ] `it throws ConfigNotFoundException for missing keys`
- [ ] `it returns typed values via getString, getInt, getBool, getFloat, getArray`
- [ ] `it supports scoped config access`
- [ ] `it returns all config values`
- [ ] `it creates scoped instance via withScope`
- [ ] `it accepts initial config values via constructor`
- [ ] `it supports setting values after construction`

## Acceptance Criteria
- All requirements have passing tests
- Implements full `ConfigRepositoryInterface` from `marko/config`
- Throws `ConfigNotFoundException` for missing keys (not null/default)
- Supports dot-notation (e.g., `auth.guards.web.driver`)
- Code follows all code standards

## Implementation Notes
### Public API
```php
class FakeConfigRepository implements ConfigRepositoryInterface
{
    /**
     * @param array<string, mixed> $config Flat key-value pairs using dot notation
     */
    public function __construct(
        private array $config = [],
    ) {}

    public function set(string $key, mixed $value): void;
    public function get(string $key, ?string $scope = null): mixed;
    public function has(string $key, ?string $scope = null): bool;
    public function getString(string $key, ?string $scope = null): string;
    public function getInt(string $key, ?string $scope = null): int;
    public function getBool(string $key, ?string $scope = null): bool;
    public function getFloat(string $key, ?string $scope = null): float;
    public function getArray(string $key, ?string $scope = null): array;
    public function all(?string $scope = null): array;
    public function withScope(string $scope): ConfigRepositoryInterface;
}
```

### Constructor usage
```php
$config = new FakeConfigRepository([
    'auth.defaults.guard' => 'web',
    'auth.guards.web.driver' => 'session',
    'auth.guards.web.provider' => 'users',
]);
```

### Scoped config
Store scoped values with a `scopes.{scope}.` prefix internally, then cascade:
1. Check `scopes.{scope}.{key}` first
2. Fall back to `default.{key}`
3. Fall back to `{key}` directly
