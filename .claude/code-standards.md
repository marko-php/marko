# Code Standards

## Auto-fixed by Pre-commit Hook

The following are handled automatically by the pre-commit toolchain (Rector, php-cs-fixer, PHPCS, fix-multiline-params). Do NOT spend time checking or fixing these — focus on the rules below that require judgment:

- Constructor property promotion (Rector)
- Import organization, unused imports, alphabetization (php-cs-fixer)
- Multiline method signatures with trailing commas (PHPCS + fix-multiline-params)
- String interpolation curly brace removal (php-cs-fixer)
- Parentheses around `new` in chains (PHPCS `UselessParentheses`)
- Anonymous class brace placement (php-cs-fixer)
- `@throws` tag consolidation — multiple tags merged to pipe-delimited (php-cs-fixer)
- PSR-12 formatting, blank lines, whitespace (php-cs-fixer)

## Linting Tools

```bash
./vendor/bin/phpcs                        # Check for issues
./vendor/bin/php-cs-fixer fix             # Auto-fix formatting
./vendor/bin/phpcs && ./vendor/bin/php-cs-fixer fix  # Combined
```

## PHP Version Requirements

- Minimum: PHP 8.5
- Use PHP 8.5 features: pipe operator (`|>`), `clone()` with properties, `array_first()`/`array_last()`, closures in constant expressions

### Prefer `array_any()` / `array_all()` Over Foreach Loops

```php
// CORRECT
return array_any($this->roles, fn ($role) => $role->isSuperAdmin());

// WRONG
foreach ($this->roles as $role) {
    if ($role->isSuperAdmin()) {
        return true;
    }
}
return false;
```

## Naming Conventions

### Classes
- Interfaces: `*Interface` (e.g., `LoggerInterface`)
- Exceptions: `*Exception` (e.g., `BindingConflictException`)
- Attributes: Descriptive name (e.g., `Plugin`, `Observer`, `Get`)
- Abstract classes: `Abstract*` (e.g., `AbstractController`)

### Sibling Modules (Driver Packages)

When creating packages that implement the same interface for different backends, follow [`.claude/sibling-modules.md`](sibling-modules.md).

Key requirements:
- Package naming: `marko/{base}-{driver}`
- Class naming: `{Driver}{Component}` (e.g., `MySqlConnection`, `PgSqlConnection`)
- Identical method names, visibilities, and patterns across all siblings
- **Even single implementations should follow sibling conventions** if future siblings are planned

## Code Structure Rules

### 1. Strict Types Required
Every PHP file: `declare(strict_types=1);`

### 2. Constructor Injection Only
```php
// CORRECT
public function __construct(
    private LoggerInterface $logger,
) {}

// WRONG - service locator
$logger = Container::get(LoggerInterface::class);
```

### 3. No Dead Code
Remove unused variables, unused constructor dependencies, and unused method parameters. When removing a constructor dependency, update all call sites.

```php
// WRONG - $parser injected but never used
public function __construct(
    private readonly ClassFileParser $parser,
) {}

// CORRECT - remove unused dependency entirely
```

### 4. Readonly (When Appropriate)

**CRITICAL: If ALL promoted properties are readonly, use `readonly class` instead.**
Never write `private readonly` on individual properties when every property is readonly — promote to class level. This is the most commonly missed rule.

```php
// CORRECT - readonly on the class
readonly class UserService
{
    public function __construct(
        private UserRepository $repo,
        private LoggerInterface $logger,
    ) {}
}

// WRONG - all properties readonly but class is not
class UserService
{
    public function __construct(
        private readonly UserRepository $repo,
        private readonly LoggerInterface $logger,
    ) {}
}
```

**Mixed-mutability classes:** Mark immutable properties as `readonly` individually. Only promote to `readonly class` when ALL properties are immutable.

```php
// CORRECT - mixed mutability
class FakeQueue
{
    public array $pushed = [];              // mutable

    public function __construct(
        private readonly string $defaultQueue = 'default',  // immutable
    ) {}
}
```

**Use `readonly class` for:** value objects, DTOs, config objects, stateless services.
**Not for:** entities with mutable state, builders, objects designed to change.

### 5. Asymmetric Visibility (PHP 8.4+)
Prefer `public private(set)` over property hooks or getter methods for simple read-only exposure.

