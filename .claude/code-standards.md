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

### Sibling Modules (Driver Packages)

When creating packages that implement the same interface for different backends (e.g., `database-mysql`, `database-pgsql`), follow the **Sibling Module Standards** documented in [`.claude/sibling-modules.md`](sibling-modules.md).

Key requirements:
- Package naming: `marko/{base}-{driver}`
- Class naming: `{Driver}{Component}` (e.g., `MySqlConnection`, `PgSqlConnection`)
- Identical method names, visibilities, and patterns across all siblings
- Multi-line PHPDoc format for property annotations
- Test files must have proper PSR-4 namespaces
- Use anonymous class testing pattern (not reflection)

**Even single implementations should follow sibling conventions** if future siblings are planned. This prevents costly refactoring when adding drivers later.

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

### 5. Asymmetric Visibility (PHP 8.4+)
Use asymmetric visibility for properties that should be publicly readable but privately writable. This replaces getter methods and is cleaner than property hooks for simple cases.

```php
// CORRECT - asymmetric visibility for public read, private write
class Message
{
    public private(set) ?string $subject = null;
    public private(set) ?Address $from = null;

    /** @var array<Address> */
    public private(set) array $to = [];

    public function subject(
        string $subject,
    ): self {
        $this->subject = $subject;
        return $this;
    }

    public function to(
        string $email,
        ?string $name = null,
    ): self {
        $this->to[] = new Address($email, $name);  // Array modification works!
        return $this;
    }
}

// WRONG - traditional getters
class Message
{
    private ?string $subject = null;

    public function getSubject(): ?string
    {
        return $this->subject;
    }
}

// WRONG - property hooks with only get (blocks array modification)
class Message
{
    /** @var array<Address> */
    public array $to = [] {
        get {
            return $this->to;
        }
    }

    public function to(string $email): self
    {
        $this->to[] = new Address($email);  // ERROR: Indirect modification not allowed!
        return $this;
    }
}
```

**When to use what:**

| Pattern | Use When |
|---------|----------|
| `public private(set)` | Public read, private write (most common) |
| `public protected(set)` | Public read, subclass can write |
| `{ get => expr; }` | Computed/validated/lazy access |
| `{ set => expr; }` | Validation or transformation on write |

**Rules:**
- Prefer `public private(set)` over property hooks for simple read-only exposure
- Keep explicit `public` - explicit over implicit
- Property hooks with only `get` are implicitly read-only and block indirect modifications (like `$this->array[] = ...`)
- Use property hooks only when you need computed values or validation logic

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

### 8. Import Classes (No Inline Fully Qualified Names)
Always import classes at the top of the file with `use` statements. Never use fully qualified class names with leading backslash in code.

```php
// CORRECT - import at top of file
use Closure;
use Marko\Core\Container\Container;
use Marko\Database\ConnectionInterface;

class MyClass
{
    public function process(
        Closure $callback,
        ConnectionInterface $connection,
    ): void {
        if ($callback instanceof Closure) {
            // ...
        }
    }
}

// WRONG - inline fully qualified names
class MyClass
{
    public function process(
        \Closure $callback,
        \Marko\Database\ConnectionInterface $connection,
    ): void {
        if ($callback instanceof \Closure) {
            // ...
        }
    }
}
```

**Rules:**
- Import all classes used in the file with `use` statements
- Group imports: global classes first, then vendor, then project classes
- Alphabetize within each group
- Use short class name everywhere after import

### 9. No Magic Methods
Avoid `__get`, `__set`, `__call`, `__callStatic`. Be explicit.

### 10. No Traits
Traits inject behavior implicitly - you can't see at a glance where a method comes from. Use explicit composition instead.

```php
// WRONG - trait injects behavior implicitly
trait LoggingTrait
{
    public function log(string $message): void { /* ... */ }
}

class UserService
{
    use LoggingTrait; // Where does log() come from? Have to hunt for it.
}

// CORRECT - explicit composition, visible dependency
class UserService
{
    public function __construct(
        private Logger $logger,
    ) {}

    public function doSomething(): void
    {
        $this->logger->log('message'); // Clear where logging comes from
    }
}
```

**For testing utilities**, use helper classes instead of traits:

```php
// WRONG - trait for test helpers
trait DatabaseTestCase
{
    protected function seedTable(...): void { /* ... */ }
}

// CORRECT - explicit helper class
$dbHelper = new DatabaseTestHelper($connection);
$dbHelper->seedTable('users', $rows);
```

