# marko/scope-mysql

MySQL and MariaDB driver for `marko/scope` — scoped `ORDER BY` and automatic `scopes` column migration.

## Installation

```bash
composer require marko/scope-mysql
```

Installs `marko/scope` automatically as a transitive dependency.

## Quick Example

```php
use App\Catalog\Entity\Product;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Query\ScopedOrderByFactory;

$scopeContext->in('locale', 'de-DE');

$products = $productRepository->matching(
    $scopedOrderByFactory->create(Product::class, 'name'),
);

// Emitted SQL:
// ORDER BY COALESCE(
//   JSON_UNQUOTE(JSON_EXTRACT(`scopes`, '$."locale:de-DE".name')),
//   JSON_UNQUOTE(JSON_EXTRACT(`scopes`, '$."locale:de".name')),
//   `name`
// ) ASC
```

## Documentation

Full usage, API reference, and examples: [marko/scope-mysql](https://marko.build/docs/packages/scope-mysql/)
