# Task 018: Migration System Core

**Status**: completed
**Depends on**: 014, 015
**Retry count**: 0

## Description
Create the core migration system including the Migrator class, Migration base class, and MigrationRepository for tracking applied migrations. This handles finding, applying, and rolling back migrations.

## Context
- Related files: packages/database/src/Migration/Migrator.php, Migration.php, MigrationRepository.php
- Patterns to follow: Batch tracking for grouped rollback
- Migrations table tracks applied migrations with batch numbers

## Requirements (Test Descriptions)
- [x] `it creates migrations table if not exists`
- [x] `it finds pending migration files in database/migrations/`
- [x] `it applies migrations in filename order`
- [x] `it executes migration up() method`
- [x] `it records applied migration with batch number`
- [x] `it groups migrations applied together in same batch`
- [x] `it rolls back last batch of migrations`
- [x] `it executes migration down() method on rollback`
- [x] `it removes migration record after rollback`
- [x] `it returns list of applied migrations`
- [x] `it returns list of pending migrations`
- [x] `it throws MigrationException on failure`
- [x] `it provides Migration base class with execute() helper`

## Acceptance Criteria
- All requirements have passing tests
- Batch system enables grouped rollback
- Migration files follow naming convention (timestamp_description.php)
- Proper error handling with helpful messages

## Implementation Notes
Created core migration system with three main components:

1. **Migration** (`packages/database/src/Migration/Migration.php`) - Abstract base class with:
   - `up()` and `down()` abstract methods for migration execution
   - `execute()` helper method for convenient SQL execution

2. **MigrationRepository** (`packages/database/src/Migration/MigrationRepository.php`) - Tracks applied migrations:
   - Creates `migrations` table with `name` and `batch` columns
   - Records/deletes migration entries
   - Queries applied migrations and batch information

3. **Migrator** (`packages/database/src/Migration/Migrator.php`) - Orchestrates migrations:
   - Discovers pending migration files from the migrations directory
   - Applies migrations in filename order (sorted alphabetically)
   - Groups migrations into batches for grouped rollback
   - Rollback removes migrations from the last batch in reverse order

4. **MigrationException** (`packages/database/src/Exceptions/MigrationException.php`) - Error handling:
   - `migrationFailed()` - When migration execution throws an error
   - `migrationNotFound()` - When migration file doesn't exist
   - `invalidMigration()` - When migration file doesn't return a Migration instance

Migration files follow the naming convention `YYYY_MM_DD_HHMMSS_description.php` and return anonymous classes extending Migration.