This keeps dependencies explicit and makes code easier to understand and trace.

### 11. String Interpolation (No Unnecessary Curly Braces)
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

### 12. Use #[\NoDiscard] for Important Returns
```php
#[\NoDiscard]
public function validate(): ValidationResult
{
    // Return value should not be ignored
}
```

### 13. Multiline Method Signatures (Always)
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

### 14. Anonymous Class Braces (Next Line)
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

## Exception Standards (Loud Errors)

All Marko exceptions extend `MarkoException` and provide three pieces of information via named parameters:

```php
class BindingConflictException extends MarkoException
{
    public static function multipleBindings(
        string $interface,
        array $modules,
    ): self {
        $moduleList = implode(', ', $modules);

        return new self(
            message: "Multiple modules bind the same interface '$interface': $moduleList",
            context: "While loading module bindings for '$interface'",
            suggestion: 'Use a Preference in a higher-priority module to resolve the conflict, or remove duplicate bindings',
        );
    }
}
```

| Parameter    | Purpose                                        |
|--------------|------------------------------------------------|
| `message`    | What went wrong (specific, actionable)         |
| `context`    | Where it happened (help locate the issue)      |
| `suggestion` | How to fix it (guide toward the solution)      |

**Guidelines:**
- Use static factory methods (e.g., `::multipleBindings()`) for common exception cases
- Include variable values in messages (interface names, module names, etc.)
- Keep suggestions actionable - tell the developer exactly what to do
- Always use named parameters when creating exceptions

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

Every package in `packages/` must have a README.md. Keep it concise—developers want to quickly understand what it is, why they'd use it, and how to use it.

### Structure

| Section               | Purpose                                        |
|-----------------------|------------------------------------------------|
| **Title + One-Liner** | What it does + practical benefit               |
| **Overview**          | 2-4 sentences expanding on the benefit         |
| **Installation**      | Composer command                               |
| **Usage**             | How module developers use it (THE KEY SECTION) |
| **Customization**     | Extending via Preferences (if applicable)      |
| **API Reference**     | Public signatures only                         |

### Interface vs Implementation Packages

Marko uses an interface/implementation split. Make the distinction clear:

**Interface packages** (e.g., `marko/errors`):
- One-liner: Describe what it defines, note it has no implementation
- Overview: Explain the contracts it provides
- Usage: Show how to type-hint against interfaces
- Note which implementation packages exist

**Implementation packages** (e.g., `marko/errors-simple`):
- One-liner: Describe what it does (the actual behavior)
- Overview: Explain the concrete benefit
- Usage: Show it works automatically, plus customization options

### Writing the One-Liner

The one-liner must include the **practical benefit**—what developers gain.

```markdown
# Good
Interfaces for error handling—defines how errors are captured and structured, not how they're displayed.

The default error handler—catches exceptions and displays them with full context and fix suggestions.

# Bad (no benefit)
Error handling contracts for the Marko Framework.

A simple error handler implementation.
```

### Writing the Usage Section

This is the most important section. Focus on developers building modules in `app/` or `modules/`.

**Lead with the common case:**
- Most packages "just work"—show that first
- Then show how to interact with it directly if needed

**Include practical code examples:**
```markdown
## Usage

### For Module Developers

You don't need to do anything special—just throw exceptions:

\`\`\`php
throw new MarkoException(
    message: 'User not found',
    context: 'Loading user profile',
    suggestion: 'Verify the user ID exists',
);
\`\`\`

### Type-Hinting the Handler

If you need direct access:

\`\`\`php
public function __construct(
    private ErrorHandlerInterface $handler,
) {}
\`\`\`
```

### Code Examples in READMEs

**Usage/Customization sections:** Follow full code standards—parameters on their own lines with trailing commas:

```php
// CORRECT - full code standards
public function handle(
    ErrorReport $report,
): void {
    // implementation
}

// WRONG - single line params
public function handle(ErrorReport $report): void
```

**API Reference sections:** Single-line signatures are acceptable for readability:

```php
// OK in API Reference only
public function handle(ErrorReport $report): void;
public function format(ErrorReport $report, bool $isDevelopment): string;
```

Zero-parameter methods stay on one line everywhere: `public function index(): Response`

### Guidelines

