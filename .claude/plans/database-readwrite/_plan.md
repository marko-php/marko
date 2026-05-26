# Plan: marko/database-readwrite

## Created
2026-05-26

## Status
completed

## Objective
Ship `marko/database-readwrite` — an opt-in sibling driver package that decorates `ConnectionInterface` to route reads to replica connections and writes to a primary connection, with sticky writes (transaction + per-request), single-request fallback on replica failure, and pluggable replica selection (random default, weighted option). Resolves GitHub issue #4.

## Related Issues
Closes #4

## Discovery Notes

**Architectural verification:**
- `ConnectionInterface` (`packages/database/src/Connection/ConnectionInterface.php`) has 6 methods. `query()` returns array (read), `execute()` returns int affected rows (write), `prepare()` returns `StatementInterface` (ambiguous — see policy below), `lastInsertId()` (write), `connect()`/`disconnect()`/`isConnected()` (lifecycle).
- `TransactionInterface` (`packages/database/src/Connection/TransactionInterface.php`) is a separate interface implemented alongside `ConnectionInterface` by drivers (`PgSqlConnection implements ConnectionInterface, TransactionInterface`). `ReadWriteConnection` must do the same to be a drop-in replacement.
- **`TransactionInterface` is NOT separately bound today.** Verified: `packages/database-pgsql/module.php` and `packages/database-mysql/module.php` bind only `ConnectionInterface::class => {Driver}Connection::class`. Consumers (`Repository::save()` line 335, `SeederRunner` via `database/module.php` line 50-52) use either `instanceof TransactionInterface` checks on the resolved connection OR `$container->has(TransactionInterface::class)` (which returns false today because no module binds it). Binding `TransactionInterface` separately in the readwrite boot IS a precedent — surfaced to maintainer in task 011 for explicit acknowledgment.
- `grep -rn '->prepare(' packages/ --include='*.php' | grep -v tests/` confirms **zero production callers** of `Connection::prepare()` outside the driver implementations. Policy decided: always route to write.
- `DatabaseConfig` is flat and single-connection. We do NOT mutate the existing shape; instead the readwrite package owns its own `ReadWriteConnectionConfig` reading nested `connections.read[]` / `connections.write` keys. We add a single additive static factory `DatabaseConfig::fromArray()` so the readwrite package can build configs for the underlying driver connections from raw arrays.
- `PgSqlQueryBuilder` and the framework's other database consumers use only `query()` and `execute()` — they route cleanly through the decorator with no changes.
- The boot-callback override pattern shipped in #31 (PR #85) is the mechanism used by the readwrite package's `module.php` to override `ConnectionInterface => ReadWriteConnection` without triggering `BindingConflictException`. **Container API confirmed:** `Container::instance(string $id, object $instance): void` exists at `packages/core/src/Container/Container.php:69-74` and stores in `$this->instances[]`; `resolve()` returns the stored instance unchanged on line 115-117. This is the correct API for sticky-stateful singletons.
- **Driver `query()` exception surface verified.** `PgSqlConnection::query()` (lines 140-151) does NOT catch PDOException — it bubbles. But `ensureConnected()` (line 130-135) can call `connect()` which catches `\Throwable` and rethrows as `Marko\Database\PgSql\Exceptions\ConnectionException` (line 52-58). So a replica that cannot CONNECT throws `ConnectionException` (a `MarkoException`), NOT `PDOException`. The fallback catch list in task 010 must include both. MySQL driver mirrors this pattern.

