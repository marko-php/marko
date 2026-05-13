# marko/scope

Scoped attributes for entities with multi-axis hierarchical fallback.

## Installation

```bash
composer require marko/scope
```

A driver package is also required:

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
use Marko\Scope\Storage\HasScopes;
use Marko\Scope\Storage\HasScopesInterface;

#[Table('products')]
class Product extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(length: 255)]
    #[Scoped(axes: ['locale'])]
    public string $name = '';
}

// Set a scoped override
$scopeResolver->setOverride($product, 'name', 'Widget DE', new Scope('locale', 'de'));

// Resolve with hierarchy fallback (de-DE walks up to de)
$scopeContext->in('locale', 'de-DE');
$localizedName = $scopeResolver->resolved($product, 'name');
```

## Documentation

Full usage, API reference, and examples: [marko/scope](https://marko.build/docs/packages/scope/)
