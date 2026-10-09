# Testing Configuration

## Test Framework
**Pest PHP 4** - Modern, expressive testing framework built on PHPUnit 12.

### Requirements
- PHP 8.3+ (Marko uses PHP 8.5)
- PHPUnit 12 (bundled with Pest 4)

### Installation
```bash
composer require pestphp/pest --dev
```

## Commands

```bash
# Run tests (fast — excludes slow destructive integration tests). Prefer this during development.
composer test

# Run all tests including destructive integration tests
composer test:all

# Run specific test file
./vendor/bin/pest tests/Unit/Container/ContainerTest.php

# Run tests in a specific directory
./vendor/bin/pest tests/Unit/

# Run tests matching a filter
./vendor/bin/pest --filter="resolves dependencies"

# Run with coverage
./vendor/bin/pest -c phpunit.xml --parallel --exclude-group=integration-destructive --coverage

# Run with coverage and minimum threshold
./vendor/bin/pest -c phpunit.xml --parallel --exclude-group=integration-destructive --coverage --min=80

# Run in parallel (faster) — equivalent to composer test:all
./vendor/bin/pest --parallel

# Run with test sharding (for CI)
./vendor/bin/pest --shard=1/4
./vendor/bin/pest --parallel --shard=2/4

# Type coverage (2x faster in Pest 4)
./vendor/bin/pest --type-coverage

# Check for profanity in test code
./vendor/bin/pest --profanity
```

## The `integration-destructive` Test Group

Tests tagged `->group('integration-destructive')` live in `tests/IntegrationVerificationTest.php`. They verify the clean-install workflow end-to-end by:

1. Deleting `vendor/` and `composer.lock`
2. Running `composer update` to re-resolve dependencies from scratch
3. Running the full test suite in a subprocess against the freshly-resolved state

Because they mutate the working directory, they're excluded from `composer test` by default. Use `composer test:all` to include them.

### When to run them

- **Before tagging a release** — verifies a fresh install still resolves and passes (`bin/release.sh` runs the full group automatically).
- **After changing the root `composer.json`** — bumping PHP version, adding/removing dependencies, or changing version constraints.
- **After changing any package `composer.json` that affects resolution** — new cross-package `require`, constraint bumps, replace/conflict declarations.
- **When investigating dependency-resolution oddities** — to confirm the committed lock file actually installs cleanly from scratch.

### When NOT to run them

- Normal feature or bug-fix work on application logic — they don't exercise application code, they exercise the install pipeline, and they will leave your local `composer.lock` potentially updated to newer transitive versions.

### Side effect to be aware of

Running the destructive group updates your working directory's `composer.lock` (whatever `composer update` currently resolves). Commit any intended lock changes, or `git checkout composer.lock vendor/` to restore if you didn't mean to bump.

## Integration Tests (`integration-services` Group)

Unit tests build classes by hand with fakes. They cannot catch wiring bugs in `module.php` or bugs that only show up with a real driver. The `integration-services` group covers those. Every test that needs a real service belongs to this one group:

- **The fixture app** (`tests/Integration/App`): boots a real application through `Application::boot()`, with the real module discovery and `module.php` wiring, against real Postgres and Redis.
- **Driver integration tests** (`packages/database-pgsql/tests/Integration`, `packages/database-mysql/tests/Integration`): savepoints, row locks, upsert, constraint violations, concurrency errors and migrations against a real PostgreSQL or MySQL server.
- **Live Redis tests** (`packages/pubsub-redis/tests/Integration/SharedConnectionLiveTest.php`, `packages/broadcasting-amphp/tests/Feature/RedisLiveTest.php`).

### Layout

| Path | Purpose |
|------|---------|
| `tests/Integration/App/Fixture/` | The fixture project: the `app/integration` module (entities, repositories, seeder, jobs, observers, scheduled tasks, routes), `config/*.php` and `database/migrations/` |
| `tests/Integration/App/Helpers.php` | Harness functions, autoloaded through `autoload-dev.files` |
| `tests/Integration/App/HarnessTest.php` | Tests for the harness itself. Needs no services, so it always runs |
| `tests/Integration/App/*Test.php` | Cases grouped by topic (`ServicesTest`, `TransactionsTest`, `QueueTest`, `SchedulerTest`, `RateLimitTest`, `AuthTest`, `ErrorMappingTest`, ...), each tagged `->issue(N)` with the ticket it covers |
| `tests/Integration/App/QueueSessionFixture/` | A third fixture with only marko/queue-database, marko/session-database and one database driver. `QueueSessionTablesPgSqlTest` (fixture server, its own `_tables` database) and `QueueSessionTablesMySqlTest` (`MARKO_TEST_MYSQL_*` server, its own `<database>_tables` database) run a fresh `db:migrate` against it and share their cases from `QueueSessionTables.php` |
| `tests/Integration/App/DatabaseTestingFixture/` | A second, smaller fixture for `RefreshDatabaseTest` and `TruncateDatabaseTest`. `databaseTestingProject()` builds it once per process with its own `_dbtesting` database, because `TestDatabase` boots and migrates once and keeps its connection open |
| `tests/Integration/compose.yml` | Postgres 17, MySQL 8.4 and Redis 7 for local runs, plus MariaDB 11.8 under the `mariadb` profile |
| `tests/Integration/postgres-init/` | Creates the `marko_test` database the pgsql driver tests use |

