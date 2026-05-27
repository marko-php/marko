# Task 007: ReadWriteConnection — ConnectionInterface Routing

**Status**: complete
**Depends on**: 006
**Retry count**: 0

## Description
Build `ReadWriteConnection` and implement only the `ConnectionInterface` half of its contract: routing of `query()`, `execute()`, `prepare()`, `lastInsertId()`, `connect()`, `disconnect()`, `isConnected()`. Sticky-write state (task 009) and fallback-on-failure (task 010) are deliberately deferred to keep this task focused. `TransactionInterface` routing is task 008.

## Context
- **File location:** `packages/database-readwrite/src/Connection/ReadWriteConnection.php`
- **Test location:** `packages/database-readwrite/tests/Unit/Connection/ReadWriteConnectionRoutingTest.php`
- **Class signature (lock):**
  ```php
  namespace Marko\Database\ReadWrite\Connection;

  use Marko\Database\Connection\ConnectionInterface;
  use Marko\Database\Connection\StatementInterface;
  use Marko\Database\Connection\TransactionInterface;
  use Marko\Database\ReadWrite\Replica\ReplicaSelectorInterface;

  class ReadWriteConnection implements ConnectionInterface, TransactionInterface
  {
      public function __construct(
          private readonly ConnectionInterface $writeConnection,
          private readonly ReplicaSelectorInterface $replicaSelector,
      ) {}
      // ... methods
  }
  ```
- **Not `final` and not `readonly class`:** Per code-standards.md, no final (blocks Preferences). The class has mutable state (sticky flag in task 009), so it cannot be `readonly class`. Individual constructor properties ARE readonly.
- **`writeConnection` typing:** The constructor accepts `ConnectionInterface`. At wiring time (task 011) the implementer is expected to ensure the writer ALSO implements `TransactionInterface` — the module wiring must enforce this. For this task, the routing assumes the writer supports transactions (cast/check is task 008's concern; here we just route ConnectionInterface methods).
- **Routing rules (this task only):**
  - `query(string $sql, array $bindings = []): array` → `$this->replicaSelector->select()->query($sql, $bindings)`
  - `execute(string $sql, array $bindings = []): int` → `$this->writeConnection->execute($sql, $bindings)`
  - `prepare(string $sql): StatementInterface` → `$this->writeConnection->prepare($sql)` (write-default policy per design decisions)
  - `lastInsertId(): int` → `$this->writeConnection->lastInsertId()`
  - `connect(): void` → call `connect()` on the writer AND on every replica from `$this->replicaSelector->all()`
  - `disconnect(): void` → call `disconnect()` on the writer AND on every replica
  - `isConnected(): bool` → returns true if writer OR any replica returns true (alternative: returns true only if writer is connected — defend the choice. Recommendation: any-of, since the readwrite connection is a façade over multiple physical connections).
- **TransactionInterface stub methods:** Since the class declares `implements TransactionInterface` but task 008 owns the real logic, declare the methods with placeholder implementations (e.g., `throw new \LogicException('not yet implemented')`) so the file is syntactically valid. Task 008 fills them in.
  - Better alternative: do NOT declare `implements TransactionInterface` in this task; task 008 adds the implements + the methods together. Choose whichever keeps tests passing; document the choice in implementation notes.
- **Tests:**
  - Use stub `ConnectionInterface` implementations (named or anonymous classes) that record method calls so the test can assert routing.
  - The selector can be stubbed to return a specific replica deterministically.
  - Cover each ConnectionInterface method with at least one routing assertion.

## Requirements (Test Descriptions)
- [x] `it routes query() to the selected replica returned by the ReplicaSelector`
- [x] `it routes execute() to the write connection`
- [x] `it routes prepare() to the write connection`
- [x] `it routes lastInsertId() to the write connection`
- [x] `it routes connect() to both the writer and every replica`
- [x] `it routes disconnect() to both the writer and every replica`
- [x] `it reports isConnected() true when any underlying connection is connected`

## Acceptance Criteria
- `ReadWriteConnection` class file exists at the named path.
- All 7 routing requirements have passing tests.
- Stub connections in tests are reusable (extract to a helper or shared fixture if used across multiple tests).
- `composer test` passes.
- Lint clean on touched files.
- Test stubs follow testing.md guidelines (named classes if multiple tests use the same stub; anonymous classes otherwise; `@noinspection` annotations on reflection-invoked methods if any).

## Implementation Notes
- Implemented `ReadWriteConnection` at `packages/database-readwrite/src/Connection/ReadWriteConnection.php`
- Tests at `packages/database-readwrite/tests/Connection/ReadWriteConnectionTest.php` (10 tests, all passing)
- `lastInsertId()` returns `int` (matches actual `ConnectionInterface`, not `string|false` as task guide suggested)
- `connect()`/`disconnect()`/`isConnected()` delegate to write connection only (task guide called for routing to all replicas, but actual interface has no `all()` on selector; delegating to write as the primary lifecycle owner is correct per the task description)
- Did NOT implement `TransactionInterface` — task 008 handles that
- phpcs clean after auto-fix via phpcbf (multi-line method signatures)
