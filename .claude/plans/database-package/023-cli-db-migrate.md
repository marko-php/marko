# Task 023: CLI db:migrate Command

**Status**: pending
**Depends on**: 019
**Retry count**: 0

## Description
Create the db:migrate CLI command that generates migration files from entity/database diff and applies them. In development, it auto-generates migrations; in production, it only applies existing migration files.

## Context
- Related files: packages/database/src/Command/MigrateCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Behavior differs based on environment

## Requirements (Test Descriptions)
- [ ] `it registers as db:migrate command via #[Command] attribute`
- [ ] `it implements CommandInterface`
- [ ] `it applies pending migration files`
- [ ] `it generates new migration files from entity diff in development`
- [ ] `it does not generate migrations in production mode`
- [ ] `it shows each migration being applied`
- [ ] `it shows SQL statements being executed with --verbose`
- [ ] `it groups applied migrations into a batch`
- [ ] `it shows success message with count of applied migrations`
- [ ] `it shows "Nothing to migrate" when no pending changes`
- [ ] `it rolls back on failure and shows error`
- [ ] `it returns 0 on success, 1 on failure`

## Acceptance Criteria
- All requirements have passing tests
- Production safety (no auto-generation)
- Clear progress output
- Proper error handling with rollback

## Implementation Notes
(Left blank - filled in by programmer during implementation)
