# Task 004: MySqlConnectionFactory in marko/database-mysql

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Mirror of task 003 for the MySQL driver. Add `MySqlConnectionFactory implements ConnectionFactoryInterface` to `packages/database-mysql/src/Connection/`. The factory's `make(DatabaseConfig $config)` returns a fresh `MySqlConnection` instance. Bind `ConnectionFactoryInterface => MySqlConnectionFactory` in the package's `module.php`. Purely additive.

## Context
- **Files to modify:**
  - New `packages/database-mysql/src/Connection/MySqlConnectionFactory.php`
  - Update `packages/database-mysql/module.php` — add the new binding
  - New `packages/database-mysql/tests/Connection/MySqlConnectionFactoryTest.php`
  - Update `packages/database-mysql/tests/Module/ModuleBindingsTest.php` (or equivalent — verify the test file path with `ls packages/database-mysql/tests/Module/`)
- **Mirror task 003's factory shape exactly** but for `MySqlConnection`:
  ```php
  namespace Marko\Database\MySql\Connection;

  final readonly class MySqlConnectionFactory implements ConnectionFactoryInterface
  {
      public function __construct(private string $charset = 'utf8mb4') {}

      public function make(DatabaseConfig $config): ConnectionInterface
      {
          return new MySqlConnection($config, $this->charset);
      }
  }
  ```
- **Charset default:** MySQL typically defaults to `utf8mb4`. Verify the actual default in `packages/database-mysql/src/Connection/MySqlConnection.php` constructor and match it.
- **Note for the implementer:** task 003 (pgsql factory) is structurally identical. If task 003 has already run, copy its shape and adapt for mysql. Sibling-modules.md REQUIRES the two factories to read as if written by the same person.

## Requirements (Test Descriptions)
- [ ] `it implements ConnectionFactoryInterface`
- [ ] `it returns a MySqlConnection instance from make()`
- [ ] `it threads the charset through to the constructed connection`
- [ ] `it produces a fresh connection on each make() call`
- [ ] `the mysql module.php binds ConnectionFactoryInterface to MySqlConnectionFactory`
- [ ] `existing ConnectionInterface binding to MySqlConnection is preserved`

## Acceptance Criteria
- Factory class lives at `packages/database-mysql/src/Connection/MySqlConnectionFactory.php`.
- `module.php` binds both the existing `ConnectionInterface => MySqlConnection` AND the new `ConnectionFactoryInterface => MySqlConnectionFactory`.
- Tests assert both factory behavior and the new module binding.
- `composer test` passes; all existing mysql tests still pass.
- `./vendor/bin/phpcs packages/database-mysql/` clean.
- `./vendor/bin/php-cs-fixer fix packages/database-mysql/ --dry-run --diff` clean.
- Reads as a mirror of task 003 — file names, structure, comment voice match exactly.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
