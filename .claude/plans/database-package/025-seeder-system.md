# Task 025: Seeder System

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the seeder system with attribute-based discovery (#[Seeder]) and a simple SeederInterface. Seeders populate development/test data and are blocked from running in production.

## Context
- Related files: packages/database/src/Seed/SeederInterface.php, SeederDiscovery.php, SeederRunner.php
- Patterns to follow: #[Seeder] attribute like #[Command], discovery pattern
- Seeders live in module Seed/ directories

## Requirements (Test Descriptions)
- [ ] `it defines SeederInterface with run(Connection) method`
- [ ] `it defines #[Seeder] attribute with name and optional order`
- [ ] `it discovers seeders via #[Seeder] attribute`
- [ ] `it discovers seeders in vendor/*/*/Seed/`
- [ ] `it discovers seeders in modules/*/*/Seed/`
- [ ] `it discovers seeders in app/*/Seed/`
- [ ] `it runs seeders in order specified by attribute`
- [ ] `it blocks seeder execution in production environment`
- [ ] `it provides SeederRunner to execute discovered seeders`
- [ ] `it supports running specific seeder by name`
- [ ] `it shows error when seeder not found`

## Acceptance Criteria
- All requirements have passing tests
- Production safety (blocked in production)
- Order control via attribute
- Discovery follows module patterns

## Implementation Notes
(Left blank - filled in by programmer during implementation)
