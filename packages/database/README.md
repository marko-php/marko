# marko/database

Entity-driven schema definition with the Data Mapper pattern for the Marko framework.

## Installation

```bash
composer require marko/database
```

You typically install a driver package (like `marko/database-pgsql`) which requires this automatically.

## Quick Example

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Repository\Repository;

#[Table('posts')]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $title;

    #[Column(type: 'json')]
    public array $metadata = [];
}

class PostRepository extends Repository
{
    protected const string ENTITY_CLASS = Post::class;
}
```

## Entity Extensions

Any module can add columns to an existing entity's table without modifying the original entity class. Extensions are auto-discovered, merged into the entity's schema at boot, and hydrated transparently from the same DB row.

### Declaring an Extension

Create a class in `src/EntityExtension/` that extends `EntityExtension` and annotate it with `#[ExtensionOf]`:

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ExtensionOf;
use Marko\Database\Entity\EntityExtension;

#[ExtensionOf(Product::class)]
class PricingExtension extends EntityExtension
{
    #[Column]
    public float $price;

    #[Column(nullable: true)]
    public ?string $currency = null;
}
```

The framework discovers this class automatically — no registration step required.

### Running Migrations

After declaring an extension, run a schema diff and migration to add the new columns to the existing table. The columns live in the base entity's table (`products` in this example), so a standard `SELECT *` query returns them automatically.

### Accessing Extensions

```php
$product = $productRepository->find(1);

$pricing = $product->extension(PricingExtension::class); // PricingExtension|null

if ($pricing !== null) {
    echo $pricing->price;
}
```

The `extension()` method is typed with `@template`, so IDEs infer the exact return type without any code generation.

### Setting Extensions Before Saving

Attach an extension instance before inserting or updating:

```php
$product = new Product();
$product->sku = 'ABC-123';
$product->name = 'Widget';

$extension = new PricingExtension();
$extension->price = 9.99;
$product->setExtension($extension);

$productRepository->insert($product);
```

### Save-Time Policy (When Extension Is Not Attached)

If an entity is saved without a particular extension attached, the framework applies the following policy per column:

| Column type | Behaviour |
|---|---|
| Nullable (`?type`) | Written as `NULL` |
| Non-nullable with a default value | Written as the declared default |
| Non-nullable with no default | Throws `RepositoryException` loudly |

### Behaviour Notes

- **Hydration**: if none of an extension's columns are present in the DB row (e.g. the migration has not been run yet), the extension is silently skipped and `extension()` returns `null`. This prevents crashes during a rolling deployment where the column was just added.
- **Updates**: all extension columns are always written on every `UPDATE`, regardless of whether their values changed. Extension properties are not dirty-tracked.
- **Column conflicts**: if two extensions (or an extension and the base entity) declare the same column name or property name, the framework throws an exception at boot — never at runtime.

### Restrictions

Extension classes **cannot** declare:

- A `#[Table]` attribute
- A primary key column (`primaryKey: true`)
- Relationship properties
- Index annotations

Violating any of these restrictions throws an exception when the extension is parsed.

## Documentation

Full usage, API reference, and examples: [marko/database](https://marko.build/docs/packages/database/)
