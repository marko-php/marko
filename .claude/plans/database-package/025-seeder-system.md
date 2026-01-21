# Task 025: Seeder System

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Create the seeder system with attribute-based discovery (#[Seeder]) and a simple SeederInterface. Seeders populate development/test data and are blocked from running in production.

## Context
- Related files: packages/database/src/Seed/SeederInterface.php, SeederDiscovery.php, SeederRunner.php
- Patterns to follow: #[Seeder] attribute like #[Command], discovery pattern
- Seeders live in module Seed/ directories

## Requirements (Test Descriptions)
- [x] `it defines SeederInterface with run(Connection) method`
- [x] `it defines #[Seeder] attribute with name and optional order`
- [x] `it discovers seeders via #[Seeder] attribute`
- [x] `it discovers seeders in vendor/*/*/Seed/`
- [x] `it discovers seeders in modules/*/*/Seed/`
- [x] `it discovers seeders in app/*/Seed/`
- [x] `it runs seeders in order specified by attribute`
- [x] `it blocks seeder execution in production environment`
- [x] `it provides SeederRunner to execute discovered seeders`
- [x] `it supports running specific seeder by name`
- [x] `it shows error when seeder not found`

## Acceptance Criteria
- All requirements have passing tests
- Production safety (blocked in production)
- Order control via attribute
- Discovery follows module patterns

## Implementation Notes

### Files Created
- `packages/database/src/Seed/SeederInterface.php` - Interface with run(ConnectionInterface) method
- `packages/database/src/Seed/Seeder.php` - #[Seeder] attribute with name and order properties
- `packages/database/src/Seed/SeederDefinition.php` - Value object for discovered seeders
- `packages/database/src/Seed/SeederDiscovery.php` - Discovery class with discoverInVendor(), discoverInModules(), discoverInApp(), discoverInPath() methods
- `packages/database/src/Seed/SeederRunner.php` - Runner with runAll() and runByName() methods, production blocking
- `packages/database/src/Exceptions/SeederException.php` - Exception with blockedInProduction() and seederNotFound() factory methods

### Test Files Created
- `packages/database/tests/Seed/SeederInterfaceTest.php`
- `packages/database/tests/Seed/SeederAttributeTest.php`
- `packages/database/tests/Seed/SeederDiscoveryTest.php`
- `packages/database/tests/Seed/SeederRunnerTest.php`

### Key Design Decisions
1. SeederRunner takes a map of seeder instances rather than using the container, making it easier to test and more explicit
2. Production blocking is done via a simple boolean parameter, allowing integration with any environment detection mechanism
3. Discovery methods follow Marko's path patterns: vendor/*/*/Seed, modules/*/*/Seed, app/*/Seed
4. Seeders are sorted by the order property before execution (ascending)
