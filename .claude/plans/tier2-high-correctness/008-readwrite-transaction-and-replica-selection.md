# Task 008: F8 — ReadWriteConnection transaction sticky-write + write routing + replica-selection safety

**Status**: complete
**Depends on**: [009]
**Retry count**: 0

> **File-collision note:** Task 009 adds `ConnectionInterface::driverName()`, edits
> `ReadWriteConnection.php` (passthrough), and updates the `database-readwrite/tests/**`
> `ConnectionInterface` stubs. This task ALSO edits `ReadWriteConnection.php`
> (`transaction()`, `query()` write-routing) and the same readwrite tests. Depend on 009
> so the `driverName()` passthrough/stubs already exist; this task then layers the
> sticky-write and routing changes on top without clobbering 009's edits or hitting an
> abstract-method load error.

## Description
The read/write split routes writes to replicas. `ReadWriteConnection::transaction()`
delegates straight to `$this->write->transaction()` without setting `stickyWrite`, so
`query()` calls inside the callback still fan out to replicas (unlike
`beginTransaction()` which sets sticky). PostgreSQL `INSERT ... RETURNING` flows
through `query()` and is therefore routed to a replica. And `WeightedReplicaSelector::
select()` indexes the passed (possibly shrunken) replica array by original-position
weights, so after a fallback removes a replica it can index a missing slot →
null/`TypeError`. Make `transaction()` sticky for the whole callback, route
write-producing statements to the primary, and make replica selection safe after removal.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/database-readwrite/src/Connection/ReadWriteConnection.php`
    (`transaction(callable $callback): mixed` returns `$this->write->transaction($callback)`
    WITHOUT setting `$this->stickyWrite`; `beginTransaction()` sets it; `query()` fans to
    replicas unless `stickyWrite`; `execute()` sets `stickyWrite = true`;
    `resetStickyState()` clears it)
  - `/Users/markshust/Sites/marko/packages/database-readwrite/src/Replica/WeightedReplicaSelector.php`
    (`select(array $replicas)`: sums fixed `$this->weights`, walks them, returns
    `$replicas[$index]`; after `query()` removes a failed replica via `array_values(
    array_filter(...))` the indices no longer line up with `$this->weights`, and the
    fallback `return $replicas[count($replicas) - 1]` can still mis-target)
  - `/Users/markshust/Sites/marko/packages/database-readwrite/tests/` (existing
    ReadWriteConnection / selector tests for fixture style)
- Patterns to follow:
  - `transaction()`: set `$this->stickyWrite = true` before invoking, ensure it stays
    sticky for the whole callback (mirror `beginTransaction()`), and reset it in a
    `finally` so a callback that throws still clears sticky state (the underlying
    `$write->transaction()` rolls back on throw; sticky must not leak to the next
    request/call). Note the write driver's `transaction()` invokes `$callback()` with NO
    arguments, so user code inside the callback calls back through the
    `ReadWriteConnection` it holds — which is exactly why sticky-write must be set on
    `$this` for the callback duration.
  - Write routing: send `INSERT`/`UPDATE`/`DELETE` and `INSERT ... RETURNING` through
    the write connection. `RETURNING` must still use `query()` to read returned rows, so
    detect a leading INSERT/UPDATE/DELETE in `query()` and route to `$this->write`.
    Prefix detection must be robust: trim leading whitespace AND strip a leading SQL
    line/block comment (`-- ...`, `/* ... */`) before sniffing the first keyword, and
    match case-insensitively. (CTEs — a leading `WITH` whose final statement is an
    INSERT — are out of scope for v1; document that `WITH ... INSERT ... RETURNING`
    routes to a replica and recommend `execute()`/explicit write for those. Add a code
    comment noting this limitation.)
  - Selector: `WeightedReplicaSelector` is a `readonly class` holding `$weights`; it
    cannot mutate `$weights` in `select()`. Realign by computing the cumulative
    distribution over only the FIRST `count($replicas)` weights (re-summing that slice),
    or select a positional index modulo `count($replicas)`, so the returned replica is
    always an element of the passed array and never an undefined index. The fallback
    `return $replicas[count($replicas) - 1]` is safe only when `$replicas` is non-empty;
    `ReadWriteConnection::query()` already guards the empty case, but `select()` should
    still never compute an index `>= count($replicas)`.

## Requirements (Test Descriptions)
- [x] `it routes query() calls executed inside a transaction() callback to the primary, not a replica`
- [x] `it resets sticky-write state after the transaction() callback completes`
- [x] `it resets sticky-write state even when the transaction() callback throws`
- [x] `it routes an INSERT ... RETURNING statement issued via query() to the primary`
- [x] `it routes a write statement to the primary even when it has leading whitespace or a
      leading SQL comment before the INSERT/UPDATE/DELETE keyword (case-insensitive)`
- [x] `it continues routing plain SELECTs to a replica when not in a transaction and not sticky`
- [x] `it selects a valid remaining replica after one replica has been removed during
      fallback (never indexes a removed/undefined slot)`
- [x] `it never returns null or throws a TypeError from select() when the passed replica
      array is smaller than the original weights array`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes

### transaction() sticky-write
`ReadWriteConnection::transaction()` now sets `$this->stickyWrite = true` before delegating to `$write->transaction($callback)` and resets it in a `finally` block, ensuring sticky-write is cleared even when the callback throws.

### query() write routing
`ReadWriteConnection::query()` calls a private `isWriteStatement()` helper that strips leading whitespace and optional leading `--` line comment or `/* */` block comment, then matches `/^(INSERT|UPDATE|DELETE)\b/i`. Matching statements are routed to `$this->write`. v1 limitation documented in code comments: `WITH ... INSERT ... RETURNING` CTEs are not detected and route to a replica.

### WeightedReplicaSelector safe indexing
`WeightedReplicaSelector::select()` now slices `$this->weights` to `count($replicas)` elements before computing the cumulative distribution, so the returned index is always within bounds of the passed array even after a replica has been removed by the fallback loop.
