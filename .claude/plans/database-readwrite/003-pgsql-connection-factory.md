# Task 003: PgSqlConnectionFactory in marko/database-pgsql

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Add `PgSqlConnectionFactory implements ConnectionFactoryInterface` to `packages/database-pgsql/src/Connection/`. The factory's `make(DatabaseConfig $config)` returns a fresh `PgSqlConnection` instance built from the given config. Bind `ConnectionFactoryInterface => PgSqlConnectionFactory` in the package's `module.php`. Purely additive: no existing class, signature, or binding changes.

## Context
- **Files to modify:**
  - New `packages/database-pgsql/src/Connection/PgSqlConnectionFactory.php`
  - Update `packages/database-pgsql/module.php` — add the new binding alongside the existing 5 bindings
  - New `packages/database-pgsql/tests/Connection/PgSqlConnectionFactoryTest.php`
  - Update `packages/database-pgsql/tests/Module/ModuleBindingsTest.php` — add an assertion for the new binding
- **Factory shape (lock):**
  ```php
  namespace Marko\Database\PgSql\Connection;

  final readonly class PgSqlConnectionFactory implements ConnectionFactoryInterface
  {
      public function __construct(private string $charset = 'utf8') {}

      public function make(DatabaseConfig $config): ConnectionInterface
      {
          return new PgSqlConnection($config, $this->charset);
      }
  }
  ```
- **Why `final readonly`:** The factory is a value-style stateless service. Marking final is acceptable here because it's a leaf concrete class with no extension story (CLAUDE.md "no final" guidance is about blocking Preferences; Preferences on a factory are unusual — but verify this with the standards-enforcer in post-implementation. If `final` is rejected, drop it.)
- **Charset handling:** `PgSqlConnection` accepts an optional `string $charset = 'utf8'`. The factory should be able to thread the charset through. Default to `'utf8'` matching the connection's default.
- **Binding addition in module.php:** Add `ConnectionFactoryInterface::class => PgSqlConnectionFactory::class` to the existing bindings array. No change to the existing 5 bindings.

## Requirements (Test Descriptions)
- [ ] `it implements ConnectionFactoryInterface`
- [ ] `it returns a PgSqlConnection instance from make()`
- [ ] `it threads the charset through to the constructed connection`
- [ ] `it produces a fresh connection on each make() call`
- [ ] `the pgsql module.php binds ConnectionFactoryInterface to PgSqlConnectionFactory`
- [ ] `existing ConnectionInterface binding to PgSqlConnection is preserved`

## Acceptance Criteria
- Factory class lives at `packages/database-pgsql/src/Connection/PgSqlConnectionFactory.php`.
- `module.php` binds both `ConnectionInterface => PgSqlConnection` (existing) AND `ConnectionFactoryInterface => PgSqlConnectionFactory` (new).
- Tests assert both the factory behavior and the new module binding.
- `composer test` passes; all existing pgsql tests still pass.
- `./vendor/bin/phpcs packages/database-pgsql/` clean.
- `./vendor/bin/php-cs-fixer fix packages/database-pgsql/ --dry-run --diff` clean.
- No new construction of a real PDO in the test — the factory's `make()` returning an instance is enough; do not call `connect()` in the test.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
