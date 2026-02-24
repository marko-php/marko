# Task 002: FakeEventDispatcher

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `FakeEventDispatcher` that implements `EventDispatcherInterface` from `marko/core`. It captures all dispatched events in memory and provides assertion methods for verifying events were dispatched in tests.

## Context
- Related files:
  - `packages/core/src/Event/EventDispatcherInterface.php` - interface to implement (single method: `dispatch(Event $event): void`)
  - `packages/core/src/Event/Event.php` - base Event class
  - `packages/authentication/tests/Integration/TestEventDispatcher.php` - existing ad-hoc implementation (28 lines, to be replaced)
- Patterns to follow: Existing fake patterns in codebase (capture + assert)
- Location: `packages/testing/src/Fake/FakeEventDispatcher.php`

## Requirements (Test Descriptions)
- [ ] `it implements EventDispatcherInterface`
- [ ] `it captures dispatched events in memory`
- [ ] `it returns all dispatched events`
- [ ] `it returns dispatched events filtered by event class`
- [ ] `it asserts event was dispatched by class name`
- [ ] `it throws AssertionFailedException when asserting dispatched event that was not dispatched`
- [ ] `it asserts event was not dispatched`
- [ ] `it throws AssertionFailedException when asserting not dispatched event that was dispatched`
- [ ] `it asserts dispatched count for a specific event class`
- [ ] `it clears all captured events`

## Acceptance Criteria
- All requirements have passing tests
- Implements `EventDispatcherInterface` from `marko/core`
- Uses `public private(set)` for the dispatched events array
- Assertion failures throw `AssertionFailedException` with helpful messages
- Code follows all code standards

## Implementation Notes
### Public API
```php
class FakeEventDispatcher implements EventDispatcherInterface
{
    /** @var array<Event> */
    public private(set) array $dispatched = [];

    public function dispatch(Event $event): void;
    public function dispatched(string $eventClass): array;  // filter by class
    public function assertDispatched(string $eventClass): void;
    public function assertNotDispatched(string $eventClass): void;
    public function assertDispatchedCount(string $eventClass, int $expected): void;
    public function assertNothingDispatched(): void;
    public function clear(): void;
}
```