**Wiring challenge & solution:**
The readwrite package needs to construct TWO+ underlying driver connections (one writer, N readers) without conflicting with the underlying driver's existing single-connection binding. Solution:
1. **New `ConnectionFactoryInterface` in `marko/database`** (additive) — defines `make(DatabaseConfig $config): ConnectionInterface`.
2. **Per-driver factory classes** — `PgSqlConnectionFactory` and `MySqlConnectionFactory` implement the interface. Each driver binds `ConnectionFactoryInterface => {Driver}ConnectionFactory` in its `module.php`.
3. **`DatabaseConfig::fromArray()` static factory** (additive) — lets the readwrite package build `DatabaseConfig` instances from the nested config arrays for each underlying connection.
4. **Readwrite `module.php` boot callback** — reads the nested config, uses the registered `ConnectionFactoryInterface` to build write + read connections, wraps in `ReadWriteConnection`, calls `$container->bind(ConnectionInterface::class, $instance)` to override.

**Conflict consideration:** If a user installs both `marko/database-pgsql` AND `marko/database-mysql`, both drivers will bind `ConnectionFactoryInterface => {Driver}ConnectionFactory` at vendor priority, triggering `BindingConflictException`. This already happens today for `ConnectionInterface` — installing two drivers is not supported. The new factory binding follows the same rule.

**Sticky-write lifecycle:**
- HTTP requests (PHP-FPM): `ReadWriteConnection` is a singleton with the lifetime of the script. Sticky flag is automatically reset between requests by the natural process lifecycle. No middleware needed.
- Long-running processes (queue workers, Swoole, RoadRunner): `resetStickyState()` is a public method on `ReadWriteConnection` that users (or future integration) can call between jobs. **v1 ships the method but does not auto-wire it into queue/HTTP middleware** — that's a follow-up if/when needed.
- Transactions: `beginTransaction()` sets sticky to true; the flag stays set after `commit()`/`rollback()` because if you wrote in a transaction, you may want to read your own writes for the rest of the request. Sticky is only cleared by `resetStickyState()`.

**Config schema:**
```php
// config/database.php (when database-readwrite is installed)
return [
    'driver' => 'readwrite',
    'connections' => [
        'write' => [
            'driver' => 'pgsql',
            'host' => 'primary.db',
            'port' => 5432,
            'database' => 'app',
            'username' => '...',
            'password' => '...',
        ],
        'read' => [
            ['driver' => 'pgsql', 'host' => 'replica1.db', 'port' => 5432, ...],
            ['driver' => 'pgsql', 'host' => 'replica2.db', 'port' => 5432, 'weight' => 3],
        ],
        'read_strategy' => 'random', // or 'weighted'
    ],
];
```

The `driver` per connection allows (rare but possible) cross-driver pairing (e.g., MySQL writer with MySQL replicas via different DSN). The package's `module.php` registers itself for `'driver' => 'readwrite'` at the top level; when that's set, the readwrite override engages.

## Scope

### In Scope
- New package `packages/database-readwrite/` with composer.json, module.php, src/, tests/, README, LICENSE, .gitattributes.
- Additive changes to `marko/database`: `ConnectionFactoryInterface`, `DatabaseConfig::fromArray()` static factory. Zero changes to existing public method signatures.
- Additive changes to `marko/database-pgsql` and `marko/database-mysql`: `{Driver}ConnectionFactory` class + binding in `module.php`.
- `ReadWriteConnection implements ConnectionInterface, TransactionInterface` with full method routing.
- Sticky-write tracking: transaction-triggered + write-triggered, public `resetStickyState()`.
- Single-request fallback on read failure (try next replica on `PDOException`, throw loud error when all exhausted).
- `ReplicaSelectorInterface` with `RandomReplicaSelector` and `WeightedReplicaSelector` implementations.
- `ReadWriteConnectionConfig` reading nested keys with loud-error validation.
- Integration tests proving full wiring over pgsql and over mysql.
- README per Package README Standards.
- Docs page at `docs/src/content/docs/packages/database-readwrite.md`.
- Root `composer.json` path repositories, `PackagingTest`, `IntegrationVerificationTest`, issue-template entries updated for the new package.

