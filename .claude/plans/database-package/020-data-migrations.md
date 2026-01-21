# Task 020: Data Migrations System

**Status**: pending
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
- [ ] `it creates DataMigration base class extending Migration`
- [ ] `it discovers data migrations in vendor/*/*/Data/`
- [ ] `it discovers data migrations in modules/*/*/Data/`
- [ ] `it discovers data migrations in app/*/Data/`
- [ ] `it tracks data migrations in same migrations table`
- [ ] `it applies data migrations in filename order`
- [ ] `it supports raw SQL via execute() with nowdoc syntax`
- [ ] `it provides insert() helper for single/bulk inserts`
- [ ] `it provides update() helper with where clause`
- [ ] `it provides delete() helper with where clause`
- [ ] `it supports down() for rollback`
- [ ] `it runs in production (not blocked like seeders)`
- [ ] `it runs data migrations after schema migrations in same batch`

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
(Left blank - filled in by programmer during implementation)
