# Task 021: CLI db:status Command

**Status**: pending
**Depends on**: 018
**Retry count**: 0

## Description
Create the db:status CLI command that shows the current state of migrations - which have been applied and which are pending. This helps developers understand the migration state.

## Context
- Related files: packages/database/src/Command/StatusCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Similar to existing commands in marko/core

## Requirements (Test Descriptions)
- [ ] `it registers as db:status command via #[Command] attribute`
- [ ] `it implements CommandInterface`
- [ ] `it shows list of applied migrations with batch number`
- [ ] `it shows list of pending migrations`
- [ ] `it shows total count of applied migrations`
- [ ] `it shows total count of pending migrations`
- [ ] `it shows "No migrations found" when migrations directory empty`
- [ ] `it shows "All migrations applied" when no pending migrations`
- [ ] `it returns 0 exit code on success`

## Acceptance Criteria
- All requirements have passing tests
- Output is clear and readable
- Command integrates with CLI system
- Helpful for debugging migration state

## Implementation Notes
(Left blank - filled in by programmer during implementation)
