# Task 024: CLI db:rollback Command

**Status**: pending
**Depends on**: 018
**Retry count**: 0

## Description
Create the db:rollback CLI command that reverts the last batch of migrations. This executes the down() method of each migration in reverse order.

## Context
- Related files: packages/database/src/Command/RollbackCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Rolls back entire batch, not individual migrations

## Requirements (Test Descriptions)
- [ ] `it registers as db:rollback command via #[Command] attribute`
- [ ] `it implements CommandInterface`
- [ ] `it blocks execution in production environment`
- [ ] `it shows error message when blocked in production`
- [ ] `it does NOT support --force flag (rollback is never allowed in production)`
- [ ] `it rolls back last batch of migrations in development`
- [ ] `it executes down() in reverse order within batch`
- [ ] `it shows each migration being rolled back`
- [ ] `it removes migration records from tracking table`
- [ ] `it supports --step option to rollback multiple batches`
- [ ] `it offers to delete uncommitted migration files`
- [ ] `it shows "Nothing to rollback" when no applied migrations`
- [ ] `it warns about entity sync after rollback`
- [ ] `it returns 0 on success, 1 on failure`

## Acceptance Criteria
- All requirements have passing tests
- **Production safety: rollback is always blocked in production (no --force override)**
- Batch rollback is atomic
- Clear warnings about entity sync
- Handles uncommitted file cleanup

## Implementation Notes
(Left blank - filled in by programmer during implementation)
