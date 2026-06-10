# Task 010: Atomic session DB upsert and loud write-after-close

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`DatabaseSessionHandler::write()` performs a separate `DELETE` then `INSERT` for the same session id, which is non-atomic: concurrent writes for one id can collide or drop the row. Replace it with a single driver-appropriate upsert (`ON DUPLICATE KEY UPDATE` for MySQL, `ON CONFLICT (id) DO UPDATE` for Postgres/SQLite). Separately, `Session::save()` calls `session_write_close()` but never clears `$this->started`, so subsequent `set()` calls are silently accepted and never persisted — make post-`save()` writes fail loudly.

## Context
- Related files: `packages/session-database/src/Handler/DatabaseSessionHandler.php` (write ~43-58 DELETE+INSERT, read ~28-41, destroy ~60-69, gc ~71-80), `packages/session/src/Session.php` (save ~222-230 — early-returns when `!$this->started` and never resets `started`, set ~85, ensureStarted ~259-265), `packages/database/src/Connection/ConnectionInterface.php` (today exposes only `connect/disconnect/isConnected/query/execute/prepare/lastInsertId` — NO dialect accessor), session exceptions dir
- Patterns to follow: single `INSERT ... ON CONFLICT/ON DUPLICATE KEY UPDATE` upsert keyed on `id`, branching on the connection's dialect; loud error: after `save()`, a `set()`/write should throw a session exception (message/context/suggestion) rather than silently discarding data — reuse or add a `SessionException` subclass consistent with existing session exceptions; `readonly` handler stays readonly.

### Cross-tier coordination — shared dialect accessor (REQUIRED, verified at review time)
`ConnectionInterface` has **no** dialect/driver-name accessor today. **Tier 2 Task 009 (F9 `insertBatch`) is ALSO adding a driver-name/dialect accessor to `ConnectionInterface` and all its implementers (mysql, pgsql, `ReadWriteConnection`, test stubs).** Do NOT add a second, competing accessor here — that would produce two interface methods for the same concept and a guaranteed merge conflict on the contract.
- **Sequencing:** this task depends on the Tier 2 Task 009 accessor. Rebase onto / coordinate with that addition and consume the SAME method (e.g. `driverName()` returning `'mysql'`/`'pgsql'`/`'sqlite'`) to select the upsert dialect.
- If, at implementation time, Tier 2 Task 009 has not yet landed, the implementer must add the accessor with the EXACT signature Tier 2 Task 009 specifies (see `tier2-high-correctness` Architecture Notes F9) so the two converge rather than collide — and update every implementer in one cycle.
- Do NOT sniff SQL or string-match the connection class name; switch on the explicit dialect accessor only.

### Session write-after-close (do not skip)
`Session::save()` calls `session_write_close()` but never resets `$this->started`. The fix must flip `started` to `false` (or an equivalent "closed" flag) inside `save()` so a subsequent `set()`/write hits `ensureStarted()`-style guarding and throws loudly instead of silently mutating `$this->data` that is never persisted.

## Requirements (Test Descriptions)
- [ ] `it writes a session row via a single upsert statement`
- [ ] `it updates the payload and last_activity for an existing session id without dropping the row`
- [ ] `it preserves a session row when two writes target the same id in sequence`
- [ ] `it issues the MySQL upsert form for a MySQL connection`
- [ ] `it issues the ON CONFLICT upsert form for a Postgres or SQLite connection`
- [ ] `it throws a loud session exception when set is called after save`
- [ ] `it persists data written before save`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
