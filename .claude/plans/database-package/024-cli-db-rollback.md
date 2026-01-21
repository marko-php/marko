# Task 024: CLI db:rollback Command

**Status**: completed
**Depends on**: 018
**Retry count**: 0

## Description
Create the db:rollback CLI command that reverts the last batch of migrations. This executes the down() method of each migration in reverse order.

## Context
- Related files: packages/database/src/Command/RollbackCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Rolls back entire batch, not individual migrations

## Requirements (Test Descriptions)
- [x] `it registers as db:rollback command via #[Command] attribute`
- [x] `it implements CommandInterface`
- [x] `it blocks execution in production environment`
- [x] `it shows error message when blocked in production`
- [x] `it does NOT support --force flag (rollback is never allowed in production)`
- [x] `it rolls back last batch of migrations in development`
- [x] `it executes down() in reverse order within batch`
- [x] `it shows each migration being rolled back`
- [x] `it removes migration records from tracking table`
- [x] `it supports --step option to rollback multiple batches`
- [x] `it offers to delete uncommitted migration files`
- [x] `it shows "Nothing to rollback" when no applied migrations`
- [x] `it warns about entity sync after rollback`
- [x] `it returns 0 on success, 1 on failure`

## Acceptance Criteria
- All requirements have passing tests
- **Production safety: rollback is always blocked in production (no --force override)**
- Batch rollback is atomic
- Clear warnings about entity sync
- Handles uncommitted file cleanup

## Implementation Notes
Implemented `RollbackCommand` in `packages/database/src/Command/RollbackCommand.php`:

- Uses `#[Command(name: 'db:rollback', description: 'Rollback the last batch of migrations')]` attribute
- Implements `CommandInterface`
- Production blocking: Always returns exit code 1 in production with clear error message
- No `--force` flag support: Even if provided, production rollback is still blocked
- Supports `--step=N` option to rollback multiple batches (calls `Migrator::rollback()` N times)
- Displays each migration being rolled back: "Rolling back: {migration_name}"
- Shows "Nothing to rollback" when no migrations exist to rollback
- Provides hint about uncommitted migration files that may need cleanup
- Warns about entity sync after successful rollback, recommending `db:diff` command

Test file: `packages/database/tests/Command/RollbackCommandTest.php` with 14 passing tests.
