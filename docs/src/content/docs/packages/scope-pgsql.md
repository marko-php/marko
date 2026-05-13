---
title: marko/scope-pgsql
description: PostgreSQL driver for marko/scope — jsonb column support and scoped ORDER BY.
---

PostgreSQL driver for `marko/scope` --- adds `jsonb` column support and scoped `ORDER BY` for PostgreSQL-backed applications. The `scopes` column is added automatically --- either via the `HasScopes` trait on the entity itself, or via a `ScopedOverridesEntity` companion class when the entity cannot be modified. The `json` column type materialises as `JSONB` in PostgreSQL via the driver's type map, giving full indexed JSON support without any extra configuration.

## Installation

```bash
composer require marko/scope-pgsql
```

This automatically installs `marko/scope` as a transitive dependency.

## Usage

### Adding the `scopes` column

There are two ways to get the `scopes` JSONB column into your entity's table.

**Option 1 --- `HasScopes` trait (recommended).** Implement `HasScopesInterface` and use the `HasScopes` trait on the entity. The trait declares the column directly; no companion class is needed:

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

**Option 2 --- companion class.** When the entity class cannot be modified, declare a `ScopedOverridesEntity` subclass with `#[Table(extends:)]` pointing at your entity. When `db:migrate` runs, the `scopes` JSONB column is merged into the parent table automatically:

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
