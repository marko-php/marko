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

### 5. Property Hooks Over Getters/Setters (PHP 8.4+)
Use property hooks instead of traditional getter/setter methods. This is cleaner and more idiomatic PHP 8.4+.

```php
// CORRECT - property hooks with asymmetric visibility
class Application
{
    public private(set) ContainerInterface $container;
    public private(set) PreferenceRegistry $preferenceRegistry;

    // For computed/validated access
    public Router $router {
        get => $this->_router ?? throw new RuntimeException('Call boot() first');
    }
}

// WRONG - traditional getters
class Application
{
    private ContainerInterface $container;

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
```

**Rules:**
- Use `{ get; }` for simple read-only property access
- Use `{ get => expr; }` for computed or validated access
- Use `{ set => expr; }` when validation/transformation is needed on write
- Asymmetric visibility: `public private(set)` for public read, private write (keep explicit `public` - explicit over implicit)

### 6. Avoid Final (Blocks Extensibility)
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

### 7. Type Declarations Required
All parameters, return types, and properties must have type declarations:
```php
public function resolve(string $abstract): object
{
    // ...
}
```

### 8. No Magic Methods
Avoid `__get`, `__set`, `__call`, `__callStatic`. Be explicit.

### 9. String Interpolation (No Unnecessary Curly Braces)
When interpolating simple variables in double-quoted strings, do NOT use curly braces.
**Enforced by:** PhpStorm `PhpUnnecessaryCurlyVarSyntaxInspection`

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

### 10. Use #[\NoDiscard] for Important Returns
```php
#[\NoDiscard]
public function validate(): ValidationResult
{
    // Return value should not be ignored
}
```

### 11. Multiline Method Signatures (Always)
**ALL method parameters MUST be on their own line with a trailing comma**, regardless of parameter count:

```php
// CORRECT - parameter on its own line with trailing comma
public function registerModule(
    ModuleManifest $module,
): void {
    // ...
}

public function __construct(
    private LoggerInterface $logger,
    private EventDispatcher $events,
) {}

public function extractClassName(
    string $filePath,
): ?string {
    // ...
}

// WRONG - parameter on same line as method name
public function registerModule(ModuleManifest $module): void
{
    // ...
}

// WRONG - opening brace on new line
public function registerModule(
    ModuleManifest $module,
): void
{
    // ...
}
```

**Rules:**
- Each parameter on its own line (even if only one parameter)
- Trailing comma after last parameter
- Opening brace `{` on same line as closing parenthesis/return type
- Only zero-parameter methods stay on one line: `public function getName(): string {`

### 12. Anonymous Class Braces (Next Line)
Anonymous classes follow the same brace placement as regular classes - opening brace on the **next line**.
**Enforced by:** php-cs-fixer `braces_position.anonymous_classes_opening_brace`

```php
// CORRECT - opening brace on next line
$controller = new #[Preference(replaces: PostController::class)]
class extends PostController
{
    public function show(): Response
    {
        return new Response('Custom');
    }
};

// WRONG - opening brace on same line
$controller = new class extends PostController {
    // ...
};
```

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
// CORRECT - use short class names (import at top of file) with pipe operator
/**
 * @throws ModuleException|CircularDependencyException|BindingConflictException
 */
public function boot(): void

// CORRECT - single exception
/**
 * @throws PluginException
 */
private function discoverPlugins(): void

// CORRECT - use interface exception types when calling interface methods
// ContainerInterface::get() declares @throws ContainerExceptionInterface
/**
 * @throws ContainerExceptionInterface|EventException
 */
private function discoverObservers(): void
{
    $observerDiscovery = $this->container->get(ObserverDiscovery::class); // throws ContainerExceptionInterface
    $observers = $observerDiscovery->discover($this->modules);            // throws EventException
}

// WRONG - using fully qualified class names
/**
 * @throws \Marko\Core\Exceptions\PluginException
 */

