# Task 006: FakeLogger

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `FakeLogger` that implements `LoggerInterface` from `marko/log`. It captures all log entries in memory with their level and context, and provides assertion methods for verifying logging behavior in tests.

## Context
- Related files:
  - `packages/log/src/Contracts/LoggerInterface.php` - interface to implement (9 methods: emergency, alert, critical, error, warning, notice, info, debug, log)
  - `packages/log/src/LogLevel.php` - LogLevel enum
- Location: `packages/testing/src/Fake/FakeLogger.php`

## Requirements (Test Descriptions)
- [ ] `it implements LoggerInterface`
- [ ] `it captures log entries with level, message, and context`
- [ ] `it returns all log entries`
- [ ] `it returns log entries filtered by level`
- [ ] `it asserts message was logged`
- [ ] `it asserts message was logged at specific level`
- [ ] `it throws AssertionFailedException when asserting logged message that was not logged`
- [ ] `it asserts nothing was logged`
- [ ] `it clears all captured entries`

## Acceptance Criteria
- All requirements have passing tests
- Implements `LoggerInterface` from `marko/log`
- All 8 level methods (emergency through debug) route to the generic `log()` method
- Uses `public private(set)` for the entries array
- Code follows all code standards

## Implementation Notes
### Public API
```php
class FakeLogger implements LoggerInterface
{
    /** @var array<array{level: LogLevel, message: string, context: array}> */
    public private(set) array $entries = [];

    public function emergency(string $message, array $context = []): void;
    public function alert(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function notice(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function log(LogLevel $level, string $message, array $context = []): void;

    public function entriesForLevel(LogLevel $level): array;
    public function assertLogged(string $message, ?LogLevel $level = null): void;
    public function assertNotLogged(string $message, ?LogLevel $level = null): void;
    public function assertLoggedAtLevel(LogLevel $level): void;
    public function assertNothingLogged(): void;
    public function clear(): void;
}
```
