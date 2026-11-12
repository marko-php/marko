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

use Marko\Cache\Contracts\CacheInterface;
use Marko\Database\Connection\ConnectionInterface;

readonly class PostService
{
    public function __construct(
        private ConnectionInterface $connection,
        private CacheInterface $cache,
    ) {}

    public function getPost(int $id): ?array
    {
        $cached = $this->cache->get("post.{$id}");

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->connection->query(
            'SELECT * FROM posts WHERE id = ?',
            [$id],
        );

        return $result[0] ?? null;
    }
}
```

The container sees that `PostService` needs a `ConnectionInterface` and a `CacheInterface`, resolves them from the registered bindings, and passes them in.

## Bindings

Bindings tell the container which concrete class to use when an interface is requested. They're declared in `module.php`:

```php title="module.php"
<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\File\Driver\FileCacheDriver;

return [
    'bindings' => [
        CacheInterface::class => FileCacheDriver::class,
    ],
];
```

Now whenever any class requests `CacheInterface`, the container creates a `FileCacheDriver`.

## Singletons

By default, the container creates a **new instance** every time a class is resolved. For classes that should be created once and reused (database connections, loggers, etc.), register them as singletons:

```php
return [
    'singletons' => [
        FileCacheDriver::class,
    ],
];
```

You can also combine singletons with bindings:

```php
return [
    'bindings' => [
        CacheInterface::class => FileCacheDriver::class,
    ],
    'singletons' => [
        FileCacheDriver::class,
    ],
];
```

## Argument Substitution

Autowiring fills every constructor parameter from the container. Sometimes one parameter needs a different value: a setting read from config, a specific implementation, or an explicit `null`. The `arguments` key in `module.php` sets individual constructor arguments by parameter name, and every parameter you do not list still autowires.

```php title="module.php"
<?php

declare(strict_types=1);

use Marko\Authorization\Config\AuthorizationConfig;
use Marko\Authorization\Gate;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Core\Container\ContainerInterface;

return [
    'bindings' => [
        GateInterface::class => Gate::class,
    ],
    'arguments' => [
        Gate::class => [
            'guard' => fn (ContainerInterface $container): GuardInterface => $container
                ->get(AuthManager::class)
                ->guard($container->get(AuthorizationConfig::class)->defaultGuard()),
        ],
    ],
];
```

`Gate` takes a guard, a policy registry, a gate hook registry and an event dispatcher. Only `guard` needs custom logic, so it is the only entry; the other three autowire. The interface is bound to the class, so plugins on `Gate` apply when it is resolved through `GateInterface`.

### How a value is read

The container reads each value from the declared type of the parameter it is for:

| Parameter type | Value | Result |
|---|---|---|
| A class or interface | A class name string | The class is resolved through the container (so its own arguments and bindings apply) |
| A built-in type (`string`, `int`, `bool`, `array`, `float`) | A literal | Used as given |
| An enum | An enum case | Used as given |
| Any type | A closure | A factory called with the container, same as a closure binding; its return value is the argument |
| A nullable type | `null` | Passed as `null`, even for a class-typed parameter |

```php title="module.php"
use Marko\DocsMarkdown\MarkdownRepository;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;

return [
    'arguments' => [
        // Literal for a string parameter
        MarkdownRepository::class => [
            'docsPath' => __DIR__ . '/docs',
        ],
        // null stops the optional, class-typed parameter from being autowired
        AdvancedErrorHandler::class => [
            'prettyHtmlFormatter' => null,
        ],
    ],
];
```

`null` matters for optional class-typed parameters: the autowirer resolves them from the container even when they have a default, so an unbound `?SomeInterface $x = null` throws unless you list it as `null`.

Union, intersection, `mixed`, `object` and untyped parameters cannot be read from a type, so they require a closure. Object instances and variadic parameters are rejected. A `Closure`-typed parameter receives whatever closure your factory returns.

### Loud errors

Arguments are validated at the end of every boot, after the `boot` callbacks have run, and again when a class is resolved. A bad entry fails with a message, the offending class and parameter, and a suggestion. Nothing is silently ignored:

- **Unknown parameter**: the class has no constructor parameter with that name (`ArgumentException`)
- **Type mismatch**: the value does not fit the parameter's type, for example a string for an `int`, or a class that does not satisfy the declared type
- **Interface key**: arguments are keyed by concrete classes, never by an interface. Bind the interface to a class and key the class
- **Closure-bound class**: the class is bound to a closure, so its constructor never receives arguments
- **Rebound class**: `bindings` maps the class to another class, so it is never built and its arguments never apply
- **Ambiguous type**: the parameter's type is a union, intersection, `mixed`, `object` or untyped, so a non-closure value cannot be checked

### Overriding per argument

Arguments merge **per argument** across module priority: `vendor` < `modules` < `app`. An `app` module that sets one argument of a class overrides only that argument and leaves the ones a vendor module set.

```php title="app/web/module.php"
use Marko\Translation\Translator;

return [
    'arguments' => [
        // Only fallbackLocale changes; the vendor module's 'locale' entry still applies
        Translator::class => [
            'fallbackLocale' => 'de',
        ],
    ],
];
```

If two modules at the **same priority** set the same argument of the same class, the container throws an `ArgumentConflictException` naming both modules, the same rule as binding and preference conflicts.

### Arguments and Preferences

When `#[Preference]` replaces class `A` with class `B`, `B` is built with `A`'s arguments, and `B`'s own arguments override them per argument. Every parameter name `A`'s arguments set must exist on `B`'s constructor, or boot fails.

A plain `extends` without a `#[Preference]` inherits nothing: `B` extends `A` is a different class, and its arguments are its own.