Each fixture test copies the fixture into a fresh temporary directory and links the packages listed in `INTEGRATION_MODULES` into its `vendor/marko/` as symlinks. The test then drops and recreates its Postgres database and boots the app. Nothing is written into the repository. Each parallel worker gets its own database (`marko_integration_<TEST_TOKEN>`). The driver tests create and drop their own tables in `marko_test`, a separate database, because the fixture drops `marko_integration` before every case.

### Environment variables

| Variable | Used by | Default |
|----------|---------|---------|
| `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` | Fixture app (Postgres) | unset (skip), `5432`, `marko`, `marko`, `marko_integration` |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Fixture app, pubsub-redis and broadcasting-amphp live tests | unset (skip), `6379`, none |
| `MARKO_TEST_PGSQL_HOST`, `_PORT`, `_DATABASE`, `_USERNAME`, `_PASSWORD` | `database-pgsql` driver tests | unset (skip), `5432`, `marko_test`, `postgres`, empty |
| `MARKO_TEST_MYSQL_HOST`, `_PORT`, `_DATABASE`, `_USERNAME`, `_PASSWORD` | `database-mysql` driver tests | unset (skip), `3306`, `marko_test`, `root`, empty |
| `MARKO_TEST_MYSQL_SERVER` | `database-mysql` driver tests | unset: no check. `mysql` or `mariadb`: a run that reached the other server fails |
| `MARKO_INTEGRATION_REQUIRED` | Every suite in the group | unset: a missing or unreachable service skips. `1`: it fails |

### Running locally

```bash
docker compose -f tests/Integration/compose.yml up -d --wait

DB_HOST=127.0.0.1 REDIS_HOST=127.0.0.1 \
MARKO_TEST_PGSQL_HOST=127.0.0.1 MARKO_TEST_PGSQL_USERNAME=marko MARKO_TEST_PGSQL_PASSWORD=marko \
MARKO_TEST_MYSQL_HOST=127.0.0.1 MARKO_TEST_MYSQL_PASSWORD=marko \
composer test:integration
```

Export only the variables for the services you need: a suite whose host variable is unset skips. To run one part of the group:

```bash
# Fixture app only
DB_HOST=127.0.0.1 REDIS_HOST=127.0.0.1 ./vendor/bin/pest -c phpunit.xml tests/Integration/App

# One driver
MARKO_TEST_MYSQL_HOST=127.0.0.1 MARKO_TEST_MYSQL_PASSWORD=marko \
  ./vendor/bin/pest -c phpunit.xml packages/database-mysql/tests/Integration

# Live Redis tests
REDIS_HOST=127.0.0.1 ./vendor/bin/pest -c phpunit.xml --group=integration-services packages/pubsub-redis packages/broadcasting-amphp
```

The `database-mysql` driver tests also run against MariaDB, together with the other MySQL suites: `packages/admin-auth/tests/Integration/MySql`, `packages/queue-database/tests/Integration/MySqlRoundTripTest.php`, `packages/session-database/tests/Integration/MySql`, `packages/search/tests/Integration/MySql` and `tests/Integration/App/QueueSessionTablesMySqlTest.php`. CI runs them again against MariaDB 11.8 on port 3307 and MariaDB 10.11 on port 3308, with `MARKO_TEST_MYSQL_SERVER=mariadb`; tests whose expectations differ between the servers branch on `IntegrationDatabase::isMariaDb()`, and the MariaDB-only `1020` snapshot-conflict tests skip on MySQL. Locally (port 3308 for 10.11):

```bash
docker compose -f tests/Integration/compose.yml --profile mariadb up -d --wait

MARKO_TEST_MYSQL_HOST=127.0.0.1 MARKO_TEST_MYSQL_PORT=3307 MARKO_TEST_MYSQL_PASSWORD=marko \
MARKO_TEST_MYSQL_SERVER=mariadb \
  ./vendor/bin/pest -c phpunit.xml --group=integration-services packages/database-mysql/tests/Integration
```

If port 5432, 6379 or 3306 is already taken, start the services with `DB_PORT=55432 REDIS_PORT=56379 MYSQL_PORT=53306 docker compose -f tests/Integration/compose.yml up -d --wait` and export the same ports when running the suite (`DB_PORT`, `REDIS_PORT`, `MARKO_TEST_PGSQL_PORT=55432`, `MARKO_TEST_MYSQL_PORT=53306`).

### Skipping and required mode

- Every fixture case calls `setUpIntegrationTest($this)` in `beforeEach`, which calls `integrationServicesSkipReason()`. If `DB_HOST` or `REDIS_HOST` is unset or unreachable, the case is **skipped** with the reason and the compose command. The driver tests do the same through their package's `tests/Fixtures/IntegrationDatabase::config()`, and the live Redis tests through their own skip-reason function. This is why `composer test` stays green on a machine with no services.
- The CI **Integration** job sets `MARKO_INTEGRATION_REQUIRED=1`. In that mode the same condition **fails** the case, so the job can never pass by skipping everything.

### CI

The **Integration** job in `.github/workflows/ci.yml` runs `composer test:integration` against `postgres:17`, `mysql:8.4` and `redis:7` service containers, with every variable above set and `marko_test` created on the Postgres service before the run. It runs serially, because the driver tests share their databases. `tests/CiWorkflowTest.php` asserts the services and variables, so dropping one fails `composer test`.

