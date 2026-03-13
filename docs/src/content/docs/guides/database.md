---
title: Database
description: Entity-driven schema, migrations, and querying with the Data Mapper pattern.
---

Marko's database layer uses an entity-driven approach with the Data Mapper pattern. Your PHP entities define the schema — no separate migration files to write by hand.

## Setup

```bash
composer require marko/database marko/database-pgsql
```

Configure your connection in `config/database.php`:

```php title="config/database.php"
<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => (int) env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'marko'),
            'username' => env('DB_USERNAME', 'marko'),
            'password' => env('DB_PASSWORD', ''),
        ],
    ],
];
```

## Defining Entities

Entities are plain PHP classes. Marko infers the database schema from PHP types:

```php title="app/blog/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attribute\Entity;
use Marko\Database\Attribute\Id;
use DateTimeImmutable;

#[Entity(table: 'posts')]
class Post
{
    #[Id]
    public int $id;

    public string $title;

    public string $body;

    public bool $published = false;

    public DateTimeImmutable $createdAt;
}
```

### Type Inference

Marko maps PHP types to database columns automatically:

| PHP Type | PostgreSQL | MySQL |
|---|---|---|
| `int` | `INTEGER` | `INT` |
| `string` | `TEXT` | `VARCHAR(255)` |
| `bool` | `BOOLEAN` | `TINYINT(1)` |
| `float` | `DOUBLE PRECISION` | `DOUBLE` |
| `DateTimeImmutable` | `TIMESTAMP` | `DATETIME` |

## Migrations

Generate and run migrations from your entity definitions:

```bash
# Generate a migration from entity changes
marko db:migrate

# Roll back the last migration
marko db:rollback

# Reset and re-run all migrations
marko db:reset

# Check migration status
marko db:status
```

## Querying

### Query Builder

Use `QueryBuilderInterface` for fluent query building:

```php title="app/blog/Repository/PostRepository.php"
<?php

declare(strict_types=1);

namespace App\Blog\Repository;

use Marko\Database\Query\QueryBuilderInterface;
use DateTimeImmutable;

class PostRepository
{
    public function __construct(
        private readonly QueryBuilderInterface $queryBuilder,
    ) {}

    public function findById(int $id): ?array
    {
        return $this->queryBuilder->table('posts')
            ->where('id', '=', $id)
            ->first();
    }

    public function findPublished(): array
    {
        return $this->queryBuilder->table('posts')
            ->where('published', '=', true)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public function create(string $title, string $body): int
    {
        return $this->queryBuilder->table('posts')->insert([
            'title' => $title,
            'body' => $body,
            'published' => false,
            'created_at' => new DateTimeImmutable(),
        ]);
    }
}
```

## Seeders

Populate your database with test or default data:

```php title="app/blog/Database/Seeder/PostSeeder.php"
<?php

declare(strict_types=1);

namespace App\Blog\Database\Seeder;

use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\SeederInterface;
use DateTimeImmutable;

class PostSeeder implements SeederInterface
{
    public function __construct(
        private readonly QueryBuilderInterface $queryBuilder,
    ) {}

    public function run(): void
    {
        $this->queryBuilder->table('posts')->insert([
            'title' => 'Hello World',
            'body' => 'Welcome to Marko.',
            'published' => true,
            'created_at' => new DateTimeImmutable(),
        ]);
    }
}
```

```bash
marko db:seed
```

## Switching Database Drivers

Thanks to the interface/implementation split, switching from MySQL to PostgreSQL (or vice versa) is a Composer swap. Each driver package automatically binds `ConnectionInterface` to its implementation via its `module.php`:

```bash
# Remove the old driver, install the new one
composer remove marko/database-mysql
composer require marko/database-pgsql
```

That's it — no binding changes needed. The driver package handles the wiring. Update your `config/database.php` connection settings to match the new driver, and your application code stays the same since it depends on `ConnectionInterface`, not a specific driver.

## Next Steps

- [Authentication](/docs/guides/authentication/) — user management and guards
- [Caching](/docs/guides/caching/) — cache query results
- [Testing](/docs/guides/testing/) — test database interactions
- [Database package reference](/docs/packages/database/) — full API details