// WRONG - missing @throws tags
public function resolve(array $modules): array  // Throws exceptions without documenting them!
```

**Rules:**
- Import exception classes at top of file, use short names in `@throws`
- Use pipe operator (`|`) to combine multiple exceptions on one line
- **Never use multiple `@throws` tags** - consolidate into a single tag with pipe-delimited exceptions
- Document ALL thrown exceptions, including those from called methods if not caught
- When calling methods on interfaces, use the exception types declared by the interface (e.g., `ContainerExceptionInterface` not `BindingException`)
- Private methods that throw should also be documented if the exception propagates
- Description after exception name is optional (omit if exception name is self-explanatory)
- **Test files are exempt** from `@throws` requirements (inspection disabled for `packages/*/tests/`)

**Enforced by:** Custom php-cs-fixer rule `Marko/phpdoc_consolidate_throws` automatically consolidates multiple `@throws` tags into one.

## Git Hooks

**Location:** `.githooks/pre-commit`

The pre-commit hook runs automatically on commit (when configured via `git config core.hooksPath .githooks`). It performs the following in order:

1. **Rector** - Auto-fixes code quality issues (`rector.php` config)
2. **Multiline params fixer** - Forces params on separate lines (`tools/fix-multiline-params.php`)
3. **PHP-CS-Fixer** - Auto-fixes formatting (`.php-cs-fixer.php` config)
4. **PHPCBF** - Auto-fixes phpcs violations (`phpcs.xml` config)
5. **PHPCS** - Final validation (fails commit if violations remain)

Each tool auto-stages its fixes before the next tool runs.

### Setup Git Hooks
```bash
git config core.hooksPath .githooks
```

### Related Config Files
- `.githooks/pre-commit` - The hook script
- `rector.php` - Rector rules (code modernization)
- `.php-cs-fixer.php` - PHP-CS-Fixer rules (formatting)
- `phpcs.xml` - PHP_CodeSniffer rules (style validation)
- `tools/fix-multiline-params.php` - Custom multiline params script

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

## Package README Standards

Every package in `packages/` must have a README.md that follows this structure:

### Required Sections

1. **Title + One-Line Description**
   ```markdown
   # Package Name

   One sentence describing what it does AND the practical benefit.
   ```

   Example: "Error handling contracts that capture not just what went wrong, but the context and how to fix it."

   Not: "Error handling contracts for the Marko Framework." (no benefit stated)

2. **Overview** (2-4 sentences max)
   - Lead with the benefit—what does the developer gain?
   - Be concrete, not marketing speak
   - Keep it brief—details come later

3. **Installation**
   ```markdown
   ## Installation

   ```bash
   composer require marko/package-name
   ```
   ```

4. **Usage** (THE KEY SECTION)
   - Focus on developers building modules in `app/` or `modules/`
   - Show practical examples of how to USE the package
   - Include code snippets showing common use cases
   - Structure as subsections: "For Module Developers", "Type-Hinting", etc.

5. **Customization/Extension** (if applicable)
   - How to extend or customize behavior
   - Creating custom implementations via Preferences

6. **API Reference**
   - Public interfaces and classes with method signatures
   - Keep it concise—just signatures, not explanations
   - Group related classes together

### Guidelines

**Do:**
- Lead with "how to use it" for module developers
- Include code examples for common scenarios
- Show Preference usage for customization
- Keep prose minimal—let code speak
- Use bullet points over paragraphs

**Don't:**
- Write long explanatory paragraphs
- Repeat information from code comments
- Include internal implementation details
- Over-explain obvious things

### Example Structure

```markdown
# Marko Package Name

Brief description of what this package does.

## Overview

2-4 sentences about purpose and key benefit.

## Installation

\`\`\`bash
composer require marko/package-name
\`\`\`

## Usage

### For Module Developers

[Most common use case with code example]

### [Other Use Case]

[Code example]

## Customization

### Creating Custom [Thing]

[Code showing Preference pattern]

## API Reference

### InterfaceName

\`\`\`php
interface InterfaceName
{
    public function method(): ReturnType;
}
\`\`\`
```
