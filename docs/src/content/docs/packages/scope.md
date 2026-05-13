---
title: marko/scope
description: Scoped entity attributes with multi-axis hierarchical fallback.
---

Scoped attributes for entities with multi-axis hierarchical fallback. `marko/scope` defines the contracts and core logic for attaching per-scope override values to entity properties. Each property marked `#[Scoped]` can carry different values across multiple independent axes (e.g. `locale`, `market`, `channel`), with automatic walk-up through the declared hierarchy when no exact match exists. The package ships the `#[Scoped]` attribute, `ScopeContext`, `ScopeResolver`, `HasScopesInterface`, the `HasScopes` trait, and the `ScopedOrderBy` query specification --- but no database driver. Applications must install `marko/scope-mysql` or `marko/scope-pgsql` to persist and query overrides.

## Installation

```bash
composer require marko/scope
```

No sort renderer is bound by default. You must also install a driver package:

```bash
composer require marko/scope-mysql
# or
composer require marko/scope-pgsql
```

## Configuration

Declare axes and their path hierarchies in `config/scope.php`:

```php title="config/scope.php"
<?php

declare(strict_types=1);

return [
    'axes' => [
        'locale' => [
            'hierarchy' => [
                'en',
                'de',
                'de-DE',
                'de-AT',
                'fr',
                'fr-FR',
                'fr-BE',
            ],
        ],
        'market' => [
            'hierarchy' => [
                'eu',
                'eu.de',
                'eu.fr',
                'eu.at',
                'us',
                'us.east',
                'us.west',
            ],
        ],
    ],
];
```

Paths use dot notation. `walkUp('eu.de')` yields `['eu.de', 'eu']`, so a value set at `eu` is inherited by `eu.de` when no `eu.de`-specific override exists.

## Usage

### Marking a property as scoped

Add `#[Scoped(axes: [...])]` to any entity property that should carry per-scope override values. There are two ways to store the overrides: the `HasScopes` trait (recommended for entities you own) and a companion `ScopedOverridesEntity` class (for entities you cannot modify).

### Storage approach 1: `HasScopes` trait (recommended)

Implement `HasScopesInterface` and use the `HasScopes` trait on your entity. The trait declares a `$scopes` JSON column automatically --- no separate migration helper or companion class is required:

```php title="app/catalog/Entity/Product.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Scope\Attributes\Scoped;
use Marko\Scope\Storage\HasScopes;
use Marko\Scope\Storage\HasScopesInterface;

#[Table('products')]
class Product extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    #[Scoped(axes: ['locale'])]
    public string $name = '';

    #[Column(type: 'decimal', precision: 10, scale: 2)]
    #[Scoped(axes: ['market'])]
    public float $price = 0.0;
}
```

Register `Product` with the `SchemaRegistry`. The `scopes` column will appear in the `products` table after the next migration run.

### Storage approach 2: companion class

Use a `ScopedOverridesEntity` subclass when scoped overrides are contributed by a separate package, or when the entity class cannot be modified. Declare it with `#[Table(extends: Product::class)]` so the database layer merges the `scopes` JSON column into the entity's table:

```php title="app/catalog/Entity/ProductScopedOverrides.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use Marko\Database\Attributes\Table;
use Marko\Scope\Storage\ScopedOverridesEntity;

#[Table(extends: Product::class)]
class ProductScopedOverrides extends ScopedOverridesEntity
{
}
```

One companion class handles all scoped properties on the entity --- both `$name` and `$price` above share the same `ProductScopedOverrides`.

Register both `Product` and `ProductScopedOverrides` with the `SchemaRegistry`.

### Setting the active context

Inject `ScopeContext` and call `in()` to set the active path for each axis before resolving values:

```php
<?php

declare(strict_types=1);

use Marko\Scope\Context\ScopeContext;

$context->in('locale', 'de-DE');
$context->in('market', 'eu.fr');
```

### Writing overrides

Use `ScopeResolver::setOverride()` to attach a scoped value to an entity before persisting:

```php
<?php

declare(strict_types=1);

use Marko\Scope\Resolver\ScopeResolver;
use Marko\Scope\Scope;

$product = new Product();
$product->name = 'Widget';
$product->price = 100.00;

// Set a German locale override for the name
$scopeResolver->setOverride($product, 'name', 'Widget DE', new Scope('locale', 'de'));

// Set market overrides for price at different hierarchy levels
$scopeResolver->setOverride($product, 'price', 89.99, new Scope('market', 'eu'));
$scopeResolver->setOverride($product, 'price', 79.99, new Scope('market', 'eu.de'));

$productRepository->save($product);
```

