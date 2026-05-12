# marko/page-cache-entity

Bridge package that auto-purges page-cache tags when entities implementing `IdentityInterface` are saved or deleted.

## Installation

```bash
composer require marko/page-cache-entity
```

## Quick Example

```php
use Marko\Database\Entity\Entity;
use Marko\PageCache\Contracts\IdentityInterface;

class Product extends Entity implements IdentityInterface
{
    public function getIdentities(): array
    {
        return ['products', "product-{$this->id}"];
    }
}
```

Saving or deleting the entity purges the listed cache tags automatically --- no extra wiring needed.

## Documentation

Full usage, API reference, and examples: [marko/page-cache-entity](https://marko.build/docs/packages/page-cache-entity/)