### Choosing between a binding, arguments and a closure

| Situation | Use |
|---|---|
| An interface maps to one implementation that autowires | A plain binding |
| Most parameters autowire; one or a few need a value | `arguments` |
| The construction has real branching or builds several objects | A closure binding |

Prefer a plain binding, then `arguments`, and reach for a closure binding only when the logic does not fit a per-parameter value.

Two behaviours to know:

- **A closure binding on a class takes over construction.** If a higher-priority module binds a class to a closure, that closure builds the class and the arguments set by lower-priority modules are dropped. A closure binding of equal or lower priority than the arguments, or one made by `$container->bind()` in a boot callback, throws instead.
- **Known limit:** an interface bound to a closure that calls `new Foo(...)` itself bypasses `Foo`'s arguments, because the container never builds `Foo`. This is not detected. Bind the interface to the class (`GateInterface::class => Gate::class`) so the container builds it.

### Parameter names are public API

Once a class has `arguments` entries, its constructor parameter names are part of its public API: renaming `guard` to `defaultGuard` breaks every module that sets it, and boot fails with the unknown-parameter error. Treat a rename as a breaking change.

## Why There Are No Virtual Types

Magento lets you declare a **virtual type**: a name in XML that stands for an existing class with different constructor arguments, and which you can then inject or bind as if it were a class. It is Magento's way to configure the same class two ways without writing a subclass.

Marko does not have them. A virtual type is a class that has no file: it cannot be type-hinted, grepped for, navigated to in an IDE, or analyzed by PHPStan, because it exists only as a string in configuration. That conflicts with the "explicit over implicit" principle.

Instead, use one of these:

- **A real subclass.** If you need a second configuration of a class, write the class. It has a name, a file, and a place in `module.php` bindings.
- **Arguments.** If you only need to change what a class receives, set `arguments` for it. The class stays the real class, and the change is visible in one `module.php` entry.

## Resolution Order

When the container resolves a dependency, it follows this priority:

1. **Explicit bindings** — registered in `module.php`
2. **Preferences** — interface-to-implementation swaps from higher-priority modules
3. **Auto-resolution** — if a concrete class is requested (not an interface), the container creates it automatically by resolving its constructor parameters

Whichever builds the class, parameters named in that class's `arguments` take the configured value and the rest autowire. Closure bindings build the class themselves, so `arguments` do not apply to them.

### Auto-Resolution Example

If you request a concrete class with no special bindings, the container just builds it:

```php
// No binding needed — container sees the constructor and resolves dependencies
readonly class PostController
{
    public function __construct(
        private PostService $postService,
    ) {}
}
```

The container resolves `PostService`, which triggers resolution of its dependencies, and so on recursively.

## What Won't Auto-Resolve

The container cannot auto-resolve:

- **Interfaces** without a binding (throws a clear error telling you to add a binding)
- **Scalar parameters** (`string`, `int`, etc.) without defaults. Give them a value with [`arguments`](#argument-substitution)
- **Union types** or `mixed` without a binding. A closure in `arguments` can supply them

In all cases, Marko throws a loud, helpful error explaining exactly what's missing and how to fix it.

## Overriding another module's bindings

Sometimes a module needs to replace a binding that a different module — often a vendor package — has already registered. The static `bindings` key cannot do this: the `BindingRegistry` throws a `BindingConflictException` when two same-priority modules attempt to bind the same interface, protecting against accidental conflicts.

The supported escape hatch is a `boot` callback. Boot callbacks run after **all** static bindings are registered and call `$container->bind()` directly, which intentionally allows rebinding. The example below is from a third-party `acme/database-cockroachdb` package overriding a binding owned by `marko/database-pgsql` — variant packages live under their own vendor namespace (the `marko/` namespace is reserved for core Marko packages):

```php title="module.php"
<?php

declare(strict_types=1);

use Acme\Database\CockroachDb\Diff\CockroachDbGenerator;
use Marko\Core\Container\Container;
use Marko\Database\Diff\SqlGeneratorInterface;

return [
    'boot' => function (Container $container): void {
        $container->bind(SqlGeneratorInterface::class, CockroachDbGenerator::class);
    },
];
```

Because this is an explicit call inside your own `module.php`, the intent is clear and visible — there is no hidden conflict.

### Override timing and load order

Boot callbacks run in module sequence order. A variant module that `require`s its parent in `composer.json` automatically boots after it — no extra configuration needed. Use `sequence.after` only when there is no `require` relationship:

```php title="module.php"
return [
    'sequence' => [
        'after' => ['marko/database-pgsql'],
    ],
    'boot' => function (Container $container): void {
        $container->bind(SqlGeneratorInterface::class, CockroachDbGenerator::class);
    },
];
```

For most sibling driver packages the `composer.json` `require` is sufficient — Marko derives the boot order from it automatically.

### Singleton caveat

If the interface being overridden is also listed in the parent module's `singletons` key **and** a request resolves it before the variant's boot callback runs, the container caches that first instance. Subsequent `bind()` calls will not replace the already-cached instance. The override pattern is safe whenever the interface is not a singleton, or when nothing resolves it before boot completes.

If you need to override a singleton binding, ensure nothing resolves the interface during the registration phase (before boot runs), or use `$container->instance()` to replace the cached object directly.

For the full database-specific application of this pattern — including dialect, schema, and query-builder interfaces — see [Database Package](/docs/packages/database/).

## Next Steps

- [Preferences](/docs/concepts/preferences/) — swap implementations across modules
- [Plugins](/docs/concepts/plugins/) — intercept method calls
- [Modularity](/docs/concepts/modularity/) — how modules provide bindings
- [Core Package](/docs/packages/core/) — API reference for the DI container
