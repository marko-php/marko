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

## Documentation

Full usage, API reference, and examples: [marko/database](https://marko.build/docs/packages/database/)
