# Task 004: Refactor blog event tests to use marko/testing

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace ~14 inline EventDispatcherInterface anonymous classes and 1 MailerInterface stub across all blog event test files with FakeEventDispatcher and FakeMailer. These stubs all follow the same pattern — capture dispatched events into an array via reference.

## Context
- Related files (all in `packages/blog/tests/Events/`):
  - `Tag/TagLifecycleEventsTest.php` — 5 inline EventDispatcherInterface stubs
  - `Category/CategoryEventsTest.php` — 6 inline EventDispatcherInterface stubs (uses object capture pattern)
  - `Author/AuthorEventsTest.php` — 1 inline EventDispatcherInterface (helper function with reference)
  - `Comment/CommentLifecycleEventsTest.php` — 1 EventDispatcherInterface + 1 MailerInterface
  - `Post/PostLifecycleEventsTest.php` — 1 EventDispatcherInterface (helper function)
- Patterns found (all equivalent, just different capture mechanisms):
  - Pattern 1: `new class ($events) implements ED { dispatch(Event $e) { $events[] = $e; } }` (reference array)
  - Pattern 2: Object capture: `$capture->events[] = $event`
  - Pattern 3: Helper function returning anonymous class
- Replacement: `new FakeEventDispatcher()` — use `->dispatched` property or `->assertDispatched()` methods

### Key Property Changes
- `$dispatchedEvents` (local array) → `$dispatcher->dispatched` (private(set) property on FakeEventDispatcher)
- `$capture->events` (object capture) → `$dispatcher->dispatched`
- Assertions like `expect($dispatchedEvents)->toHaveCount(1)` → `expect($dispatcher->dispatched)->toHaveCount(1)`
- Can use `expect($dispatcher)->toHaveDispatched(EventClass::class)` Pest expectation

### MailerInterface Replacement
- In CommentLifecycleEventsTest: anonymous `MailerInterface` that just returns true
- Replace with `new FakeMailer()` — sends are captured, returns true by default

## Requirements (Test Descriptions)
- [ ] `it uses FakeEventDispatcher in TagLifecycleEventsTest (5 replacements)`
- [ ] `it uses FakeEventDispatcher in CategoryEventsTest (6 replacements)`
- [ ] `it uses FakeEventDispatcher in AuthorEventsTest (1 replacement)`
- [ ] `it uses FakeEventDispatcher and FakeMailer in CommentLifecycleEventsTest (2 replacements)`
- [ ] `it uses FakeEventDispatcher in PostLifecycleEventsTest (1 replacement)`
- [ ] `it preserves all existing event dispatch and assertion behaviors`

## Acceptance Criteria
- All existing blog event tests pass unchanged
- All inline EventDispatcher and Mailer stubs removed
- Helper functions creating stubs removed or updated
- Blog package already has `marko/testing` in require-dev (added in task 005 or this task — whichever runs first should add it)
- No new test behaviors added (pure refactor)
- Run: `./vendor/bin/pest packages/blog/tests/Events/ --parallel`
