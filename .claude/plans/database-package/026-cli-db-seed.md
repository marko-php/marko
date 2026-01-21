# Task 026: CLI db:seed Command

**Status**: completed
**Depends on**: 025
**Retry count**: 0

## Description
Create the db:seed CLI command that runs database seeders for development and testing. This command is blocked in production to prevent accidental data pollution.

## Context
- Related files: packages/database/src/Command/SeedCommand.php
- Patterns to follow: #[Command] attribute, implements CommandInterface
- Must check environment before running

## Requirements (Test Descriptions)
- [x] `it registers as db:seed command via #[Command] attribute`
- [x] `it implements CommandInterface`
- [x] `it discovers all seeders from modules`
- [x] `it runs seeders in specified order`
- [x] `it shows each seeder being run`
- [x] `it supports --class option to run specific seeder`
- [x] `it blocks execution in production environment`
- [x] `it shows error message when blocked in production`
- [x] `it does NOT support --force flag (seeders never run in production)`
- [x] `it shows "No seeders found" when none discovered`
- [x] `it returns 0 on success, 1 on failure`

## Acceptance Criteria
- All requirements have passing tests
- **Production safety: seeders are always blocked in production (no override)**
- Clear progress output
- Development-only tool for test data

## Implementation Notes
Created `SeedCommand` class at `packages/database/src/Command/SeedCommand.php` that:

1. Uses `#[Command(name: 'db:seed', description: 'Run database seeders')]` attribute
2. Implements `CommandInterface` with `execute(Input, Output): int` method
3. Discovers seeders from vendor, modules, and app directories using `SeederDiscovery`
4. Runs seeders via `SeederRunner` in order specified by `#[Seeder(order: N)]` attribute
5. Shows "Running seeder: {name}" for each seeder being executed
6. Supports `--class=name` option to run a specific seeder
7. Blocks execution in production with error message (no `--force` flag support)
8. Shows "No seeders found" when no seeders discovered
9. Returns 0 on success, 1 on failure

Test file: `packages/database/tests/Command/SeedCommandTest.php` - 11 tests covering all requirements.