The required checks on `develop` are `Tests`, `Lint` and `Static analysis`. Whether `Integration` becomes a required check is a maintainer decision, tracked in #226.

### Adding a case

1. If the case needs new behaviour from the fixture, add it to `Fixture/app/integration` (a route, job, observer or entity) or to `Fixture/config`. If the case needs a package outside `INTEGRATION_MODULES`, add the package there. Don't add a second driver for an interface that is already bound: two drivers fail boot with a binding conflict.
2. Start the file with `pest()->group('integration-services');`, then add `beforeEach(fn () => setUpIntegrationTest($this));` and `afterEach(fn () => tearDownIntegrationTest($this));`. This gives the test `$this->app` (migrated) and `$this->project` (the temp project path). Pass `migrate: false` to `setUpIntegrationTest()` for a test that must start from an empty schema.
3. Send requests with `$this->app->router->handle(integrationRequest('GET', '/path'))`. Run console commands with `runIntegrationCommand($this->app, 'queue:work', ['--once'])`. Both go through the real router and the real `CommandRunner`.
4. Don't hand-construct services. Resolve them from `$this->app->container`.
5. Tag the case `->issue(N)` with the ticket whose behaviour it proves. `HarnessTest` checks that every ticket in the #187 hand-off table is still referenced.
6. Redis is shared by every run and never flushed. Use unique keys (and unique client addresses for rate limits), or a rerun inside a TTL starts from the previous run's state.

### Known gaps

Don't park a known bug as a `->todo()` row. `HarnessTest` fails on any todo in the integration suite. Write the failing test in the ticket that fixes the bug instead.

## TDD Workflow Commands

Optimized commands for fast feedback during TDD cycles:

```bash
# RED phase - verify test fails (stop on first failure)
./vendor/bin/pest --filter="test name here" --bail

# GREEN phase - verify package tests pass (parallel, scoped to package)
./vendor/bin/pest packages/{package}/tests/ --parallel

# REFACTOR phase - same as GREEN, run after each change
./vendor/bin/pest packages/{package}/tests/ --parallel

# Final verification - full suite with parallelism
./vendor/bin/pest --parallel
```

### Why These Optimizations Matter

| Flag | Purpose | When to Use |
|------|---------|-------------|
| `--filter` | Run only matching tests | RED phase - confirm specific test fails |
| `--bail` | Stop on first failure | RED phase - fast failure confirmation |
| `--parallel` | Multi-process execution | GREEN/REFACTOR - faster full runs |
| Package scope | Only run package tests | During task work - skip unrelated tests |

### Example TDD Cycle

```bash
# 1. RED - Write test, verify it fails
./vendor/bin/pest --filter="creates user with valid email" --bail
# Expected: FAILED

# 2. GREEN - Implement, verify test passes
./vendor/bin/pest packages/core/tests/ --parallel
# Expected: All pass

# 3. REFACTOR - Clean up, verify still passes
./vendor/bin/pest packages/core/tests/ --parallel
# Expected: All pass

# 4. Before commit - full suite
./vendor/bin/pest --parallel
```

## Test File Locations

### Monorepo Structure
Each package has its own tests directory:
```
packages/
  core/
    tests/
      Unit/
      Feature/
      Browser/
  routing/
    tests/
      Unit/
      Feature/
  database/
    tests/
      Unit/
      Feature/
```

### Test Types
- **Unit tests** (`tests/Unit/`): Test individual classes in isolation with mocked dependencies
- **Feature tests** (`tests/Feature/`): Test integrated functionality, may touch multiple classes
- **Browser tests** (`tests/Browser/`): End-to-end tests using Playwright (Pest 4)

## Tests Never Read Documentation (Hard Rule)

No test may open, assert on, or depend on documentation: docs pages under `packages/docs-markdown/docs/`, package `README.md` files, `CLAUDE.md`, anything in `.claude/`, or `docs/DOCS-STANDARDS.md`. Docs explain how things work for humans and AI; they are not a spec for the implementation, and a test that greps them turns every wording change into a red build.

- Do not write `ReadmeTest`, `DocsTest`, "documents X" or "README has section Y" tests.
- Do not check that a docs page or README exists, or that it links somewhere.
- Code that reads Markdown (e.g. `MarkdownRepository`) is tested against fixtures under `tests/Fixtures/`, never the real docs tree.
- Files the code itself ships or emits (templates, generated config, installed skill files, exception messages that contain a docs URL) are product, not docs, and may be tested.

Docs accuracy is checked outside the test suite: `composer docs:lint` (run by the CI `Lint` job on every PR) fails when a PHP example in a README or docs page references a `Marko\...` class that does not exist; the `doc-updater` agent and PR review cover the rest.

## Coverage Requirements
- Minimum: 80%
- All new code must have tests
- Critical paths (DI container, module loading, plugin system) should have >90% coverage

## Test Naming Convention

### File Names
- Test files: `{ClassName}Test.php`
- Example: `ContainerTest.php`, `ModuleLoaderTest.php`

### Test Methods (Pest Style)
```php
it('resolves a class with no dependencies', function () {
    // ...
});

it('throws BindingException when interface has no binding', function () {
    // ...
});

test('module loader discovers modules in all directories', function () {
    // ...
});
```

