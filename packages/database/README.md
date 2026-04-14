# Marko Database

Entity-Driven Schema definition with Data Mapper pattern for the Marko framework.

## Installation

```bash
composer require marko/database
```

You typically install a driver package (like `marko/database-pgsql`) which requires this automatically.

## Quick Example

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('blog_posts')]
#[Index(columns: ['slug'], unique: true)]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $title;

    #[Column(length: 255, unique: true)]
    public string $slug;

    #[Column(type: 'text', nullable: true)]
    public ?string $content = null;

    #[Column(nullable: true, default: 'draft')]
    public ?string $status = 'draft';
}
```

## Entity-Driven Schema

In Marko, the entity class is the single source of truth for both your PHP types and your database schema. You define columns using PHP attributes directly on entity properties — there is no separate migration file to keep in sync.

Marko compares your entity definitions against the live database schema and generates the necessary DDL to bring them into alignment.

## Data Mapper

Marko uses the Data Mapper pattern, keeping domain objects free from persistence concerns. Entities extend `Entity` but contain no query logic — all database interaction goes through a Repository.

## Repository

Repositories handle all database reads and writes for an entity type, keeping persistence logic separate from your domain model.

```php
use Marko\Database\Repository\Repository;

class PostRepository extends Repository
{
    protected const string ENTITY_CLASS = Post::class;
}
```

## Type Inference

Marko infers SQL column types from PHP type declarations where possible. For example, `int` maps to `INT`, `string` maps to `VARCHAR`, and `bool` maps to `TINYINT(1)`. You can always override the inferred type with an explicit `type` parameter on `#[Column]`.

## Attributes

| Attribute | Purpose |
|-----------|---------|
| `#[Table]` | Maps a class to a database table |
| `#[Column]` | Maps a property to a table column |
| `#[Index]` | Defines an index on one or more columns |
| `#[HasOne]` | Defines a one-to-one relationship |
| `#[HasMany]` | Defines a one-to-many relationship |
| `#[BelongsTo]` | Defines an inverse one-to-one/many relationship |
| `#[BelongsToMany]` | Defines a many-to-many relationship via pivot entity |

## Relationships

Define relationships with attributes and load them explicitly via `with()`:

```php
$posts = $postRepository->with('author', 'comments')->findAll();
```

No lazy loading, no proxies — relationships are only loaded when you ask for them.

## Query Specifications

Composable, named query objects replace magic scopes:

```php
$posts = $postRepository->matching(new PublishedPosts(), new RecentPosts(days: 30))->toArray();
```

## CLI Commands

| Command | Description |
|---------|-------------|
| `db:diff` | Show pending schema changes |
| `db:migrate` | Apply pending schema changes |
| `db:rollback` | Roll back the last migration |
| `db:status` | Show migration status |

## Framework Comparison

| Feature | Laravel | Doctrine | Marko |
|---------|---------|----------|-------|
| Schema definition | Migrations | XML/YAML/Annotations | PHP Attributes |
| Pattern | Active Record | Data Mapper | Data Mapper |
| Source of truth | Migration files | Mapping files | Entity classes |

## Available Drivers

- **marko/database-mysql** --- MySQL/MariaDB driver
- **marko/database-pgsql** --- PostgreSQL driver

## Documentation

Full usage, API reference, and examples: [marko/database](https://marko.build/docs/packages/database/)