### Out of Scope
- Cross-request circuit-breaker / health-aware replica skipping (deferred; ops-layer concern; requires shared state store).
- Auto-reset of sticky state in queue worker / Swoole / RoadRunner middleware (`resetStickyState()` ships; auto-wiring is follow-up).
- Per-query routing override (e.g., a `forceRead()` modifier on the query builder).
- Multi-tenant / scoped connections.
- Connection pooling.
- Database failover / automatic primary election.
- Read-from-writer fallback when all replicas fail (currently throws; fallback could be a v2 config flag).
- Modifications to `ConnectionInterface`, `TransactionInterface`, `StatementInterface`, or any existing method signature on `DatabaseConfig`.

## Success Criteria
- [ ] `ReadWriteConnection` implements both `ConnectionInterface` and `TransactionInterface`.
- [ ] `query()` routes to a read replica via the configured `ReplicaSelectorInterface`.
- [ ] `execute()`, `prepare()`, `lastInsertId()`, transaction methods route to the write connection.
- [ ] Sticky-write flag activates on any write or transaction begin; `query()` honors it; `resetStickyState()` clears it.
- [ ] Single-request fallback: a `PDOException` from one replica routes the same query to the next replica; loud-error exception when all replicas exhausted.
- [ ] Random and Weighted selectors both work; selector is chosen by `read_strategy` config.
- [ ] Config validation throws Marko exceptions with message/context/suggestion for: missing write, empty read array, invalid weight (≤ 0 or non-int), unknown driver per connection, unknown `read_strategy`.
- [ ] Integration test proves end-to-end wiring over pgsql.
- [ ] Integration test proves end-to-end wiring over mysql.
- [ ] `composer test` passes including all new tests and zero regressions in existing tests.
- [ ] `./vendor/bin/phpcs` and `./vendor/bin/php-cs-fixer fix --dry-run` clean on touched files.
- [ ] `npm --prefix docs run build` passes with no new warnings.
- [ ] Package README follows Package README Standards (slim pointer: title, install, quick example, docs link).
- [ ] Docs page at `packages/database-readwrite.md` covers installation, config schema, sticky-write semantics, `prepare()` policy, replica strategies, fallback behavior.
- [ ] PR opened against `develop` with "Closes #4" in the body.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add ConnectionFactoryInterface + DatabaseConfig::fromArray() to marko/database | - | completed |
| 002 | Package scaffolding (composer.json, module.php placeholder, LICENSE, root config, packaging tests) | - | completed |
| 003 | PgSqlConnectionFactory in marko/database-pgsql | 001 | completed |
| 004 | MySqlConnectionFactory in marko/database-mysql | 001 | completed |
| 005 | ReadWriteConnectionConfig (nested config loading + loud-error validation) | 002 | completed |
| 006 | ReplicaSelectorInterface + RandomReplicaSelector + WeightedReplicaSelector | 002 | completed |
| 007 | ReadWriteConnection — ConnectionInterface methods routing (query/execute/prepare/lastInsertId/connect/disconnect/isConnected) | 006 | completed |
| 008 | ReadWriteConnection — TransactionInterface methods routing (begin/commit/rollback/inTransaction/transaction) | 007 | completed |
| 009 | Sticky-write state on ReadWriteConnection (flag, write triggers, transaction triggers, resetStickyState()) | 008 | completed |
| 010 | Single-request fallback in ReadWriteConnection query routing | 008 | completed |
| 011 | module.php boot callback wiring (build underlying connections via factory, construct selector + ReadWriteConnection, override ConnectionInterface binding) | 003, 004, 005, 009, 010 | completed |
| 012 | Integration test over pgsql wiring | 011 | completed |
| 013 | Integration test over mysql wiring | 011 | completed |
| 014 | Docs page (packages/database-readwrite.md) | 011 | completed |
| 015 | README per Package README Standards (final) | 002, 011, 012, 013, 014 | completed |