### Descriptions
- Use present tense: "resolves", "throws", "returns"
- Be specific about behavior being tested
- Include the condition: "when", "with", "without"
- **Keep test names concise** - don't enumerate every method or property name in the test title
- **Never use "demonstrate" in test names** - tests verify behavior, they don't demonstrate it

```php
// WRONG - enumerates every method name (too verbose, fragile)
it('defines MenuItemInterface with getId, getLabel, getUrl, getIcon, getSortOrder, getPermission methods', ...);

// RIGHT - concise, describes the concept
it('defines MenuItemInterface with get methods', ...);

// WRONG - "demonstrate" implies pseudo-functionality
it('uses DisableRoute to demonstrate route removal', ...);

// RIGHT - describes what the feature does
it('removes route when method has DisableRoute attribute', ...);
```

### Grouping Tests with describe()

Use `describe()` blocks to group related tests. This improves readability and organization:

```php
describe('Column', function (): void {
    it('creates readonly Column class with name, type, and constraints', function (): void {
        $column = new Column(
            name: 'email',
            type: 'varchar',
            length: 255,
        );

        expect($column->name)->toBe('email')
            ->and($column->type)->toBe('varchar');
    });

    it('supports column properties: nullable, default, unique', function (): void {
        // ...
    });
});
```

**Guidelines:**
- Use `describe()` when testing a single class with multiple aspects
- Group by behavior or feature area within the class
- Keep function signatures consistent: `function (): void`
- Avoid deeply nesting `describe()` blocks

## Expectation Chaining (Required)

**Always chain expectations** using `->and()` to switch subjects. Never use consecutive `expect()` calls when they can be chained.

### Same Subject - Chain Directly
```php
// CORRECT - chain assertions on the same subject
expect($exception)
    ->toBeInstanceOf(BindingException::class)
    ->toBeInstanceOf(MarkoException::class);

// WRONG - separate expect() calls on same subject
expect($exception)->toBeInstanceOf(BindingException::class);
expect($exception)->toBeInstanceOf(MarkoException::class);
```

### Different Subjects - Use ->and()
```php
// CORRECT - use ->and() to switch subjects
expect($exception)
    ->toBeInstanceOf(BindingException::class)
    ->and($exception->getMessage())
    ->toContain('No implementation bound')
    ->toContain($interface);

// WRONG - separate expect() calls
expect($exception)->toBeInstanceOf(BindingException::class);
expect($exception->getMessage())->toContain('No implementation bound');
expect($exception->getMessage())->toContain($interface);
```

### Complex Example
```php
it('has PSR-4 autoloading configured', function () {
    $composer = json_decode(file_get_contents($path), true);

    // Chain everything with ->and()
    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Core\\')
        ->and($composer['autoload']['psr-4']['Marko\\Core\\'])->toBe('src/');
});
```

### When Separate expect() is Acceptable
Only use separate `expect()` calls when there's a logical break (setup, action, different phase):

```php
it('creates user and sends welcome email', function () {
    // Setup phase
    expect(User::count())->toBe(0);

    // Action
    $user = UserService::create(['email' => 'test@example.com']);

    // Assertions - chain these together
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('test@example.com')
        ->and(Mail::sent(WelcomeEmail::class))->toBeTrue();
});
```

## Assertion Simplification (Required)

Use the specific boolean matchers instead of generic `toBe()`:

```php
// CORRECT - use specific matchers
expect($config->has('key'))->toBeTrue();
expect($config->has('missing'))->toBeFalse();
expect($value)->toBeNull();

// WRONG - generic toBe() when specific matcher exists
expect($config->has('key'))->toBe(true);
expect($config->has('missing'))->toBe(false);
expect($value)->toBe(null);
```

**Rules:**
- `->toBe(true)` → `->toBeTrue()`
- `->toBe(false)` → `->toBeFalse()`
- `->toBe(null)` → `->toBeNull()`
- `->toHaveCount(0)` → `->toBeEmpty()`
- `->toBe([])` → `->toBeEmpty()`

## Test File Checklist (MANDATORY)

**Run through this checklist after creating or modifying any test file.** This ensures consistent quality across all tests.

### Before Committing Any Test File

- [ ] **No unused imports** - Remove any `use` statements for classes that aren't actually used in the file.
  ```php
  // WRONG - import not used anywhere
  use Marko\Database\Migration\Migration;  // Appears in heredoc strings but not in code
  ```

- [ ] **No unused variables** - Remove variable assignments where the value is never read. Use the expression directly.
  ```php
  // WRONG - $attributes assigned but never used
  $attributes = $reflection->getAttributes(Attribute::class);
  $attr = $reflection->getAttributes(Attribute::class)[0]->newInstance();

  // CORRECT - use the expression directly
  $attr = $reflection->getAttributes(Attribute::class)[0]->newInstance();
  ```

- [ ] **All classes imported with `use` statements** - Never use inline fully-qualified class names. Import at the top of the file.
  ```php
  // CORRECT
  use Marko\Database\Connection\ConnectionInterface;
  use Marko\Database\Exceptions\DatabaseException;

  expect($conn)->toBeInstanceOf(ConnectionInterface::class);

  // WRONG - inline fully-qualified name
  expect($conn)->toBeInstanceOf(\Marko\Database\Connection\ConnectionInterface::class);
  ```

