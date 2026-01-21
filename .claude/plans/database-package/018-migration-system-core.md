# Task 018: Migration System Core

**Status**: pending
**Depends on**: 014, 015
**Retry count**: 0

## Description
Create the core migration system including the Migrator class, Migration base class, and MigrationRepository for tracking applied migrations. This handles finding, applying, and rolling back migrations.

## Context
- Related files: packages/database/src/Migration/Migrator.php, Migration.php, MigrationRepository.php
- Patterns to follow: Batch tracking for grouped rollback
- Migrations table tracks applied migrations with batch numbers

## Requirements (Test Descriptions)
- [ ] `it creates migrations table if not exists`
- [ ] `it finds pending migration files in database/migrations/`
- [ ] `it applies migrations in filename order`
- [ ] `it executes migration up() method`
- [ ] `it records applied migration with batch number`
- [ ] `it groups migrations applied together in same batch`
- [ ] `it rolls back last batch of migrations`
- [ ] `it executes migration down() method on rollback`
- [ ] `it removes migration record after rollback`
- [ ] `it returns list of applied migrations`
- [ ] `it returns list of pending migrations`
- [ ] `it throws MigrationException on failure`
- [ ] `it provides Migration base class with execute() helper`

## Acceptance Criteria
- All requirements have passing tests
- Batch system enables grouped rollback
- Migration files follow naming convention (timestamp_description.php)
- Proper error handling with helpful messages

## Implementation Notes
(Left blank - filled in by programmer during implementation)