### Reading resolved values

`$product->name` returns the raw column value. Use `ScopeResolver::resolved()` to walk the active context hierarchy and return the most specific override:

```php
<?php

declare(strict_types=1);

use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Resolver\ScopeResolver;

// Set the active context
$context->in('locale', 'de-DE');
$context->in('market', 'eu.fr');

// Raw column value — no scope resolution
$raw = $product->name; // 'Widget'

// resolved() walks de-DE → de → column value
$localizedName = $scopeResolver->resolved($product, 'name'); // 'Widget DE' (de override)

// eu.fr has no override; walks up to eu
$marketPrice = $scopeResolver->resolved($product, 'price'); // 89.99 (eu override)
```

### Resolving at a specific scope

Use `resolvedAt()` to resolve a value at a particular scope regardless of the active context:

```php
<?php

declare(strict_types=1);

use Marko\Scope\Resolver\ScopeResolver;
use Marko\Scope\Scope;

$dePrice = $scopeResolver->resolvedAt($product, 'price', new Scope('market', 'eu.de')); // 79.99
```

### Ordered queries

Use `ScopedOrderByFactory::create()` to build a `ScopedOrderBy` `QuerySpecification` that sorts by the resolved value for the active context:

```php
<?php

declare(strict_types=1);

use App\Catalog\Entity\Product;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Query\ScopedOrderByFactory;

$context->in('locale', 'de-DE');

$products = $productRepository->matching(
    $scopedOrderByFactory->create(Product::class, 'name', 'asc'),
);
```

The driver package emits a `COALESCE` expression that mirrors the PHP resolution order:

```sql
ORDER BY COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(scopes, '$."locale:de-DE".name')),
    JSON_UNQUOTE(JSON_EXTRACT(scopes, '$."locale:de".name')),
    name
) ASC
```

When no scope path is active the specification falls back to a plain `ORDER BY name ASC`.

### Clearing overrides

```php
<?php

declare(strict_types=1);

use Marko\Scope\Resolver\ScopeResolver;
use Marko\Scope\Scope;

$scopeResolver->clearOverride($product, 'price', new Scope('market', 'eu.de'));
$productRepository->save($product);
```

### Mixing storage approaches

Do **not** use both `use HasScopes` and a `ScopedOverridesEntity` extender on the same entity. The boot-time validator raises `ScopeConfigurationException::traitAndCompanionConflict()` if both are detected. If validation is bypassed, the schema build will fail with a duplicate-column error.

## Customization

### DB-driven scope registry

By default, axes are loaded from `config/scope.php` via `PhpScopeRegistry`. To drive axes from a database table so they can be managed at runtime, implement `ScopeRegistryInterface` and bind it in your module:

```php title="app/catalog/Registry/DatabaseScopeRegistry.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Registry;

use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Hierarchy\ScopeHierarchy;
use Marko\Scope\Registry\ScopeRegistryInterface;

class DatabaseScopeRegistry implements ScopeRegistryInterface
{
    public function hasAxis(string $name): bool { /* ... */ }
    public function getAxis(string $name): ScopeAxis { /* ... */ }
    public function listAxes(): array { /* ... */ }
    public function getHierarchy(string $axisName): ScopeHierarchy { /* ... */ }
}
```

```php title="app/catalog/module.php"
<?php

declare(strict_types=1);

use App\Catalog\Registry\DatabaseScopeRegistry;
use Marko\Scope\Registry\ScopeRegistryInterface;

return [
    'bindings' => [
        ScopeRegistryInterface::class => DatabaseScopeRegistry::class,
    ],
];
```

## API Reference

