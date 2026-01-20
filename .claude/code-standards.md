# Code Standards

## Style Guide
PSR-12 Extended Coding Style with additional Marko-specific rules.

## Linting Tools

### PHP_CodeSniffer (Detection)
```bash
# Check for issues
./vendor/bin/phpcs

# Check specific file/directory
./vendor/bin/phpcs packages/core/src/

# Show detailed report
./vendor/bin/phpcs --report=full
```

### PHP CS Fixer (Auto-fixing)
```bash
# Fix all issues
./vendor/bin/php-cs-fixer fix

# Dry run (show what would be fixed)
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix specific directory
./vendor/bin/php-cs-fixer fix packages/core/src/
```

### Combined Workflow
```bash
# Check first, then fix
./vendor/bin/phpcs && ./vendor/bin/php-cs-fixer fix
```

## PHP Version Requirements
- Minimum: PHP 8.5
- Use all PHP 8.5 features where appropriate:
  - Pipe operator (`|>`) for functional composition
  - `clone($obj, ['prop' => value])` for readonly objects
  - `#[\NoDiscard]` on functions where return value should be used
  - `array_first()` and `array_last()` instead of `reset()`/`end()`
  - Final properties via constructor promotion
  - Closures in constant expressions for attributes

## Naming Conventions

### Classes
- **PascalCase**: `ModuleLoader`, `ContainerInterface`, `BindingException`
- **Suffixes by type**:
  - Interfaces: `*Interface` (e.g., `LoggerInterface`)
  - Exceptions: `*Exception` (e.g., `BindingConflictException`)
  - Attributes: Descriptive name (e.g., `Plugin`, `Observer`, `Get`)
  - Abstract classes: `Abstract*` (e.g., `AbstractController`)

### Methods
- **camelCase**: `resolve()`, `getBindings()`, `dispatchEvent()`
- **Verb prefixes**:
  - `get*` - Returns a value
  - `set*` - Sets a value (avoid; prefer immutable)
  - `is*` / `has*` / `can*` - Returns boolean
  - `create*` - Factory method
  - `find*` - May return null
  - `load*` - Loads from storage

### Variables and Properties
- **camelCase**: `$moduleLoader`, `$bindings`, `$eventDispatcher`
- **No Hungarian notation**: Use `$modules` not `$arrModules`

### Constants
- **SCREAMING_SNAKE_CASE**: `DEFAULT_PRIORITY`, `MAX_RETRIES`

### Files
- One class per file
- Filename matches class name: `ModuleLoader.php`
- PSR-4 autoloading: `Marko\Core\ModuleLoader` → `packages/core/src/ModuleLoader.php`

## Code Structure Rules

### 1. Strict Types Required
Every PHP file must declare strict types:
```php
<?php

declare(strict_types=1);
```

### 2. Constructor Injection Only
All dependencies must be injected via constructor:
```php
// CORRECT
public function __construct(
    private LoggerInterface $logger,
    private EventDispatcher $events,
) {}

// WRONG - service locator
public function doSomething(): void
{
    $logger = Container::get(LoggerInterface::class);
}
```

### 3. Constructor Property Promotion (Always)
Always use constructor property promotion - reduces boilerplate:
```php
// CORRECT - constructor property promotion
public function __construct(
    private string $name,
    private array $dependencies,
) {}

// WRONG - verbose, unnecessary
private string $name;
private array $dependencies;

public function __construct(string $name, array $dependencies)
{
    $this->name = $name;
    $this->dependencies = $dependencies;
}
```

### 4. Readonly (When Appropriate)
Use `readonly` when immutability is the design intent - not as a blanket rule.

**Good use cases:**
- Value objects (`Money`, `Email`, `Address`)
- DTOs (data transfer objects)
- Configuration objects

**Not needed for:**
- Entities with mutable state
- Builders
- Objects designed to change

**If ALL properties are readonly, mark the class instead:**
```php
// CORRECT - readonly class when all properties are immutable
readonly class OrderId
{
    public function __construct(
        private string $value,
    ) {}
}

// WRONG - redundant readonly on each property
class OrderId
{
    public function __construct(
        private readonly string $value,
    ) {}
}
```

### 5. Avoid Final (Blocks Extensibility)
`final` prevents Preferences from extending classes. Avoid it.

```php
// WRONG - blocks Preference from overriding
final class ProductService { }

// CORRECT - extensible
class ProductService { }
```

