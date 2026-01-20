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
