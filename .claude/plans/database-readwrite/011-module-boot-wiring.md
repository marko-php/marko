# Task 011: module.php Boot Callback Wiring

**Status**: pending
**Depends on**: 003, 004, 005, 009, 010
**Retry count**: 0

## Description
Wire the readwrite package's `module.php` boot callback. At boot time, read `ReadWriteConnectionConfig`, build the writer + replica connections via the registered `ConnectionFactoryInterface`, construct the configured `ReplicaSelectorInterface` (random or weighted), assemble `ReadWriteConnection`, and override `ConnectionInterface` (and `TransactionInterface`) bindings in the container via direct `$container->bind()` calls (the proven boot-override pattern from #31).

## Context
- **File to update:** `packages/database-readwrite/module.php`
- **Additional new file:** `packages/database-readwrite/src/Connection/ReadWriteConnectionBuilder.php` — extract the boot logic into a builder class so it can be unit-tested without spinning up the full module pipeline. The boot closure just calls `ReadWriteConnectionBuilder::build($container)` (or similar).
- **Container API confirmed (read packages/core/src/Container/Container.php:69-74):** `Container::instance(string $id, object $instance): void` exists and stores the instance in a dedicated array (`$this->instances[$id]`); subsequent `resolve($id)` calls return that exact object first (line 115-117), bypassing the binding lookup entirely. This is the correct API for "register this specific object" — exactly what we need so the sticky flag persists across resolves.

- **Boot callback logic (pseudocode — note the driver check happens FIRST):**
  ```php
  return [
      'singletons' => [ReadWriteConnectionConfig::class],
      'boot' => function (Container $container, ProjectPaths $paths): void {
          // STEP 1: Cheap check — does config opt-in? Bail without resolving ReadWriteConnectionConfig.
          // (Resolving ReadWriteConnectionConfig would throw because `connections` key is absent
          //  when driver !== 'readwrite'.)
          $rawConfig = require $paths->config . '/database.php';
          if (($rawConfig['driver'] ?? null) !== 'readwrite') {
              return; // no-op: app installed the package but didn't engage it
          }

          // STEP 2: Now safe to resolve the validated config.
          $config = $container->get(ReadWriteConnectionConfig::class);
          $factory = $container->get(ConnectionFactoryInterface::class);

          $writeConnection = $factory->make($config->writeConfig);
          $readConnections = array_map(
              fn (DatabaseConfig $cfg) => $factory->make($cfg),
              $config->readConfigs,
          );

          $selector = match ($config->readStrategy) {
              'random' => new RandomReplicaSelector($readConnections),
              'weighted' => new WeightedReplicaSelector($readConnections, $config->readWeights),
          };

          $readWriteConnection = new ReadWriteConnection($writeConnection, $selector);

          // Use Container::instance() so the SAME object is returned every resolve.
          // ReadWriteConnection holds mutable sticky state — a closure-based bind that
          // captures the instance would also work, but instance() is the semantic fit.
          $container->instance(ConnectionInterface::class, $readWriteConnection);
          $container->instance(TransactionInterface::class, $readWriteConnection);
      },
  ];
  ```
- **Why `instance()`:** Confirmed above — see Container.php:69-74. The method exists and is the right tool. Do NOT use `bind()` with a class string here: the readwrite connection requires constructor arguments (writer, selector) that aren't auto-resolvable, AND mutable sticky state means we MUST hand back the SAME object. `instance()` does exactly that.

- **Why TransactionInterface gets the same instance — PRECEDENT NOTE:** This is a deliberate departure from the existing pgsql/mysql drivers, which currently bind ONLY `ConnectionInterface::class => PgSqlConnection::class` and rely on consumers doing `$conn instanceof TransactionInterface` at call sites (see `packages/database/src/Repository/Repository.php:335` and `packages/database/module.php:50-52` for the runtime `instanceof` / `$container->has()` pattern). By binding `TransactionInterface` separately, the readwrite package sets a precedent. Justification: the consuming code in `SeederRunner` already gracefully handles the case where `TransactionInterface` is bound (`$container->has(TransactionInterface::class)` returns true), and the readwrite façade benefits from container-level resolution rather than runtime type-checking. The behavior is consistent (resolving either interface returns the same object that implements both). **Surfaced for human review:** if the maintainer prefers NOT to set this precedent, drop the `TransactionInterface` instance() call and require consumers to use `$conn instanceof TransactionInterface` checks instead — sticky state still works because there's only ever one resolution path through `ConnectionInterface`.

- **Builder API:** Extract the boot logic into `ReadWriteConnectionBuilder` with a clear seam for the no-op path:
  ```php
  final class ReadWriteConnectionBuilder
  {
      public function build(Container $container, ProjectPaths $paths): void
      {
          $rawConfig = require $paths->config . '/database.php';
          if (($rawConfig['driver'] ?? null) !== 'readwrite') {
              return;
          }
          // ...rest of wiring as above
      }
  }
  ```
  This makes the no-op branch unit-testable without spinning up a full module pipeline.
- **Tests:**
  - Unit test the `ReadWriteConnectionBuilder::build($container)` with a stubbed container holding fake `ConnectionFactoryInterface` and `ReadWriteConnectionConfig`.
  - Assert the resulting `ReadWriteConnection` has the expected writer and replicas wired in.
  - Assert the no-op behavior when `driver !== 'readwrite'`.
  - Full integration with the real module pipeline is tasks 012 and 013.

## Requirements (Test Descriptions)
- [ ] `it builds a ReadWriteConnection from config using the registered ConnectionFactoryInterface`
- [ ] `it wires the write connection from connections.write config`
- [ ] `it wires read connections from connections.read config array`
- [ ] `it uses RandomReplicaSelector when read_strategy is random`
- [ ] `it uses WeightedReplicaSelector with the configured weights when read_strategy is weighted`
- [ ] `it overrides the ConnectionInterface binding with the ReadWriteConnection instance`
- [ ] `it overrides the TransactionInterface binding with the same ReadWriteConnection instance`
- [ ] `it does nothing when the top-level driver key is not readwrite`

## Acceptance Criteria
- `module.php` returns a valid manifest with at minimum `boot` and `singletons` keys.
- `ReadWriteConnectionBuilder` is a testable class that the boot callback delegates to.
- All 8 requirements have passing tests using container/factory stubs.
- The boot does not invoke any real PDO connection (use stubs for the factory).
- `composer test` passes; no regressions in any other package.
- Lint clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
