# marko/scope

Scoped attributes for entities with multi-axis hierarchical fallback.

## Installation

```bash
composer require marko/scope
```

A driver package is also required for sort rendering:

```bash
composer require marko/scope-mysql
# or
composer require marko/scope-pgsql
```

## Quick start

Add the `HasScopes` trait and `HasScopesInterface` to your entity:

```php
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
}
```

Then run your migration to add the `scopes` column:

```bash
php artisan marko:migrate
```

## How it works (schema)

The `HasScopes` trait declares a `$scopes` property with a `#[Column]` attribute. `EntityMetadataFactory` picks this up automatically during schema generation, adding a `scopes` JSON column to your entity's table. No manual column definition is required.

## Resolver API

Use `ScopeResolver` to read and write scoped values. The API is identical regardless of which storage approach you use:

```php
use Marko\Scope\Resolver\ScopeResolver;
use Marko\Scope\Scope;

// Write a scoped override
$scopeResolver->setOverride($product, 'name', 'Widget DE', new Scope('locale', 'de'));

// Clear a scoped override
$scopeResolver->clearOverride($product, 'name', new Scope('locale', 'de'));

// Read with hierarchy fallback (de-DE walks up to de)
$scopeContext->in('locale', 'de-DE');
$localizedName = $scopeResolver->resolved($product, 'name'); // 'Widget DE'
```

## Quick Example

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Scope\Attributes\Scoped;
use Marko\Scope\Resolver\ScopeResolver;
use Marko\Scope\Scope;
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
}

// Write a scoped override
$scopeResolver->setOverride($product, 'name', 'Widget DE', new Scope('locale', 'de'));

// Read with hierarchy fallback (de-DE walks up to de)
$scopeContext->in('locale', 'de-DE');
$localizedName = $scopeResolver->resolved($product, 'name'); // 'Widget DE'
```

## Alternative: companion class

When the scoped overrides are contributed by a separate package, or when you cannot modify the entity class, use a `ScopedOverridesEntity` extender instead:

```php
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Scope\Storage\ScopedOverridesEntity;

#[Table('product_scopes')]
class ProductScopedOverrides extends ScopedOverridesEntity
{
    // Links back to Product
}
```

Register the companion via the module's extender configuration. This pattern is useful when:

- Scoped overrides are provided by a module that does not own the base entity
- You want to keep the base entity's schema clean
- The entity class cannot be modified (e.g. it comes from a vendor package)

## Don't mix them

Do **not** use both `use HasScopes` and a `ScopedOverridesEntity` extender on the same entity.

The boot-time validator will surface a `ScopeConfigurationException::traitAndCompanionConflict()` error if you do. If validation is bypassed, the schema build will fail with `EntityException::duplicateColumnInExtender('scopes', ...)`.

## Batch insert compatibility

Trait-based entities (`use HasScopes`) have no attached companion, so they are fully compatible with `Repository::insertBatch()`.

Companion-based scoped entities (`ScopedOverridesEntity` extender) are **not** compatible with `Repository::insertBatch()`. Attempting a batch insert will throw `BatchInsertException::companionsNotSupported()`.

## Documentation

Full usage, API reference, and examples: [marko/scope](https://marko.build/docs/packages/scope/)