```php
// CORRECT
class Message
{
    public private(set) ?string $subject = null;
    public private(set) array $to = [];
}
```

**Rules:**
- Property hooks with only `get` block indirect modifications (like `$this->array[] = ...`) — use asymmetric visibility instead
- Do NOT replace getter methods with property hooks when the getter satisfies an interface contract or when properties must remain writable (entity hydration)
- **Disabled inspection:** `PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection`

### 6. Avoid Final (Blocks Extensibility)
`final` prevents Preferences from extending classes. Only use when security-critical, and document why.

### 7. Type Declarations Required
All parameters, return types, and properties must have type declarations. **Use the narrowest type possible** — prefer `string` or `string|int` over `mixed`.

```php
// CORRECT
public function get(string $key): null { return null; }

// WRONG - overly broad
public function get(string $key): mixed { return null; }
```

### 8. Typed Constants (PHP 8.3+)
All class constants must have explicit type declarations.

```php
// CORRECT
private const string TEST_KEY = 'marko_health_check';
private const int MAX_RETRIES = 3;
private const array VALID_TYPES = ['json', 'xml'];

// WRONG - untyped constants
private const TEST_KEY = 'marko_health_check';
private const MAX_RETRIES = 3;
```

### 9. Handle All Checked Exceptions
PHP functions that throw checked exceptions (`random_bytes()` → `RandomException`, `json_encode()` → `JsonException`, config getters → `ConfigNotFoundException`) must be handled. Either:
- **Propagate** by declaring `@throws` on the containing method
- **Catch and convert** to a domain-specific exception

**Never silently catch and ignore** — this violates the "loud errors" principle.

```php
// CORRECT - propagate
/**
 * @throws RandomException
 */
public function generateToken(): string {
    return bin2hex(random_bytes(40));
}

// CORRECT - catch and convert to domain exception
public function generateToken(): string {
    try {
        return bin2hex(random_bytes(40));
    } catch (RandomException) {
        throw new TokenException('Failed to generate secure token');
    }
}

// WRONG - silent failure
public function generateUrl(): string {
    try {
        return $this->config->urlPrefix() . '/path';
    } catch (ConfigNotFoundException) {
        return ''; // Violates loud errors!
    }
}
```

### 10. No Deprecated APIs
Always use current API methods. When a method is deprecated, replace it immediately.

```php
// CORRECT
$imagick->clear();

// WRONG - deprecated
$imagick->destroy();
```

### 11. SQL Identifier Validation
Dynamic values used as SQL identifiers (table names, column names, sort directions) cannot use parameter binding. Validate them against a safe pattern before interpolation.

```php
private const string IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

private function assertValidIdentifier(
    string $identifier,
    string $type,
): void {
    if (!preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
        throw SearchException::invalidIdentifier($identifier, $type);
    }
}
```

Sort directions must be validated against an allowlist (`['asc', 'desc']`).

### 12. Don't Pass Default Values as Arguments
```php
// CORRECT
$user = createTestAdminUser();
$userRepo = createMockUserRepo(findReturn: $user);

// WRONG - passing values that match defaults
$user = createTestAdminUser(id: 1, email: 'admin@example.com', name: 'Admin');
$userRepo = createMockUserRepo(findReturn: null, rolesReturn: []);
```

### 13. No Magic Methods
Avoid `__get`, `__set`, `__call`, `__callStatic`. Be explicit.

### 14. No Traits
Use explicit composition instead. Traits hide where behavior comes from.

```php
// WRONG
class UserService { use LoggingTrait; }

// CORRECT
class UserService
{
    public function __construct(
        private Logger $logger,
    ) {}
}
```

### 15. Use #[\NoDiscard] for Important Returns
```php
#[\NoDiscard]
public function validate(): ValidationResult
{
    // Return value should not be ignored
}
```

## Attribute Standards

Attributes go on their own line above the target. Stack multiple attributes vertically:
```php
#[Get('/posts/{id}')]
#[Middleware(AuthMiddleware::class)]
public function show(int $id): Response {}
```

## Exception Standards (Loud Errors)

