# Task 021: CLI db:status Command

**Status**: completed
**Depends on**: 018
**Retry count**: 0

## Description
Create the db:status CLI command that shows the current state of migrations - which have been applied and which are pending. This helps developers understand the migration state.

## Context
- Related files: packages/database/src/Command/StatusCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Similar to existing commands in marko/core

## Requirements (Test Descriptions)
- [x] `it registers as db:status command via #[Command] attribute`
- [x] `it implements CommandInterface`
- [x] `it shows list of applied migrations with batch number`
- [x] `it shows list of pending migrations`
- [x] `it shows total count of applied migrations`
- [x] `it shows total count of pending migrations`
- [x] `it shows "No migrations found" when migrations directory empty`
- [x] `it shows "All migrations applied" when no pending migrations`
- [x] `it returns 0 exit code on success`

## Acceptance Criteria
- All requirements have passing tests
- Output is clear and readable
- Command integrates with CLI system
- Helpful for debugging migration state

## Implementation Notes
- Created `StatusCommand` class at `/Users/markshust/Sites/marko/packages/database/src/Command/StatusCommand.php`
- Added `getAppliedWithBatch()` method to `MigrationRepository` to retrieve migrations with their batch numbers
- Command displays:
  - Applied migrations grouped with their batch numbers in format `[batch] migration_name`
  - Pending migrations (without batch number)
  - Summary showing "Applied: N" and "Pending: M" counts
  - Special messages for "No migrations found" and "All migrations applied" states
- Tests added at `/Users/markshust/Sites/marko/packages/database/tests/Command/StatusCommandTest.php`
- Test for new repository method added to `/Users/markshust/Sites/marko/packages/database/tests/Migration/MigrationRepositoryTest.php`
