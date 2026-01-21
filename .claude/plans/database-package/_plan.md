# Plan: Database Package

## Created
2025-01-20

## Status
in_progress

## Objective
Implement the database layer for Marko framework with entity-driven schema definition, auto-generated migrations, and both MySQL and PostgreSQL drivers.

## Scope

### In Scope
- `marko/database` package with interfaces, entity system, diff engine, migration system, CLI commands
- `marko/database-mysql` package with MySQL driver implementation
- `marko/database-pgsql` package with PostgreSQL driver implementation
- Entity-driven schema: #[Table], #[Column], #[Index], #[ForeignKey] attributes define database structure
- Query builder (fluent + raw queries)
- Transaction support (callback + manual)
- Migration system with auto-generation from entity/database diff
- Data migrations for required production data (lookup tables, defaults)
- Seeder system for development/test data only (blocked in production)
- Repository pattern with query methods (find, findBy, save, delete)
- CLI commands: db:diff, db:migrate, db:rollback, db:seed, db:status

### Out of Scope
- Active Record pattern (entities don't save themselves)
- Separate schema definition files (entities ARE the schema)
- Connection pooling
- Read/write splitting

## Success Criteria
- [ ] Entity attributes define complete table schema (columns, indexes, foreign keys)
- [ ] `db:diff` shows differences between entities and database
- [ ] `db:migrate` generates and applies migrations from entity changes
- [ ] `db:rollback` reverts last migration batch
- [ ] Data migrations run in production for required module data
- [ ] `db:seed` runs seeders in development
- [ ] `db:status` shows migration status
- [ ] Both MySQL and PostgreSQL drivers work identically
- [ ] Query builder supports fluent and raw queries
- [ ] Transactions work with callback and manual API
- [ ] Entities hydrate from database rows and persist via repositories
- [ ] Loud error when no database driver is installed
- [ ] `db:rollback` blocked in production (no override)
- [ ] `db:migrate` only applies existing files in production (no generation)
- [ ] `db:seed` blocked in production (no override)
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json, module.php) | - | completed |
| 002 | Connection interfaces and config | 001 | completed |
| 003 | MySQL connection implementation | 002 | pending |
| 004 | PostgreSQL connection implementation | 002 | pending |
| 005 | Schema value objects (Table, Column, Index, ForeignKey) | 001 | completed |
| 006 | Query builder interface | 002 | pending |
| 007 | MySQL query builder | 003, 006 | pending |
| 008 | PostgreSQL query builder | 004, 006 | pending |
| 009 | Database introspector interface | 002 | pending |
| 010 | MySQL introspector | 003, 009 | pending |
| 011 | PostgreSQL introspector | 004, 009 | pending |
| 012 | Entity base class and core attributes | 005 | pending |
| 013 | Entity metadata parser and schema builder | 012 | pending |
| 014 | Diff engine | 005, 009, 013 | pending |
| 015 | SQL generator interface | 014 | pending |
| 016 | MySQL SQL generator | 010, 015 | pending |
| 017 | PostgreSQL SQL generator | 011, 015 | pending |
| 018 | Migration system core | 014, 015 | pending |
| 019 | Migration file generator (nowdoc syntax) | 018 | pending |
| 020 | Data migrations system | 018 | pending |
| 021 | CLI: db:status command | 018 | pending |
| 022 | CLI: db:diff command | 014 | pending |
| 023 | CLI: db:migrate command | 019, 020 | pending |
| 024 | CLI: db:rollback command | 018 | pending |
| 025 | Seeder system | 002 | pending |
| 026 | CLI: db:seed command | 025 | pending |
| 027 | Transaction support | 002, 003, 004 | pending |
| 028 | Entity hydration | 012, 006 | pending |
| 029 | Repository interface and base class | 028 | pending |
| 030 | Repository query methods | 029 | pending |
| 031 | Integration tests | 001-030 | pending |

## Architecture Notes

### Package Structure
```
packages/
  database/           # Interfaces + shared code
    src/
      Attributes/     # #[Table], #[Column], #[Index], #[ForeignKey], #[Seeder]
      Config/         # DatabaseConfig
      Connection/     # ConnectionInterface, TransactionInterface
      Diff/           # SchemaDiff, TableDiff, ColumnDiff, DiffCalculator
      Entity/         # Entity base, EntityMetadata, EntityMetadataFactory
      Exceptions/     # DatabaseException hierarchy
      Introspection/  # IntrospectorInterface
      Migration/      # Migrator, MigrationGenerator, Migration base
      Query/          # QueryBuilderInterface
      Repository/     # RepositoryInterface, Repository base
      Schema/         # Table, Column, Index, ForeignKey (value objects)
      Command/        # CLI commands
  database-mysql/     # MySQL implementation
    src/
      Connection/     # MySqlConnection
      Introspection/  # MySqlIntrospector
      Query/          # MySqlQueryBuilder
      Sql/            # MySqlGenerator
  database-pgsql/     # PostgreSQL implementation
    src/
      Connection/     # PgSqlConnection
      Introspection/  # PgSqlIntrospector
      Query/          # PgSqlQueryBuilder
      Sql/            # PgSqlGenerator
```

