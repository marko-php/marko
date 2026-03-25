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