- [ ] **All expectations chained with `->and()`** - No consecutive `expect()` calls on related assertions.
  ```php
  // CORRECT
  expect($result)
      ->toBeInstanceOf(User::class)
      ->and($result->name)->toBe('Alice')
      ->and($result->email)->toContain('@');

  // WRONG - separate expect() calls
  expect($result)->toBeInstanceOf(User::class);
  expect($result->name)->toBe('Alice');
  expect($result->email)->toContain('@');
  ```

- [ ] **Test names use present tense verbs** - "resolves", "throws", "returns", not "should resolve" or "demonstrates"

- [ ] **Test names are concise** - Don't enumerate every method or property in the title. Describe the concept.
  ```php
  // WRONG - lists every method name
  it('defines MenuItemInterface with getId, getLabel, getUrl, getIcon, getSortOrder, getPermission methods', ...);
  // CORRECT
  it('defines MenuItemInterface with get methods', ...);
  ```

- [ ] **No "demonstrate" in test names** - Tests verify behavior, they don't demonstrate it

- [ ] **Reflection-invoked methods have `@noinspection PhpUnused`** - Plugin methods, observer handlers, etc.

- [ ] **Anonymous class properties accessed via reflection have `@noinspection PhpUnused`** - When properties are only accessed through ORM/reflection

- [ ] **Anonymous class stubs that skip parent constructor have `@noinspection PhpMissingParentConstructorInspection`** - When extending a class (not implementing an interface) and intentionally not calling `parent::__construct()`. Add on BOTH the instantiation line and the constructor:
  ```php
  /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
  $mock = new class () extends AMQPChannel
  {
      /** @noinspection PhpMissingParentConstructorInspection */
      public function __construct() {}
  };
  ```

- [ ] **Reference properties have `@noinspection PhpPropertyOnlyWrittenInspection`** - When using reference properties (`private array &$log`) to track state from anonymous classes:
  ```php
  public function __construct(
      /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
      private array &$log,
  ) {}
  ```

- [ ] **Use `@var` annotations when accessing subclass properties on interface/parent return types** - When a method returns an interface or parent type but the test needs subclass-specific properties:
  ```php
  // CORRECT - narrow the type when pop() returns ?JobInterface but we need TestJob::$message
  /** @var TestJob $popped */
  $popped = $queue->pop();
  expect($popped)->toBeInstanceOf(TestJob::class)
      ->and($popped->message)->toBe('expected value');

  // CORRECT - narrow the type when find() returns a generic type
  /** @var User $user */
  $user = $repository->find(1);
  expect($user->name)->toBe('Alice');
  ```

- [ ] **Remove unused properties from test fixtures** - Delete declared properties that are never used:
  ```php
  // WRONG - property declared but never used
  class UserSeeder implements SeederInterface
  {
      public array $executedInserts = [];  // Never populated or read
      public function run(...) { ... }
  }
  ```

- [ ] **Use `readonly` on appropriate properties and classes** - Constructor-promoted properties that aren't reassigned should be `readonly`. Anonymous classes whose properties are set once via constructor should use `readonly class`:
  ```php
  // CORRECT - readonly class for immutable anonymous class
  return new readonly class ($id, $label) implements SectionInterface
  {
      public function __construct(
          private string $id,
          private string $label,
      ) {}
  };

  // CORRECT - readonly on individual property
  public function __construct(
      private readonly array $storage,
  ) {}
  ```

- [ ] **Narrow return types when possible** - If a method always returns `null`, use `null` not `mixed`:
  ```php
  // CORRECT - specific return type
  public function transaction(callable $callback): null
  {
      return null;
  }

  // WRONG - overly broad return type
  public function transaction(callable $callback): mixed
  {
      return null;
  }
  ```

- [ ] **Extract repeated code into helper functions** - If a setup pattern repeats 3+ times, extract it to `Helpers.php`:
  ```php
  // WRONG - duplicated setup in every test
  $discovery = createStubEntityDiscovery();
  $introspector = createStubIntrospector();
  $metadataFactory = new EntityMetadataFactory();
  $command = new DiffCommand(...);  // 10 lines repeated

  // CORRECT - helper function in Helpers.php
  $command = createDiffCommand(diffCalculator: $customCalculator);
  ['output' => $output] = executeDiffCommand($command);
  ```
  Create helpers that accept only the varying parts as parameters.

- [ ] **Run linter on specific test files AFTER tests pass** - This is MANDATORY. Run php-cs-fixer only on the specific files you created/modified, and only after the test is complete and passing:
  ```bash
  # Run php-cs-fixer on the SPECIFIC file(s) you modified
  ./vendor/bin/php-cs-fixer fix packages/{package}/tests/Path/To/YourTest.php
  ```
  **Important:** Run this AFTER your test passes, not during development. This prevents needing to re-read the file after the linter reformats it. The pre-commit hook also runs this automatically, but running it explicitly ensures clean commits.

  This fixes: unnecessary curly braces, trailing commas, whitespace issues, and other formatting problems.

### Quick Verification Commands

```bash
# Run the tests first
./vendor/bin/pest packages/{package}/tests/ --parallel

# AFTER tests pass: Run linter on specific files you modified
./vendor/bin/php-cs-fixer fix packages/{package}/tests/Path/To/YourTest.php

# Check for unchained expectations (should return 0 or very few results)
grep -rn "^[[:space:]]*expect(" packages/{package}/tests --include="*.php" | grep -v "->and(" | head -20

# Check for inline fully-qualified class names (should return 0)
grep -rn "\\\\Marko\\\\" packages/{package}/tests --include="*.php" | grep -v "^[^:]*:use " | head -20
```

