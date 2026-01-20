# Architecture

## Repository Structure (Monorepo)

```
marko/
  packages/               # All framework packages
    core/                 # Bootstrap, DI container, module loader
    routing/              # Route attributes and router
    database/             # Database interfaces
    database-mysql/       # MySQL driver
    database-postgresql/  # PostgreSQL driver
    cache/                # Cache interfaces
    cache-file/           # File cache driver
    cache-redis/          # Redis cache driver
    view/                 # View interfaces
    view-latte/           # Latte template driver
    cli/                  # Global CLI tool
    errors/               # Error handling interfaces
    errors-basic/         # Basic error handler
    errors-advanced/      # Advanced error handler (dev)
  docs/                   # Documentation
  composer.json           # Root composer.json
  README.md
  LICENSE
```

## Package Internal Structure

Each package follows this structure:
```
package-name/
  src/                    # PHP source code (PSR-4 root)
    Attributes/           # PHP attributes (#[Plugin], #[Observer], etc.)
    Contracts/            # Interfaces (service contracts)
    Exceptions/           # Package-specific exceptions
    ...                   # Domain-specific directories
  config/                 # Default configuration files
  tests/
    Unit/                 # Unit tests (mirrors src/ structure)
    Feature/              # Integration tests
  composer.json           # Package composer.json
  module.php              # Module manifest
```

## Three-Directory Application Structure

When Marko is installed in a project:
```
project/
  vendor/                 # Composer packages (lowest priority)
    marko/
      core/
      routing/
      database-mysql/
    acme/
      some-package/
  modules/                # Manual installations (middle priority)
    internal/
      company-auth/
    client/
      custom-checkout/
  app/                    # Application modules (highest priority - always wins)
    blog/
    admin/
    api/
  public/
    index.php             # Web entry point
  composer.json
```

## Override Priority
1. `vendor/` - Lowest priority (Composer packages)
2. `modules/` - Middle priority (manual installs, internal packages)
3. `app/` - Highest priority (application code always wins)

## Module System

### Module Manifest (`module.php`)
Every module requires a manifest file:
```php
<?php

declare(strict_types=1);

return [
    'name' => 'acme/blog',
    'version' => '1.0.0',
    'enabled' => true,

    // Dependencies (must be loaded first)
    'require' => [
        'marko/core',
        'marko/routing',
        'marko/database',
    ],

    // Load order hints
    'sequence' => [
        'after' => ['marko/database'],
        'before' => ['app/admin'],
    ],

    // Interface → Implementation bindings
    'bindings' => [
        PostRepositoryInterface::class => PostRepository::class,
    ],
];
```

### Module Discovery
Modules are automatically discovered by scanning for `module.php` files in:
1. `vendor/*/` (two levels deep)
2. `modules/**/` (recursive)
3. `app/*/` (one level deep)

### Module States
- **Enabled** (default): Module is loaded and active
- **Disabled**: Set `'enabled' => false` in manifest

## Dependency Injection

### Container Resolution Order
1. Check explicit binding in requesting context
2. Check preferences (class → class replacement)
3. Check module-level bindings (app > modules > vendor)
4. Autowire if no binding exists

### Bindings (Interface → Implementation)
Defined in module manifest:
```php
'bindings' => [
    LoggerInterface::class => FileLogger::class,
    CacheInterface::class => RedisCache::class,
],
```

### Preferences (Class → Class Replacement)
Use `#[Preference]` attribute to replace a class globally:
```php
#[Preference(replaces: VendorProductService::class)]
class CustomProductService extends VendorProductService
{
    // Your customizations
}
```

### Conflict Handling
Multiple bindings for same interface without explicit override = **LOUD ERROR**

## Plugin System

### Purpose
Modify behavior of any public method without touching source code.

### Two Plugin Types Only

#### Before Plugins
Run before the original method. Can short-circuit by returning early.
```php
#[Plugin(ProductService::class)]
class ProductValidationPlugin
{
    #[Before(sortOrder: 10)]
    public function beforeSave(Product $product): ?Product
    {
        if (!$product->isValid()) {
            throw new ValidationException('Invalid product');
        }
        return null; // Continue to original method
    }
}
```

#### After Plugins
Run after the original method. Can modify the return value.
```php
#[Plugin(PriceCalculator::class)]
class DiscountPlugin
{
    #[After(sortOrder: 20)]
    public function afterCalculate(float $result, Product $product): float
    {
        return $result * 0.9; // 10% discount
    }
}
```

### No Around Plugins
If you need to completely replace behavior, use `#[Preference]` instead.

### Sort Order
- Defined at method level only
- Lower numbers run first (negative allowed)
- Default is 0

## Event System

### Dispatching Events
```php
$this->eventDispatcher->dispatch(new UserCreated($user));
```

### Observers
```php
#[Observer(event: UserCreated::class, priority: 100)]
class SendWelcomeEmail
{
    public function handle(UserCreated $event): void
    {
        // Send email
    }
}
```

