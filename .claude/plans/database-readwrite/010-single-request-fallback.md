# Task 010: Single-Request Fallback in query() Routing

**Status**: pending
**Depends on**: 008
**Retry count**: 0

## Description
Add single-request fallback to `ReadWriteConnection::query()`. When a replica throws a recoverable read failure, the query is retried against the next replica from `$replicaSelector->all()`. If every replica fails, a loud-error Marko exception is thrown listing every attempted replica and its error. This handles transient replica failures without falling back to the writer.

**What "recoverable read failure" means:** Two distinct exception families can escape `query()` from a real driver:
1. `\PDOException` — raised directly by PDO during prepare/execute/fetch when the connection is alive but the query fails (network blip mid-query, replica restart, query syntax error).
2. Driver-level `ConnectionException` (Marko exception) — raised by `ensureConnected()` when the initial `connect()` call fails. The pgsql driver wraps `PDOException` in `Marko\Database\PgSql\Exceptions\ConnectionException::connectionFailed(...)`; the mysql driver mirrors this.

A replica that cannot be reached at all (DNS failure, refused connection, auth failure) throws `ConnectionException`, NOT `PDOException`. If the fallback only catches `PDOException`, an unreachable replica won't trigger fallback — defeating the feature. Catch BOTH exception types.

## Context
- **Files:**
  - Update `packages/database-readwrite/src/Connection/ReadWriteConnection.php` — wrap the read path in iteration-with-fallback
  - New `packages/database-readwrite/src/Exceptions/AllReplicasFailedException.php` extending `MarkoException`
  - New `packages/database-readwrite/tests/Unit/Connection/ReadFallbackTest.php`
- **Fallback algorithm (lock):**
  1. Pick a replica via `$this->replicaSelector->select()`. Try the query on it. On success, return.
  2. On a recoverable read failure (`\PDOException` OR a driver-level `ConnectionException` — see Description; catch the broadest reasonable type, e.g., `\Throwable` is too broad, but catching both `PDOException` and `MarkoException` covers driver-wrapped connection failures), record the failure (which replica, exception class, error message), then try the NEXT replica from `$this->replicaSelector->all()` skipping any already-tried.
  3. Continue until success OR all replicas exhausted.
  4. If all exhausted, throw `AllReplicasFailedException` with a message listing each attempted replica and its error.
- **Exception catch list (lock):** Catch `\PDOException` AND `\Marko\Core\Exceptions\MarkoException` (the base class of `ConnectionException`). Do NOT catch `\Throwable` (too broad — would swallow `\Error`, `\LogicException`, etc.). The two-class catch is intentionally specific.
- **What counts as "the next replica":** Use `$replicaSelector->all()` as the iteration source; the first attempt uses `select()` for the strategy-driven pick, then subsequent attempts walk `all()` in order skipping the already-tried (compared via `===` identity, not just hash — these are object instances held by the selector).
- **Stability of `all()`:** `ReplicaSelectorInterface::all()` MUST return the replica list in a stable order within a single request lifetime. Both `RandomReplicaSelector` and `WeightedReplicaSelector` already store replicas as a constructor-provided array — returning that array is naturally stable. Document the stability requirement in the interface PHPDoc (task 006).
- **`select()` ↔ `all()` consistency:** The implementation MUST guarantee that the object returned by `select()` is identity-equal (`===`) to one of the objects in `all()`. The two built-in selectors satisfy this trivially because both methods reference the same internal array.
- **Do NOT fall back to the writer.** Reads must not silently hit the writer (that defeats the purpose of replicas). If users want writer-fallback behavior, it's a future opt-in flag (out of scope for v1).
- **Sticky-write interaction:**
  - If `$this->stickToWriter` is true, route directly to the writer (no fallback needed — there's only one writer).
  - If sticky is false, run the fallback loop on replicas as above.
  - The fallback DOES NOT set the sticky flag. Failure to read from a replica is not a write.
- **Exception shape:**
  - `AllReplicasFailedException::allFailed(array $attempts): self`
  - `$attempts` is `array<int, array{replica: string, error: string}>` (some readable identifier per replica)
  - Message includes count and a brief summary; suggestion guides ops ("Check replica health; verify replication; consider monitoring `pg_stat_replication` / `SHOW SLAVE STATUS`")
- **Test approach:** Use stub replicas that throw exceptions from `query()`. Test BOTH exception families: one test where the stub throws `\PDOException`, another where the stub throws a driver-style `ConnectionException` (or any `MarkoException` subclass — a tiny anonymous-class stub extending `MarkoException` is fine in tests). Construct a `ReadWriteConnection` with N stubs, assert that calling `query()` exhausts all stubs in order and throws the loud-error exception. Use a stub that succeeds on attempt N to assert routing-to-next-on-failure.
- **Real PDOException construction in tests:** `new PDOException('simulated failure')` works fine — no real DB needed.

## Requirements (Test Descriptions)
- [ ] `it returns the result when the first selected replica succeeds`
- [ ] `it tries the next replica when the first throws PDOException`
- [ ] `it tries the next replica when the first throws a driver ConnectionException (MarkoException)`
- [ ] `it iterates through all replicas before giving up`
- [ ] `it throws AllReplicasFailedException when every replica throws PDOException`
- [ ] `it throws AllReplicasFailedException when every replica throws a driver ConnectionException`
- [ ] `the exception lists each attempted replica and the error it raised`
- [ ] `it does not fall back to the writer even when all replicas fail`
- [ ] `it does not set the sticky flag when fallback occurs`
- [ ] `it does not swallow other exception types (e.g., LogicException, RuntimeException) — they bubble up immediately`

## Acceptance Criteria
- All 7 requirements have passing tests.
- `AllReplicasFailedException` extends `MarkoException` with proper message/context/suggestion.
- Sticky-write tests from task 009 still pass.
- `composer test` passes.
- Lint clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
