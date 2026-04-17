---
title: marko/database
description: Entity-driven schema definition with the Data Mapper pattern.
---

Database abstraction with entity-driven schema, type inference, migrations, and seeders.

**This package has no implementation.** Install `marko/database-mysql` or `marko/database-pgsql` for actual database connectivity.

## Installation

```bash
composer require marko/database
```

You typically install a driver package (like `marko/database-pgsql`) which requires this automatically.

## Entity-Driven Schema

Your entity class is the single source of truth for both your PHP code and database structure. No separate migration files to write by hand, no XML mappings, no YAML configuration. Define your entities with attributes, and Marko generates the SQL to make your database match.

### Complete Example

```php title="app/blog/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Entity\Entity;

#[Table('blog_posts')]
#[Index('idx_status_created', ['status', 'created_at'])]
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

    #[Column(default: 'draft')]
    public PostStatus $status = PostStatus::Draft;

    #[Column(references: 'users.id', onDelete: 'cascade')]
    public int $authorId;

    #[Column(default: 'CURRENT_TIMESTAMP')]
    public DateTimeImmutable $createdAt;

    #[Column]
    public ?DateTimeImmutable $updatedAt = null;
}
```

### Attributes Overview

| Attribute | Purpose |
|-----------|---------|
| `#[Table]` | Defines table name |
| `#[Column]` | Column configuration (name, primaryKey, autoIncrement, length, type, unique, default, references, onDelete, onUpdate) |
| `#[Index]` | Composite indexes |
| `#[HasOne]` | Declares a has-one relationship to another entity |
| `#[HasMany]` | Declares a has-many relationship to another entity |
| `#[BelongsTo]` | Declares a belongs-to relationship to another entity |
| `#[BelongsToMany]` | Declares a many-to-many relationship through a pivot entity |

Property names are automatically converted from camelCase to snake_case for column names. For example, `$createdAt` maps to the `created_at` column. Use the `name` parameter to override this: `#[Column(name: 'custom_column')]`.

### Type Inference Rules

Marko infers database types from PHP types:

| PHP Type | Database Type |
|----------|---------------|
| `int` | INT (or SERIAL/BIGSERIAL if autoIncrement) |
| `string` | VARCHAR(255) by default, TEXT if type='text' |
| `bool` | BOOLEAN |
| `float` | DECIMAL or FLOAT |
| `?type` | Column is NULLABLE |
| `DateTimeImmutable` | TIMESTAMP |
| `BackedEnum` | ENUM with cases as values |
| Default values | From property initializers |

## Data Mapper Pattern

Entities are plain PHP objects. They don't save themselves or know about the database. Repositories handle all persistence.

```php title="app/blog/Repository/PostRepository.php"
<?php

declare(strict_types=1);

namespace App\Blog\Repository;

use App\Blog\Entity\Post;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Repository\Repository;

class PostRepository extends Repository
{
    protected const ENTITY_CLASS = Post::class;

    public function findBySlug(string $slug): ?Post
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findPublished(): EntityCollection
    {
        return $this->query()
            ->where('status', '=', 'published')
            ->orderBy('created_at', 'desc')
            ->getEntities();
    }
}
```

### Why Data Mapper?

- **Testability**: Entities are plain objects, easy to construct in tests
- **Separation**: Business logic stays in entities, persistence in repositories
- **Flexibility**: Switch databases without changing entity code
- **Clarity**: No hidden magic, explicit saves via repository

### Custom Queries with `query()`

The base `Repository` provides three ways to query, each suited to a different use case:

| Method                              | When to use                                                                 |
|-------------------------------------|-----------------------------------------------------------------------------|
| `findBy(array $criteria)`           | Simple equality matches on columns                                          |
| `matching(QuerySpecification ...)`  | Reusable, composable query fragments shared across repositories             |
| `query()`                           | One-off custom queries --- joins, raw conditions, ordering, limits, offsets |

`query()` returns a `RepositoryQueryBuilder` pre-configured with the repository's table name. It implements the full `QueryBuilderInterface` and adds entity hydration.

```php
public function findPublished(int $limit = 10): EntityCollection
{
    return $this->query()
        ->where('status', '=', 'published')
        ->whereNotNull('published_at')
        ->orderBy('published_at', 'desc')
        ->limit($limit)
        ->getEntities();
}
```

#### Returning entities vs arrays

| Method            | Returns                                 |
|-------------------|-----------------------------------------|
| `getEntities()`   | `EntityCollection<TEntity>` --- hydrated, supports eager loading |
| `firstEntity()`   | `?TEntity` --- hydrated, or `null` if no match |
| `get()`           | `array<array<string, mixed>>` --- raw rows |
| `first()`         | `?array<string, mixed>` --- raw row, or `null` |
| `count()`         | `int`                                   |

Use `getEntities()` / `firstEntity()` for typed domain objects. Drop to `get()` / `first()` only for reports or aggregates where building entities adds no value.

