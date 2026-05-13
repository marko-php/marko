# marko/scope-pgsql

PostgreSQL driver for `marko/scope` — jsonb column support and scoped `ORDER BY`.

## Installation

```bash
composer require marko/scope-pgsql
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
//   "scopes"->'locale:de-DE'->>'name',
//   "scopes"->'locale:de'->>'name',
//   "name"
// ) ASC
```

## Documentation

Full usage, API reference, and examples: [marko/scope-pgsql](https://marko.build/docs/packages/scope-pgsql/)