All Marko exceptions extend `MarkoException` with three named parameters:

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
            suggestion: 'Use a Preference in a higher-priority module to resolve the conflict',
        );
    }
}
```

| Parameter    | Purpose                                   |
|--------------|-------------------------------------------|
| `message`    | What went wrong (specific, actionable)    |
| `context`    | Where it happened (help locate the issue) |
| `suggestion` | How to fix it (guide toward the solution) |

Use static factory methods for common cases. Include variable values in messages. Always use named parameters.

## Documentation Standards

### When to Document
- Public API methods on interfaces
- Complex algorithms, non-obvious design decisions
- **Do NOT document**: self-explanatory code, obvious getters/setters

### @throws Tags (Required)
**Every method that contains a `throw` statement or calls a method that throws MUST have a `@throws` tag.** This is the most commonly missed rule.

```php
// CORRECT
/**
 * @throws ContainerExceptionInterface|EventException
 */
private function discoverObservers(): void
{
    $observerDiscovery = $this->container->get(ObserverDiscovery::class);
    $observers = $observerDiscovery->discover($this->modules);
}
```

**Rules:**
- Use pipe operator (`|`) to combine multiple exceptions on one line
- Document ALL thrown exceptions, including those from called methods if not caught
- When calling interface methods, use the exception types declared by the interface
- Private methods that throw should also be documented if the exception propagates
- **Test files are exempt** from `@throws` requirements
- **Applies to ALL classes** — fakes, services, helpers, not just interfaces

> After writing any method, scan it for `throw` keywords and method calls that propagate exceptions, and add the `@throws` PHPDoc block.

## Git Hooks

The pre-commit hook (`.githooks/pre-commit`) runs: Rector → fix-multiline-params → PHP-CS-Fixer → PHPCBF → PHPCS.

```bash
git config core.hooksPath .githooks  # Setup
```

Config files: `rector.php`, `.php-cs-fixer.php`, `phpcs.xml`, `tools/fix-multiline-params.php`

## Configuration Standards

### Defaults Belong in Config Files
Default values must be defined in config files (`config/*.php`), not hardcoded in code.

```php
// config/blog.php
return [
    'posts_per_page' => 10,
    'site_name' => 'My Blog',
];
```

### No Fallback Parameters
Config getters throw `ConfigNotFoundException` when keys are missing. Never pass fallback parameters.

```php
// CORRECT
return $this->config->getInt('blog.posts_per_page');

// WRONG - hardcoded fallback
return $this->config->getInt('blog.posts_per_page', 10);
```

### Environment Variables in Config Files Only
`$_ENV` should only be referenced in `config/*.php` files, never in application code.

## Latte Template Standards

Templates are pure presentation. Keep them clean, minimal, and consistent.

- **No useless comments** — the filename says what the template is
- **Use `n:if`** for single-element conditionals instead of `{if}...{/if}` blocks
- **Prefer dual `n:if`** over `{if}/{else}` blocks for simple either/or
- **Combine defaults** on one line: `{default $a = null, $b = null}`
- **Use PHP truthiness** for cleaner conditions: `n:if="!$currentDepth"` not `n:if="$currentDepth === 0"`
- **No variable extraction** — inline expressions, don't extract to `{var}`
- **Remove dead variables** — if declared in `{default}` but never used, delete it
- **Consistent style** — don't mix `{if}` blocks and `n:if` in the same template

## Package README Standards

Every package must have a README.md with these sections:

| Section               | Purpose                                        |
|-----------------------|------------------------------------------------|
| **Title + One-Liner** | What it does + practical benefit               |
| **Overview**          | 2-4 sentences expanding on the benefit         |
| **Installation**      | Composer command                               |
| **Usage**             | How module developers use it (THE KEY SECTION) |
| **Customization**     | Extending via Preferences (if applicable)      |
| **API Reference**     | Public signatures only                         |

### Interface vs Implementation Packages

**Interface packages** (e.g., `marko/errors`): Describe what it defines, note it has no implementation, show type-hinting.

**Implementation packages** (e.g., `marko/errors-simple`): Describe what it does, explain concrete benefit, show it works automatically.

### Guidelines

- State the benefit upfront in the one-liner
- Lead Usage with the common case ("just works")
- Keep prose minimal — let code speak
- Follow full code standards in Usage/Customization examples
- Single-line signatures acceptable in API Reference only