#### Available filters

`where`, `whereIn`, `whereNull`, `whereNotNull`, `orWhere`, `join`, `leftJoin`, `rightJoin`, `orderBy`, `limit`, `offset`, `select`. All return `static` for chaining. The escape hatch is `raw(string $sql, array $bindings = [])` for queries the builder can't express.

#### Eager loading

Chain `->with('comments', 'author')` before `getEntities()` to load relationships in a single round trip:

```php
return $this->query()
    ->where('status', '=', 'published')
    ->with('author', 'comments.author')
    ->getEntities();
```

Dot-notation loads nested relationships.

#### Query builder requirement

`query()` depends on `QueryBuilderFactoryInterface` being injected into the repository. When a driver package (`marko/database-mysql`, `marko/database-pgsql`) is installed, the container wires this automatically. If you construct a repository manually without providing a factory, `query()` throws `RepositoryException::queryBuilderNotConfigured`.

## Relationships

Define relationships between entities using property attributes. Marko loads related entities explicitly — there is no lazy loading.

### HasOne

A user has one profile. The `foreignKey` is the property name on the **related** entity pointing back to this entity.

```php title="app/blog/Entity/User.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('users')]
class User extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $name;

    #[HasOne(entityClass: Profile::class, foreignKey: 'userId')]
    public ?Profile $profile = null;
}
```

### HasMany

A post has many comments. The `foreignKey` is the property name on the **related** entity pointing back to this entity.

```php title="app/blog/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;

#[Table('posts')]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $title;

    #[HasMany(entityClass: Comment::class, foreignKey: 'postId')]
    public EntityCollection $comments;
}
```

### BelongsTo

A comment belongs to a post. The `foreignKey` is the property name on **this** entity pointing to the related entity.

```php title="app/blog/Entity/Comment.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('comments')]
class Comment extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(name: 'post_id')]
    public int $postId;

    #[Column(type: 'text')]
    public string $body;

    #[BelongsTo(entityClass: Post::class, foreignKey: 'postId')]
    public ?Post $post = null;
}
```

### BelongsToMany

A post belongs to many tags through a pivot entity. The `foreignKey` is the pivot property pointing to **this** entity; `relatedKey` is the pivot property pointing to the related entity.

```php title="app/blog/Entity/Post.php"
#[BelongsToMany(
    entityClass: Tag::class,
    pivotClass: PostTag::class,
    foreignKey: 'postId',
    relatedKey: 'tagId',
)]
public EntityCollection $tags;
```

### Eager Loading

Use `with()` on the repository to load relationships without N+1 queries. Pass dot-notation strings for nested relationships.

```php
// Load posts with their comments
$posts = $postRepository->with('comments')->findAll();

// Load posts with comments and each comment's author
$posts = $postRepository->with('comments.author')->findAll();

// Multiple relationships
$posts = $postRepository->with('comments', 'tags')->findAll();
```

`with()` returns a cloned repository instance — the original is unchanged. Relationships are loaded in a single batch query per relationship level.

## EntityCollection

`findAll()` and `findBy()` return an `EntityCollection` instead of a plain array. `EntityCollection` is iterable, countable, and provides collection methods.

```php
use Marko\Database\Entity\EntityCollection;

$posts = $postRepository->findAll();

// Iterate
foreach ($posts as $post) { ... }

// Count
$posts->count();
$posts->isEmpty();

// Access
$posts->first();
$posts->last();

// Transform
$posts->filter(fn (Post $p): bool => $p->published);
$posts->map(fn (Post $p): string => $p->title);
$posts->each(fn (Post $p): void => ...);
$posts->pluck('title');          // array of property values

// Sort and group
$posts->sortBy('createdAt', descending: true);
$posts->groupBy('status');       // array<string, EntityCollection>
$posts->chunk(10);               // array<int, EntityCollection>

// Search
$posts->contains(fn (Post $p): bool => $p->id === 5);

// Convert
$posts->toArray();
```

## Query Specifications

`QuerySpecification` is an interface for encapsulating reusable query logic. Use `matching()` on the repository to apply one or more specifications.

```php title="app/blog/Query/PublishedSpec.php"
<?php

declare(strict_types=1);

namespace App\Blog\Query;

use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;

class PublishedSpec implements QuerySpecification
{
    public function apply(QueryBuilderInterface $queryBuilder): void
    {
        $queryBuilder->where('status', '=', 'published');
    }
}
```

```php title="app/blog/Query/RecentSpec.php"
<?php

declare(strict_types=1);

namespace App\Blog\Query;

use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;

readonly class RecentSpec implements QuerySpecification
{
    public function __construct(
        private int $limit = 10,
    ) {}

    public function apply(QueryBuilderInterface $queryBuilder): void
    {
        $queryBuilder->orderBy('created_at', 'desc')->limit($this->limit);
    }
}
```

