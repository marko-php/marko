# Task 031: Integration Tests

**Status**: pending
**Depends on**: 001, 002, 003, 004, 005, 006, 007, 008, 009, 010, 011, 012, 013, 014, 015, 016, 017, 018, 019, 020, 021, 022, 023, 024, 025, 026, 027, 028, 029
**Retry count**: 0

## Description
Create comprehensive integration tests that verify all database package components work together correctly. This includes end-to-end tests with real database connections for both MySQL and PostgreSQL.

## Context
- Related files: packages/database/tests/Feature/, packages/database-mysql/tests/Feature/, packages/database-pgsql/tests/Feature/
- Patterns to follow: Pest PHP, existing test patterns in marko packages
- Requires Docker or local database for integration tests

## Requirements (Test Descriptions)
- [ ] `it runs complete entity-to-migration workflow`
- [ ] `it creates tables from entity definitions`
- [ ] `it detects and generates migrations for entity changes`
- [ ] `it applies and rolls back migrations correctly`
- [ ] `it runs seeders and populates test data`
- [ ] `it performs CRUD operations via repository`
- [ ] `it handles transactions with commit and rollback`
- [ ] `it works identically on MySQL and PostgreSQL`
- [ ] `it throws loud errors when no driver installed`
- [ ] `it provides test helpers for database testing`
- [ ] `it supports test database isolation via transactions`

## Acceptance Criteria
- All requirements have passing tests
- Tests run against real databases
- Both MySQL and PostgreSQL tested
- CI-friendly with Docker support

## Implementation Notes
(Left blank - filled in by programmer during implementation)
