# Task 014: Roll Back Open Transactions on Reset

**Status**: completed
**Depends on**: 010
**Retry count**: 0

## Description
Extend `ReadWriteConnection::reset()` to roll back a transaction left open by a request that threw. Task 010 wired `reset()` to `resetStickyState()`, which clears the sticky-write flag but leaves an in-progress transaction alive on a pooled connection.

## Context
- Modify: `packages/database-readwrite/src/Connection/ReadWriteConnection.php`

**The leak, verified from source.** `reset()` currently delegates only to `resetStickyState()`. If a request calls `beginTransaction()` directly — not via the `transaction()` helper, which already wraps its work in `try/finally { $this->stickyWrite = false; }` — and then throws before `commit()`/`rollback()`, the underlying write connection is left with `inTransaction() === true`. That connection is pooled across requests in a long-running process, so **the next request's writes are silently appended to the previous request's abandoned transaction.**

- Guard the rollback with `inTransaction()` so `reset()` stays safe to call when no transaction is open.
- Keep `resetStickyState()` public and its behaviour unchanged — it is documented in `packages/docs-markdown/docs/packages/database-readwrite.md` and removing or altering it is a BC break.
- A rollback that itself throws must not prevent the sticky-state reset. Decide the ordering deliberately and document it.
- Under PHP-FPM `reset()` is never called, so this is a no-op there — all pre-existing tests must pass unmodified.
- Update the package docs page to note that `reset()` also rolls back.

## Requirements (Test Descriptions)
- [x] `it rolls back an open transaction when reset`
- [x] `it does not attempt a rollback when no transaction is open`
- [x] `it still clears sticky write state when reset`
- [x] `it clears sticky write state even when the rollback fails`

## Acceptance Criteria
- All requirements have passing tests
- All pre-existing `packages/database-readwrite/` tests pass unmodified
- `resetStickyState()` remains public with unchanged behaviour
- Code follows code standards

## Implementation Notes
- `reset()` now guards the rollback with `$this->write->inTransaction()`, calls `$this->write->rollback()` only when true, and always runs `resetStickyState()` in a `finally` block. If `rollback()` throws, the exception propagates after `resetStickyState()` has already run — deliberately not swallowed, per code standard rule 9 ("never silently catch and ignore"); the caller needs to know a rollback failed even though the connection's sticky flag is safely cleared.
- `resetStickyState()` is unchanged (still public, still just clears `$this->stickyWrite`) — no BC break.
- Added rollback-throw support to the `makeConnection()` test stub (`overrides['rollback']`) to test the "rollback fails" case; this is additive and does not change existing test behaviour, confirmed by the pre-existing `ReadWriteConnectionTest.php` suite (38 tests) still passing unmodified alongside the 4 new tests (42 total).
- `packages/database-readwrite/tests/Integration/MySqlWiringTest.php` currently fails independently of this change (`ContainerInterface::resolvedInstances` abstract-method error) — caused by a sibling worker's concurrent edit to `packages/core/src/Container/ContainerInterface.php`, not this task.
- Updated `packages/docs-markdown/docs/packages/database-readwrite.md`: the "Long-Running Processes" section now explains the rollback behaviour, and the API reference table row for `reset()` reflects it.
- Full-project `composer phpstan` — no errors. `phpcs` on touched files — clean.
