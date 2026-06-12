# Task 010: Atomic session DB upsert and loud write-after-close

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`DatabaseSessionHandler::write()` performs a separate `DELETE` then `INSERT` for the same session id, which is non-atomic: concurrent writes for one id can collide or drop the row. Replace it with a single driver-appropriate upsert (`ON DUPLICATE KEY UPDATE` for MySQL, `ON CONFLICT (id) DO UPDATE` for Postgres/SQLite). Separately, `Session::save()` calls `session_write_close()` but never clears `$this->started`, so subsequent `set()` calls are silently accepted and never persisted — make post-`save()` writes fail loudly.

## Context
- Related files: `packages/session-database/src/Handler/DatabaseSessionHandler.php` (write ~43-58 DELETE+INSERT, read ~28-41, destroy ~60-69, gc ~71-80), `packages/session/src/Session.php` (save ~222-230 — early-returns when `!$this->started` and never resets `started`, set ~85-92 already calls `ensureStarted('set')`, ensureStarted ~259-265 throws `SessionNotStartedException`), `packages/database/src/Connection/ConnectionInterface.php` (`driverName(): string` is ALREADY present at lines 54-62 — use it), session exceptions dir
- Patterns to follow: single `INSERT ... ON CONFLICT/ON DUPLICATE KEY UPDATE` upsert keyed on `id`, branching on the connection's dialect; loud error: after `save()`, a `set()`/write should throw a session exception (message/context/suggestion) rather than silently discarding data — reuse or add a `SessionException` subclass consistent with existing session exceptions; `readonly` handler stays readonly.

### Dialect accessor — `ConnectionInterface::driverName()` ALREADY EXISTS (verified against MERGED source)
`ConnectionInterface::driverName(): string` is already present (lines 54-62) and implemented across `MySqlConnection` (`'mysql'`), `PgSqlConnection` (`'pgsql'`), `ReadWriteConnection` (delegates to the write connection), and the test stubs (`'sqlite'`). **Do NOT add or re-add any accessor.** Simply call `$this->connection->driverName()` and switch on `'mysql'` vs `'pgsql'`/`'sqlite'` to pick the upsert form. Do NOT sniff SQL or string-match the connection class name.

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