### Events vs Plugins
| Use Events | Use Plugins |
|------------|-------------|
| Multiple independent reactions | Modify specific method behavior |
| Reactions are optional/decoupled | Transformation is required |
| "Something happened" | "Intercept this thing" |
| Order doesn't matter much | Order is critical |

## Routing

### Attribute-Based Routes
```php
class PostController
{
    #[Get('/posts')]
    public function index(): Response { }

    #[Get('/posts/{id}')]
    public function show(int $id): Response { }

    #[Post('/posts')]
    #[Middleware(AuthMiddleware::class)]
    public function store(Request $request): Response { }
}
```

### Route Attributes
- `#[Get(path, middleware?)]`
- `#[Post(path, middleware?)]`
- `#[Put(path, middleware?)]`
- `#[Patch(path, middleware?)]`
- `#[Delete(path, middleware?)]`

### Overriding Routes
Use `#[Preference]` on controller + one of:
- Override method with new `#[Get]` attribute → New route
- Override method with `#[DisableRoute]` → Route removed
- Override method with no attribute → **ERROR** (ambiguous)

## Configuration

### PHP Only - No XML, YAML, or DSL
```php
// config/database.php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'host' => env('DB_HOST', 'localhost'),
            'database' => env('DB_DATABASE', 'marko'),
        ],
    ],
];
```

### Benefits
- IDE autocompletion
- Type checking
- Conditional logic when needed
- Actual syntax errors instead of silent failures

## PHP Attributes Used

### Framework Attributes
| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[Plugin]` | Class | Marks class as a plugin for another class |
| `#[Before]` | Method | Plugin method runs before target |
| `#[After]` | Method | Plugin method runs after target |
| `#[Observer]` | Class | Reacts to dispatched events |
| `#[Preference]` | Class | Replaces another class globally |
| `#[Get]`, `#[Post]`, etc. | Method | Defines HTTP route |
| `#[Middleware]` | Method/Class | Applies middleware |
| `#[DisableRoute]` | Method | Explicitly removes inherited route |
| `#[Command]` | Class | Registers CLI command |

### PHP Built-in Attributes to Use
| Attribute | Purpose |
|-----------|---------|
| `#[\NoDiscard]` | Warn if return value ignored |
| `#[\Override]` | Verify method overrides parent |
| `#[\Deprecated]` | Mark as deprecated |

### Extensibility Considerations
- **Use `readonly`** for immutability - doesn't block inheritance
- **Avoid `final`** on classes/properties - blocks Preferences from extending
- Only use `final` on internal implementation that must not be part of extension API

## Bootstrap Process

### Sequence
1. **Autoload**: Load Composer autoloader
2. **Bootstrap**: Execute `vendor/marko/core/bootstrap.php`
3. **Scan**: Find all `module.php` files in vendor/, modules/, app/
4. **Parse**: Read all module manifests
5. **Validate**: Check dependencies, detect conflicts
6. **Sort**: Topological sort for load order
7. **Boot**: Load modules in order, register bindings
8. **Ready**: Container ready, handle request

### Entry Points

#### Web
```php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/marko/core/bootstrap.php';

$app->handleRequest();
```

#### CLI
```bash
marko command:name
# Finds project root → autoload → bootstrap → run command
```

## Error Handling Philosophy

### Every Error Provides
1. Clear description of what went wrong
2. Context about where it happened
3. Suggestion for how to fix it

### Common Exceptions
| Exception | Cause |
|-----------|-------|
| `BindingException` | No implementation for interface |
| `BindingConflictException` | Multiple bindings without override |
| `ModuleException` | Module manifest issues |
| `CircularDependencyException` | Modules depend on each other |
| `RouteConflictException` | Duplicate route definitions |
| `RouteException` | Override without attribute |
| `PluginException` | Plugin configuration issues |

## Versioning

### Unified Versioning
All packages share the same version number. When releasing:
- All packages get the same tag
- Even unchanged packages get new version
- Guarantees compatibility: all v1.2.3 packages work together

### Semantic Versioning
- **Patch** (1.2.3 → 1.2.4): Bug fixes
- **Minor** (1.2.4 → 1.3.0): New features, backward compatible
- **Major** (1.3.0 → 2.0.0): Breaking changes

## Key Design Decisions

### 1. No XML Configuration
All configuration is PHP for IDE support and type safety.

### 2. No Around Plugins
Keeps call stack clean. Use Preference for full replacement.

### 3. No Magic Methods
Everything is explicit and discoverable.

### 4. Composer Naming Everywhere
Package name = module name = namespace root. One convention.

### 5. Loud Errors
No silent failures. Conflicts are surfaced immediately with resolution guidance.

### 6. Readonly Over Final
Use `readonly` for immutability, avoid `final` for extensibility. Preferences need to extend classes.
