# Task 023: CLI db:migrate Command

**Status**: completed
**Depends on**: 019
**Retry count**: 0

## Description
Create the db:migrate CLI command that generates migration files from entity/database diff and applies them. In development, it auto-generates migrations; in production, it only applies existing migration files.

## Context
- Related files: packages/database/src/Command/MigrateCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Behavior differs based on environment

## Requirements (Test Descriptions)
- [x] `it registers as db:migrate command via #[Command] attribute`
- [x] `it implements CommandInterface`
- [x] `it applies pending migration files`
- [x] `it generates new migration files from entity diff in development`
- [x] `it does not generate migrations in production mode`
- [x] `it shows each migration being applied`
- [x] `it shows SQL statements being executed with --verbose`
- [x] `it groups applied migrations into a batch`
- [x] `it shows success message with count of applied migrations`
- [x] `it shows "Nothing to migrate" when no pending changes`
- [x] `it rolls back on failure and shows error`
- [x] `it returns 0 on success, 1 on failure`

## Acceptance Criteria
- All requirements have passing tests
- Production safety (no auto-generation)
- Clear progress output
- Proper error handling with rollback

## Implementation Notes
- Created MigrateCommand at packages/database/src/Command/MigrateCommand.php
- Uses #[Command(name: 'db:migrate', description: 'Apply database migrations')] attribute
- Implements CommandInterface
- In development mode (isProduction=false):
  - Calculates diff between entities and database using DiffCalculator
  - Auto-generates migration files using MigrationGenerator
  - Then applies pending migrations
- In production mode (isProduction=true):
  - Only applies existing migration files
  - Does NOT auto-generate migrations
  - Shows warning if entity schema differs from database
- Shows each migration as "Migrating: {name}"
- With --verbose flag, shows SQL statements being executed
- Returns 0 on success, 1 on failure
- Shows count of applied migrations on success
- Shows "Nothing to migrate" when no pending changes
