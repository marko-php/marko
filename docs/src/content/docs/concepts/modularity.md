---
title: Modularity
description: How Marko's module system works — discovery, priority, and composition.
---

Modularity is the foundation of Marko. Every feature — routing, caching, authentication, your business logic — is a module. Understanding how modules work unlocks the full power of the framework.

## What Is a Module?

A module is any Composer package that Marko recognizes. At minimum, it needs a `name`, PSR-4 `autoload` mapping, and the `extra.marko.module` flag set to `true`:

```json title="composer.json"
{
    "name": "app/blog",
    "autoload": {
        "psr-4": {
            "App\\Blog\\": "src/"
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

The `extra.marko.module` flag tells Marko this package should be wired into the application. Without it, the package is treated as a plain Composer dependency — installed but not discovered by the module system.

No service provider registration, no boot methods, no kernel configuration. Marko discovers modules automatically.

## Module Discovery

Marko scans three locations for modules, in priority order:

```
app/        → Your application (wins all conflicts)
modules/    → Third-party (overrides vendor)
vendor/     → Composer packages (base defaults)
```

Discovery is automatic. Drop a module in `app/` or `modules/`, and Marko picks it up on the next request.

## The `module.php` File

Each module can optionally include a `module.php` at its root. This file declares the module's dependency injection wiring:

```php title="module.php"
<?php

declare(strict_types=1);

use App\Blog\Repository\PostRepository;
use App\Blog\Repository\PostRepositoryInterface;

return [
    // Bind interfaces to implementations
    'bindings' => [
        PostRepositoryInterface::class => PostRepository::class,
    ],

    // Register shared instances (created once, reused)
    'singletons' => [
        PostRepository::class,
    ],
];
```

### What Can `module.php` Declare?

| Key | Purpose |
|---|---|
| `bindings` | Map interfaces to concrete implementations |
| `singletons` | Classes that should only be instantiated once |

## Interface/Implementation Split

Marko packages follow a deliberate pattern: interfaces and implementations are separate packages.

```
marko/cache          → CachePoolInterface (the contract)
marko/cache-file     → FileCachePool (file-based)
marko/cache-redis    → RedisCachePool (Redis-based)
```

Your application code depends on the **interface package**:

```php
use Marko\Cache\CachePoolInterface;

class ProductService
{
    public function __construct(
        private readonly CachePoolInterface $cachePool,
    ) {}
}
```

The **implementation** is wired in `module.php`. To switch from file cache to Redis, change one binding — zero application code changes.

## Module Composition

Modules can build on each other. The `marko/blog` module depends on `marko/routing`, `marko/database`, and `marko/view` — but you never wire that manually. Composer handles the dependency graph, and Marko handles the module discovery.

```json title="composer.json"
{
    "name": "marko/blog",
    "require": {
        "marko/core": "^1.0",
        "marko/routing": "^1.0",
        "marko/database": "^1.0"
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

## Overriding Vendor Behavior

The priority system means your `app/` modules always win. But Marko provides structured ways to customize vendor behavior:

- **[Preferences](/docs/concepts/preferences/)** — Swap an interface's implementation entirely
- **[Plugins](/docs/concepts/plugins/)** — Modify a method's input or output without replacing the class
- **[Events & Observers](/docs/concepts/events/)** — React to things happening in the system

## Next Steps

- [Dependency Injection](/docs/concepts/dependency-injection/) — how the container resolves modules
- [Preferences](/docs/concepts/preferences/) — swapping implementations
- [Plugins](/docs/concepts/plugins/) — intercepting method calls
- [Core Package](/docs/packages/core/) — API reference for module discovery and the DI container
- [Routing Guide](/docs/guides/routing/) — define and handle HTTP routes
