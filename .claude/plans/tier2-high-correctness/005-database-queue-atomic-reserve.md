# Task 005: F5 — DatabaseQueue atomic reserve + reservation-timeout reclaim + single attempt source

**Status**: complete
**Depends on**: [004, 009]
**Retry count**: 0

> **File-collision note:** Task 009 adds `ConnectionInterface::driverName()` and updates
> the `ConnectionInterface` test stub in `packages/queue-database/tests/DatabaseQueueTest.php`
> (among ~43 stubs). This task also edits that same test file. Depend on 009 so the
> `driverName()` method already exists on the stub before this task reworks it; otherwise
> the queue-database suite fails to load (abstract-method error) or the two tasks clobber
> each other's edits to the shared stub. Reuse the existing stub's `driverName()` and gate
> the dialect-specific `FOR UPDATE SKIP LOCKED` clause on it.

## Description
`DatabaseQueue::popJob()` SELECTs a job then UPDATEs `reserved_at` in a separate
statement with no row locking and no `reserved_at IS NULL` guard / affected-rows
check, so two concurrent workers can reserve and run the same job. There is no
reservation timeout, so a crashed worker leaves a job reserved forever (lost). And
attempts are double-counted: `popJob` increments `attempts` in the DB while
`Worker::work()` also calls `incrementAttempts()`. Make reservation atomic, reclaim
timed-out reservations, and make attempt counting have a single source of truth so
`maxAttempts` means exactly N executions.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/queue-database/src/DatabaseQueue.php`
    (`pop()` wraps `popJob()` in `transaction()` when the connection is a
    `TransactionInterface`; `popJob()` does `SELECT ... WHERE reserved_at IS NULL AND
    available_at <= :now ... LIMIT 1` then an unguarded `UPDATE ... SET reserved_at,
    attempts = attempts + 1 WHERE id = :id`, then re-syncs `$job` attempts in a loop)
  - `/Users/markshust/Sites/marko/packages/queue-database/src/Migration/CreateJobsTable.php`
    (columns: id, queue, payload, attempts, reserved_at, available_at, created_at)
  - `/Users/markshust/Sites/marko/packages/queue/src/Worker.php`
    (`work()` calls `incrementAttempts()` then `handle()`; `handleFailedJob` uses
    `$job->attempts < $job->maxAttempts`)
  - `/Users/markshust/Sites/marko/packages/queue/src/QueueConfig.php`
    (`queue.retry_after` config key already exists per WorkerTest defaults)
  - `/Users/markshust/Sites/marko/packages/queue-database/tests/DatabaseQueueTest.php`,
    `/Users/markshust/Sites/marko/packages/queue-database/tests/Fixtures/TestJob.php`
- Patterns to follow:
  - Atomic reserve: `SELECT ... FOR UPDATE SKIP LOCKED` inside the transaction (pgsql /
    mysql 8), then `UPDATE ... SET reserved_at = :now WHERE id = :id AND reserved_at IS
    NULL`; affected-rows = 0 means another worker won → return null. Gate the
    `FOR UPDATE SKIP LOCKED` clause on dialect (SQLite lacks it; the affected-rows
    guard preserves correctness regardless).
  - Reclaim: candidate predicate becomes `reserved_at IS NULL OR reserved_at <=
    :reclaim_cutoff` where cutoff = now - `queue.retry_after` seconds.
  - Attempts single source: pick the Worker as authoritative and remove the DB-side
    increment + the resync loop (or vice versa) — assert one increment per execution.
  - Tier-1 rebase note: `unserialize($row['payload'])` is touched by Tier 1's
    unserialize hardening; rebase onto that seam.

## Requirements (Test Descriptions)
- [x] `it reserves a job atomically so a second concurrent pop() of the same queue does
      not return the already-reserved job (affected-rows guard returns null on race loss)`
- [x] `it issues the reserve UPDATE with a reserved_at IS NULL guard`
- [x] `it reclaims a job whose reservation is older than queue.retry_after and makes it
      available to pop() again`
- [x] `it does not reclaim a job whose reservation is within the retry_after window`
- [x] `it counts exactly one attempt per execution (popping then processing a job once
      yields attempts == 1, not 2)`
- [x] `it reaches maxAttempts after exactly maxAttempts executions (job moves to failed
      store on the Nth, not the (N-1)th, failure)`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `retryAfter` constructor parameter (default 90s) to `DatabaseQueue`; replaces `QueueConfig` injection to keep the class narrowly scoped
- `popJob` SELECT widened to `(reserved_at IS NULL OR reserved_at <= :reclaim_cutoff)` where `reclaim_cutoff = now - retryAfter` to reclaim crashed reservations
- `FOR UPDATE SKIP LOCKED` gated on `$connection->driverName()` (mysql/pgsql only); SQLite uses no locking but the affected-rows guard still provides correctness
- UPDATE uses `WHERE id = :id AND (reserved_at IS NULL OR reserved_at <= :reclaim_cutoff)` as atomic guard; affected-rows = 0 → race lost → return null
- Removed DB-side `attempts = attempts + 1` increment and the post-sync loop from `popJob`; Worker's `incrementAttempts()` is the single source of truth
- Job returns with `attempts = 0` from `pop()`; Worker increments to 1 on first execution → `maxAttempts = N` means exactly N executions before failure
