# Task 026: CLI db:seed Command

**Status**: pending
**Depends on**: 025
**Retry count**: 0

## Description
Create the db:seed CLI command that runs database seeders for development and testing. This command is blocked in production to prevent accidental data pollution.

## Context
- Related files: packages/database/src/Command/SeedCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Must check environment before running

## Requirements (Test Descriptions)
- [ ] `it registers as db:seed command via #[Command] attribute`
- [ ] `it implements CommandInterface`
- [ ] `it discovers all seeders from modules`
- [ ] `it runs seeders in specified order`
- [ ] `it shows each seeder being run`
- [ ] `it supports --class option to run specific seeder`
- [ ] `it blocks execution in production environment`
- [ ] `it shows error message when blocked in production`
- [ ] `it does NOT support --force flag (seeders never run in production)`
- [ ] `it shows "No seeders found" when none discovered`
- [ ] `it returns 0 on success, 1 on failure`

## Acceptance Criteria
- All requirements have passing tests
- **Production safety: seeders are always blocked in production (no override)**
- Clear progress output
- Development-only tool for test data

## Implementation Notes
(Left blank - filled in by programmer during implementation)
