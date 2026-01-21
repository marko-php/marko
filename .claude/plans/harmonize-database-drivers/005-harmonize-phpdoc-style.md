# Task 005: Harmonize PHPDoc Style in MySQL Package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Update MySqlQueryBuilder to use multi-line PHPDoc format for property annotations to match the PostgreSQL style.

## Context
- Related files:
  - `packages/database-mysql/src/Query/MySqlQueryBuilder.php`
  - `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` (reference)
- Pattern: PostgreSQL uses multi-line PHPDoc format

## Requirements (Test Descriptions)
- [ ] `it converts inline PHPDoc comments to multi-line format in MySqlQueryBuilder`
- [ ] `it ensures all property annotations use consistent multi-line format`

## Acceptance Criteria
- All requirements have passing tests
- MySqlQueryBuilder PHPDoc style matches PgSqlQueryBuilder
- Code passes linting
- All existing tests pass

## Implementation Notes

Current MySQL style (inline):
```php
/** @var array<string> */
private array $columns = ['*'];
```

Target PostgreSQL style (multi-line):
```php
/**
 * @var array<string>
 */
private array $columns = ['*'];
```