Compose multiple specifications in a single `matching()` call:

```php
use App\Blog\Query\PublishedSpec;
use App\Blog\Query\RecentSpec;

$posts = $postRepository->matching(
    new PublishedSpec(),
    new RecentSpec(limit: 5),
);
```

## Seeders

Seeders populate development/test databases with sample data. They're discovered via the `#[Seeder]` attribute.

Each seeder runs inside a database transaction. If a seeder fails partway through, all its changes are automatically rolled back — preventing partial data that would require manual cleanup.

```php title="app/blog/Seed/PostSeeder.php"
<?php

declare(strict_types=1);

namespace App\Blog\Seed;

use App\Blog\Entity\Post;
use App\Blog\Repository\PostRepositoryInterface;
use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;

#[Seeder(name: 'posts', order: 10)]
readonly class PostSeeder implements SeederInterface
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
    ) {}

    public function run(): void
    {
        $post = new Post();
        $post->title = 'Hello World';
        $post->slug = 'hello-world';
        $post->content = 'Welcome to my blog!';
        $post->createdAt = date('Y-m-d H:i:s');

        $this->postRepository->save($post);
    }
}
```

> **Why `new Post()` instead of factories?** Entities are simple data objects without dependencies or complex construction logic. Direct instantiation is explicit — you see exactly what's being set. This aligns with Marko's "explicit over implicit" principle. If your tests need realistic fake data at scale, consider adding a test data builder for that specific need rather than a general factory abstraction.

> **IDE Note:** PhpStorm may report seeder classes as "unused" since they're discovered via attributes rather than direct instantiation. The `@noinspection PhpUnused` annotation suppresses this false positive.

Place seeders in your module's `Seed/` directory. The `order` parameter controls execution sequence — use spaced numbers (10, 20, 30) rather than sequential (1, 2, 3) to allow other modules to insert seeders between existing ones without renumbering.

## CLI Commands

| Command | Description |
|---------|-------------|
| `marko db:status` | Show migration status |
| `marko db:diff` | Preview changes between entities and database |
| `marko db:migrate` | Generate and apply migrations |
| `marko db:rollback` | Revert last migration batch (development only) |
| `marko db:reset` | Rollback all migrations (development only) |
| `marko db:rebuild` | Reset + re-run all migrations (development only) |
| `marko db:seed` | Run seeders (development only) |

### Development Workflow

```bash
# 1. Define/modify your entity
# 2. Preview what will change
marko db:diff

# 3. Generate migration and apply it
marko db:migrate

# 4. If mistake, rollback (development only)
marko db:rollback
```

### Production Workflow

```bash
# Deploy code (includes migration files)
# Apply existing migrations only
marko db:migrate
```

In production, `db:migrate` only applies existing migration files — it never generates new ones.

## Switching Database Drivers

Since entities are the single source of truth, switching between database systems is a config change — each driver's `SqlGenerator` translates entity attributes to native SQL automatically.

### Example: MySQL to PostgreSQL

1. Delete the migration files in `database/migrations/` — they contain MySQL-specific SQL:

```bash
rm database/migrations/*.php
```

2. Swap drivers:

```bash
composer remove marko/database-mysql
composer require marko/database-pgsql
```

3. Update your database config:

```php title="config/database.php"
return [
    'driver' => 'pgsql',
    'host' => '127.0.0.1',
    'port' => 5432,
    'database' => 'myapp',
    'username' => 'postgres',
    'password' => '',
];
```

4. Create the database and run migrations:

```bash
createdb myapp
marko db:migrate
marko db:seed
```

`db:migrate` diffs entity attributes against the empty database, generates new migration files with PostgreSQL-native SQL (e.g., `SERIAL` instead of `AUTO_INCREMENT`, `BOOLEAN` instead of `TINYINT(1)`), and applies them. Your entity code and application logic remain unchanged.

## Framework Comparison

| Feature | Laravel | Doctrine | Marko |
|---------|---------|----------|-------|
| Schema definition | Separate migration files | XML/YAML or attributes | Entity attributes (single source of truth) |
| Migration generation | Manual | `doctrine:schema:update` | `db:migrate` auto-generates |
| Entity persistence | Active Record (Eloquent) | Data Mapper | Data Mapper |
| Schema location | `database/migrations/` | Mapping files or entity | Entity only |

## Benefits of Entity as Single Source of Truth

1. **No schema drift** — Entity changes automatically sync to database
2. **Refactoring updates both** — Rename a property, schema updates automatically
3. **IDE support** — Full autocomplete and type checking for schema
4. **No context switching** — Everything about your model in one place
5. **Reduced cognitive load** — One file to understand, not entity + migration + mapping

## Available Drivers

- [marko/database-pgsql](/docs/packages/database-pgsql/) — PostgreSQL driver
- [marko/database-mysql](/docs/packages/database-mysql/) — MySQL driver
