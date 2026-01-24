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
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Unit/Container/ContainerTest.php

# Run tests in a specific directory
./vendor/bin/pest tests/Unit/

# Run tests matching a filter
./vendor/bin/pest --filter="resolves dependencies"

# Run with coverage
./vendor/bin/pest --coverage

# Run with coverage and minimum threshold
./vendor/bin/pest --coverage --min=80

# Run in parallel (faster)
./vendor/bin/pest --parallel

# Run with test sharding (for CI)
./vendor/bin/pest --shard=1/4
./vendor/bin/pest --parallel --shard=2/4

# Type coverage (2x faster in Pest 4)
./vendor/bin/pest --type-coverage

# Check for profanity in test code
./vendor/bin/pest --profanity
```

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
- **Never use "demonstrate" in test names** - tests verify behavior, they don't demonstrate it

```php
// WRONG - "demonstrate" implies pseudo-functionality
it('uses DisableRoute to demonstrate route removal', ...);

// RIGHT - describes what the feature does
it('removes route when method has DisableRoute attribute', ...);
```

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

## Test File Checklist (MANDATORY)

**Run through this checklist after creating or modifying any test file.** This ensures consistent quality across all tests.

### Before Committing Any Test File

- [ ] **No unused imports** - Remove any `use` statements for classes that aren't actually used in the file.
  ```php
  // WRONG - import not used anywhere
  use Marko\Database\Migration\Migration;  // Appears in heredoc strings but not in code
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

- [ ] **No "demonstrate" in test names** - Tests verify behavior, they don't demonstrate it

- [ ] **Reflection-invoked methods have `@noinspection PhpUnused`** - Plugin methods, observer handlers, etc.

- [ ] **Anonymous class properties accessed via reflection have `@noinspection PhpUnused`** - When properties are only accessed through ORM/reflection

- [ ] **Reference properties have `@noinspection PhpPropertyOnlyWrittenInspection`** - When using reference properties (`private array &$log`) to track state from anonymous classes:
  ```php
  public function __construct(
      /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
      private array &$log,
  ) {}
  ```

- [ ] **Use `@var` annotations for polymorphic call warnings** - When accessing properties/methods on nullable or generic return types:
  ```php
  // CORRECT - annotate the type before accessing properties
  /** @var User $user */
  $user = $repository->find(1);
  expect($user->name)->toBe('Alice');

  // CORRECT - annotate array element types
  /** @var array<Product> $products */
  $products = $repository->findBy(['active' => true]);
  expect($products[0]->isActive)->toBeTrue();
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

- [ ] **Use `readonly` on appropriate properties** - Constructor-promoted properties that aren't reassigned should be `readonly`:
  ```php
  // CORRECT
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

## Pest 4 Features

### Browser Testing (Playwright-Powered)
Pest 4 includes first-class browser testing. No need for Dusk.

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

When test fixtures contain methods that are invoked via reflection (e.g., plugin `beforeXxx`/`afterXxx` methods, observer `handle` methods), PhpStorm cannot trace the usage and flags them as unused.

**Add `@noinspection PhpUnused` to each reflection-invoked method:**

```php
#[Plugin(target: TargetService::class)]
class TargetServicePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 10)]
    public function beforeDoSomething(): void {}

    /** @noinspection PhpUnused - Invoked via reflection */
    #[After(sortOrder: 20)]
    public function afterDoSomething(): void {}
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
When a stub intentionally skips the parent constructor, add the annotation on BOTH the return statement and the constructor:
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

### Stub Return Types with Custom Properties
When a stub helper returns an anonymous class with custom properties, document them in the return type to avoid "potentially polymorphic call" warnings:
```php
/**
 * @return Migrator&object{rolledBack: array<string>, rollbackCallCount: int}
 */
function createStubMigrator(): Migrator {
    return new class () extends Migrator
    {
        public array $rolledBack = [];
        public int $rollbackCallCount = 0;
        // ...
    };
}
```
The `&object{...}` syntax tells PhpStorm about the additional properties on the returned object.

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
