# Task 012: Refactor queue package tests — Config stubs only

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Replace ConfigRepositoryInterface stubs in queue tests with FakeConfigRepository. QueueInterface stubs in WorkerTest stay (intentionally custom per-test with specific pop/delete/release behaviors). Add `marko/testing` to require-dev.

## Context
- Related files:
  - `packages/queue/tests/Command/StatusCommandTest.php` — ConfigRepositoryInterface (line 54)
  - `packages/queue/tests/WorkerTest.php` — ConfigRepositoryInterface (line 28)
  - `packages/queue/tests/Feature/IntegrationTest.php` — ConfigRepositoryInterface (line 39)
  - `packages/queue/tests/QueueConfigTest.php` — ConfigRepositoryInterface (line 12)
  - `packages/queue/composer.json` — add marko/testing to require-dev

### What Gets Replaced
4 ConfigRepositoryInterface anonymous stubs → `new FakeConfigRepository([...])`

### What Stays (intentionally custom)
- `WorkerTest.php` lines 180-236: StopTestQueue — controls pop behavior for worker loop testing
- `WorkerTest.php` lines 252-770: 6 anonymous QueueInterface stubs — each tracks specific state (deleted, released, releasedDelay, popCount)
- `Command/StatusCommandTest.php` line 149: QueueInterface stub for size()
- `Command/QueueClearCommandTest.php` line 19: QueueInterface stub for clear()
- `Command/Helpers.php`: StubQueue + StubFailedJobRepository
- `Feature/IntegrationTest.php` line 173: QueueInterface stub

These are testing queue infrastructure itself — they need custom per-test behaviors.

## Requirements (Test Descriptions)
- [ ] `it uses FakeConfigRepository in StatusCommandTest`
- [ ] `it uses FakeConfigRepository in WorkerTest`
- [ ] `it uses FakeConfigRepository in IntegrationTest`
- [ ] `it uses FakeConfigRepository in QueueConfigTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing queue package tests pass unchanged
- Config stubs replaced, QueueInterface stubs untouched
- `marko/testing` added to `packages/queue/composer.json` require-dev
- Run: `./vendor/bin/pest packages/queue/tests/ --parallel`
