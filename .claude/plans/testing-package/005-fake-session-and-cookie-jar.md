# Task 005: FakeSession and FakeCookieJar

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create `FakeSession` implementing `SessionInterface` and `FakeCookieJar` implementing `CookieJarInterface`. Both are in-memory implementations that track state for assertion in tests. These replace the ad-hoc TestSession (84 lines) and TestCookieJar (36 lines) currently duplicated in authentication tests.

## Context
- Related files:
  - `packages/session/src/Contracts/SessionInterface.php` - session interface (12 methods + `$started` property)
  - `packages/session/src/FlashBag.php` - flash message support
  - `packages/authentication/src/Contracts/CookieJarInterface.php` - cookie interface (3 methods: get, set, delete)
  - `packages/authentication/tests/Integration/TestSession.php` - existing ad-hoc session (84 lines)
  - `packages/authentication/tests/Integration/TestCookieJar.php` - existing ad-hoc cookie jar (36 lines)
- Location: `packages/testing/src/Fake/FakeSession.php`, `packages/testing/src/Fake/FakeCookieJar.php`

## Requirements (Test Descriptions)
- [ ] `FakeSession implements SessionInterface`
- [ ] `FakeSession stores and retrieves values in memory`
- [ ] `FakeSession tracks whether session was started`
- [ ] `FakeSession tracks whether session was regenerated`
- [ ] `FakeSession supports flash messages via FlashBag`
- [ ] `FakeSession generates and tracks session IDs`
- [ ] `FakeSession clears all stored values`
- [ ] `FakeCookieJar implements CookieJarInterface`
- [ ] `FakeCookieJar stores and retrieves cookies in memory`
- [ ] `FakeCookieJar deletes cookies`
- [ ] `FakeCookieJar returns null for missing cookies`

## Acceptance Criteria
- All requirements have passing tests
- FakeSession implements full SessionInterface including flash() support
- FakeCookieJar implements CookieJarInterface
- Both use `public private(set)` for trackable state properties
- Code follows all code standards

## Implementation Notes
### FakeSession Public API
```php
class FakeSession implements SessionInterface
{
    public private(set) bool $started = false;
    public private(set) bool $regenerated = false;
    public private(set) bool $destroyed = false;
    public private(set) bool $saved = false;

    public function start(): void;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function remove(string $key): void;
    public function clear(): void;
    public function all(): array;
    public function regenerate(bool $deleteOldSession = true): void;
    public function destroy(): void;
    public function getId(): string;
    public function setId(string $id): void;
    public function flash(): FlashBag;
    public function save(): void;
}
```

### FakeCookieJar Public API
```php
class FakeCookieJar implements CookieJarInterface
{
    /** @var array<string, string> */
    public private(set) array $cookies = [];

    public function get(string $name): ?string;
    public function set(string $name, string $value, int $minutes = 0): void;
    public function delete(string $name): void;
}
```
