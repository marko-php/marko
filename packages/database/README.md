# Marko Database

Entity-driven schema definition with Data Mapper pattern for the Marko framework.

## Installation

```bash
composer require marko/database
```

You typically install a driver package (like `marko/database-pgsql`) which requires this automatically.

## Quick Example

```php
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Column;
use Marko\Database\Entity\Entity;

#[Table('blog_posts')]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $title;

    #[Column(length: 255, unique: true)]
    public string $slug;

    #[Column(type: 'text')]
    public ?string $content = null;
}
```

## Available Drivers

- **marko/database-mysql** --- MySQL/MariaDB driver
- **marko/database-pgsql** --- PostgreSQL driver

## Documentation

Full usage, API reference, and examples: [marko/database](https://marko.build/docs/packages/database/)