**Do:**
- State the benefit upfront
- Show code examples for common scenarios
- Keep prose minimal—let code speak
- Use bullet points over paragraphs
- Make interface vs implementation distinction clear
- Follow code standards in Usage/Customization examples

**Don't:**
- Write long explanatory paragraphs
- Use marketing speak ("designed to never fail")
- Include internal implementation details
- Repeat what's obvious from the code

## Configuration Standards

### Defaults Belong in Config Files
Default values must be defined in config files (`config/*.php`), not hardcoded in code. This provides:
- **Single source of truth** - All defaults visible in one place
- **Easy overrides** - Other modules can override via higher-priority config files
- **Transparency** - Developers can see all configurable options
- **Loud errors** - Missing config fails immediately, not silently uses wrong default

```php
// config/blog.php - all defaults defined here
return [
    'posts_per_page' => 10,
    'site_name' => 'My Blog',
];
```

```php
// CORRECT - no fallback, config file is the source of truth
public function getPostsPerPage(): int
{
    return $this->config->getInt('blog.posts_per_page');
}

// WRONG - hardcoded fallback hides missing config
public function getPostsPerPage(): int
{
    return $this->config->getInt('blog.posts_per_page', 10);
}
```

## Latte Template Standards

Templates are pure presentation. Keep them clean, minimal, and consistent.

### No Useless Comments
The filename already says what the template is. Don't add comments like `{* Blog post index template *}`.

```latte
{* WRONG - useless, filename says this *}
{* Blog post index template *}
<main>

{* CORRECT - just start the template *}
<main>
```

### Use `n:if` Attribute Syntax
For single-element conditionals, use `n:if` on the element instead of wrapping in `{if}...{/if}` blocks.

```latte
{* WRONG - verbose block syntax *}
{if $canonicalUrl}
<link rel="canonical" href="{$canonicalUrl}">
{/if}

{* CORRECT - attribute syntax *}
<link n:if="$canonicalUrl" rel="canonical" href="{$canonicalUrl}">
```

**When to use block syntax:** `{if}/{elseif}/{else}` chains with 3+ branches still use blocks.

### Prefer Dual `n:if` Over `{if}/{else}` Blocks
When showing either an empty state OR content, use `n:if` on both elements instead of a block.

```latte
{* WRONG - block syntax for simple either/or *}
{if $posts->isEmpty()}
    <p class="no-posts">No posts yet.</p>
{else}
    <ul class="post-list">
        ...
    </ul>
{/if}

{* CORRECT - n:if on both elements *}
<p n:if="$posts->isEmpty()" class="no-posts">No posts yet.</p>
<ul n:if="!$posts->isEmpty()" class="post-list">
    ...
</ul>
```

### Combine Default Declarations
Declare multiple defaults on one line with commas.

```latte
{* WRONG - verbose *}
{default $canonicalUrl = null}
{default $metaDescription = null}
{default $pageTitle = null}

{* CORRECT - combined *}
{default $canonicalUrl = null, $metaDescription = null, $pageTitle = null}
```

### Use PHP Truthiness for Cleaner Conditions
Leverage PHP's falsy values (`0`, `null`, `''`, `[]`) for cleaner conditionals.

```latte
{* WRONG - verbose *}
n:if="$currentDepth === 0"
n:if="empty($comments)"
n:if="$posts->isEmpty()"

{* CORRECT - use truthiness *}
n:if="!$currentDepth"
n:if="!$comments"
n:if="!$posts->isEmpty()"
```

### No Variable Extraction
Templates are presentational - don't extract expressions into variables. Inline them.

```latte
{* WRONG - unnecessary variable *}
{var $params = array_merge($queryParams, ['page' => $pageNumber])}
<a href="{$baseUrl}?{http_build_query($params)}">

{* CORRECT - inline *}
<a href="{$baseUrl}?{http_build_query(array_merge($queryParams, ['page' => $pageNumber]))}">
```

### Remove Dead Variables
If a variable is declared in `{default}` but never used (or only passed through), remove it.

### Consistent Attribute Usage
Don't mix `{if}` blocks and `n:if` for similar elements. Pick one style per template.

```latte
{* WRONG - inconsistent *}
{if $hasPrev}
    <a href="..." class="prev">Previous</a>
{/if}
<a n:if="$hasNext" href="..." class="next">Next</a>

{* CORRECT - consistent *}
<a n:if="$hasPrev" href="..." class="prev">Previous</a>
<a n:if="$hasNext" href="..." class="next">Next</a>
```
