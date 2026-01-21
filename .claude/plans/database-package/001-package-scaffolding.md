# Task 001: Package Scaffolding

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the basic package structure for marko/database, marko/database-mysql, and marko/database-pgsql with composer.json files, module.php files, and directory structure.

## Context
- Related files: packages/errors/composer.json, packages/errors-simple/composer.json (patterns to follow)
- Patterns to follow: Existing interface/implementation split in errors packages
- All packages need PSR-4 autoloading configured

## Requirements (Test Descriptions)
- [x] `it creates marko/database package with valid composer.json`
- [x] `it creates marko/database-mysql package with valid composer.json requiring marko/database`
- [x] `it creates marko/database-pgsql package with valid composer.json requiring marko/database`
- [x] `it creates module.php for database-mysql with connection binding`
- [x] `it creates module.php for database-pgsql with connection binding`
- [x] `it creates proper directory structure for all three packages`
- [x] `it configures PSR-4 namespaces correctly`
- [x] `it creates README.md for marko/database explaining entity-driven schema and Data Mapper pattern`
- [x] `it creates README.md for marko/database-mysql with installation and configuration`
- [x] `it creates README.md for marko/database-pgsql with installation and configuration`

## Acceptance Criteria
- All requirements have passing tests
- Composer validates all package json files
- Directory structure matches architecture document
- PSR-4 namespaces: Marko\Database, Marko\Database\MySql, Marko\Database\PgSql
- Main README covers: entity-driven schema, Data Mapper pattern, type inference rules, attributes overview, repository pattern, CLI commands overview
- Main README includes complete Post entity example showing all attribute types (#[Table], #[Column], #[Index], foreign keys, enums, nullable types, defaults)
- Main README includes framework comparison table (Laravel separate migrations, Doctrine XML/YAML or attributes, Marko single source of truth)
- Main README explains benefits of entity as single source of truth: no schema drift, refactoring updates both code and schema, IDE support for schema, no context switching between files
- Driver READMEs cover: installation, configuration, driver-specific notes

## Implementation Notes
(Left blank - filled in by programmer during implementation)
