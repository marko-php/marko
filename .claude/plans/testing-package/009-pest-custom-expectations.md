# Task 009: Pest Custom Expectations

**Status**: pending
**Depends on**: 002, 003, 004, 005, 006, 007, 008
**Retry count**: 0

## Description
Create custom Pest expectations that provide fluent assertion syntax for Marko's fake objects. These are registered via `expect()->extend()` and allow tests to use idiomatic Pest syntax like `expect($fake)->toHaveDispatched(Event::class)`.

## Context
- Related files:
  - `packages/testing/src/Fake/FakeEventDispatcher.php` - event fake (from task 002)
  - `packages/testing/src/Fake/FakeMailer.php` - mailer fake (from task 003)
  - `packages/testing/src/Fake/FakeQueue.php` - queue fake (from task 004)
  - `packages/testing/src/Fake/FakeLogger.php` - logger fake (from task 006)
  - `.claude/testing.md` - references Pest expectations pattern
- Location: `packages/testing/src/Pest/Expectations.php`
- The expectations file should be autoloaded via composer.json `autoload.files`

## Requirements (Test Descriptions)
- [ ] `it registers toHaveDispatched expectation for FakeEventDispatcher`
- [ ] `it registers toHaveSent expectation for FakeMailer`
- [ ] `it registers toHavePushed expectation for FakeQueue`
- [ ] `it registers toHaveLogged expectation for FakeLogger`
- [ ] `it provides negated expectations (not->toHaveDispatched, etc.)`
- [ ] `it throws clear error when expectation used on wrong type`

## Acceptance Criteria
- All requirements have passing tests
- Expectations integrate with Pest's `expect()` API
- Negation works via Pest's built-in `->not->` chain
- Wrong type usage produces helpful error messages
- File is auto-loaded via composer.json `autoload.files`
- Code follows all code standards

## Implementation Notes
### Expectations file structure
```php
// src/Pest/Expectations.php
declare(strict_types=1);

use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeMailer;
use Marko\Testing\Fake\FakeQueue;
use Marko\Testing\Fake\FakeLogger;

expect()->extend('toHaveDispatched', function (string $eventClass): Expectation {
    // Verify $this->value is FakeEventDispatcher
    // Call assertDispatched()
    return $this;
});
```

### composer.json addition
```json
{
    "autoload": {
        "files": ["src/Pest/Expectations.php"]
    }
}
```

### Full expectation list
- `toHaveDispatched(string $eventClass)` - for FakeEventDispatcher
- `toHaveSent(?callable $callback = null)` - for FakeMailer
- `toHavePushed(string $jobClass, ?callable $callback = null)` - for FakeQueue
- `toHaveLogged(string $message, ?LogLevel $level = null)` - for FakeLogger

Each expectation should verify the value type first and throw a clear TypeError if misused.
