# Marko Database

Entity-driven schema definition with Data Mapper pattern for the Marko framework.

## Overview

Marko Database takes a fundamentally different approach: your PHP entity classes **are** your database schema. No separate migration files to write by hand, no XML mappings, no YAML configuration. Define your entities with attributes, and Marko generates the SQL to make your database match.

**This package has no implementation.** Install `marko/database-mysql` or `marko/database-pgsql` for actual database connectivity.

## Installation

```bash
composer require marko/database
```

Note: You typically install a driver package (like `marko/database-pgsql`) which requires this automatically.

## Entity-Driven Schema

Your entity class is the single source of truth for both your PHP code and database structure.

### Complete Example

```php
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

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

    #[Column(name: 'author_id', references: 'users.id', onDelete: 'cascade')]
    public int $authorId;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public \DateTimeImmutable $createdAt;

    #[Column(name: 'updated_at', nullable: true)]
    public ?\DateTimeImmutable $updatedAt = null;
}
```

### Attributes Overview

| Attribute | Purpose |
|-----------|---------|
| `#[Table]` | Defines table name |
| `#[Column]` | Column configuration (type, length, nullable, default, unique, references) |
| `#[Index]` | Composite indexes |

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

```php
<?php

declare(strict_types=1);

namespace App\Blog\Repository;

use App\Blog\Entity\Post;
use Marko\Database\Repository\Repository;

class PostRepository extends Repository
{
    protected const ENTITY_CLASS = Post::class;

    public function findBySlug(string $slug): ?Post
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findPublished(): array
    {
        return $this->query()
            ->where('status', '=', 'published')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
```

### Why Data Mapper?

- **Testability**: Entities are plain objects, easy to construct in tests
- **Separation**: Business logic stays in entities, persistence in repositories
- **Flexibility**: Switch databases without changing entity code
- **Clarity**: No hidden magic, explicit saves via repository

## Seeders

Seeders populate development/test databases with sample data. They're discovered via the `#[Seeder]` attribute.

### Creating Seeders

```php
<?php

declare(strict_types=1);

namespace App\Blog\Seed;

use App\Blog\Entity\Post;
use App\Blog\Repository\PostRepositoryInterface;
use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;

/** @noinspection PhpUnused */
#[Seeder(name: 'posts', order: 10)]
readonly class PostSeeder implements SeederInterface
{
    public function __construct(
        private PostRepositoryInterface $repository,
    ) {}

    public function run(): void
    {
        $post = new Post();
        $post->title = 'Hello World';
        $post->slug = 'hello-world';
        $post->content = 'Welcome to my blog!';
        $post->createdAt = date('Y-m-d H:i:s');

        $this->repository->save($post);
    }
}
```

> **Why `new Post()` instead of factories?** Entities are simple data objects without dependencies or complex construction logic. Direct instantiation is explicit - you see exactly what's being set. This aligns with Marko's "explicit over implicit" principle. If your tests need realistic fake data at scale, consider adding a test data builder for that specific need rather than a general factory abstraction.

> **IDE Note:** PhpStorm may report seeder classes as "unused" since they're discovered via attributes rather than direct instantiation. The `@noinspection PhpUnused` annotation suppresses this false positive.

Place seeders in your module's `Seed/` directory. The `order` parameter controls execution sequence - use spaced numbers (10, 20, 30) rather than sequential (1, 2, 3) to allow other modules to insert seeders between existing ones without renumbering.

## CLI Commands

| Command | Description |
|---------|-------------|
| `marko db:status` | Show migration status |
| `marko db:diff` | Preview changes between entities and database |
| `marko db:migrate` | Generate and apply migrations |
| `marko db:rollback` | Revert last migration batch (development only) |
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

In production, `db:migrate` only applies existing migration files - it never generates new ones.

## Framework Comparison

| Feature | Laravel | Doctrine | Marko |
|---------|---------|----------|-------|
| Schema definition | Separate migration files | XML/YAML or attributes | Entity attributes (single source of truth) |
| Migration generation | Manual | `doctrine:schema:update` | `db:migrate` auto-generates |
| Entity persistence | Active Record (Eloquent) | Data Mapper | Data Mapper |
| Schema location | `database/migrations/` | Mapping files or entity | Entity only |

## Benefits of Entity as Single Source of Truth

1. **No schema drift** - Entity changes automatically sync to database
2. **Refactoring updates both** - Rename a property, schema updates automatically
3. **IDE support** - Full autocomplete and type checking for schema
4. **No context switching** - Everything about your model in one place
5. **Reduced cognitive load** - One file to understand, not entity + migration + mapping

## Available Drivers

- **marko/database-mysql** - MySQL/MariaDB driver
- **marko/database-pgsql** - PostgreSQL driver
