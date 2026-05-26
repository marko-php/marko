# Task 005: ReadWriteConnectionConfig (Nested Config + Validation)

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Build `ReadWriteConnectionConfig` — a readonly value object that loads the nested `connections.read` / `connections.write` / `read_strategy` keys from `config/database.php`, validates the structure with loud-error exceptions, and exposes typed accessors. This config object is the source of truth that drives the `module.php` boot wiring in task 011.

## Context
- **File location:** `packages/database-readwrite/src/Config/ReadWriteConnectionConfig.php`
- **Companion exception class:** `packages/database-readwrite/src/Exceptions/ReadWriteConfigurationException.php` extending `MarkoException` (look at `packages/database/src/Exceptions/ConfigurationException.php` for the existing pattern — `MarkoException` with `message` / `context` / `suggestion` named parameters per code-standards.md).
- **Config shape to parse:**
  ```php
  return [
      'driver' => 'readwrite',  // marker that readwrite override engages
      'connections' => [
          'write' => [
              'driver' => 'pgsql',
              'host' => '...',
              'port' => 5432,
              'database' => '...',
              'username' => '...',
              'password' => '...',
              // optional SSL fields
          ],
          'read' => [
              ['driver' => 'pgsql', 'host' => 'replica1', ...],
              ['driver' => 'pgsql', 'host' => 'replica2', ..., 'weight' => 3],
          ],
          'read_strategy' => 'random',  // or 'weighted'
      ],
  ];
  ```
- **What the config object exposes (lock):**
  - `public DatabaseConfig $writeConfig` — built via `DatabaseConfig::fromArray()` from `connections.write`
  - `public array $readConfigs` — array of `DatabaseConfig` objects, one per replica, built via `DatabaseConfig::fromArray()` from each `connections.read[i]`
  - `public array $readWeights` — array of int weights aligned by index to `$readConfigs`. If `weight` key missing on a replica, defaults to `1`.
  - `public string $readStrategy` — one of `'random'` or `'weighted'`. Defaults to `'random'` when key absent.
- **Validation rules (each must throw a Marko exception with message/context/suggestion):**
  1. Top-level `connections` key missing → throw
  2. `connections.write` missing → throw
  3. `connections.read` missing OR empty array → throw
  4. `connections.read` is not an indexed array (e.g., associative) → throw
  5. Any `weight` value that is not a positive int → throw
  6. `read_strategy` present and not in `['random', 'weighted']` → throw
  7. Any required per-connection key missing (driver/host/port/database/username/password) → throw (propagate from `DatabaseConfig::fromArray()`)
- **Activation guard (handled in task 011, NOT here):** `ReadWriteConnectionConfig` is ONLY instantiated when the top-level `driver === 'readwrite'`. The boot in task 011 bails before resolving this class otherwise. **Implication for this task:** the constructor MAY assume `connections` is present and fail loud if it isn't — the loud error in that case is a developer-protection net for someone who instantiates `ReadWriteConnectionConfig` directly without going through the boot guard.
- **Validation rule 8 (suspicious-config detection, helpful but not required):** If the top-level `driver` key IS present and is something other than `'readwrite'`, throw a clarifying error (`"ReadWriteConnectionConfig was constructed but the top-level driver is '$driver', not 'readwrite' — did you mean to instantiate DatabaseConfig instead?"`). This catches misuse — it does NOT replace the boot-level guard. Skip this rule if it adds complexity without value; the boot guard is the real protection.
- **Construction signature:** Takes `ProjectPaths $paths` like the existing `DatabaseConfig` does, loads `$paths->config . '/database.php'`, parses the nested keys. Mirror the existing `DatabaseConfig` constructor's file-loading pattern.
- **Loud-error guidance:** Every exception must explain WHAT was wrong (e.g., "Replica index 2 has weight value 'three', expected positive integer"), WHERE it came from ("While parsing connections.read in config/database.php"), and HOW to fix it ("Set 'weight' to an integer >= 1, or omit the key to default to 1").

## Requirements (Test Descriptions)
- [ ] `it loads write config from connections.write nested key`
- [ ] `it loads read configs from connections.read indexed array`
- [ ] `it defaults read_strategy to random when key is absent`
- [ ] `it accepts read_strategy of weighted`
- [ ] `it defaults each replica weight to 1 when key is absent`
- [ ] `it preserves explicit replica weights`
- [ ] `it throws when connections key is missing from the config file`
- [ ] `it throws when connections.write is missing`
- [ ] `it throws when connections.read is missing`
- [ ] `it throws when connections.read is an empty array`
- [ ] `it throws when a replica weight is zero or negative`
- [ ] `it throws when a replica weight is not an integer`
- [ ] `it throws when read_strategy is not random or weighted`

## Acceptance Criteria
- Config and exception classes follow code-standards.md (strict types, readonly class, constructor property promotion, no magic methods).
- All exceptions extend `MarkoException` with `message` / `context` / `suggestion` named-parameter shape (mirror `ConfigurationException`).
- Tests use temp directories with fixture `database.php` files (mirror the pattern in `packages/database-pgsql/tests/Module/ModuleBindingsTest.php` for the temp-dir technique).
- `composer test` passes.
- `./vendor/bin/phpcs packages/database-readwrite/` and `./vendor/bin/php-cs-fixer fix packages/database-readwrite/ --dry-run --diff` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
