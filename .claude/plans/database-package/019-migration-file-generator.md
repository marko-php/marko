# Task 019: Migration File Generator (Nowdoc Syntax)

**Status**: completed
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
- [x] `it generates migration filename with timestamp prefix`
- [x] `it generates migration filename with descriptive suffix from changes`
- [x] `it generates valid PHP migration class`
- [x] `it includes up() method with SQL statements`
- [x] `it includes down() method with rollback SQL`
- [x] `it uses nowdoc syntax for SQL statements`
- [x] `it includes semicolons at end of SQL statements`
- [x] `it formats SQL with proper indentation inside nowdoc`
- [x] `it uses $this->execute() for each SQL statement`
- [x] `it writes file to database/migrations/ directory`
- [x] `it creates migrations directory if not exists`
- [x] `it returns path to generated file`
- [x] `it generates separate migrations per table change`
- [x] `it handles empty diff with no file generated`

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
- Created `MigrationGenerator` class at `packages/database/src/Migration/MigrationGenerator.php`
- Takes `SqlGeneratorInterface` (driver-specific) and a base path for writing migrations
- Generates separate migration files for each table operation (create, alter, drop)
- Uses nowdoc syntax (`<<<'SQL'...SQL`) for SQL statements for easy copy/paste to external tools
- Migration files follow pattern: `{YmdHis}_{operation}_{tablename}.php`
- Generated migrations extend the base `Migration` class and implement proper `up()` and `down()` methods
- Additional tests cover alter and drop operations, plus validation that generated PHP is syntactically valid and can be required
