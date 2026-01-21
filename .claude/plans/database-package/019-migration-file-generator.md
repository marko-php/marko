# Task 019: Migration File Generator (Nowdoc Syntax)

**Status**: pending
**Depends on**: 018
**Retry count**: 0

## Description
Create the MigrationGenerator that produces migration files from SchemaDiff objects. This generates PHP migration classes with up() and down() methods containing SQL statements using **nowdoc syntax** for easy copy/paste testing in external database tools.

## Context
- Related files: packages/database/src/Migration/MigrationGenerator.php
- Patterns to follow: Code generation, template-based
- Files go to database/migrations/ with timestamp prefix
- **Nowdoc syntax** makes SQL easy to copy/paste for EXPLAIN or testing

## Requirements (Test Descriptions)
- [ ] `it generates migration filename with timestamp prefix`
- [ ] `it generates migration filename with descriptive suffix from changes`
- [ ] `it generates valid PHP migration class`
- [ ] `it includes up() method with SQL statements`
- [ ] `it includes down() method with rollback SQL`
- [ ] `it uses nowdoc syntax for SQL statements`
- [ ] `it includes semicolons at end of SQL statements`
- [ ] `it formats SQL with proper indentation inside nowdoc`
- [ ] `it uses $this->execute() for each SQL statement`
- [ ] `it writes file to database/migrations/ directory`
- [ ] `it creates migrations directory if not exists`
- [ ] `it returns path to generated file`
- [ ] `it generates separate migrations per table change`
- [ ] `it handles empty diff with no file generated`

## Acceptance Criteria
- All requirements have passing tests
- Generated files are valid PHP syntax
- **SQL uses nowdoc syntax for easy copy/paste to external tools**
- Migration classes extend Migration base class
- Files are ready to apply immediately

## Example Output
```php
return new class extends Migration {
    public function up(): void
    {
        $this->execute(<<<'SQL'
            CREATE TABLE "blog_posts" (
                "id" SERIAL PRIMARY KEY,
                "title" VARCHAR(255) NOT NULL,
                "content" TEXT NULL
            );
            SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
            DROP TABLE "blog_posts";
            SQL);
    }
};
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
