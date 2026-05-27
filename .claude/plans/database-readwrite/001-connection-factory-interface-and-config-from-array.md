# Task 001: ConnectionFactoryInterface + DatabaseConfig::fromArray()

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Add two purely additive primitives to `marko/database` that enable the readwrite package to construct multiple underlying driver connections from raw config arrays: (1) a new `ConnectionFactoryInterface` defining `make(DatabaseConfig $config): ConnectionInterface`, and (2) a `DatabaseConfig::fromArray(array $config): self` static factory that runs the same validation as the existing file-loading constructor but accepts a raw array.

## Context
- **Files to modify:**
  - `packages/database/src/Connection/` — new `ConnectionFactoryInterface.php`
  - `packages/database/src/Config/DatabaseConfig.php` — add static `fromArray()` factory
  - `packages/database/tests/` — new tests for both
- **Why additive only:** Existing apps using `DatabaseConfig` via the constructor continue to work. Existing drivers binding `ConnectionInterface => {Driver}Connection` are untouched. The new interface has no existing binding to conflict with.
- **Interface shape (lock):**
  ```php
  namespace Marko\Database\Connection;

  interface ConnectionFactoryInterface
  {
      public function make(DatabaseConfig $config): ConnectionInterface;
  }
  ```
- **`DatabaseConfig::fromArray()` shape (lock):** Static factory accepting the same key shape the file-loading path expects (`driver`, `host`, `port`, `database`, `username`, `password`, and optional SSL fields). Validation MUST be equivalent to the existing constructor — missing required keys throw `ConfigurationException::missingRequiredKey()`, incomplete SSL key pair throws `ConfigurationException::incompleteSslKeyPair()`. Use the same constants the existing constructor uses.
- **Implementation hint:** `DatabaseConfig` is currently a `readonly class` whose constructor takes `ProjectPaths $paths`, reads `config/database.php`, validates required keys, and assigns properties. Refactor strategy:
  1. Extract a **private static** helper `private static function validateConfigArray(array $config): void` that runs the required-key + SSL-pair checks (raising `ConfigurationException` exactly as today).
  2. Refactor the existing constructor so it (a) reads the file → array, (b) calls `validateConfigArray($array)`, (c) assigns `$this->driver = $array['driver']`, etc. The constructor signature does NOT change.
  3. Add `public static function fromArray(array $config): self`. Inside, do the same validation, then `new self(...)`. **But:** the constructor reads from a file. To avoid that, introduce a **second private constructor path** by adding a sentinel-free factory method: have `fromArray()` use `(new ReflectionClass(self::class))->newInstanceWithoutConstructor()` to create the object, then assign each readonly property in `fromArray()` itself. This works because `fromArray` is in the same class scope, and PHP allows readonly property assignment from within the declaring class once.
  4. **Alternative (cleaner if existing callers are few):** Change the constructor to accept the validated array directly: `__construct(array $config)`. Add a NEW static `static fromPaths(ProjectPaths $paths): self` that loads the file then calls the constructor. Migrate existing callers (`packages/database/module.php` and elsewhere) from `new DatabaseConfig($paths)` to `DatabaseConfig::fromPaths($paths)`. This breaks backwards compatibility for any user code that calls `new DatabaseConfig($paths)` directly — search the codebase before choosing this path: `grep -rn 'new DatabaseConfig' packages/ --include='*.php'`. If usage is internal-only, this is the cleanest refactor.
  5. **Pick whichever strategy** keeps tests green and minimizes risk. Document the choice in Implementation Notes. The reflection approach (option 3) is safer for backwards compat; the constructor change (option 4) is cleaner long-term.

Do NOT change the existing constructor's PUBLIC signature unless option 4 is chosen AND all internal callers are migrated in the same task.

## Requirements (Test Descriptions)
- [x] `it defines ConnectionFactoryInterface with a make method returning ConnectionInterface`
- [x] `it builds a DatabaseConfig from an array with all required keys`
- [x] `it throws ConfigurationException when a required key is missing from the array`
- [x] `it throws ConfigurationException when ssl_cert is provided without ssl_key`
- [x] `it throws ConfigurationException when ssl_key is provided without ssl_cert`
- [x] `it populates SSL fields when provided`
- [x] `it produces a DatabaseConfig with identical property values to the file-loaded path given equivalent input`

## Acceptance Criteria
- `packages/database/src/Connection/ConnectionFactoryInterface.php` exists with `declare(strict_types=1)`, namespaced correctly, and the `make()` signature above.
- `DatabaseConfig::fromArray()` static method exists, returns `self`, performs the same validation as the constructor.
- New tests live under `packages/database/tests/Unit/` (mirror existing test layout — verify by `ls packages/database/tests/`).
- `composer test` passes with all new tests green.
- `./vendor/bin/phpcs packages/database/` clean.
- `./vendor/bin/php-cs-fixer fix packages/database/src/ packages/database/tests/ --dry-run --diff` clean.
- No existing `DatabaseConfig` test breaks.

## Implementation Notes
Used reflection strategy (option 3) to preserve backward compatibility of the existing `__construct(ProjectPaths $paths)` signature. `fromArray()` uses `ReflectionClass::newInstanceWithoutConstructor()` to create an uninitialized instance, then assigns each readonly property via `ReflectionProperty::setValue()` — permitted in PHP 8.1+ for uninitialized readonly properties within the declaring class scope. Extracted `validateConfigArray(array $config): void` as a private static helper shared by both the constructor and `fromArray()`. All 7 new tests live in `packages/database/tests/Unit/`.