## Testing Principles

### 1. Test Behavior, Not Implementation
Focus on what the code does, not how it does it internally.

### 2. Loud Failures
Tests should fail loudly with clear messages explaining what went wrong.

### 3. Isolated Tests
Each test should be independent and not rely on state from other tests.

### 4. Test the Contract
For interfaces, test against the interface contract, not specific implementations.

### 5. Deterministic Under Parallel Load
`composer test` runs a paratest worker on every core, so a test must pass on a saturated machine as well as on an idle laptop.

- **Poll for the condition. Never assert after a fixed delay.** `delay(1.2)` followed by an assertion breaks as soon as the machine is busy. Wait for the condition you're about to assert, with a generous timeout (5s or more). The timeout is only an upper bound: a passing test returns as soon as the condition holds. In amphp tests use `Marko\Broadcasting\Amphp\Tests\Support\Poll::until($condition, 'what is awaited')`. It polls with `delay()`, so the event loop keeps running. A fixed delay is fine only when the test has to prove that something stays true for a period of time, such as a stream outliving a timeout or nothing being logged.
- **Give subprocess tests their own temp state.** A test that starts another process (Pest, Composer, a server) writes into a temp directory unique to that run, such as `sys_get_temp_dir() . '/name-' . bin2hex(random_bytes(8))`, and removes it afterwards. Never write to a fixed path: concurrent runs in worktrees, or `composer test` running next to `composer ci`, would share it.
- **Pass the parent environment, minus the paratest variables.** Strip `PARATEST`, `TEST_TOKEN`, `UNIQUE_TEST_TOKEN` and `PEST_PARALLEL*` so the child doesn't act as a worker of the parent run. Keep everything else, including `TMPDIR`. Pass `-d memory_limit=...` explicitly when the child needs it.
- **Boot a subprocess once per file** when several tests read the same result. Memoise the result in a `static` (see `packages/testing/tests/Feature/PestPluginRegistrationTest.php`).
- **Put the subprocess output in every failure message**, e.g. `expect(str_contains($output, '...'))->toBeTrue($output)`. Pest's `toContain()` takes no message argument.

### 6. Clean Runs: No Notices, Deprecations, Risky Tests or Warnings
`phpunit.xml` sets `failOnDeprecation`, `failOnNotice`, `failOnPhpunitDeprecation`, `failOnPhpunitNotice`, `failOnRisky` and `failOnWarning`, so any of them fails `composer test` and the integration jobs, the same as a failing test. `tests/PhpunitConfigTest.php` keeps the flags on. Skipped tests still pass (`failOnSkipped` is off), because the integration tests skip without their services. `<source ignoreIndirectDeprecations="true">` limits `failOnDeprecation` to deprecations Marko's own code triggers, so a deprecation raised inside a third-party vendor package doesn't fail the run.

- **Stubs via `createStub()`.** Use `createMock()` only for a double that gets `expects()` (or a `with()` rule). A mock with no expectation triggers a PHPUnit notice. Don't silence it with `#[AllowMockObjectsWithoutExpectations]`. When a shared double needs `expects()` in only some tests, stub it in the shared setup and create a mock in those tests.
- **Every test asserts.** A test that performs no assertion is risky. Test helpers that check something go through `PHPUnit\Framework\Assert` (`Assert::assertSame()`, `Assert::assertArrayHasKey()`, ...), never a hand-thrown `AssertionFailedError`, so a passing check still counts.
- **No raw PHP warnings from code under test.** Don't hide them with `@`. Run the failing call through `Marko\Core\Support\ErrorCapture::run($reason, fn () => ...)`, fold `$reason` into the loud exception, then assert the reason in the test. Don't use `@` + `error_get_last()`: PHPUnit still records the suppressed warning, and `error_get_last()` can return a stale, unrelated error.
- **Find issues** with `--display-notices --display-deprecations --display-warnings --display-phpunit-deprecations --display-phpunit-notices`.

## Pest 4 Features

> **Note:** The following sections document Pest 4 capabilities. Some features require additional plugins that may not be installed in the project yet. Check `composer.json` for current dependencies.

### Browser Testing (Playwright-Powered)
Pest 4 includes first-class browser testing. No need for Dusk. **Requires:** `pestphp/pest-plugin-browser`

**Installation:**
```bash
composer require pestphp/pest-plugin-browser --dev
npm install playwright@latest
npx playwright install
```

**Example:**
```php
it('loads the homepage', function () {
    $page = visit('/');

    $page->assertSee('Welcome')
         ->assertNoJavascriptErrors();
});

it('handles login flow', function () {
    $page = visit('/login')
        ->type('email', 'user@example.com')
        ->type('password', 'secret')
        ->press('Sign In');

    $page->assertPathIs('/dashboard')
         ->assertSee('Welcome back');
});
```

### Device Testing
Test across different devices and viewports:
```php
it('displays mobile menu on small screens', function () {
    $page = visit('/')
        ->on()->mobile();  // or ->on()->tablet(), ->on()->desktop()

    $page->assertSee('Menu Icon');
});

it('supports dark mode', function () {
    $page = visit('/')
        ->inDarkMode();

    $page->assertScreenshotMatches();
});
```

