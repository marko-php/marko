# Task 009: Sticky-Write State on ReadWriteConnection

**Status**: pending
**Depends on**: 008
**Retry count**: 0

## Description
Add sticky-write state to `ReadWriteConnection`. After any write OR after `beginTransaction()` is called, subsequent `query()` calls route to the writer instead of a replica for the lifetime of the singleton (which in PHP-FPM equals the request). A public `resetStickyState()` method explicitly clears the flag for long-running-process use cases.

## Context
- **Files:**
  - Update `packages/database-readwrite/src/Connection/ReadWriteConnection.php`
  - New `packages/database-readwrite/tests/Unit/Connection/StickyWriteTest.php`
- **State to add:**
  ```php
  private bool $stickToWriter = false;
  ```
- **When the flag becomes true:**
  - Inside `execute(...)` — set BEFORE delegating to the writer
  - Inside `prepare(...)` — set BEFORE delegating (prepare goes to write, so any later execute on the statement is also write-side; sticky-flip is the safe interpretation)
  - Inside `beginTransaction()` — set BEFORE delegating
  - Inside `transaction(callable $cb)` — set BEFORE delegating. **This MUST be set explicitly inside the façade's own `transaction()` method, NOT relied on via `beginTransaction()`.** Why: task 008 routes `ReadWriteConnection::transaction($cb)` to `$this->writeConnection->transaction($cb)` as a single delegated call. The writer's internal `beginTransaction()` is called inside the writer's own object — the façade's `beginTransaction()` is bypassed entirely. Without an explicit set inside the façade's `transaction()`, a `query()` made between `transaction()` start and the first `execute()` inside the callback would still hit a replica — a stale-read race. Set the flag BEFORE calling `$this->writeConnection->transaction($cb)`.
  - **Inside `lastInsertId()` — DO NOT set sticky.** It's a write-derived read, but by the time it's called, an `execute()` has already set sticky in the same request. Setting it again is harmless but unnecessary. (Surfaced as a deliberate choice: arguments exist for setting it for safety; the chosen behavior is "don't set" because the write that produced the id already flipped the flag.)
- **When the flag is consulted:**
  - Inside `query(...)`: if `$this->stickToWriter`, call `$this->writeConnection->query(...)` instead of the selector. Do NOT bypass single-request fallback for the writer (writer fallback doesn't apply; only one writer exists).
- **When the flag is cleared:**
  - Only by `resetStickyState(): void` — a new public method on `ReadWriteConnection`.
  - **Not** cleared by `commit()` or `rollback()`. Justification: if the request just wrote, subsequent reads should still see-own-writes; commit doesn't mean "I no longer care about the data I just wrote." Document this in the docs page (task 014).
- **Sticky and `inTransaction()`:** Note that while transactions are open, the writer's `inTransaction()` returns true; while reads route to write anyway, this is consistent. After `commit()`, `inTransaction()` returns false but `$stickToWriter` remains true until `resetStickyState()`.
- **Tests:**
  - Reuse stub connections from tasks 007/008 (extract to a Helpers file if not already).
  - Assert flag is FALSE initially → reads go to replica.
  - Assert flag becomes TRUE after `execute()` → next read goes to writer.
  - Assert flag becomes TRUE after `prepare()` → next read goes to writer.
  - Assert flag becomes TRUE after `beginTransaction()` → next read goes to writer.
  - Assert flag becomes TRUE after `transaction(callable)` invocation → next read AFTER the transaction goes to writer.
  - Assert `commit()` and `rollback()` do NOT clear the flag.
  - Assert `resetStickyState()` clears the flag → next read goes back to replica.
  - Optional but recommended: a test asserting `isSticky(): bool` accessor (add this method if helpful for testing — it's a small additive).

## Requirements (Test Descriptions)
- [ ] `it routes query() to a replica when no write has occurred`
- [ ] `it sticks subsequent query() calls to the writer after execute() has run`
- [ ] `it sticks subsequent query() calls to the writer after prepare() has been called`
- [ ] `it sticks subsequent query() calls to the writer after beginTransaction()`
- [ ] `it sticks subsequent query() calls to the writer after a transaction() callable runs`
- [ ] `it sets the sticky flag before invoking the writer's transaction() so reads inside the callback go to the writer`
- [ ] `it does not clear the sticky flag on commit()`
- [ ] `it does not clear the sticky flag on rollback()`
- [ ] `it clears the sticky flag and resumes routing reads to replicas after resetStickyState()`

## Acceptance Criteria
- All 8 requirements have passing tests.
- All prior task tests (007, 008) still pass.
- `resetStickyState()` is a public method documented with a brief PHPDoc explaining when to call it (between queue jobs, long-running processes).
- `composer test` passes.
- Lint clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