| Class / Interface | Description |
|---|---|
| `Marko\Scope\Attributes\Scoped` | Property attribute declaring which axes scope a value |
| `Marko\Scope\Context\ScopeContext` | Mutable singleton holding the active path per axis for the current request |
| `Marko\Scope\Resolver\ScopeResolver` | Resolves scoped values by walking the active context hierarchy; also writes and clears overrides |
| `Marko\Scope\Storage\HasScopesInterface` | Interface for entities that store scoped overrides directly via the `HasScopes` trait |
| `Marko\Scope\Storage\HasScopes` | Trait that adds a `$scopes` JSON column to the entity and implements `HasScopesInterface` |
| `Marko\Scope\Storage\ScopedOverridesEntity` | Abstract companion entity holding the JSON `scopes` column (alternative to `HasScopes`) |
| `Marko\Scope\Query\ScopedOrderBy` | `QuerySpecification` that orders by resolved scope value |
| `Marko\Scope\Query\ScopedOrderByFactory` | Factory for building `ScopedOrderBy` specifications |
| `Marko\Scope\Query\ScopeSortRendererInterface` | Interface implemented by driver packages to emit DB-specific `COALESCE` expressions |
| `Marko\Scope\Registry\ScopeRegistryInterface` | Interface for scope axis/hierarchy providers |
| `Marko\Scope\Scope` | Value object representing a single axis+path pair (`axis:path`) |
| `Marko\Scope\Hierarchy\ScopeHierarchy` | Ordered list of declared paths; provides `walkUp()` for fallback traversal |

### `ScopeContext`

| Method | Description |
|--------|-------------|
| `in(string $axis, string $path): static` | Set the active path for an axis. Throws `UnknownAxisException` or `ScopeContextException` if the axis or path is invalid. |
| `get(string $axis): ?string` | Return the active path for an axis, or `null` if not set. |
| `clear(string $axis): void` | Remove the active path for an axis. |
| `clearAll(): void` | Remove all active paths. Call between requests in long-running processes. |
| `activeAxes(): list<string>` | Return the names of all axes that have an active path. |

### `ScopeResolver`

| Method | Description |
|--------|-------------|
| `resolved(Entity $entity, string $property): mixed` | Walk the active context hierarchy and return the most specific override, falling back to the column value. |
| `resolvedAt(Entity $entity, string $property, Scope $scope): mixed` | Resolve at a specific scope regardless of the active context. |
| `setOverride(Entity $entity, string $property, mixed $value, Scope $scope): void` | Attach a scoped value to the entity. For `HasScopesInterface` entities writes directly to `$scopes`; for companion-based entities creates the companion if one does not exist yet. |
| `clearOverride(Entity $entity, string $property, Scope $scope): void` | Remove a scoped override from the entity. |

### `ScopedOrderByFactory`

| Method | Description |
|--------|-------------|
| `create(string $entityClass, string $property, string $direction = 'asc'): ScopedOrderBy` | Build a `QuerySpecification` that orders by the resolved scope value for the active context. |

### `ScopeHierarchy`

| Method | Description |
|--------|-------------|
| `fromPaths(list<string> $paths): self` | Build a hierarchy from a flat list of dotted paths. |
| `paths(): list<string>` | Return all declared paths in declaration order. |
| `exists(string $path): bool` | Check whether a path is declared. |
| `isAncestor(string $ancestor, string $descendant): bool` | Return true if `$ancestor` is a strict ancestor of `$descendant`. |
| `walkUp(string $path): list<string>` | Return the path and all ancestors in deepest-first order. |

## Caveats

**`ScopeContext` is a mutable singleton.** It holds active paths for the entire PHP process lifetime. In long-running processes (FPM workers, queue daemons, ReactPHP servers), the bootstrap layer must call `$scopeContext->clearAll()` between requests or jobs to prevent cross-request scope leakage.

**`Repository::insertBatch` support depends on the storage approach.** Trait-based entities (`use HasScopes`) store overrides directly on the entity and are fully compatible with `insertBatch()`. Companion-based entities (`ScopedOverridesEntity` extender) are **not** compatible --- batch inserts bypass the companion lifecycle and will throw `BatchInsertException::companionsNotSupported()`. Use individual `save()` calls for companion-based scoped entities.

**Terminology overlap with `marko/config`.** The `marko/config` package uses the term "tenant scope" as a configuration parameter name. This is unrelated to `marko/scope`'s axis/path concept --- the two systems are independent.

## Related Packages

- [marko/scope-mysql](/docs/packages/scope-mysql/) --- MySQL/MariaDB driver
- [marko/scope-pgsql](/docs/packages/scope-pgsql/) --- PostgreSQL driver
- [marko/database](/docs/packages/database/) --- Entity system and `QuerySpecification` interface
