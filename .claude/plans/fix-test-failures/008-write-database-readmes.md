# Task 008: Write database READMEs (database, database-mysql, database-pgsql)

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Three database-related READMEs need content updates. The main database package needs conceptual documentation (Entity-Driven Schema, Data Mapper, etc.) and the two driver packages need Configuration sections with environment variables.

## Context
- Related files:
  - `packages/database/README.md` — current README
  - `packages/database-mysql/README.md` — current README
  - `packages/database-pgsql/README.md` — current README
  - `packages/database/tests/PackageScaffoldingTest.php` — test expectations for all 3
  - `packages/database/src/`, `packages/database-mysql/src/`, `packages/database-pgsql/src/` — source code
  - All have docs pages
- Test checks for database README (all `toContain` checks are case-sensitive):
  - Exact string `Entity-Driven Schema` (capital D, capital S -- the current README has `Entity-driven schema` which will NOT match)
  - Exact string `Data Mapper` (capital D, capital M)
  - Exact string `Type Inference`
  - Attributes: `#[Table]`, `#[Column]`, `#[Index]`
  - Post entity example: `class Post`, `primaryKey`, `autoIncrement`, `unique`, `nullable`, `default` -- the current Quick Example has most of these but is MISSING `nullable` and `default` as literal strings (it uses PHP `?string` and `= null` instead). Add Column attributes with `nullable: true` and `default: ...` parameters.
  - `Repository` pattern
  - CLI commands: `db:diff`, `db:migrate`, `db:rollback`, `db:status`
  - Framework comparison: `Laravel`, `Doctrine`, `Marko`
  - Exact phrase `single source of truth`
- **IMPORTANT:** The current database README already has a Quick Example with a Post entity. Extend it rather than replacing it -- add `#[Index]`, `nullable`, and `default` Column parameters. Also add all missing sections (Type Inference, Repository, CLI commands, framework comparison, single source of truth).
- Test checks for database-mysql README:
  - `composer require marko/database-mysql`
  - `config/database.php`, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
  - MySQL, MariaDB mentioned
- Test checks for database-pgsql README:
  - `composer require marko/database-pgsql`
  - `config/database.php`, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
  - PostgreSQL mentioned

## Requirements (Test Descriptions)
- [ ] `Package Scaffolding > it creates README.md for marko/database with entity-driven schema docs` — all conceptual sections present
- [ ] `Package Scaffolding > it creates README.md for marko/database-mysql with configuration` — installation, config, env vars, MySQL/MariaDB
- [ ] `Package Scaffolding > it creates README.md for marko/database-pgsql with configuration` — installation, config, env vars, PostgreSQL

## Acceptance Criteria
- All 3 requirements have passing tests
- README content accurately reflects actual database package code and attributes
- Existing passing tests continue to pass
