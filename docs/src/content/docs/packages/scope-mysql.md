---
title: marko/scope-mysql
description: MySQL and MariaDB driver for marko/scope — scoped ORDER BY and automatic scopes column migration.
---

MySQL and MariaDB driver for `marko/scope` --- enables scoped `ORDER BY` queries and automatic `scopes` column migration. The package provides `MySqlScopeSortRenderer`, which emits `COALESCE(JSON_UNQUOTE(JSON_EXTRACT(...)), column)` expressions for scope-aware sorting. The `scopes` JSON column is declared by the `HasScopes` trait on the entity; no separate migration helper is needed. Requires MariaDB 10.3+ or MySQL 8.0+.

## Installation

```bash
composer require marko/scope-mysql
```

This automatically installs `marko/scope` as a transitive dependency.

## Usage

### Adding the `scopes` column

Implement `HasScopesInterface` and use the `HasScopes` trait on the entity. The trait declares the `scopes` JSON column directly; no companion class is needed:

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
}
```

Register `Product` with the `SchemaRegistry`. The `scopes` column will appear in the `products` table after the next migration run.

### Scoped ORDER BY

Pass a `ScopedOrderBy` specification to `Repository::matching`. The renderer emits the appropriate `COALESCE(JSON_UNQUOTE(JSON_EXTRACT(...)), column)` expression and falls back to a plain column sort when no scope path is active:

```php title="app/catalog/Repository/ProductRepository.php"
<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Entity\Product;
use Marko\Database\Repository\Repository;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Query\ScopedOrderByFactory;

class ProductRepository extends Repository
{
    protected const ENTITY_CLASS = Product::class;

    public function __construct(
        private ScopedOrderByFactory $scopedOrderByFactory,
        private ScopeContext $scopeContext,
    ) {}

    public function listByName(): array
    {
        $this->scopeContext->in('locale', 'de-DE');

        return $this->matching(
            $this->scopedOrderByFactory->create(Product::class, 'name'),
        );
    }
}
```

Emitted SQL:

```sql
ORDER BY COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(`scopes`, '$."locale:de-DE".name')),
    JSON_UNQUOTE(JSON_EXTRACT(`scopes`, '$."locale:de".name')),
    `name`
) ASC
```

## Customization

To replace the renderer with a custom implementation, bind your class to `ScopeSortRendererInterface` in your module:

```php title="app/catalog/module.php"
<?php

declare(strict_types=1);

use App\Catalog\Query\CustomScopeSortRenderer;
use Marko\Scope\Query\ScopeSortRendererInterface;

return [
    'bindings' => [
        ScopeSortRendererInterface::class => CustomScopeSortRenderer::class,
    ],
];
```

## API Reference

### `MySqlScopeSortRenderer`

| Method | Description |
|--------|-------------|
| `render(ScopeSortExpression $expression): string` | Renders a `ScopeSortExpression` as a MySQL `COALESCE(JSON_UNQUOTE(JSON_EXTRACT(...)), column)` fragment. Throws `InvalidColumnException` if any identifier in the expression is invalid. |

**Compatibility:** Requires MySQL 8.0+ or MariaDB 10.3+ for `JSON_EXTRACT` and `JSON_UNQUOTE`.

## Related Packages

- [marko/scope](/docs/packages/scope/) --- Core scoped attributes package
- [marko/scope-pgsql](/docs/packages/scope-pgsql/) --- PostgreSQL driver
- [marko/database-mysql](/docs/packages/database-mysql/) --- MySQL database driver