**When `final` IS appropriate:**
- Internal implementation details that must not be part of extension API
- Security-critical methods that must not be overridden
- Always document WHY something is final

### 6. Type Declarations Required
All parameters, return types, and properties must have type declarations:
```php
public function resolve(string $abstract): object
{
    // ...
}
```

### 7. No Magic Methods
Avoid `__get`, `__set`, `__call`, `__callStatic`. Be explicit.

### 8. String Interpolation (No Unnecessary Curly Braces)
When interpolating simple variables in double-quoted strings, do NOT use curly braces:

```php
// CORRECT - no curly braces for simple variables
$message = "No implementation bound for interface: $interface";
$context = "While loading module '$moduleName'";

// WRONG - unnecessary curly braces
$message = "No implementation bound for interface: {$interface}";
$context = "While loading module '{$moduleName}'";
```

**Only use curly braces when required** for complex expressions:
```php
// Curly braces required for array access
$message = "User {$user['name']} not found";

// Curly braces required for object properties
$message = "Order {$order->id} processed";

// Curly braces required to disambiguate
$message = "Found {$count}items"; // Without braces: $countitems would be a different variable
```

### 9. Use #[\NoDiscard] for Important Returns
```php
#[\NoDiscard]
public function validate(): ValidationResult
{
    // Return value should not be ignored
}
```

### 10. Multiline Method Signatures (2+ Parameters)
Methods with 2 or more parameters MUST have each parameter on its own line with a trailing comma:

```php
// CORRECT - each parameter on its own line with trailing comma
public static function multipleBindings(
    string $interface,
    array $modules,
): self {
    // ...
}

public function __construct(
    private LoggerInterface $logger,
    private EventDispatcher $events,
) {}

// WRONG - multiple parameters on single line
public static function multipleBindings(string $interface, array $modules): self
{
    // ...
}
```

**Rules:**
- Each parameter on its own line
- Trailing comma after last parameter
- Opening brace `{` on same line as closing parenthesis/return type
- Single-parameter methods may remain on one line

## Attribute Standards

### Attribute Placement
Attributes go on their own line above the target:
```php
#[Plugin(ProductService::class)]
class PriceModifierPlugin
{
    #[Before]
    public function beforeGetPrice(Product $product): void
    {
        // ...
    }
}
```

### Multiple Attributes
Stack vertically:
```php
#[Get('/posts/{id}')]
#[Middleware(AuthMiddleware::class)]
public function show(int $id): Response
{
    // ...
}
```

## Documentation Standards

### When to Document
- Public API methods on interfaces
- Complex algorithms
- Non-obvious design decisions
- **Do NOT document**:
  - Self-explanatory code
  - Private implementation details
  - Obvious getters/setters

### @throws Tags (Required)
**All methods that throw exceptions MUST have `@throws` PHPDoc tags.** This is critical for:
- IDE autocompletion and warnings
- Static analysis tools (PHPStan)
- Developers understanding what exceptions to catch

```php
// CORRECT - @throws tag for each exception type
/**
 * Resolve module dependencies and return modules in load order.
 *
 * @param ModuleManifest[] $modules
 * @return ModuleManifest[]
 * @throws ModuleException When a required module dependency is not found
 * @throws CircularDependencyException When modules have circular dependencies
 */
public function resolve(array $modules): array

// WRONG - missing @throws tags
/**
 * Resolve module dependencies and return modules in load order.
 */
public function resolve(array $modules): array  // Throws exceptions without documenting them!
```

**Rules:**
- One `@throws` tag per exception type
- Include brief description after the class name
- Document ALL thrown exceptions, including those from called methods if not caught
- Private methods that throw should also be documented if the exception propagates

### PHPDoc Format
```php
/**
 * Resolves a class or interface from the container.
 *
 * @throws BindingException When no binding exists for an interface
 * @throws CircularDependencyException When circular dependency detected
 */
public function resolve(string $abstract): object
```

## Pre-commit Checks
Before committing, ensure:
1. `./vendor/bin/phpcs` passes with no errors
2. `./vendor/bin/pest` passes all tests
3. No `declare(strict_types=1)` missing
4. All new public methods have type declarations

## Code Review Checklist
- [ ] Follows PSR-12 style
- [ ] Uses strict types
- [ ] Dependencies injected via constructor
- [ ] No magic methods
- [ ] Types declared on all parameters and returns
- [ ] Tests written for new functionality
- [ ] Loud errors with helpful messages
- [ ] No silent failures or fallbacks
