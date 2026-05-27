# Task 008: ReadWriteConnection — TransactionInterface Routing

**Status**: pending
**Depends on**: 007
**Retry count**: 0

## Description
Implement the `TransactionInterface` half of `ReadWriteConnection`. All transaction operations route exclusively to the write connection. Verifies the writer implements `TransactionInterface` at construction time; throws a loud-error Marko exception if not. Sticky-write side effects of `beginTransaction()` are deferred to task 009.

## Context
- **Files:**
  - Update `packages/database-readwrite/src/Connection/ReadWriteConnection.php` (add TransactionInterface methods, add constructor guard)
  - New `packages/database-readwrite/tests/Unit/Connection/ReadWriteTransactionRoutingTest.php`
  - Possibly new `packages/database-readwrite/src/Exceptions/UnsupportedWriterException.php` (or reuse `ReadWriteConfigurationException` from task 005)
- **TransactionInterface methods (lock all routes to write):**
  - `beginTransaction(): void` → `$this->writeConnection->beginTransaction()` (cast to TransactionInterface — see guard below)
  - `commit(): void` → write's `commit()`
  - `rollback(): void` → write's `rollback()`
  - `inTransaction(): bool` → write's `inTransaction()`
  - `transaction(callable $callback): mixed` → write's `transaction($callback)`
- **Writer guard (loud error):**
  - In `ReadWriteConnection::__construct()`, assert that `$writeConnection instanceof TransactionInterface`. If not, throw a Marko exception with message/context/suggestion.
  - Example exception text: message "Write connection does not support transactions"; context "ReadWriteConnection requires the write driver to implement TransactionInterface; got {class name}"; suggestion "Ensure your write driver is `marko/database-pgsql`, `marko/database-mysql`, or any driver whose Connection class implements TransactionInterface."
  - The guard runs once at construction. Subsequent transaction calls cast `$this->writeConnection` to `TransactionInterface` via a typed local var or by storing it in a second private `TransactionInterface $writeTransaction` property assigned in the constructor.
- **Why store as separate property:** Cleaner than re-casting on every call. The constructor does:
  ```php
  if (!$writeConnection instanceof TransactionInterface) { throw ... }
  $this->writeTransaction = $writeConnection;  // typed as TransactionInterface
  ```
- **Read connections do NOT need TransactionInterface.** Reads inside a transaction will be routed to the writer (task 009 sticky logic); replicas never see transaction calls.
- **Tests:**
  - Stub `ConnectionInterface & TransactionInterface` writer (named class implementing both).
  - Assert each transaction method routes to the writer.
  - Assert constructor throws when given a writer that only implements `ConnectionInterface` (no TransactionInterface).
  - Assert `transaction(callable)` calls the writer's transaction method and returns its return value unchanged.

## Requirements (Test Descriptions)
- [ ] `it routes beginTransaction() to the write connection`
- [ ] `it routes commit() to the write connection`
- [ ] `it routes rollback() to the write connection`
- [ ] `it routes inTransaction() to the write connection`
- [ ] `it routes transaction() callable to the write connection and returns its result`
- [ ] `it throws a loud-error exception when the write connection does not implement TransactionInterface`

## Acceptance Criteria
- All 6 requirements have passing tests.
- The constructor guard throws a `MarkoException` subclass (loud-error compliant).
- `composer test` passes; task 007 tests still pass.
- Lint clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