### Smoke Testing
Quickly validate multiple pages for JavaScript errors:
```php
it('has no smoke on critical pages', function () {
    $routes = ['/', '/about', '/docs', '/contact'];

    visit($routes)->assertNoSmoke();
    // Shorthand for assertNoJavascriptErrors() + assertNoConsoleLogs()
});
```

### Visual Regression Testing
Compare screenshots against baseline images:
```php
it('matches visual baseline', function () {
    $pages = visit(['/', '/about', '/contact']);

    $pages->assertScreenshotMatches();
});
```

### Conditional Skipping
```php
it('requires external service', function () {
    // Skip when running locally
})->skipLocally();

it('only runs in CI', function () {
    // Skip on CI environments
})->skipOnCi();
```

### New Expectations (Pest 4)
```php
expect('hello-world')->toBeSlug();
expect($text)->not->toHaveSuspiciousCharacters();
```

## Reflection-Invoked Test Fixtures

When test fixtures contain methods that are invoked via reflection (e.g., plugin methods with `#[Before]`/`#[After]`, observer `handle` methods), PhpStorm cannot trace the usage and flags them as unused.

**Add `@noinspection PhpUnused` to each reflection-invoked method:**

```php
#[Plugin(target: TargetService::class)]
class TargetServicePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 10)]
    public function doSomething(): void {}
}
```

This applies to:
- Plugin methods with `#[Before]` or `#[After]` attributes
- Observer `handle()` methods with `#[Observer]` attribute
- Any method discovered and invoked via reflection by the framework

**Do NOT disable the `PhpUnused` inspection globally for tests** - it catches legitimate unused code. Only suppress it on specific methods that are genuinely used via reflection.

## Anonymous Class Stub Guidelines

When creating anonymous class stubs that extend real classes, follow these patterns:

### Skipping Parent Constructor
When a stub **extends a class** and intentionally skips the parent constructor, add the annotation on BOTH the return statement and the constructor:
```php
/** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
return new class () extends RealClass
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(
        private readonly array $stubData,
    ) {}
};
```

**When to use this annotation:**
- Only when using `extends SomeClass` where the parent class has a constructor
- Only when you intentionally skip calling `parent::__construct()`

**When NOT to use this annotation:**
- When using `implements SomeInterface` - interfaces don't have constructors, so there's no parent constructor to skip
- When the parent class has no constructor
- When you do call `parent::__construct()` in your stub

### Stub Classes with Custom Properties (Named Class Pattern)
When tests need to access custom properties on a stub that extends a real class, **extract the anonymous class into a named class** at the top of the test file. The `@return Type&object{...}` intersection annotation does NOT work in PhpStorm for this purpose.

```php
// CORRECT - Named class avoids "Potentially polymorphic call" warnings
/** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
class MockQueueChannel extends AMQPChannel
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct() {}

    public function basic_publish($msg, ...): void
    {
        $this->calls[] = ['method' => 'basic_publish', 'msg' => $msg];
    }
}

// Tests can now access $channel->calls without warnings
$channel = new MockQueueChannel();
// ... use $channel in test ...
$publishCalls = array_filter($channel->calls, fn ($c) => $c['method'] === 'basic_publish');

// WRONG - Anonymous class causes "Potentially polymorphic call" on $channel->calls
function createMockChannel(): AMQPChannel {
    return new class () extends AMQPChannel {
        public array $calls = [];  // PhpStorm can't see this through AMQPChannel return type
    };
}
```

**When to extract to named class:**
- The stub has custom public properties accessed in tests (e.g., `$mock->calls`, `$mock->publishedMessages`)
- Multiple tests use the same stub and access its custom members

**When anonymous class is fine:**
- The stub only overrides parent/interface methods and has no custom properties
- Custom properties are only accessed inside the anonymous class itself

### Using `readonly` Properties
Always use `readonly` on constructor-promoted properties in anonymous classes:
```php
return new class ($param) extends BaseClass
{
    public function __construct(
        private readonly array $data,  // Use readonly
    ) {}
};
```

### Entity Fixture Properties
Entity properties that exist for structural definition but aren't directly accessed in tests:
```php
class TestEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    public string $name;
}
```

### Testing Invalid Enum Values
When testing that `tryFrom()` returns null for invalid enum backing values, PhpStorm warns that the value doesn't exist in the enum. Suppress with `PhpCaseWithValueNotFoundInEnumInspection`:
```php
it('can be created from string value', function () {
    /** @noinspection PhpCaseWithValueNotFoundInEnumInspection */
    expect(LogLevel::from('error'))->toBe(LogLevel::Error)
        ->and(LogLevel::tryFrom('invalid'))->toBeNull();
});
```
Place the annotation above the `expect()` call, not inline with the assertion.

### Reference Properties for Tracking
When using reference properties to track state changes from anonymous class methods:
```php
$executionOrder = [];

$seeder = new class ($executionOrder) implements SeederInterface
{
    public function __construct(
        /** @noinspection PhpUnused - Reference property used to track execution */
        private array &$order,
    ) {}

    public function run(ConnectionInterface $connection): void
    {
        $this->order[] = 'executed';  // Modifies external $executionOrder
    }
};

// After seeder runs:
expect($executionOrder)->toBe(['executed']);
```
PhpStorm flags these as "Property is only written but never read" because it doesn't understand the reference semantics. Add the `@noinspection` annotation to suppress.

