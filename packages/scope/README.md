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

## Quick Example

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Scope\Attributes\Scoped;
use Marko\Scope\Resolver\ScopeResolver;
use Marko\Scope\Scope;

#[Table('products')]
class Product extends Entity
{
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

## Documentation

Full usage, API reference, and examples: [marko/scope](https://marko.build/docs/packages/scope/)
