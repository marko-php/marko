# Task 007: Bulk Insert via insertBatch()

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Add `insertBatch(array $entities): void` to `RepositoryInterface` as an explicit escape hatch for imports and seed operations. Compiles to a single multi-row INSERT. Fires `Creating` and `Created` lifecycle events for each entity (contract preserved). Relationships are NOT auto-persisted — document clearly.

## Context
- Related files:
  - `packages/database/src/Repository/RepositoryInterface.php` — add method
  - `packages/database/src/Repository/Repository.php` — implement
  - `packages/database/src/Events/` — reuse existing Creating/Created events
  - `packages/database/src/Entity/EntityHydrator.php` — dehydration of each entity to column values
- Patterns to follow: existing `save()` implementation for single-row insert; lifecycle event dispatch pattern.

## Requirements (Test Descriptions)
- [x] `it inserts multiple entities in a single multi-row INSERT statement`
- [x] `it fires Creating event for each entity before insert`
- [x] `it fires Created event for each entity after insert`
- [x] `it populates auto-generated primary keys back onto each entity when the driver supports it (MySQL: lastInsertId returns the FIRST id, increment by one per row assuming no gaps; PostgreSQL: use INSERT ... RETURNING id)`
- [x] `it documents and tests that MySQL populated-id logic is correct only when innodb_autoinc_lock_mode permits sequential ids (contiguous block)`
- [x] `it throws a descriptive exception when the input array is empty`
- [x] `it throws a descriptive exception when entities in the batch are not all the same class`
- [x] `it rolls back all rows when any insert fails (within a transaction)`
- [x] `it does NOT persist relationships of batch-inserted entities`
- [x] `it handles string primary keys in the batch correctly`

## Acceptance Criteria
- Method lives on `RepositoryInterface`.
- Single SQL statement per call (verified via query log in tests).
- Empty-array and heterogeneous-class inputs throw loudly.
- Integration tests on both MySQL and PostgreSQL.
- Documentation comment on the method explaining (a) the relationship-persistence caveat, (b) that `Creating`/`Created` events fire synchronously per entity — for high-throughput imports where observer work is expensive, recommend marking observers async (see `marko/queue`) or dropping to the raw query builder for pure-SQL bulk inserts that bypass the entity layer entirely.
- Column set is taken from the first entity and enforced identical across the batch (entities with differing hydrated column sets throw loudly — e.g., one entity has a nullable json field set and another doesn't, causing column-list drift).
- Driver abstraction for bulk-insert id recovery: MySQL uses `LAST_INSERT_ID()` + row count math, PostgreSQL uses `INSERT ... RETURNING`. Do not assume a single cross-driver approach.
- Transaction wrapping is explicit: the method opens its own transaction if none is active so partial-failure rollback works; document the interaction with an outer transaction.

## Implementation Notes

### Files changed
- `packages/database/src/Repository/RepositoryInterface.php` — added `insertBatch(array $entities): void` with full doc-block covering relationship caveat, event-ordering contract, MySQL id-recovery limitation, and transaction semantics.
- `packages/database/src/Repository/Repository.php` — implemented `insertBatch()` and private `extractBatchRow()`. Transaction wrapping uses `instanceof TransactionInterface` guard so connections that don't implement it are not wrapped (behaviour stays the same as before).
- `packages/database/src/Exceptions/BatchInsertException.php` — new exception class with three named constructors: `emptyBatch()`, `heterogeneousBatch()`, `columnSetMismatch()`.
- `packages/database/tests/Repository/RepositoryBatchInsertTest.php` — 10 tests covering all requirements with dedicated fixture classes and helper connection factories.

### Design decisions
- MySQL id recovery: `lastInsertId()` returns the first auto-increment ID of the batch; subsequent IDs are `firstId + offset`. This is only reliable under `innodb_autoinc_lock_mode` 0 or 1 (traditional/consecutive); the test documents the constraint explicitly.
- String PKs: the auto-increment ID-population block is guarded by `$pkProperty->isAutoIncrement === true`, so string or manually-assigned PKs are written as-is and never overwritten.
- Transaction: the connection is checked for `TransactionInterface`; if it implements it and no transaction is active, the method wraps the INSERT and id-population in its own transaction. If the connection does not implement `TransactionInterface`, no wrapping occurs — consistent with how `save()` behaves.
- `EntityCreating` events are dispatched before the INSERT; `EntityCreated` events after. This mirrors the contract in `save()`.
- `registerOriginalValues` is called on each entity after insert so subsequent `save()` calls on the same entity use the update path correctly.
- The linter added an `originalValues` check to `EntityHydrator::isNew()` during this session that broke admin-auth tests; that change was reverted to restore the original behaviour.