## Cross-Doc Updates (handled by doc-updater in post-implementation pipeline)
- `docs/src/content/docs/packages/database.md` — the "Wire-compatible variants" section (line 876) lists the 5-binding split. With this plan, `ConnectionFactoryInterface` becomes a sibling new-binding (additive, optional to override). Doc-updater should add a brief pointer paragraph linking to the new `database-readwrite.md` page as another extension pattern (decoration vs. dialect-replacement).
- `.claude/architecture.md` — package inventory should grow a "Database Read/Write Split" entry.
- The github-slugger anchor for the readwrite docs page section "Customization / Preferences" should remain stable (`#customization--preferences` or similar default slug — the docs page in task 014 cross-links TO `dependency-injection.md#overriding-another-modules-bindings` which is the only externally-referenced anchor; no other plan tasks hardcode anchors INTO the readwrite docs page).

## Architecture Notes

- **Additive-only principle:** Every change to `marko/database`, `marko/database-pgsql`, and `marko/database-mysql` is purely additive (new interface, new static factory, new factory classes, new bindings). No method signature or class shape changes on the existing public API. Existing apps continue working unchanged.
- **Override mechanism:** Readwrite's `module.php` uses the boot-callback pattern documented in `concepts/dependency-injection.md#overriding-another-modules-bindings` to override `ConnectionInterface` without triggering `BindingConflictException`.
- **Driver coupling:** The readwrite package depends on `marko/database` (the interface package) only. It resolves `ConnectionFactoryInterface` from the container — the actual driver factory is provided by whichever driver the user installed (pgsql, mysql, future). No hard dependency on any specific driver.
- **Cross-driver pairing:** The per-connection `driver` key in config allows different drivers for writer vs readers (rare but supported by virtue of factory-based construction). Whoever resolves `ConnectionFactoryInterface` provides the only available factory; multi-driver setups would need a future enhancement.
- **Sticky-write singleton lifecycle:** `ReadWriteConnection` is bound as a singleton (matching the existing `ConnectionInterface` binding lifecycle). In PHP-FPM, the singleton's lifetime equals the request — sticky state auto-resets. Long-running process integration is a documented follow-up.

## Risks & Mitigations
- **Risk:** Adding `ConnectionFactoryInterface` to `marko/database` triggers binding conflict if user installs both pgsql AND mysql drivers. **Mitigation:** This already happens for `ConnectionInterface` — multi-driver installation is unsupported and fails loud. Document explicitly in the readwrite docs page. The factory binding inherits the same constraint.
- **Risk:** `DatabaseConfig::fromArray()` could become a back door for bypassing config file validation. **Mitigation:** The static factory runs the same validation as the constructor; missing required keys throw `ConfigurationException`. Add a test asserting the validation is equivalent.
- **Risk:** Sticky-write singleton lifecycle assumption (PHP-FPM = per-request) breaks in long-running processes. **Mitigation:** Ship `resetStickyState()` as a public method, document the long-running-process caveat clearly in the docs page, defer auto-wiring to a follow-up.
- **Risk:** `WeightedReplicaSelector` with all-zero weights throws division by zero or selects nothing. **Mitigation:** Config validation enforces `weight > 0`; the validator throws a Marko exception during boot if any weight is ≤ 0 or non-int. Document in the loud-error message.
- **Risk:** Test fixture connections in integration tests could accidentally hit a real database. **Mitigation:** Use mock implementations of `ConnectionInterface` (anonymous classes or named test stubs); never construct real `PgSqlConnection` or `MySqlConnection` against a real DSN in unit/integration tests. Reserve real-database verification for `tests/IntegrationVerificationTest.php` if at all.
- **Risk:** Single-request fallback masks real connection issues (silently retries on permanent failures). **Mitigation:** Only catch `PDOException` and only for the duration of one query's replica iteration; never retry on the writer; loud-error exception when all replicas exhausted with a list of which ones failed and why.
- **Risk:** Adding `ConnectionFactoryInterface` binding in pgsql and mysql `module.php` is an existing-package change — could conflict with the "additive-only to existing packages" claim. **Mitigation:** A new binding for a new interface IS additive — no existing binding or method is touched. Existing tests in those packages stay green; we add a new test for the factory binding.
