# Marko Framework: Master Architecture Document

> **Opinionated, not restrictive. There's always a way - it's just the right way.**

---

## Table of Contents

1. [Philosophy](#philosophy)
2. [Core Equation](#core-equation)
3. [Before Writing Code](#before-writing-code)
4. [Package Inventory](#package-inventory)
5. [Naming Conventions](#naming-conventions)
6. [Directory Structure](#directory-structure)
7. [Module System](#module-system)
8. [Package Architecture](#package-architecture)
9. [Dependency Injection](#dependency-injection)
10. [Plugin System](#plugin-system)
11. [Event System](#event-system)
12. [Routing](#routing)
13. [Configuration](#configuration)
14. [PHP Attributes](#php-attributes)
15. [CLI System](#cli-system)
16. [Error Handling](#error-handling)
17. [Bootstrap Process](#bootstrap-process)
18. [Development & Release Process](#development--release-process)

---

## Philosophy

### Core Belief

Developers need protection from themselves, but not limitation. Every bad pattern has a good alternative. Marko doesn't block functionality - it redirects it toward better architecture.

### Learning from Others

Marko draws inspiration from the PHP frameworks that came before it.

**From Magento**, Marko takes the powerful concepts of true modularity, dependency injection with preferences and plugins, event-driven architecture, and service contracts. These architectural patterns enable the kind of extensibility that powers large-scale applications.

**From Laravel**, Marko takes the focus on developer experience - clean syntax, readable code, excellent tooling, and the principle that a framework should be enjoyable to use.

**What Marko adds** is the combination of both, with strong opinions that protect developers from common pitfalls: no XML configuration, no magic methods, explicit everything, and loud errors that guide rather than confuse.

### Opinionated, Not Restrictive

Marko makes the right thing easy and the wrong thing annoying. Not impossible. Annoying.

You can always accomplish what you need. You just can't accomplish it the lazy way that creates technical debt.

Every "no" comes with a "yes, this way instead”. The framework teaches. The guardrails guide, they don't wall.

### Target Developer

Marko is for:

- Developers who value architectural consistency
- Team leads who desire predictable codebases
- Agencies busy maintaining multiple client projects
- Senior devs who want to enforce best practices & patterns
- Anyone who wishes to build a modular application that lasts

### Loud Errors

Marko fails loudly. No silent failures. No ambiguous behavior. Every decision is explicit. Every conflict is surfaced. Every mistake is caught early with helpful messages that explain what went wrong and how to fix it.

---

## Core Equation

```
Marko = Enterprise-grade extensibility
  + Modern developer experience
  + Strong opinions
```

### Extensibility Features

- True modularity where everything is a module
- Dependency injection with preferences and plugins
- Events and observers
- Service contracts via interfaces
- Module fallback chains

### Developer Experience Features

- Clean, expressive syntax
- PHP-native configuration (no XML or DSL)
- Simple, powerful CLI
- Readable, self-documenting code
- IDE-friendly with full type support

### Strong Opinions

- One way to do things, documented clearly
- Explicit over implicit
- Loud errors over silent failures
- Constraints that create quality

---

## Before Writing Code

### Discovery First

Before implementing new functionality, always check what already exists. Marko follows an interface/driver architecture - many features you need are already implemented.

### Checklist

**Before writing code:**

1. **Check for existing packages** - Scan `packages/` for functionality that might already exist
2. **Use interface packages, not driver packages** - Depend on `marko/log`, not `marko/log-file`. Let the application choose the driver.
3. **Check the Package Inventory** - Review the inventory below before creating new packages
4. **Follow the interface/driver split** - If building new infrastructure, create the interface package first, then driver packages

**After creating a new package:**

5. **Update the Package Inventory** - Add the new package to the inventory section in this document so it stays current

### Common Mistakes to Avoid

| Mistake | Correct Approach |
|---------|------------------|
| Writing custom file logging | Use existing `LoggerInterface` from `marko/log` |
| Direct database queries | Use `ConnectionInterface` from `marko/database` |
| Custom caching logic | Use `CacheInterface` from `marko/cache` |
| Rolling your own config parsing | Use `ConfigRepositoryInterface` from `marko/config` |
| Depending on a driver package | Depend on the interface package instead |

### Why Interface Packages

When your code depends on `marko/log` (interface) instead of `marko/log-file` (driver):

- **Flexibility**: Applications can swap drivers without changing your code
- **Testing**: Tests can use lightweight drivers (array, null) instead of file I/O
- **Decoupling**: Your package doesn't dictate implementation details
- **Composability**: Multiple packages can share the same logger instance

---

## Package Inventory

### Core Infrastructure

| Package | Type | Description |
|---------|------|-------------|
| `marko/core` | Core | Bootstrap, DI container, module loader |
| `marko/config` | Core | Configuration repository and access |
| `marko/env` | Core | Environment variable loading |
| `marko/routing` | Core | Route attributes and router |
| `marko/cli` | Core | Global CLI command (thin client) |

### Logging

| Package | Type | Description |
|---------|------|-------------|
| `marko/log` | Interface | `LoggerInterface`, formatters, log levels |
| `marko/log-file` | Driver | File-based logging with rotation |

### Database

| Package | Type | Description |
|---------|------|-------------|
| `marko/database` | Interface | `ConnectionInterface`, query builder interfaces |
| `marko/database-mysql` | Driver | MySQL/MariaDB implementation |
| `marko/database-pgsql` | Driver | PostgreSQL implementation |

### Caching

| Package | Type | Description |
|---------|------|-------------|
| `marko/cache` | Interface | `CacheInterface`, cache items |
| `marko/cache-file` | Driver | File-based caching |
| `marko/cache-array` | Driver | In-memory caching (request lifetime) |

### Mail

| Package | Type | Description |
|---------|------|-------------|
| `marko/mail` | Interface | `MailerInterface`, `Message` builder |
| `marko/mail-smtp` | Driver | SMTP transport |
| `marko/mail-log` | Driver | Logs emails via LoggerInterface (dev/testing) |

### Queue

| Package | Type | Description |
|---------|------|-------------|
| `marko/queue` | Interface | `QueueInterface`, job dispatching |
| `marko/queue-sync` | Driver | Synchronous (immediate) execution |
| `marko/queue-database` | Driver | Database-backed queue |

### View/Templates

| Package | Type | Description |
|---------|------|-------------|
| `marko/view` | Interface | `ViewInterface`, template rendering |
| `marko/view-latte` | Driver | Latte template engine |

### Filesystem

| Package | Type | Description |
|---------|------|-------------|
| `marko/filesystem` | Interface | `FilesystemInterface`, file operations |
| `marko/filesystem-local` | Driver | Local disk operations |

### Session

| Package | Type | Description |
|---------|------|-------------|
| `marko/session` | Interface | `SessionInterface`, session management |
| `marko/session-file` | Driver | File-based sessions |

### Error Handling

| Package | Type | Description |
|---------|------|-------------|
| `marko/errors` | Interface | `ErrorHandlerInterface` |
| `marko/errors-simple` | Driver | Basic error logging |
| `marko/errors-advanced` | Driver | Pretty stack traces, suggestions |

### Other Packages

| Package | Type | Description |
|---------|------|-------------|
| `marko/authentication` | Feature | Authentication services |
| `marko/hashing` | Feature | Password hashing |
| `marko/validation` | Feature | Input validation |
| `marko/blog` | Module | Blog functionality |
| `marko/framework` | Metapackage | Bundles common packages |

---

## Naming Conventions

### Single Naming Standard

Marko uses Composer naming conventions everywhere. One name, one format, no translation between systems.

| Element      | Format              | Example                 |
|--------------|---------------------|-------------------------|
| Package name | `vendor/package`    | `marko/database`        |
| Module name  | `vendor/package`    | `acme/blog`             |
| Namespace    | PSR-4 from Composer | `Acme\Blog\Controllers` |
| Directory    | Matches Composer    | `vendor/acme/blog/`     |

### Consistency

The Composer package name is the module name. The directory structure matches. The namespace follows PSR-4. One convention everywhere eliminates confusion.

---

## Directory Structure

### Three Top-Level Directories

```
vendor/     # Packages installed via Composer
modules/    # Packages installed manually (git clone, zip, shared code)
app/        # Your application code
```

Clear purpose for each. No ambiguity.

### Vendor Directory

Contains all Composer-installed packages. Managed entirely by Composer. Don't edit files here directly.

```
vendor/
  marko/
    core/
    routing/
    database/
    database-mysql/
  acme/
    blog/
```

### Modules Directory

Contains third-party modules not installed via Composer. Use this for:

- Private client work meant to be extensible
- Internal company modules shared among multiple projects
- Downloaded modules from forums or repositories
- Rapid prototyping before publishing to Packagist
- Learning and experimentation

```
modules/
  our-shared-module/
    custom-checkout/
      composer.json
      module.php      # Optional
      src/
  internal/
    company-auth/
      composer.json
      module.php      # Optional
      src/
```

### App Directory

Contains your application modules. `app` is your vendor namespace.

```
app/
  blog/
    composer.json
    module.php        # Optional
    src/
  admin/
    composer.json
    src/
  rest-api/
    composer.json
    module.php        # Optional
    src/
```

### Override Priority

When the same thing is defined in multiple places, later directories win:

1. `vendor/` (lowest priority)
2. `modules/` (middle priority)
3. `app/` (highest priority - always wins)

### Module Internal Structure

Each module follows the same structure:

```
module-name/
  composer.json        # Package metadata (required) - name, version, require
  module.php           # Marko-specific config (optional) - enabled, sequence, bindings
  src/                 # PHP source code
    Controllers/
    Models/
    Services/
    Plugins/
    Observers/
  config/              # Module configuration files
  resources/           # Views, translations, assets
  database/            # Migrations, seeders
  tests/               # Module tests
```

---

## Module System

### Everything Is a Module

There is no distinction between "framework code," "vendor packages," and "application code." Everything is a module. Your application is modules. Vendor code is modules. Even the core code for Marko is a module. The same rules apply everywhere.

### Module Configuration Split

Module configuration is split between two files with clear responsibilities:

**composer.json (required)** - Standard Composer package metadata:
- `name` - Package name (vendor/package format)
- `version` - Semantic version
- `require` - Dependencies

**module.php (optional)** - Marko-specific configuration:
- `enabled` - Whether module is active (default: true)
- `sequence` - Load order hints (after/before other modules)
- `bindings` - Interface → implementation mappings
- `singletons` - Shared instances (see Dependency Injection section)
- `boot` - Closure that runs after all bindings are registered (receives the Container)

This split keeps standard PHP metadata in the standard location (composer.json) while Marko-specific config lives in module.php. A minimal module only needs a composer.json with a `name` field.

### Module Discovery

The framework automatically discovers modules by scanning for `composer.json` files:

1. `vendor/*/` - Two levels deep (e.g., `vendor/marko/core/composer.json`)
2. `modules/**/` - Recursive (any depth)
3. `app/*/` - One level deep (e.g., `app/blog/composer.json`)

No manual registration required. Just drop a module in the right place and it's discovered.

### Module States

- **Enabled**: Module is loaded and active (default)
- **Disabled**: Module is installed but not loaded (set `enabled => false` in manifest)

### Dependency Resolution

Modules declare their dependencies in the manifest. The framework:

1. Builds a dependency graph from all module manifests
2. Detects circular dependencies (error if found)
3. Performs topological sort to determine load order
4. Respects explicit sequence hints (after/before)

### Conflict Handling

If two modules conflict (e.g., both bind the same interface without one being a preference), the framework throws a loud error with details about the conflict and how to resolve it.

---

## Package Architecture

### Package Internal Structure

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
  composer.json           # Package metadata (required) - name, version, require
  module.php              # Marko config (optional) - enabled, sequence, bindings
```

### Interface/Implementation Split

Marko packages follow a consistent pattern: base packages define interfaces, driver packages provide implementations.

### The Pattern

```
marko/database            # Interfaces only
marko/database-mysql      # MySQL implementation, requires marko/database
marko/database-postgresql # PostgreSQL implementation, requires marko/database
```

### How It Works

1. **Base package** (`marko/database`) defines interfaces like `DatabaseInterface`, `QueryBuilderInterface`
2. **Driver packages** (`marko/database-mysql`) require the base package and bind implementations to those interfaces
3. Installing a driver automatically pulls in the base package via Composer
4. Installing only the base package results in a loud error at runtime explaining that a driver is needed

### Standard Package Splits

| Base Package     | Driver Packages                                     |
|------------------|-----------------------------------------------------|
| `marko/database` | `marko/database-mysql`, `marko/database-postgresql` |
| `marko/cache`    | `marko/cache-array`, `marko/cache-file`, `marko/cache-redis` |
| `marko/view`     | `marko/view-latte`, `marko/view-liquid`             |
| `marko/errors`   | `marko/errors-basic`, `marko/errors-advanced`       |
| `marko/queue`    | `marko/queue-rabbitmq`, `marko/queue-sqs`           |

### Metapackages

Metapackages bundle common combinations for convenience:

- **`marko/framework`**: Core + routing + database + cache + view + errors + cli (interfaces only, you pick drivers)
- **`marko/cms`**: Framework + pages + blocks + media
- **`marko/commerce`**: Framework + catalog + cart + checkout + payments

Using a metapackage is optional. You can always require packages individually for full control.

### Swapping Implementations

To replace a bundled package:

1. Use Composer's `replace` directive to tell Composer you're providing that package yourself
2. Require your alternative package
3. Your alternative binds the same interfaces

---

## Dependency Injection

### Constructor Injection Everywhere

All dependencies are injected via constructor. Explicit dependencies make code testable, readable, and maintainable.

### Autowiring

The container reads constructor signatures and automatically resolves dependencies. No manual wiring needed for simple cases.

### Bindings

Bindings tell the container what to create when something requests an interface. Defined in `module.php`.

**Simple bindings** map an interface to a class. The container autowires the class (reads its constructor, resolves params automatically):

```php
'bindings' => [
    UserRepositoryInterface::class => UserRepository::class,
],
```

**Closure bindings** are for when a simple class mapping isn't enough — when you need custom build logic like calling a method, reading config, or passing specific constructor values:

```php
'bindings' => [
    // Can't express "call a method on another service" as a class mapping
    GuardInterface::class => function (ContainerInterface $container): GuardInterface {
        return $container->get(AuthManager::class)->guard();
    },

    // Need to pass a config value to the constructor
    PasswordHasherInterface::class => function (ContainerInterface $container): PasswordHasherInterface {
        $config = $container->get(AuthConfig::class);

        return new BcryptPasswordHasher(
            cost: $config->bcryptCost(),
        );
    },
],
```

**Prefer simple bindings.** Only use closures when autowiring can't express what you need. If a class takes only type-hinted interfaces/classes as constructor params, it's autowirable — no closure needed.

### Singletons

By default, the container creates a new instance every time something asks for a class. Singletons tell the container: **build it once, then reuse that same instance for every subsequent request.**

**Key-value format** — binds and shares in one step. Use this for simple interface → implementation mappings that should be shared:

```php
'singletons' => [
    SessionInterface::class => Session::class,
],

// Equivalent to writing both:
// 'bindings'   => [SessionInterface::class => Session::class],
// 'singletons' => [SessionInterface::class],
```

**List-style format** — only marks as singleton. The container already knows how to build the class (from a `bindings` closure or via autowiring). You're just saying "only build it once":

```php
'bindings' => [
    // Closure binding — container needs custom logic to build this
    GuardInterface::class => function (ContainerInterface $container): GuardInterface {
        return $container->get(AuthManager::class)->guard();
    },
],
'singletons' => [
    AuthManager::class,      // Autowirable concrete class — just share it
    GuardInterface::class,   // Has a closure binding above — don't re-run the closure, reuse the result
],
```

Why not use key-value for `GuardInterface`? Because key-value (`GuardInterface::class => SomeClass::class`) would replace the closure with a simple class mapping, losing the custom build logic.

**When singletons matter:** Any service that holds state across the request must be a singleton. For example, if a boot callback registers policies into a `PolicyRegistry`, that registry must be a singleton — otherwise boot writes to one instance and request handlers get a different, empty one.

### Preferences

Preferences replace one concrete class with another globally. Unlike bindings (interface → implementation), preferences swap class → class.

Use the `#[Preference]` attribute on the replacement class to declare what it replaces.

### Resolution Order

When resolving a dependency:

1. Check for explicit binding in the requesting context
2. Check for preference that replaces the class
3. Check module-level bindings (app overrides modules overrides vendor)
4. Autowire if no binding exists

### Conflict Handling

If multiple modules bind the same interface without one explicitly overriding the other, the framework throws a loud error. No silent "last one wins" behavior.

### Environment-Specific Bindings

For cases where different environments need different implementations (e.g., a mock payment gateway in development vs a real one in production), use the `boot` callback in `module.php` to conditionally register bindings based on `$_ENV['APP_ENV']`.

Boot callbacks support auto-injection: any dependency registered in the container can be type-hinted as a parameter, and the container resolves it automatically. Use `ContainerInterface` when you need dynamic binding, or type-hint other dependencies directly:

```php
// module.php
use Psr\Container\ContainerInterface;

return [
    'bindings' => [
        // Default binding used in all environments
        PaymentGatewayInterface::class => StripePaymentGateway::class,
    ],
    'boot' => function (ContainerInterface $container) {
        // Override for development only
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $container->bind(
                PaymentGatewayInterface::class,
                MockPaymentGateway::class,
            );
        }
    },
];
```

Any registered dependency can be type-hinted directly — not just `ContainerInterface`:

```php
'boot' => function (HealthCheckRegistry $registry, MetricsCollector $metrics): void {
    $registry->register($metrics->asHealthCheck());
},
```

Both styles are valid. Use `ContainerInterface` when you need to call `bind()` or `get()` dynamically. Type-hint specific dependencies when you only need to call methods on them.

Boot callbacks run after all module bindings are registered, so `$container->bind()` in a boot callback overrides the static binding from the same module. This keeps the override explicit and visible in the module's own configuration.

**When to use this pattern:**
- You need a completely different implementation class per environment (mock vs real)
- The behavior difference can't be achieved through configuration values alone

**When NOT to use this pattern — use config instead:**
- Different API endpoints per environment → use config with `$_ENV`
- Enabling/disabling features → use config flags
- Different credentials → use environment variables in config files

```php
// WRONG - don't use boot callbacks for config-level differences
'boot' => function (ContainerInterface $container) {
    if ($_ENV['APP_ENV'] === 'development') {
        $container->bind(MailerInterface::class, SandboxMailer::class);
    }
};

// RIGHT - same mailer class, different config per environment
// config/mail.php
return [
    'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['MAIL_PORT'] ?? 1025),
];
```

The principle: keep DI deterministic whenever possible. The container should wire the same way across environments, with behavior differences driven by configuration. Reserve boot callback overrides for the rare case where you genuinely need a different class.

---

## Plugin System

### Purpose

Plugins modify the behavior of any public method without touching source code. This enables extending vendor code cleanly.

### Two Plugin Types Only

Marko supports only two plugin types:

1. **Before**: Runs before the original method. Return `null` to pass through, return an `array` to modify the arguments passed to the original method, or return any other non-null value to short-circuit (skip the original method entirely).
2. **After**: Runs after the original method. Can modify the return value. When multiple after plugins target the same method, each after plugin's return value is passed as `$result` to the next after plugin in sort order (chaining).

### No Around Plugins

Marko intentionally does not support around plugins. If you need to completely replace a method's behavior, use a Preference on the class instead. This keeps the call stack clean and makes the intent explicit.

### Sort Order

Sort order determines execution sequence when multiple plugins target the same method. Sort order is defined on the attribute at the method level only, not the class level: `#[Before(sortOrder: 10)]`.

- Lower numbers run first (includes negative numbers)
- Default is 0
- Only matters when multiple plugins hook the same method with the same timing (before or after)

### Plugin Discovery

Plugins are discovered via the `#[Plugin]` attribute on classes. Methods with `#[Before]` or `#[After]` attributes are interceptors. The method name determines which target method is intercepted — a method named `save()` with `#[Before]` intercepts `save()` on the target. Use `#[Before(method: 'targetMethod')]` to target a specific method when the plugin method has a different name. Before plugins have three return behaviors: `null` (pass through), `array` (modify arguments), or any other non-null value (short-circuit). After plugins receive the result and must return the (possibly modified) result; when multiple after plugins target the same method they chain — each one's return value becomes the next one's input.

---

## Event System

### Purpose

Events decouple "something happened" from "react to it." Unlike plugins (which modify specific method behavior), events broadcast that something occurred and let any number of observers react.

### Dispatching Events

Any code can dispatch an event. Events can be string-based (simple) or class-based (type-safe).

### Observers

Observers react to events. They are discovered via the `#[Observer]` attribute.

### Observer Priority

When multiple observers listen to the same event, priority determines execution order. Higher priority runs first.

### Async Observers

Observers can be marked as async. They are queued for later processing rather than running during the request. (Requires a queue module to be installed.)

### Events vs Plugins

| Use Events When...                       | Use Plugins When...                 |
|------------------------------------------|-------------------------------------|
| Multiple independent reactions needed    | Modifying specific method behavior  |
| Reactions are optional/decoupled         | Transformation is required          |
| You're broadcasting "something happened" | You're intercepting "do this thing" |
| Order of reactions doesn't matter much   | Execution order is critical         |
| Async processing is acceptable           | Synchronous modification needed     |

---

## Routing

### Attribute-Based Routes

Routes are defined via attributes on controller methods. No separate route files required.

### Route Attributes

- `#[Get(path)]` - GET request
- `#[Post(path)]` - POST request
- `#[Put(path)]` - PUT request
- `#[Patch(path)]` - PATCH request
- `#[Delete(path)]` - DELETE request

Each attribute accepts a path and optional middleware array.

### Route Parameters

Route parameters are defined in the path using curly braces: `/posts/{slug}`. Parameters are passed to the controller method.

### Modifying Route Behavior

To change what a route does without changing the route definition, use plugins on the controller method. The route stays the same; the behavior changes.

### Overriding Routes

To completely replace a vendor's controller, use `#[Preference]` on your controller class that extends the vendor's controller.

**Inheritance rules for routes when using Preference:**

| Scenario                                 | Result                           |
|------------------------------------------|----------------------------------|
| Method not overridden                    | Parent's route attribute applies |
| Method overridden with route attribute   | Your route attribute applies     |
| Method overridden with `#[DisableRoute]` | Route is intentionally removed   |
| Method overridden with no attribute      | **ERROR** - ambiguous intent     |

### Disabling Routes

To intentionally remove a route, override the method with the `#[DisableRoute]` attribute. This makes the intent explicit.

### Route Conflicts

If two modules define the same route path without one being a Preference of the other, the framework throws a loud error. Resolution options:

- Use Preference to make one extend the other
- Change one of the paths
- Disable one of the routes

### No Silent Override

Overriding a routed method without declaring intent (no route attribute, no DisableRoute) is an error. The framework requires explicit declaration.

---

## Configuration

### PHP Only

Configuration is always PHP. No XML. No YAML. No DSL. This provides:

- IDE autocompletion
- Type checking
- Easy refactoring
- Conditional logic when needed
- Actual syntax errors instead of silent failures

### Config Files Are the Single Source of Truth

All default values belong in config files, not hardcoded in application code. Getter methods throw `ConfigNotFoundException` when keys are missing - there are no silent fallbacks.

This provides:

- **Visibility** - All configurable options visible in one place
- **Override capability** - Higher-priority modules can override any value
- **Loud errors** - Missing config fails immediately with helpful messages
- **No hidden defaults** - Developers see exactly what values are used

```php
// CORRECT - config file defines defaults
// config/blog.php
return [
    'posts_per_page' => 10,
];

// CORRECT - no fallback, throws if missing
$perPage = $config->getInt('blog.posts_per_page');

// WRONG - hardcoded fallback hides missing config
$perPage = $config->getInt('blog.posts_per_page', 10);
```

### Module Configuration

Each module can have configuration files in its `config/` directory. These are PHP files that return arrays.

### Environment Variables

Environment variables should **only** be referenced in config files (`config/*.php`), never in application code. Config files act as the translation layer between environment and application.

```php
// CORRECT - env var in config file only
// config/database.php
return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
];

// Application code reads config, not env vars
$host = $config->getString('database.host');

// WRONG - reading env vars in application code
$host = $_ENV['DB_HOST'] ?? 'localhost';
```

This centralizes environment handling and ensures all configurable values are documented in config files.

### Scoped Configuration (Multi-tenant)

Configuration supports scoped access for multi-tenant applications. This is an **intentional exception** to the no-fallback rule.

When accessing config with a scope parameter, values cascade in this order:

1. `scopes.{tenant}` - Tenant-specific value (if exists)
2. `default` - Shared default value (if exists)
3. Direct access - Top-level config key

```php
// config/store.php
return [
    'default' => [
        'currency' => 'USD',
        'tax_rate' => 0.08,
    ],
    'scopes' => [
        'tenant-eu' => [
            'currency' => 'EUR',
            'tax_rate' => 0.19,
        ],
    ],
];

// Scoped access uses cascade
$config->getString('store.currency', scope: 'tenant-eu');  // 'EUR' (from scopes.tenant-eu)
$config->getFloat('store.tax_rate', scope: 'tenant-uk');   // 0.08 (falls back to default)

// Without scope - no cascade, accesses directly
$config->get('store.currency');                            // null (not at top level)
$config->get('store.default.currency');                    // 'USD' (explicit path)
```

**Why this cascade is intentional:** Multi-tenant applications need tenants to inherit shared defaults while overriding specific values. Without cascade, every tenant config would need to duplicate all shared values. This is NOT a fallback for missing configuration - it's a feature for configuration inheritance.

---

## PHP Attributes

### Single Mechanism for Metadata

Marko uses PHP attributes as the single mechanism for decorating code with metadata. No XML. No config file alternatives. One way.

### Why Attributes

**Three clean separations:**
- Class name: What something IS
- Method name: What it DOES
- Attribute: WHERE it hooks or WHAT role it plays

**Additional benefits:**
- Tooling can discover everything via reflection
- Type safety and IDE support
- Colocation - metadata lives with the code it describes

### When to Use Attributes

Attributes are for metadata attached to code - things that describe what a class or method does within the framework:

- Plugins (`#[Plugin]`, `#[Before]`, `#[After]`)
- Observers (`#[Observer]`)
- Routes (`#[Get]`, `#[Post]`, etc.)
- Preferences (`#[Preference]`)
- Commands (`#[Command]`)
- Route disabling (`#[DisableRoute]`)
- Validation rules

### When NOT to Use Attributes

Attributes are wrong for system wiring and environment configuration:

- Module manifests (use `module.php`)
- Interface → implementation bindings (use module manifest)
- Environment-specific config (use config files + env vars)
- Feature flags (use config)
- Credentials and secrets (use environment variables)

### Framework Attributes Quick Reference

| Attribute                 | Target       | Purpose                                   |
|---------------------------|--------------|-------------------------------------------|
| `#[Plugin]`               | Class        | Marks class as a plugin for another class |
| `#[Before]`               | Method       | Plugin method runs before target          |
| `#[After]`                | Method       | Plugin method runs after target           |
| `#[Observer]`             | Class        | Reacts to dispatched events               |
| `#[Preference]`           | Class        | Replaces another class globally           |
| `#[Get]`, `#[Post]`, etc. | Method       | Defines HTTP route                        |
| `#[Middleware]`           | Method/Class | Applies middleware                        |
| `#[DisableRoute]`         | Method       | Explicitly removes inherited route        |
| `#[Command]`              | Class        | Registers CLI command                     |

### PHP Built-in Attributes to Use

| Attribute        | Purpose                        |
|------------------|--------------------------------|
| `#[\NoDiscard]`  | Warn if return value ignored   |
| `#[\Override]`   | Verify method overrides parent |
| `#[\Deprecated]` | Mark as deprecated             |

---

## CLI System

### Global `marko` Command

The CLI is installed globally via Composer and available everywhere:

```
composer global require marko/cli
```

Once installed, the `marko` command is available in any Marko project.

### Thin Client Architecture

The global `marko` command is intentionally minimal. It:

1. Finds the project root (looks for `vendor/marko/core`)
2. Boots the application via bootstrap.php
3. Hands off to the booted application to run the requested command

The global CLI has no functionality of its own. It never needs updating. All commands come from modules.

### Command Discovery

Modules register commands via the `#[Command]` attribute on command classes. The CLI discovers all commands at runtime by scanning loaded modules.

### Command Namespacing

Commands are namespaced by convention:

- `db:migrate` - database commands
- `route:list` - routing commands
- `module:list` - core module commands
- `cache:clear` - cache commands

### Built-in Commands

`marko/core` provides basic commands:
- `module:list` - Show all modules and their status
- `list` - Show all available commands

Other modules provide their own:
- `marko/routing`: `route:list`
- `marko/database`: `db:migrate`, `db:rollback`, `db:seed`
- `marko/cache`: `cache:clear`

---

## Error Handling

### Fail Loud Philosophy

Every error provides:
- Clear description of what went wrong
- Context about where it happened
- Suggestion for how to fix it

### Error Handler Interface

Error handling is provided by a module that binds `ErrorHandlerInterface`. This follows the standard interface/implementation split:

- `marko/errors` - Defines interfaces
- `marko/errors-basic` - Simple logging, production-appropriate
- `marko/errors-advanced` - Pretty stack traces, suggestions, development-friendly

### Common Error Types

| Error                         | Cause                                | Resolution                                    |
|-------------------------------|--------------------------------------|-----------------------------------------------|
| `BindingException`            | No implementation for interface      | Install appropriate driver package            |
| `BindingConflictException`    | Multiple bindings for same interface | Resolve in app module or use Composer replace |
| `ModuleException`             | Module manifest issues               | Check syntax and dependencies                 |
| `CircularDependencyException` | Modules depend on each other         | Refactor module boundaries                    |
| `RouteConflictException`      | Duplicate route definitions          | Use Preference or change paths                |
| `RouteException`              | Override without attribute           | Add route attribute or DisableRoute           |
| `PluginException`             | Plugin configuration issues          | Check target class and method exist           |

---

## Bootstrap Process

### Entry Point

Every Marko application starts with `vendor/marko/core/bootstrap.php`. This is the one hardcoded entry point.

### marko/core Special Status

`marko/core` is both a module (has `composer.json`) and the bootstrap (has `bootstrap.php`). It's the only package with this dual role because it's the foundation that loads everything else.

### Boot Sequence

1. **Autoload**: Composer autoloader is loaded
2. **Bootstrap**: `bootstrap.php` is executed
3. **Scan**: Module loader scans `vendor/`, `modules/`, and `app/` for `composer.json` files
4. **Parse**: All module manifests are read
5. **Validate**: Dependencies are checked, conflicts detected
6. **Sort**: Topological sort determines load order
7. **Boot**: Modules are loaded in order, bindings registered
8. **Ready**: Container is ready, application can handle requests

### Web Entry Point

```
public/index.php
  → vendor/autoload.php
  → vendor/marko/core/bootstrap.php
  → Handle HTTP request
```

### CLI Entry Point

```
marko command:name
  → Find project root
  → vendor/autoload.php
  → vendor/marko/core/bootstrap.php
  → Run command
```

---

## Development & Release Process

### Repository Structure

Marko is developed as a monorepo hosted at `github.com/devtomic/marko`. All packages live in a single repository for easier maintenance, coordinated changes, and simplified contribution.

```
marko/
  packages/
    core/
    routing/
    database/
    database-mysql/
    database-postgresql/
    cache/
    cache-file/
    cache-redis/
    view/
    view-latte/
    cli/
    errors/
    errors-advanced/
  composer.json
  README.md
  LICENSE
```

Each package under `packages/` has its own `composer.json` and can be installed independently via Composer.

### Split Repositories

For Composer/Packagist compatibility, each package is automatically split to its own read-only repository:

- `github.com/devtomic/marko-core` (read-only mirror)
- `github.com/devtomic/marko-routing` (read-only mirror)
- `github.com/devtomic/marko-database` (read-only mirror)
- etc.

These split repos are automatically synced via GitHub Actions. Contributors never commit to them directly - all development happens in the main monorepo.

### Unified Versioning

All Marko packages share the same version number. When a release is tagged, every package gets that tag, even if only one package changed.

**Why unified versioning:**

| Benefit              | Explanation                                   |
|----------------------|-----------------------------------------------|
| Clear compatibility  | All v1.2.3 packages work together, guaranteed |
| Simple upgrades      | Upgrade everything to same version, no matrix |
| One changelog        | All changes documented in one place           |
| No version confusion | No "which routing works with which core?"     |

**Example:**

A bug fix in `marko/database` triggers release v1.2.4. All packages become v1.2.4:

- `marko/core: 1.2.4` (no changes, just new tag)
- `marko/routing: 1.2.4` (no changes, just new tag)
- `marko/database: 1.2.4` (contains the fix)

Users see `1.2.4` and know everything is compatible. Packages without changes simply have identical code to the previous version.

### Semantic Versioning

Marko follows semantic versioning (SemVer):

- **Patch** (1.2.3 → 1.2.4): Bug fixes, no breaking changes
- **Minor** (1.2.4 → 1.3.0): New features, backward compatible
- **Major** (1.3.0 → 2.0.0): Breaking changes

### Release Workflow

Releases are managed through the monorepo with automated tooling:

1. **Development**: All work happens in the monorepo via pull requests
2. **Release**: Maintainer runs release command with desired version
3. **Automation**: Tooling updates interdependencies, tags release, pushes
4. **Split**: GitHub Action splits each package to its read-only repo with the same tag
5. **Packagist**: Packagist picks up new tags from split repos automatically

### Interdependencies

Packages that depend on each other use caret version constraints:

```json
{
    "require": {
        "marko/core": "^1.2"
    }
}
```

When a release is made, all interdependencies are automatically updated to require the new version. This ensures packages stay in sync.

### Official Resources

| Resource            | Location                     |
|---------------------|------------------------------|
| Website             | marko.build                  |
| GitHub Organization | github.com/devtomic          |
| Monorepo            | github.com/devtomic/marko    |
| Packagist           | packagist.org/packages/marko |

---

## Summary

### One Way, The Right Way

- Attributes for code metadata
- Config files for system wiring
- Modules for everything
- Loud errors for everything else

### Explicit Over Implicit

- Explicit dependencies via constructor injection
- Explicit overrides via Preferences
- Explicit configuration via PHP files
- No silent failures
- No traits (use composition instead)
- No magic methods

### True Modularity

- Everything is a module
- Interface/implementation split
- Clean package boundaries
- Composer-native naming
- Three directories with clear purpose

### Developer Protection

- Constraints that create quality
- Guardrails that guide, not wall
- Every "no" has a "yes, this way instead"

**Marko: Build things that last.**

