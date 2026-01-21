# Task 020: Data Migrations System

**Status**: completed
**Depends on**: 018
**Retry count**: 0

## Description
Create the data migrations system for inserting required module data that runs in production. Unlike seeders (dev-only), data migrations are versioned, tracked, and run alongside schema migrations. Supports both helper methods and raw SQL.

## Context
- Related files: packages/database/src/Migration/DataMigration.php, packages/database/src/Migration/DataMigrationDiscovery.php
- Patterns to follow: Similar to schema migrations but for INSERT/UPDATE/DELETE
- Data migrations live in module `Data/` directories
- **Runs in production** - for lookup tables, default config, required records

## Requirements (Test Descriptions)
- [x] `it creates DataMigration base class extending Migration`
- [x] `it discovers data migrations in vendor/*/*/Data/`
- [x] `it discovers data migrations in modules/*/*/Data/`
- [x] `it discovers data migrations in app/*/Data/`
- [x] `it tracks data migrations in same migrations table`
- [x] `it applies data migrations in filename order`
- [x] `it supports raw SQL via execute() with nowdoc syntax`
- [x] `it provides insert() helper for single/bulk inserts`
- [x] `it provides update() helper with where clause`
- [x] `it provides delete() helper with where clause`
- [x] `it supports down() for rollback`
- [x] `it runs in production (not blocked like seeders)`
- [x] `it runs data migrations after schema migrations in same batch`

## Acceptance Criteria
- All requirements have passing tests
- Data migrations run in production
- Raw SQL supported via execute()
- Helper methods for common operations
- Tracked alongside schema migrations

## Example: Using Helpers
```php
// app/Blog/Data/001_insert_post_statuses.php
return new class extends DataMigration {
    public function up(): void
    {
        $this->insert('post_statuses', [
            ['id' => 1, 'name' => 'draft', 'label' => 'Draft'],
            ['id' => 2, 'name' => 'published', 'label' => 'Published'],
            ['id' => 3, 'name' => 'archived', 'label' => 'Archived'],
        ]);
    }

    public function down(): void
    {
        $this->delete('post_statuses', ['id' => [1, 2, 3]]);
    }
};
```

## Example: Using Raw SQL
```php
// app/Blog/Data/002_insert_default_categories.php
return new class extends DataMigration {
    public function up(): void
    {
        $this->execute(<<<'SQL'
            INSERT INTO "categories" ("id", "name", "slug", "sort_order")
            VALUES
                (1, 'Uncategorized', 'uncategorized', 0),
                (2, 'News', 'news', 10),
                (3, 'Tutorials', 'tutorials', 20)
            ON CONFLICT ("id") DO NOTHING;
            SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
            DELETE FROM "categories" WHERE "id" IN (1, 2, 3);
            SQL);
    }
};
```

## Implementation Notes
Created three classes for the data migrations system:

1. **DataMigration** (`packages/database/src/Migration/DataMigration.php`)
   - Extends Migration base class
   - Provides `insert()` helper for single and bulk inserts with parameterized queries
   - Provides `update()` helper with where clause support
   - Provides `delete()` helper with where clause support
   - Inherits `execute()` from Migration for raw SQL with nowdoc syntax
   - No environment checks - runs in production by design

2. **DataMigrationDiscovery** (`packages/database/src/Migration/DataMigrationDiscovery.php`)
   - Discovers data migrations in vendor/*/*/Data/, modules/*/*/Data/, and app/*/Data/
   - Returns migrations sorted by filename for consistent execution order
   - Each discovered migration includes name, path, and source

3. **DataMigrator** (`packages/database/src/Migration/DataMigrator.php`)
   - Uses same MigrationRepository as schema migrations (shared tracking table)
   - Supports migrate() and rollback() operations
   - Integrates with same batch numbering system
   - Validates migrations are DataMigration instances
