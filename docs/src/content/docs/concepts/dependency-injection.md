---
title: Dependency Injection
description: How Marko's DI container resolves dependencies automatically.
---

Marko has a built-in dependency injection container that resolves classes and interfaces automatically. You rarely interact with the container directly — just type-hint your constructor parameters and Marko wires everything together.

## Constructor Injection

The primary way to receive dependencies is through constructor injection:

```php
<?php

declare(strict_types=1);

namespace App\Blog\Service;

use Marko\Cache\CachePoolInterface;
use Marko\Database\ConnectionInterface;

class PostService
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CachePoolInterface $cachePool,
    ) {}

    public function getPost(int $id): array
    {
        return $this->cachePool->remember("post.{$id}", function () use ($id) {
            return $this->connection->table('posts')->find($id);
        });
    }
}
```

The container sees that `PostService` needs a `ConnectionInterface` and a `CachePoolInterface`, resolves them from the registered bindings, and passes them in.

## Bindings

Bindings tell the container which concrete class to use when an interface is requested. They're declared in `module.php`:

```php title="module.php"
<?php

declare(strict_types=1);

use Marko\Cache\CachePoolInterface;
use Marko\Cache\File\FileCachePool;

return [
    'bindings' => [
        CachePoolInterface::class => FileCachePool::class,
    ],
];
```

Now whenever any class requests `CachePoolInterface`, the container creates a `FileCachePool`.

## Singletons

By default, the container creates a **new instance** every time a class is resolved. For classes that should be created once and reused (database connections, loggers, etc.), register them as singletons:

```php
return [
    'singletons' => [
        FileCachePool::class,
    ],
];
```

You can also combine singletons with bindings:

```php
return [
    'bindings' => [
        CachePoolInterface::class => FileCachePool::class,
    ],
    'singletons' => [
        FileCachePool::class,
    ],
];
```

## Resolution Order

When the container resolves a dependency, it follows this priority:

1. **Explicit bindings** — registered in `module.php`
2. **Preferences** — interface-to-implementation swaps from higher-priority modules
3. **Auto-resolution** — if a concrete class is requested (not an interface), the container creates it automatically by resolving its constructor parameters

### Auto-Resolution Example

If you request a concrete class with no special bindings, the container just builds it:

```php
// No binding needed — container sees the constructor and resolves dependencies
class PostController
{
    public function __construct(
        private readonly PostService $postService,
    ) {}
}
```

The container resolves `PostService`, which triggers resolution of its dependencies, and so on recursively.

## What Won't Auto-Resolve

The container cannot auto-resolve:

- **Interfaces** without a binding (throws a clear error telling you to add a binding)
- **Scalar parameters** (`string`, `int`, etc.) without defaults
- **Union types** or `mixed` without a binding

In all cases, Marko throws a loud, helpful error explaining exactly what's missing and how to fix it.

## Next Steps

- [Preferences](/docs/concepts/preferences/) — swap implementations across modules
- [Plugins](/docs/concepts/plugins/) — intercept method calls
- [Modularity](/docs/concepts/modularity/) — how modules provide bindings
- [Core Package](/docs/packages/core/) — API reference for the DI container