## Common Test Patterns

### Testing Exceptions
```php
it('throws BindingConflictException when multiple bindings exist', function () {
    $container = new Container();
    $container->bind(LoggerInterface::class, FileLogger::class);
    $container->bind(LoggerInterface::class, ConsoleLogger::class);

    expect(fn() => $container->resolve(LoggerInterface::class))
        ->toThrow(BindingConflictException::class);
});
```

### Testing with Mocks
```php
it('dispatches event to all observers', function () {
    $observer1 = mock(ObserverInterface::class);
    $observer2 = mock(ObserverInterface::class);

    $observer1->shouldReceive('handle')->once();
    $observer2->shouldReceive('handle')->once();

    $dispatcher = new EventDispatcher([$observer1, $observer2]);
    $dispatcher->dispatch(new UserCreated($user));
});
```

### Testing Attributes
```php
it('discovers Plugin attribute on class', function () {
    $reflector = new AttributeReflector();
    $plugins = $reflector->findClassesWithAttribute(Plugin::class);

    expect($plugins)->toContain(PriceModifierPlugin::class);
});
```

### Capture Object Pattern

Use capture objects (objects with public properties) to track state changes during test execution. This is preferred over reference properties when you need to track multiple values or complex state:

```php
// Define event with capture property
class DispatcherTestEvent extends Event
{
    public array $handledBy = [];
}

it('dispatches event to observers in priority order', function (): void {
    $event = new DispatcherTestEvent();
    $dispatcher->dispatch($event);

    // Assert via capture object
    expect($event->handledBy)->toBe(['high', 'medium', 'low']);
});
```

**When to use capture objects vs reference properties:**
- **Capture objects**: When tracking multiple related values, when state belongs logically to the test subject
- **Reference properties**: When tracking simple state from anonymous classes that don't naturally own the state

### Test Helpers (Helpers.php)

When a setup pattern repeats 3+ times across tests, extract it to a `Helpers.php` file in the same test directory:

```php
// packages/database/tests/Command/Helpers.php
namespace Marko\Database\Tests\Command;

final class Helpers
{
    /**
     * Helper to capture command output.
     *
     * @return array{stream: resource, output: Output}
     */
    public static function createOutputStream(): array
    {
        $stream = fopen('php://memory', 'r+');

        return [
            'stream' => $stream,
            'output' => new Output($stream),
        ];
    }

    /**
     * Helper to create a DiffCommand with standard dependencies.
     */
    public static function createDiffCommand(
        ?DiffCalculator $diffCalculator = null,
    ): DiffCommand {
        return new DiffCommand(
            discovery: self::createStubEntityDiscovery(),
            // ...
        );
    }
}
```

**Guidelines for test helpers:**
- Place in `Helpers.php` (not `Helpers/` directory)
- Use `final class` with static methods
- Accept only the varying parts as parameters
- Return arrays with named keys for multiple values: `['stream' => $stream, 'output' => $output]`
- Document return types with PHPDoc when returning complex structures

### Test Fixtures at File Top

For simple test fixtures (classes used by multiple tests in the same file), define them at the top of the file before tests:

```php
<?php

declare(strict_types=1);

use Marko\Core\Container\Container;

// Test fixtures
interface PaymentInterface {}
class StripePayment implements PaymentInterface {}
class PayPalPayment implements PaymentInterface {}

it('registers bindings from module manifest', function (): void {
    // Uses PaymentInterface and StripePayment defined above
});
```

**When to use file-top fixtures vs anonymous classes:**
- **File-top fixtures**: When multiple tests need the same class, when class needs methods/properties
- **Anonymous classes**: When testing interface contracts, when class is only used once

## Running Tests in CI

### Basic CI Configuration
```yaml
- name: Run Tests
  run: ./vendor/bin/pest --coverage --min=80
```

### Test Sharding (Parallel CI Jobs)
Distribute tests across multiple CI runners for faster feedback:

```yaml
# GitHub Actions example
strategy:
  matrix:
    shard: [1, 2, 3, 4]
steps:
  - name: Run Tests (Shard ${{ matrix.shard }})
    run: ./vendor/bin/pest --parallel --shard=${{ matrix.shard }}/4
```

### Full CI Example
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        shard: [1, 2, 3, 4]

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: xdebug

      - name: Install Dependencies
        run: composer install --no-progress

      - name: Run Tests
        run: ./vendor/bin/pest --parallel --shard=${{ matrix.shard }}/4 --coverage --min=80
```

## Pest Configuration
The `pest.php` file in each package configures Pest:
```php
<?php

declare(strict_types=1);

uses(TestCase::class)->in('Unit', 'Feature');

// Custom expectation for Marko modules
expect()->extend('toBeValidModule', function () {
    return $this->toBeInstanceOf(ModuleInterface::class)
        ->and($this->value->getName())->not->toBeEmpty();
});

// Custom expectation for bindings
expect()->extend('toHaveBinding', function (string $interface) {
    return $this->toHaveKey($interface);
});
```

## Profanity Plugin (Optional)
Keep test code professional:
```bash
composer require pestphp/pest-plugin-profanity --dev
./vendor/bin/pest --profanity
```