### Config Location
```php
// config/database.php
return [
    'driver' => 'pgsql', // or 'mysql'
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? 5432,
    'database' => $_ENV['DB_DATABASE'] ?? 'marko',
    'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8',
];
```

### Entity-Driven Schema Pattern
```php
// app/Blog/Entity/Post.php
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
}
```

**Type Inference Rules:**
- `int` → INT (or SERIAL if autoIncrement)
- `string` → VARCHAR(255) by default, TEXT if type='text'
- `bool` → BOOLEAN
- `float` → DECIMAL or FLOAT
- `?type` → Column is NULLABLE
- `DateTimeImmutable` → TIMESTAMP
- `BackedEnum` → ENUM with cases as values
- Default values from property initializers

### Repository Pattern
```php
// app/Blog/Repository/PostRepository.php
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

### Driver Conflict Handling
Only one driver package can be installed. If both `marko/database-mysql` and `marko/database-pgsql` are installed, the framework throws a loud error during boot:

```
BindingConflictException: Multiple implementations bound for ConnectionInterface.

Context: Both MySqlConnection and PgSqlConnection are attempting to bind.

Suggestion: Install only one database driver package. Remove one with:
  composer remove marko/database-mysql
  or
  composer remove marko/database-pgsql
```

### No Driver Installed Handling
If `marko/database` is installed without a driver, attempting to use database features throws:

```
DatabaseException: No database driver installed.

Context: Attempted to resolve ConnectionInterface but no implementation is bound.

Suggestion: Install a database driver package:
  composer require marko/database-pgsql
  or
  composer require marko/database-mysql
```

## Migration Workflow

### Development Workflow

```
1. Define/modify entity    →  #[Table], #[Column] attributes
2. Preview changes         →  marko db:diff (read-only, shows what would change)
3. Generate & apply        →  marko db:migrate (creates migration file + applies it)
4. Commit migration file   →  git add database/migrations/
5. If mistake, rollback    →  marko db:rollback (development only)
```

### Production Workflow

```
1. Deploy code (includes migration files)
2. Apply migrations        →  marko db:migrate (applies existing files only, no generation)
```

### Command Behavior by Environment

| Command | Development | Production |
|---------|-------------|------------|
| `db:diff` | ✅ Shows pending changes | ✅ Shows pending changes |
| `db:migrate` | ✅ Generates + applies | ✅ Applies only (no generation) |
| `db:rollback` | ✅ Reverts last batch | ❌ **Blocked** (no --force) |
| `db:seed` | ✅ Runs seeders | ❌ **Blocked** (no override) |
| `db:status` | ✅ Shows migration state | ✅ Shows migration state |

### Why Rollback is Blocked in Production

Rollbacks in production are dangerous:
- Data loss risk (DROP COLUMN, DROP TABLE)
- No guarantee down() migrations are safe with real data
- Proper fix: create a new forward migration

If production needs fixing, deploy a new migration that corrects the issue.

### Migration File Lifecycle

```
Entity Change
     ↓
db:diff (preview)
     ↓
db:migrate (generates file)
     ↓
database/migrations/2025_01_20_143022_create_blog_posts.php  ← committed to git
     ↓
Production: db:migrate (applies committed file)
```

Migration files are the **deployment artifact**. Entities are the **source of truth**.

### Data Migrations vs Seeders

| Type | Purpose | Production | Location |
|------|---------|------------|----------|
| **Schema Migrations** | Table structure | ✅ Runs | `database/migrations/` (auto-generated) |
| **Data Migrations** | Required data (lookups, defaults) | ✅ Runs | `app/*/Data/` (hand-written) |
| **Seeders** | Dev/test data | ❌ Blocked | `app/*/Seed/` |

Data migrations use the same tracking table and run alongside schema migrations.

## Risks & Mitigations
- **Driver differences**: Abstract at interface level, test both drivers
- **Type inference complexity**: Start with common PHP types, explicit type parameter for edge cases
- **Migration conflicts**: Track batches, support rollback
- **Entity changes breaking migrations**: Diff engine detects destructive changes, warns user
