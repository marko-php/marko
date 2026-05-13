---
title: marko/scope-pgsql
description: PostgreSQL driver for marko/scope — jsonb column support and scoped ORDER BY.
---

PostgreSQL driver for `marko/scope` --- adds `jsonb` column support and scoped `ORDER BY` for PostgreSQL-backed applications. When a `ScopedOverridesEntity` extender is registered, `db:migrate` automatically adds the `scopes` column to the entity's table. The `json` column type materialises as `JSONB` in PostgreSQL via the driver's type map, giving full indexed JSON support without any extra configuration.

## Installation

```bash
composer require marko/scope-pgsql
```

This automatically installs `marko/scope` as a transitive dependency.

## Usage

### Declaring the companion class

Declare a `ScopedOverridesEntity` subclass with `#[Table(extends:)]` pointing at your entity. When `db:migrate` runs, the `scopes` JSONB column is merged into the parent table automatically:

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

Register both `Product` and `ProductScopedOverrides` with the `SchemaRegistry`. The `scopes` column will appear in the `products` table after the next migration run.

### Scoped ORDER BY

Pass a `ScopedOrderBy` specification to `Repository::matching`. `PgSqlScopeSortRenderer` emits a `COALESCE`-based expression using PostgreSQL JSONB path operators and falls back to a plain column sort when no scope path is active:

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
    "scopes"->'locale:de-DE'->>'name',
    "scopes"->'locale:de'->>'name',
    "name"
) ASC
```

## API Reference

### `PgSqlScopeSortRenderer`

| Method | Description |
|--------|-------------|
| `render(ScopeSortExpression $expression): string` | Renders a `ScopeSortExpression` as a PostgreSQL `COALESCE("scopes"->'key'->>'property', column)` fragment. Throws `InvalidColumnException` if any identifier in the expression is invalid. |

## Related Packages

- [marko/scope](/docs/packages/scope/) --- Core scoped attributes package
- [marko/scope-mysql](/docs/packages/scope-mysql/) --- MySQL/MariaDB driver
- [marko/database-pgsql](/docs/packages/database-pgsql/) --- PostgreSQL database driver
